<?php

namespace Tests\Feature\Attendance;

use App\Models\UserType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AttendanceImportHistoryFilterTest extends AttendanceFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('attendance_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('from_date');
            $table->date('to_date');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('original_filename', 255);
            $table->string('stored_path', 500);
            $table->string('file_type', 20)->nullable();
            $table->string('status', 30)->default('previewed');
            $table->unsignedInteger('total_employees')->default(0);
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('created_records')->default(0);
            $table->unsignedInteger('updated_records')->default(0);
            $table->uuid('uploaded_by');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_history_filters_imports_and_keeps_crm_table_controls(): void
    {
        $hr = $this->createUserWithRole('Attendance HR', UserType::HR);
        $otherHr = $this->createUserWithRole('Other HR', UserType::HR);

        $this->insertImport(
            'matching-attendance.xlsx',
            $hr->id,
            'completed',
            '2026-09-05',
            '2026-09-06'
        );
        $this->insertImport(
            'other-attendance.xlsx',
            $otherHr->id,
            'previewed',
            '2026-08-01',
            '2026-08-02'
        );

        $response = $this
            ->actingAs($hr)
            ->get(route('admin.attendance.import.index', [
                'from_date' => '2026-09-01',
                'to_date' => '2026-09-10',
                'status' => 'completed',
                'uploaded_by' => $hr->id,
                'search' => 'matching',
                'per_page' => 10,
            ]));

        $response->assertOk();
        $response->assertSee('matching-attendance.xlsx');
        $response->assertDontSee('other-attendance.xlsx');
        $response->assertSee('attendance-imports-table');
        $response->assertSee('attendance-import-history-search');
        $response->assertSee('name="per_page"', false);
    }

    private function insertImport(
        string $filename,
        string $uploadedBy,
        string $status,
        string $fromDate,
        string $toDate
    ): void {
        DB::table('attendance_imports')->insert([
            'id' => (string) Str::uuid(),
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'period_from' => $fromDate,
            'period_to' => $toDate,
            'original_filename' => $filename,
            'stored_path' => 'attendance-imports/' . $filename,
            'file_type' => 'xlsx',
            'status' => $status,
            'total_employees' => 1,
            'total_records' => 2,
            'created_records' => 1,
            'updated_records' => 1,
            'uploaded_by' => $uploadedBy,
            'confirmed_at' => $status === 'completed' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
