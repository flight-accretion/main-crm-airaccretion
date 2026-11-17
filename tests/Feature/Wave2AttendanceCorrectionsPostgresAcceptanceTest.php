<?php

namespace Tests\Feature;

use App\Models\AttendanceImport;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\Attendance\AttendanceCorrectionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\IsolatedWave2PostgresTestCase;

class Wave2AttendanceCorrectionsPostgresAcceptanceTest extends IsolatedWave2PostgresTestCase
{
    private function actor(string $role): User
    {
        return User::where('email', $role.'@wave1.invalid')->sole();
    }

    private function record(string $date = '2044-01-05'): AttendanceRecord
    {
        $import = AttendanceImport::create(['id' => (string) Str::uuid(), 'from_date' => $date, 'to_date' => $date,
            'original_filename' => 'synthetic.csv', 'stored_path' => 'synthetic.csv', 'file_type' => 'csv',
            'status' => 'completed', 'uploaded_by' => $this->actor('hr')->id]);
        return AttendanceRecord::create(['user_id' => $this->actor('sales-executive')->id,
            'attendance_date' => $date, 'day_name' => 'Tuesday', 'raw_status' => 'P', 'source_import_id' => $import->id])->fresh();
    }

    private function payload(AttendanceRecord $record, string $status = 'A'): array
    {
        return ['raw_status' => $status, 'reason' => 'Corrected attendance evidence',
            'version' => app(AttendanceCorrectionService::class)->version($record->fresh())];
    }

    public function test_open_correction_audit_idempotency_stale_version_and_reversal(): void
    {
        $record = $this->record();
        $payload = $this->payload($record);
        $url = route('admin.attendance.corrections.store', $record);
        $this->actingAs($this->actor('hr'))->postJson($url, array_diff_key($payload, ['reason' => 1]))
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $applied = $this->postJson($url, $payload)->assertOk()->assertJsonPath('status', 'applied');
        $id = $applied->json('id');
        $this->assertSame('A', $record->fresh()->raw_status);
        $this->assertSame($record->id, $applied->json('attendance_record_id'));
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('id', $id);
        $this->assertSame(1, DB::table('attendance_corrections')->count());
        $before = json_decode(DB::table('attendance_corrections')->where('id', $id)->value('before_values'), true);
        $this->assertSame('P', $before['raw_status']);
        $this->postJson($url, array_replace($payload, ['raw_status' => 'MIS']))->assertStatus(409);
        $this->postJson(route('admin.attendance.corrections.reverse', $id), ['reason' => 'Evidence withdrawn'])
            ->assertOk()->assertJsonPath('status', 'applied');
        $this->assertSame('P', $record->fresh()->raw_status);
        $this->assertSame(2, DB::table('attendance_corrections')->count());
        $this->assertSame($before, json_decode(DB::table('attendance_corrections')->where('id', $id)->value('before_values'), true));
        $this->postJson(route('admin.attendance.corrections.reverse', $id), ['reason' => 'Duplicate reversal'])->assertStatus(409);
        $this->get(route('admin.attendance.corrections.index'))->assertOk()->assertSee('Correction history');
    }

