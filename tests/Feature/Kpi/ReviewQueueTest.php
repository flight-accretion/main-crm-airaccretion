<?php

namespace Tests\Feature\Kpi;

use App\Http\Controllers\OperationsDashboardController;
use App\Models\OperationCase;
use App\Models\ReviewConversation;
use App\Models\UserType;
use App\Services\Operations\OperationCaseService;
use App\Services\Operations\ReviewQueueService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ReviewQueueTest extends KpiFeatureTestCase
{
    private $ops;
    private $other;
    private string $compiled;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['2026_09_23_160000_create_operation_cases_table.php', '2026_09_23_160100_create_operation_case_activities_table.php', '2026_09_23_160200_create_review_conversations_table.php'] as $file) {
            (require base_path('database/migrations/'.$file))->up();
        }
        foreach (['products' => 'product', 'services' => 'service'] as $table => $name) {
            Schema::create($table, function (Blueprint $t) use ($name) { $t->uuid('id')->primary(); $t->string($name); $t->integer('status')->default(1); });
        }
        Schema::create('lead_rides', function (Blueprint $t) { $t->uuid('id')->primary(); $t->uuid('lead_id'); $t->dateTime('from_date')->nullable(); $t->dateTime('to_date')->nullable(); });
        Schema::create('whatsapp_conversations', function (Blueprint $t) { $t->uuid('id')->primary(); $t->dateTime('last_customer_message_at')->nullable(); });
        $this->compiled = sys_get_temp_dir().'/review-queue-'.\Illuminate\Support\Str::uuid();
        mkdir($this->compiled);
        config(['view.compiled' => $this->compiled, 'session.driver' => 'array']);
        app()->forgetInstance('blade.compiler'); app('view.engine.resolver')->forget('blade');
        $this->ops = $this->createUserWithRole('Review Operator', UserType::OPERATIONS_EXECUTIVE);
        $this->other = $this->createUserWithRole('Other Operator', UserType::OPERATIONS_EXECUTIVE);
    }

    protected function tearDown(): void
    {
        (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($this->compiled);
        parent::tearDown();
    }

    private function case(array $attributes = []): OperationCase
    {
        return OperationCase::create($attributes + ['lead_id' => $this->createLeadFor($this->ops, 'Review Customer'),
            'type' => 'review', 'status' => 'pending', 'opened_at' => now()->subDays(4)]);
    }

    private function conversation(OperationCase $case, array $attributes = []): ReviewConversation
    {
        return ReviewConversation::create($attributes + ['lead_id' => $case->lead_id, 'operation_case_id' => $case->id,
            'customer_phone' => '919876543210', 'customer_replied' => false]);
    }

    private function request(array $filters = []): Request
    {
        $request = Request::create('/admin/operations/queue/review', 'GET', $filters);
        $request->setLaravelSession(app('session.store')); $request->setUserResolver(fn () => $this->ops);
        app()->instance('request', $request); auth()->setUser($this->ops);
        return $request;
    }

    public function test_counts_cover_full_filtered_dataset_not_page_and_waiting_requires_sent_request(): void
    {
        for ($i = 0; $i < 12; $i++) { $this->case(); }
        $waiting = $this->case(); $this->conversation($waiting, ['initial_message_sent_at' => now()->subDay()]);
        $negative = $this->case(['assigned_to' => $this->other->id]);
        $this->conversation($negative, ['customer_replied' => true, 'sentiment' => 'negative']);
        $this->case(['completed_at' => now(), 'status' => 'completed']);
        $controller = app(OperationsDashboardController::class);
        $data = $controller->queue($this->request(['per_page' => 10]), 'review')->getData();
        $this->assertSame(14, $data['summary']['all']); $this->assertSame(10, $data['cases']->count());
        $this->assertSame(12, $data['summary']['not_sent']); $this->assertSame(1, $data['summary']['waiting']);
        $this->assertSame(1, $data['summary']['negative']); $this->assertSame(4.0, $data['summary']['average_days']);
        $negativeData = $controller->queue($this->request(['segment' => 'negative']), 'review')->getData();
        $this->assertSame(1, $negativeData['cases']->total()); $this->assertSame(14, $negativeData['summary']['all']);
        $empty = $controller->queue($this->request(['from_date' => '2020-01-01', 'to_date' => '2020-01-02']), 'review')->getData();
        $this->assertSame(0, $empty['summary']['all']); $this->assertSame(null, $empty['summary']['average_days']);
    }

    public function test_review_claim_cannot_take_another_assignment_and_does_not_change_sales_owner(): void
    {
        $case = $this->case(['assigned_to' => $this->other->id]);
        try { app(OperationCaseService::class)->start($case, $this->ops); throw new \RuntimeException('Reassignment was allowed'); }
        catch (ValidationException $e) { $this->assertSame($this->other->id, $case->fresh()->assigned_to); }
        $unassigned = $this->case(); $owner = $unassigned->lead->representative_user_id;
        app(OperationCaseService::class)->start($unassigned, $this->ops);
        $this->assertSame($this->ops->id, $unassigned->fresh()->assigned_to);
        $this->assertSame($owner, $unassigned->lead->fresh()->representative_user_id);
    }

    public function test_states_last_touch_and_image_collection_preserve_existing_workflow(): void
    {
        $case = $this->case(['assigned_to' => $this->ops->id, 'status' => 'in_progress']);
        $conversation = $this->conversation($case, ['initial_message_sent_at' => now()->subDays(2), 'last_reminder_at' => now()->subHour()]);
        $state = app(ReviewQueueService::class)->state($case);
        $this->assertSame('waiting', $state['sentiment']); $this->assertSame('Reminder sent', $state['touch']['label']);
        $case = app(OperationCaseService::class)->completeReview($case, $this->ops, 'Received review');
        $this->assertSame(null, $case->completed_at);
        $state = app(ReviewQueueService::class)->state($case);
        $this->assertSame(true, $state['review_resolved']); $this->assertSame(false, $state['images_resolved']);
        $conversation->update(['customer_replied' => true, 'sentiment' => 'uncertain']);
        $this->assertSame('unknown', app(ReviewQueueService::class)->state($case->fresh())['sentiment']);
    }

    public function test_review_view_renders_real_states_and_keeps_other_queues_unchanged(): void
    {
        $case = $this->case(['assigned_to' => $this->ops->id, 'status' => 'in_progress']);
        $this->conversation($case, ['customer_replied' => true, 'sentiment' => 'positive']);
        $image = $this->case(['assigned_to' => $this->ops->id, 'status' => 'in_progress', 'metadata' => ['review_completed' => true]]);
        $this->case();
        $negative = $this->case(['assigned_to' => $this->other->id]);
        $this->conversation($negative, ['customer_replied' => true, 'sentiment' => 'negative']);
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi-tools/views'));
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        $controller = app(OperationsDashboardController::class);
        $html = $controller->queue($this->request(), 'review')->render();
        foreach (['Review Queue', 'Assign to me', 'Mark review done', 'Mark images done', 'Open review', 'Not sent yet', 'Positive - replied'] as $text) {
            $this->assertSame(true, str_contains($html, $text));
        }
        $this->assertSame(false, str_contains($html, 'vendor-followups'));
        $this->assertSame(false, str_contains($html, '@selected'));
        $this->assertSame('admin.pages.operations.queue', $controller->queue($this->request(), 'refund')->name());
        if ($directory = getenv('CRM_REVIEW_PREVIEW_DIR')) { file_put_contents($directory.'/review-queue.html', $html); }
    }

    public function test_crm_changes_are_visible_on_next_request_without_per_row_queries(): void
    {
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi-tools/views'));
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        $controller = app(OperationsDashboardController::class);
        $first = $this->case();
        $this->conversation($first, ['initial_message_sent_at' => now()->subHour()]);
        $request = $this->request();
        \Illuminate\Support\Facades\DB::enableQueryLog();
        \Illuminate\Support\Facades\DB::flushQueryLog();
        $controller->queue($request, 'review')->render();
        $singleQueries = count(\Illuminate\Support\Facades\DB::getQueryLog());
        for ($i = 0; $i < 18; $i++) { $this->conversation($this->case(), ['initial_message_sent_at' => now()->subHour()]); }
        \Illuminate\Support\Facades\DB::flushQueryLog();
        $controller->queue($this->request(), 'review')->render();
        $manyQueries = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();
        $this->assertSame($singleQueries, $manyQueries);
        $data = $controller->queue($this->request(), 'review')->getData();
        $this->assertSame(19, $data['summary']['waiting']);
        $first->reviewConversation->update(['customer_replied' => true, 'sentiment' => 'negative']);
        $data = $controller->queue($this->request(), 'review')->getData();
        $this->assertSame(18, $data['summary']['waiting']); $this->assertSame(1, $data['summary']['negative']);
    }
}
