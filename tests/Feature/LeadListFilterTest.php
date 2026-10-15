<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadAiScoringSetting;
use App\Services\LeadListFilter;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadListFilterTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = DB::getDefaultConnection();
        config(['database.default' => 'lead_list_testing', 'database.connections.lead_list_testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('lead_list_testing');
        DB::setDefaultConnection('lead_list_testing');
        Carbon::setTestNow(Carbon::create(2026, 10, 14, 12));
        Schema::create('leads', function (Blueprint $table) { $table->string('id')->primary(); $table->string('representative_user_id')->nullable(); });
        Schema::create('lead_followups', function (Blueprint $table) { $table->string('id')->primary(); $table->string('lead_id'); $table->integer('status'); $table->dateTime('next_followup_date')->nullable(); $table->dateTime('created_at'); });
        Schema::create('lead_ai_scores', function (Blueprint $table) { $table->string('id')->primary(); $table->string('lead_id'); $table->string('status'); $table->integer('score')->nullable(); $table->string('temperature')->nullable(); $table->dateTime('created_at'); });
        Schema::create('lead_allocation_queue', function (Blueprint $table) { $table->string('lead_id'); $table->string('status'); });
        foreach (['a' => 'own', 'b' => 'team', 'c' => 'outside', 'd' => null] as $id => $owner) DB::table('leads')->insert(['id' => $id, 'representative_user_id' => $owner]);
        $this->followup('a-old', 'a', '2026-10-13 09:00:00', '2026-10-01 10:00:00');
        $this->followup('a-new', 'a', '2026-10-15 09:00:00', '2026-10-12 10:00:00');
        $this->followup('b-old', 'b', '2026-10-14 09:00:00', '2026-10-01 10:00:00');
        $this->followup('b-new', 'b', null, '2026-10-12 10:00:00');
        $this->followup('c', 'c', '2026-10-14 23:59:59', '2026-10-01 10:00:00');
        DB::table('lead_ai_scores')->insert([
            ['id' => 'a-old', 'lead_id' => 'a', 'status' => 'completed', 'score' => 99, 'temperature' => 'hot', 'created_at' => '2026-10-01'],
            ['id' => 'a-new', 'lead_id' => 'a', 'status' => 'completed', 'score' => 50, 'temperature' => 'hot', 'created_at' => '2026-10-12'],
            ['id' => 'b', 'lead_id' => 'b', 'status' => 'completed', 'score' => 81, 'temperature' => 'neutral', 'created_at' => '2026-10-12'],
            ['id' => 'c', 'lead_id' => 'c', 'status' => 'pending', 'score' => 99, 'temperature' => 'hot', 'created_at' => '2026-10-12'],
        ]);
        DB::table('lead_allocation_queue')->insert([['lead_id' => 'a', 'status' => 'queued'], ['lead_id' => 'd', 'status' => 'queued']]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); DB::disconnect('lead_list_testing'); DB::setDefaultConnection($this->originalConnection); config(['database.default' => $this->originalConnection]); parent::tearDown();
    }

    private function followup(string $id, string $lead, ?string $date, string $created, int $status = 1): void
    {
        DB::table('lead_followups')->insert(['id' => $id, 'lead_id' => $lead, 'next_followup_date' => $date, 'created_at' => $created, 'status' => $status]);
    }

    private function ids(array $filters, bool $superAdmin = true, ?array $scope = null): array
    {
        $query = Lead::query(); if ($scope !== null) $query->whereIn('representative_user_id', $scope);
        app(LeadListFilter::class)->apply($query, Request::create('/', 'GET', $filters), $superAdmin, new LeadAiScoringSetting(['cold_max' => 10, 'neutral_max' => 80]));
        return $query->orderBy('id')->pluck('id')->all();
    }

    public function test_hot_uses_current_configured_score_not_stale_temperature_or_history(): void { $this->assertSame(['b'], $this->ids(['lead_queue' => 'hot'])); }
    public function test_date_range_uses_latest_followup_and_includes_end_of_day(): void { $this->assertSame(['c'], $this->ids(['from_followup_date' => '2026-10-14', 'to_followup_date' => '2026-10-14'])); }
    public function test_no_date_includes_missing_latest_date_and_no_history(): void { $this->assertSame(['b', 'd'], $this->ids(['lead_queue' => 'no_followup'])); }
    public function test_queue_excludes_already_assigned_leads(): void { $this->assertSame(['d'], $this->ids(['lead_queue' => 'queued'])); }
    public function test_filters_do_not_expand_sales_scope(): void { $this->assertSame([], $this->ids(['lead_queue' => 'hot'], false, ['own'])); $this->assertSame(['b'], $this->ids(['lead_queue' => 'hot'], false, ['own', 'team'])); }
    public function test_queue_is_forbidden_for_non_super_admin(): void { $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class); $this->ids(['lead_queue' => 'queued'], false); }
    public function test_upper_date_boundary_without_lower_boundary_is_valid(): void { $this->assertSame(['c'], $this->ids(['to_followup_date' => '2026-10-14'])); }
    public function test_overdue_ignores_completed_or_cancelled_status(): void { $this->followup('c-new', 'c', '2026-10-13 10:00:00', '2026-10-13 10:00:00', 2); $this->assertSame([], $this->ids(['followup_period' => 'overdue'])); }
    public function test_today_and_upcoming_only_use_current_schedule(): void { $this->assertSame(['c'], $this->ids(['followup_period' => 'today'])); $this->assertSame(['a'], $this->ids(['followup_period' => 'upcoming'])); }
    public function test_invalid_and_reversed_dates_are_rejected(): void { $this->expectException(\Illuminate\Validation\ValidationException::class); $this->ids(['from_followup_date' => '2026-10-15', 'to_followup_date' => '2026-10-14']); }
}