    public function test_closed_period_approval_rejection_review_and_closed_reversal(): void
    {
        $record = $this->record();
        $original = $record->getAttributes();
        $close = route('admin.attendance.periods.close');
        $this->actingAs($this->actor('hr'))->postJson($close, ['month' => '2044-01', 'reason' => 'Payroll finalized'])->assertForbidden();
        $this->actingAs($this->actor('super-admin'))->postJson($close, ['month' => '2044-01', 'reason' => 'Payroll finalized'])->assertOk();
        $periodBefore = (array) DB::table('attendance_payroll_periods')->first();
        $request = $this->actingAs($this->actor('hr'))->postJson(route('admin.attendance.corrections.store', $record),
            $this->payload($record))->assertOk()->assertJsonPath('status', 'pending');
        $id = $request->json('id');
        $this->assertSame($original, $record->fresh()->getAttributes());
        foreach (['hr', 'sales-executive', 'accounts'] as $role) {
            $this->actingAs($this->actor($role))->postJson(route('admin.attendance.corrections.decide', $id),
                ['decision' => 'approve', 'reason' => 'Unauthorized attempt'])->assertForbidden();
        }
        $this->actingAs($this->actor('super-admin'))->postJson(route('admin.attendance.corrections.decide', $id),
            ['decision' => 'approve', 'reason' => 'Reviewed evidence'])->assertOk()->assertJsonPath('status', 'applied');
        $this->assertSame('A', $record->fresh()->raw_status);
        $this->assertSame(1, DB::table('attendance_payroll_reviews')->where('correction_id', $id)->count());
        $this->postJson(route('admin.attendance.corrections.decide', $id),
            ['decision' => 'approve', 'reason' => 'Duplicate'])->assertStatus(409);
        $reverse = $this->actingAs($this->actor('hr'))->postJson(route('admin.attendance.corrections.reverse', $id),
            ['reason' => 'Reversal evidence'])->assertOk()->assertJsonPath('status', 'pending');
        $this->assertSame('A', $record->fresh()->raw_status);
        $this->actingAs($this->actor('super-admin'))->postJson(route('admin.attendance.corrections.decide', $reverse->json('id')),
            ['decision' => 'approve', 'reason' => 'Reversal reviewed'])->assertOk();
        $this->assertSame('P', $record->fresh()->raw_status);
        $reject = $this->actingAs($this->actor('hr'))->postJson(route('admin.attendance.corrections.store', $record),
            $this->payload($record, 'MIS'))->assertOk();
        $this->actingAs($this->actor('super-admin'))->postJson(route('admin.attendance.corrections.decide', $reject->json('id')),
            ['decision' => 'reject', 'reason' => 'Insufficient evidence'])->assertOk()->assertJsonPath('status', 'rejected');
        $this->assertSame('P', $record->fresh()->raw_status);
        $this->assertSame($periodBefore, (array) DB::table('attendance_payroll_periods')->first());
        $this->assertSame(2, DB::table('attendance_payroll_reviews')->count());
    }

    public function test_stale_approval_pending_conflict_and_forced_failure_are_atomic(): void
    {
        $record = $this->record();
        $url = route('admin.attendance.corrections.store', $record);
        $before = $record->getAttributes();
        DB::statement("ALTER TABLE attendance_correction_events ADD CONSTRAINT qa_attendance_failure CHECK (action <> 'applied')");
        try {
            $this->actingAs($this->actor('hr'))->postJson($url, $this->payload($record))->assertStatus(500);
            $this->assertSame($before, $record->fresh()->getAttributes());
            $this->assertSame(0, DB::table('attendance_corrections')->count());
        } finally {
            DB::statement('ALTER TABLE attendance_correction_events DROP CONSTRAINT qa_attendance_failure');
        }
        app(AttendanceCorrectionService::class)->closePeriod('2044-01-01', 'Finalized', $this->actor('super-admin'));
        $pending = $this->postJson($url, $this->payload($record))->assertOk();
        $this->postJson($url, $this->payload($record, 'MIS'))->assertUnprocessable()->assertJsonValidationErrors('record');
        DB::table('attendance_records')->where('id', $record->id)->update(['raw_status' => 'HLD']);
        $this->actingAs($this->actor('super-admin'))->postJson(route('admin.attendance.corrections.decide', $pending->json('id')),
            ['decision' => 'approve', 'reason' => 'Reviewed'])->assertStatus(409);
        $this->assertSame('HLD', $record->fresh()->raw_status);
        $this->assertSame('pending', DB::table('attendance_corrections')->where('id', $pending->json('id'))->value('status'));
    }
    public function test_mixed_corrected_file_is_atomic_and_closed_rows_wait_for_approval(): void
    {
        \Carbon\Carbon::setTestNow('2044-02-10 12:00:00');
        $closed = $this->record('2044-01-31');
        $open = $this->record('2044-02-01');
        $neighbor = $this->record('2044-02-02');
        $neighborBefore = $neighbor->getAttributes();
        $hr = $this->actor('hr');
        $admin = $this->actor('super-admin');
        app(AttendanceCorrectionService::class)->closePeriod('2044-01-01', 'January payroll finalized', $admin);
        $name = $this->actor('sales-executive')->name;
        $csv = "Date,Day,Name,In,Out,Status\n31/01/2044,Sunday,$name,,,A\n01/02/2044,Monday,$name,,,MIS\n";
        $csv = str_replace('\\n', "\n", $csv);
        $preview = $this->actingAs($hr)->post(route('admin.attendance.import.preview'), [
            'from_date' => '2044-01-31', 'to_date' => '2044-02-01',
            'excel_file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('mixed-correction.csv', $csv),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('total_records', 2);
        $this->assertNotEmpty($preview->json('attendance_before'));
        $this->assertContains('2044-01-01', $preview->json('closed_months'));
        $id = $preview->json('import_id');
        $data = ['import_id' => $id, 'mappings' => [$preview->json('employees.0.mapping_key') => $closed->user_id]];
        $this->postJson(route('admin.attendance.import.confirm'), $data)->assertUnprocessable();
        $this->assertSame('P', $open->fresh()->raw_status);
        $this->assertSame('P', $closed->fresh()->raw_status);
        $this->assertSame(0, DB::table('attendance_corrections')->count());
        $this->post(route('admin.attendance.import.confirm'), $data + ['correction_reason' => 'Corrected signed attendance'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('MIS', $open->fresh()->raw_status);
        $this->assertSame('P', $closed->fresh()->raw_status);
        $this->assertSame($neighborBefore, $neighbor->fresh()->getAttributes());
        $requests = DB::table('attendance_corrections')->where('source_import_id', $id)->get();
        $this->assertCount(2, $requests);
        $pending = $requests->firstWhere('status', 'pending');
        $this->assertNotNull($pending);
        $this->actingAs($admin)->postJson(route('admin.attendance.corrections.decide', $pending->id),
            ['decision' => 'approve', 'reason' => 'Reviewed January exception'])->assertOk();
        $this->assertSame('A', $closed->fresh()->raw_status);
        $this->assertSame(1, DB::table('attendance_payroll_reviews')->count());
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('local')->exists(AttendanceImport::findOrFail($id)->stored_path));
    }    public function test_simultaneous_hr_edits_reject_the_stale_editor(): void
    {
        $record = $this->record('2049-06-05');
        $month = '2049-06-01';
        $this->assertFalse(DB::table('attendance_payroll_periods')->where('month', $month)->exists());
        DB::table('attendance_payroll_periods')->insert(['month' => $month, 'closed' => false,
            'created_at' => now(), 'updated_at' => now()]);
        $version = app(AttendanceCorrectionService::class)->version($record);
        DB::commit();
        $processes = [];
        $pipes = [];
        try {
            DB::beginTransaction();
            AttendanceRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
            foreach (['A', 'MIS'] as $index => $status) {
                $processes[$index] = proc_open([PHP_BINARY, __DIR__.'/../Support/wave2_attendance_worker.php',
                    $record->id, $this->actor('hr')->id, $version, $status],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$index]);
                $this->assertIsResource($processes[$index]);
                fclose($pipes[$index][0]);
            }
            $waiting = 0;
            for ($attempt = 0; $attempt < 120 && $waiting < 2; $attempt++) {
                DB::select('SELECT pg_stat_clear_snapshot()');
                $waiting = (int) DB::selectOne("SELECT count(*) AS n FROM pg_stat_activity
                    WHERE application_name = 'wave2-attendance-concurrency' AND wait_event_type = 'Lock'")->n;
                if ($waiting < 2) usleep(100000);
            }
            DB::commit();
            $results = [];
            foreach ($processes as $index => $process) {
                $stdout = stream_get_contents($pipes[$index][1]);
                $stderr = stream_get_contents($pipes[$index][2]);
                fclose($pipes[$index][1]);
                fclose($pipes[$index][2]);
                $this->assertSame(0, proc_close($process), $stderr.$stdout);
                unset($processes[$index]);
                $results[] = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
            }
            $this->assertSame(2, $waiting);
            $this->assertEqualsCanonicalizing([200, 409], array_column($results, 'status'));
            $winner = collect($results)->firstWhere('status', 200);
            $this->assertSame($winner['raw_status'], $record->fresh()->raw_status);
            $this->assertSame(1, DB::table('attendance_corrections')->where('attendance_record_id', $record->id)->count());
            fwrite(STDOUT, json_encode(['attendance_concurrency' => $results, 'simultaneous_lock_waiters' => $waiting]).PHP_EOL);
        } finally {
            while (DB::transactionLevel()) DB::rollBack();
            foreach ($processes as $process) { proc_terminate($process); proc_close($process); }
            DB::transaction(function () use ($record, $month) {
                $ids = DB::table('attendance_corrections')->where('attendance_record_id', $record->id)->pluck('id');
                DB::table('attendance_correction_events')->whereIn('correction_id', $ids)->delete();
                DB::table('attendance_payroll_reviews')->whereIn('correction_id', $ids)->delete();
                DB::table('attendance_corrections')->whereIn('id', $ids)->delete();
                DB::table('attendance_records')->where('id', $record->id)->delete();
                DB::table('attendance_imports')->where('id', $record->source_import_id)->delete();
                DB::table('attendance_payroll_periods')->where('month', $month)->delete();
            });
        }
    }

    public function test_additive_migration_has_a_transactional_rollback_and_preserves_original_record(): void
    {
        $record = $this->record();
        $original = $record->getAttributes();
        $migration = require base_path('database/migrations/2026_11_15_120000_create_attendance_corrections.php');
        $migration->down();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('attendance_corrections'));
        $migration->up();
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('attendance_records', 'correction_revision'));
        $restored = $record->fresh()->getAttributes();
        ksort($original);
        ksort($restored);
        $this->assertSame($original, $restored);
    }

    public function test_new_closed_period_row_stays_ineffective_until_approved_and_reversal_restores_absence(): void
    {
        $source = $this->record();
        $service = app(AttendanceCorrectionService::class);
        $admin = $this->actor('super-admin');
        $hr = $this->actor('hr');
        $service->closePeriod('2044-01-01', 'Closed', $admin);
        $outcome = $service->importRow($source->user_id, '2044-01-06',
            ['raw_status' => 'A', 'source_import_id' => $source->source_import_id],
            'New evidence for missing attendance', hash('sha256', 'missing'), $hr, $source->source_import_id);
        $this->assertSame('pending', $outcome);
        $records = AttendanceRecord::where('user_id', $source->user_id)->whereDate('attendance_date', '2044-01-06');
        $this->assertFalse($records->exists());
        $request = DB::table('attendance_corrections')->where('attendance_date', '2044-01-06')->sole();
        $this->actingAs($admin)->postJson(route('admin.attendance.corrections.decide', $request->id),
            ['decision' => 'approve', 'reason' => 'Reviewed missing row'])->assertOk();
        $this->assertTrue($records->exists());
        $reverse = $this->actingAs($hr)->postJson(route('admin.attendance.corrections.reverse', $request->id),
            ['reason' => 'Evidence withdrawn'])->assertOk()->assertJsonPath('status', 'pending');
        $this->actingAs($admin)->postJson(route('admin.attendance.corrections.decide', $reverse->json('id')),
            ['decision' => 'approve', 'reason' => 'Reviewed withdrawal'])->assertOk();
        $this->assertFalse($records->exists(), 'Reversal must restore the absence of effective attendance.');
        $this->assertSame(2, DB::table('attendance_corrections')->where('attendance_date', '2044-01-06')->count());
    }    public function test_file_failure_rolls_back_earlier_rows_and_requests_in_the_batch(): void
    {
        \Carbon\Carbon::setTestNow('2044-02-10');
        $first = $this->record('2044-02-03');
        $second = $this->record('2044-02-04');
        $before = [$first->getAttributes(), $second->getAttributes()];
        $name = $this->actor('sales-executive')->name;
        $csv = "Date,Day,Name,In,Out,Status\n03/02/2044,Wednesday,$name,,,A\n04/02/2044,Thursday,$name,,,MIS\n";
        $csv = str_replace('\\n', "\n", $csv);
        $preview = $this->actingAs($this->actor('hr'))->post(route('admin.attendance.import.preview'), [
            'from_date' => '2044-02-03', 'to_date' => '2044-02-04',
            'excel_file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('batch-failure.csv', $csv),
        ], ['Accept' => 'application/json'])->assertOk();
        DB::statement("ALTER TABLE attendance_records ADD CONSTRAINT qa_import_failure CHECK (id <> '{$second->id}' OR raw_status <> 'MIS')");
        try {
            $this->postJson(route('admin.attendance.import.confirm'), [
                'import_id' => $preview->json('import_id'), 'correction_reason' => 'Corrected evidence',
                'mappings' => [$preview->json('employees.0.mapping_key') => $first->user_id],
            ])->assertStatus(500);
            $this->assertSame($before, [$first->fresh()->getAttributes(), $second->fresh()->getAttributes()]);
            $this->assertSame(0, DB::table('attendance_corrections')->count());
            $this->assertSame('previewed', AttendanceImport::findOrFail($preview->json('import_id'))->status);
        } finally {
            DB::statement('ALTER TABLE attendance_records DROP CONSTRAINT qa_import_failure');
        }
    }}
