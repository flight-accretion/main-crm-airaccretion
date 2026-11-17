<?php

namespace Tests\Feature;

use App\Models\{Client, Lead, LeadFollowup, LeadRefund, LeadVendorPayment, OperationCase, ReviewConversation, User, UserType, Vendor, VendorRefund};
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Opt-in HTTP acceptance against the existing disposable PostgreSQL QA schema. */
class Wave1QueuePostgresAcceptanceTest extends TestCase
{
    private bool $transactionStarted = false;
    private array $evidence = [];

    public function createApplication()
    {
        if (!getenv('CRM_WAVE1_QA_ROOT')) {
            $this->markTestSkipped('Set CRM_WAVE1_QA_ROOT to the existing isolated QA directory.');
        }
        $app = require __DIR__.'/../Support/wave1_qa_bootstrap.php';
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $connection = DB::connection();
        $this->assertSame(['pgsql'], array_keys(config('database.connections')));
        $identity = $connection->selectOne('SELECT current_database() AS db, current_user AS role');
        $this->assertSame('accretion_wave1_qa', $identity->db);
        $this->assertSame('accretion_wave1_qa_role', $identity->role);
        $connection->beginTransaction();
        $this->transactionStarted = true;
        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00:00'));
    }

    protected function tearDown(): void
    {
        if ($this->evidence) {
            fwrite(STDOUT, json_encode($this->evidence, JSON_UNESCAPED_SLASHES).PHP_EOL);
        }
        if ($this->transactionStarted) {
            DB::connection()->rollBack();
        }
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role): User
    {
        $type = UserType::firstOrCreate(['user_type' => $role], ['id' => (string) Str::uuid(), 'status' => 1]);
        $user = User::where('email', 'operations@wave1.invalid')->firstOrFail()->replicate();
        $user->forceFill(['id' => (string) Str::uuid(), 'email' => Str::uuid().'@wave1.invalid',
            'user_type_id' => $type->id, 'name' => 'Queue acceptance '.$role])->saveQuietly();
        return $user;
    }

    private function lead(): Lead
    {
        $client = Client::where('email', 'browser@wave1.invalid')->firstOrFail()->replicate();
        $client->forceFill(['id' => (string) Str::uuid(), 'name' => 'Queue acceptance '.Str::uuid(),
            'email' => Str::uuid().'@wave1.invalid'])->saveQuietly();
        $lead = Lead::whereHas('client', fn ($q) => $q->where('email', 'browser@wave1.invalid'))->firstOrFail()->replicate();
        $lead->forceFill(['id' => (string) Str::uuid(), 'client_id' => $client->id])->saveQuietly();
        return $lead;
    }

    private function operation(string $type, ?User $assignee, array $attributes = []): OperationCase
    {
        return OperationCase::create($attributes + ['lead_id' => $this->lead()->id,
            'type' => $type, 'status' => 'pending', 'assigned_to' => $assignee?->id,
            'opened_at' => now(), 'note' => 'Wave1 queue acceptance']);
    }

    private function ids($items): array
    {
        return collect($items)->pluck('id')->sort()->values()->all();
    }

    private function sameIds(array $expected, $actual): void
    {
        sort($expected);
        $this->assertSame($expected, $this->ids($actual));
    }

    public function test_due_today_matches_all_linked_pages_and_vendor_subset(): void
    {
        $executive = $this->user(UserType::OPERATIONS_EXECUTIVE);
        $other = $this->user(UserType::OPERATIONS_EXECUTIVE);
        $manager = $this->user(UserType::OPERATIONS_MANAGER);
        $expected = [];
        foreach ([null, now()->startOfDay(), now()->endOfDay()] as $due) {
            $expected[] = $this->operation('vendor_followup', $executive, ['next_followup_at' => $due])->id;
        }
        $bill = $this->operation('vendor_followup', $executive, ['metadata' => ['kind' => 'vendor_bill']]);
        $expected[] = $bill->id;
        for ($i = 0; $i < 26; $i++) {
            $expected[] = $this->operation('customer_call', $executive)->id;
        }
        $this->operation('vendor_followup', $executive, ['next_followup_at' => now()->subDay()->endOfDay()]);
        $this->operation('vendor_followup', $executive, ['next_followup_at' => now()->addDay()->startOfDay()]);
        $this->operation('vendor_followup', $executive, ['status' => 'completed', 'completed_at' => now()]);
        $unassigned = $this->operation('vendor_followup', null);
        $outside = $this->operation('vendor_followup', $other);

        $before = OperationCase::orderBy('id')->get()->toJson();
        $this->actingAs($executive);
        $pageOne = $this->get('/admin/operations/dashboard?cases=due')->assertOk();
        $this->assertSame(30, $pageOne->viewData('dueToday'));
        $rows = $pageOne->viewData('caseRows');
        $this->assertSame(30, $rows->total());
        $pageTwo = $this->get('/admin/operations/dashboard?cases=due&page=2')->assertOk();
        $this->sameIds($expected, $rows->getCollection()->concat($pageTwo->viewData('caseRows')->getCollection()));
        $vendorQueue = $this->get('/admin/operations/vendor-followups?assigned_to='.$executive->id)->assertOk();
        $this->assertSame(6, $vendorQueue->viewData('cases')->total());
        $this->assertSame(6, $pageOne->viewData('vendorCount'));
        foreach (['overdue' => 1, 'today' => 2, 'upcoming' => 1] as $bucket => $count) {
            $subset = $this->get('/admin/operations/vendor-followups?assigned_to='.$executive->id.'&bucket='.$bucket)->assertOk();
            $this->assertSame($count, $subset->viewData('cases')->total());
            $this->assertSame($count, $subset->viewData('counts')[$bucket]);
            $expectedBucketIds = OperationCase::where('assigned_to', $executive->id)->where('type', 'vendor_followup')
                ->whereNull('completed_at')->where('status', '!=', 'completed')
                ->whereDate('next_followup_at', ['overdue' => '<', 'today' => '=', 'upcoming' => '>'][$bucket], now()->toDateString())
                ->pluck('id')->all();
            $this->sameIds($expectedBucketIds, $subset->viewData('cases')->getCollection());
        }
        $dueVendorIds = OperationCase::whereIn('id', $expected)->where('type', 'vendor_followup')->pluck('id')->all();
        $vendorDue = $vendorQueue->viewData('cases')->getCollection()->filter(fn ($case) =>
            !$case->next_followup_at || $case->next_followup_at->isSameDay(now()));
        $this->sameIds($dueVendorIds, $vendorDue);

        $this->actingAs($manager);
        $managerPage = $this->get('/admin/operations/dashboard?cases=due')->assertOk();
        $allDueIds = OperationCase::whereNull('completed_at')->where('status', '!=', 'completed')
            ->where(fn ($q) => $q->whereNull('next_followup_at')->orWhereBetween('next_followup_at', [now()->startOfDay(), now()->endOfDay()]))
            ->pluck('id')->all();
        $managerRows = collect();
        for ($page = 1; $page <= $managerPage->viewData('caseRows')->lastPage(); $page++) {
            $data = $this->get('/admin/operations/dashboard?cases=due&page='.$page)->assertOk();
            $managerRows = $managerRows->concat($data->viewData('caseRows')->getCollection());
        }
        $this->sameIds($allDueIds, $managerRows);
        $this->assertContains($unassigned->id, $allDueIds);
        $this->assertContains($outside->id, $allDueIds);
        $this->assertSame(count($allDueIds), $managerPage->viewData('dueToday'));
        $billQueue = $this->get('/admin/operations/vendor-followups?assigned_to='.$executive->id.'&kind=vendor_bill')->assertOk();
        $this->sameIds([$bill->id], $billQueue->viewData('cases')->getCollection());
        $this->assertSame($before, OperationCase::orderBy('id')->get()->toJson());
        $this->evidence = ['task' => 'T-027', 'executive_due_ids' => $expected, 'vendor_due_ids' => $dueVendorIds,
            'manager_due_ids' => $allDueIds, 'pagination' => [25, 5]];
    }

    public function test_review_segments_filters_and_pagination_match_database_ids(): void
    {
        $actor = $this->user(UserType::OPERATIONS_MANAGER);
        $other = $this->user(UserType::OPERATIONS_EXECUTIVE);
        $expected = array_fill_keys(['waiting', 'negative', 'positive', 'neutral', 'unknown', 'not_sent', 'unassigned'], []);
        foreach (['waiting', 'negative', 'positive', 'neutral', 'unknown', 'not_sent'] as $segment) {
            for ($i = 0; $i < ($segment === 'waiting' ? 12 : 1); $i++) {
                $case = $this->operation('review', $actor, ['note' => 'Segment fixture '.$segment,
                    'status' => $segment === 'positive' ? 'in_progress' : 'pending']);
                $expected[$segment][] = $case->id;
                if ($segment !== 'not_sent') {
                    ReviewConversation::create(['lead_id' => $case->lead_id, 'operation_case_id' => $case->id,
                        'customer_phone' => '919999990000', 'initial_message_sent_at' => now(),
                        'customer_replied' => $segment !== 'waiting',
                        'sentiment' => in_array($segment, ['waiting', 'unknown']) ? null : $segment]);
                }
            }
        }
        $unassigned = $this->operation('review', null, ['opened_at' => now()->subDays(30), 'note' => 'Independent unassigned']);
        $expected['unassigned'][] = $unassigned->id;
        $foreign = $this->operation('review', $other, ['opened_at' => now()->subDays(30)]);
        $closed = $this->operation('review', $actor, ['status' => 'completed', 'completed_at' => now()]);
        $this->actingAs($actor);
        $allExpected = collect($expected)->except('unassigned')->flatten()->sort()->values()->all();
        $allFirst = $this->get('/admin/operations/queue/review?operations_user_id='.$actor->id.'&per_page=10')->assertOk();
        $allSecond = $this->get('/admin/operations/queue/review?operations_user_id='.$actor->id.'&per_page=10&page=2')->assertOk();
        $this->sameIds($allExpected, $allFirst->viewData('cases')->getCollection()->concat($allSecond->viewData('cases')->getCollection()));
        $this->assertSame(count($allExpected), $allFirst->viewData('summary')['all']);
        $statusFiltered = $this->get('/admin/operations/queue/review?operations_user_id='.$actor->id.'&status=in_progress')->assertOk();
        $this->sameIds($expected['positive'], $statusFiltered->viewData('cases')->getCollection());
        $results = [];
        foreach (array_keys($expected) as $segment) {
            $parameters = ['operations_user_id' => $actor->id, 'from_date' => now()->toDateString(),
                'to_date' => now()->toDateString(), 'segment' => $segment, 'per_page' => 10];
            if ($segment === 'unassigned') {
                $expectedIds = OperationCase::where('type', 'review')->whereNull('completed_at')->whereNull('assigned_to')->pluck('id')->all();
            } else {
                $expectedIds = $expected[$segment];
            }
            $first = $this->get('/admin/operations/queue/review?'.http_build_query($parameters))->assertOk();
            $all = collect();
            for ($page = 1; $page <= $first->viewData('cases')->lastPage(); $page++) {
                $parameters['page'] = $page;
                $response = $this->get('/admin/operations/queue/review?'.http_build_query($parameters))->assertOk();
                $all = $all->concat($response->viewData('cases')->getCollection());
            }
            $this->sameIds($expectedIds, $all);
            $this->assertSame(count($expectedIds), $first->viewData('summary')[$segment]);
            $this->assertNotContains($closed->id, $this->ids($all));
            $results[$segment] = $this->ids($all);
        }
        $dashboard = $this->get('/admin/operations/dashboard')->assertOk();
        $waiting = $this->get('/admin/operations/queue/review?segment=waiting')->assertOk();
        $this->assertSame($dashboard->viewData('reviewCount'), $waiting->viewData('cases')->total());
        $empty = $this->get('/admin/operations/queue/review?search=NO-SUCH-QUEUE-CUSTOMER&segment=waiting')->assertOk();
        $this->assertSame(0, $empty->viewData('cases')->total());
        $this->assertSame(0, $empty->viewData('summary')['waiting']);
        $filtered = $this->get('/admin/operations/queue/review?operations_user_id='.$other->id)->assertOk();
        $this->sameIds([$foreign->id], $filtered->viewData('cases')->getCollection());
        $dated = $this->get('/admin/operations/queue/review?operations_user_id='.$other->id
            .'&from_date='.now()->toDateString().'&to_date='.now()->toDateString())->assertOk();
        $this->assertSame(0, $dated->viewData('cases')->total());
        $unassignedSearch = $this->get('/admin/operations/queue/review?segment=unassigned&search=NO-SUCH-QUEUE-CUSTOMER'
            .'&status=in_progress&operations_user_id='.$other->id.'&date_filter=today')->assertOk();
        $this->sameIds($results['unassigned'], $unassignedSearch->viewData('cases')->getCollection());
        $this->evidence = ['task' => 'T-029', 'segment_ids' => $results, 'dashboard_waiting' => $dashboard->viewData('reviewCount')];
    }

    public function test_completed_status_is_excluded_from_dashboard_linked_open_queues(): void
    {
        $actor = $this->user(UserType::OPERATIONS_EXECUTIVE);
        $vendor = $this->operation('vendor_followup', $actor, ['status' => 'completed', 'completed_at' => null]);
        $review = $this->operation('review', $actor, ['status' => 'completed', 'completed_at' => null]);
        ReviewConversation::create(['lead_id' => $review->lead_id, 'operation_case_id' => $review->id,
            'customer_phone' => '919999990000', 'initial_message_sent_at' => now(), 'customer_replied' => false]);
        $this->actingAs($actor);
        $dashboard = $this->get('/admin/operations/dashboard')->assertOk();
        $this->assertSame(0, $dashboard->viewData('vendorCount'));
        $this->assertSame(0, $dashboard->viewData('reviewCount'));
        $vendorPage = $this->get('/admin/operations/vendor-followups?assigned_to='.$actor->id)->assertOk();
        $this->assertSame(0, $vendorPage->viewData('cases')->total());
        $this->assertSame(0, $vendorPage->viewData('counts')['open']);
        $reviewPage = $this->get('/admin/operations/queue/review?segment=waiting&operations_user_id='.$actor->id)->assertOk();
        $this->assertSame(0, $reviewPage->viewData('cases')->total());
        $this->assertNotContains($vendor->id, $vendorPage->viewData('cases')->pluck('id')->all());
        $this->assertNotContains($review->id, $reviewPage->viewData('cases')->pluck('id')->all());
    }

    public function test_waiting_refund_approval_ids_tabs_filters_and_transitions(): void
    {
        $actor = $this->user(UserType::ACCOUNTS_MANAGER);
        $this->actingAs($actor);
        $pending = [];
        $vendorPending = [];
        $createdCustomers = [];
        $createdPayments = [];
        $vendor = Vendor::where('email', 'vendor@wave1.invalid')->firstOrFail();
        foreach (['pending', 'approved', 'rejected'] as $approval) {
            $lead = $this->lead();
            $serviceDate = $approval === 'pending' ? '2026-11-15' : '2026-11-16';
            \App\Models\LeadRide::create(['id' => (string) Str::uuid(), 'lead_id' => $lead->id,
                'from_date' => $serviceDate.' 10:00:00', 'to_date' => $serviceDate.' 11:00:00']);
            $source = LeadFollowup::where('status', 2)->firstOrFail();
            $followup = $source->replicate();
            $followup->forceFill(['id' => (string) Str::uuid(), 'lead_id' => $lead->id])->saveQuietly();
            $refund = LeadRefund::forceCreate(['id' => (string) Str::uuid(), 'lead_followup_id' => $followup->id,
                'original_amount' => 5000, 'refund_amount' => 1000, 'refund_type' => 'Bank Transfer',
                'refund_date' => $serviceDate, 'status' => 1, 'approval_status' => $approval]);
            $createdCustomers[$approval] = $refund;
            if ($approval === 'pending') {
                $pending[] = $refund->id;
            }
            $payment = LeadVendorPayment::create(['id' => (string) Str::uuid(), 'lead_id' => $lead->id,
                'vendor_id' => $vendor->id, 'total_service_amount' => 5000, 'total_vendor_service_amount' => 4000, 'payment_status' => 'unpaid']);
            $createdPayments[$approval] = $payment;
            for ($i = 0; $i < 2; $i++) {
                VendorRefund::create(['lead_id' => $lead->id, 'lead_vendor_payment_id' => $payment->id, 'vendor_id' => $vendor->id,
                    'refund_amount' => 500, 'cancellation_amount' => 3000, 'status' => 1, 'approval_status' => $approval]);
            }
            if ($approval === 'pending') {
                $vendorPending[] = $payment->id;
            }
        }
        // Completed and never-sent customer records must not enter the waiting set.
        foreach ([0, 2] as $status) {
            $followup = LeadFollowup::where('status', 2)->firstOrFail()->replicate();
            $followup->forceFill(['id' => (string) Str::uuid(), 'lead_id' => $this->lead()->id])->saveQuietly();
            LeadRefund::forceCreate(['id' => (string) Str::uuid(), 'lead_followup_id' => $followup->id,
                'original_amount' => 5000, 'refund_amount' => 1000, 'status' => $status, 'approval_status' => 'pending']);
        }
        // More than one vendor page, with a completed row alongside an open group.
        $pageIds = [];
        for ($i = 0; $i < 12; $i++) {
            $payment = LeadVendorPayment::create(['id' => (string) Str::uuid(), 'lead_id' => $this->lead()->id,
                'vendor_id' => $vendor->id, 'total_service_amount' => 5000, 'total_vendor_service_amount' => 4000, 'payment_status' => 'unpaid']);
            $pageIds[] = $payment->id;
            foreach ([1, 2] as $status) {
                VendorRefund::create(['lead_id' => $payment->lead_id, 'lead_vendor_payment_id' => $payment->id,
                    'vendor_id' => $vendor->id, 'refund_amount' => 500, 'cancellation_amount' => 3000,
                    'status' => $status, 'approval_status' => $status === 1 ? 'approved' : 'pending']);
            }
        }
        $customer = $this->get('/admin/refunds')->assertOk();
        $vendorPage = $this->get('/admin/operations/vendor-refunds?per_page=10')->assertOk();
        $expectedCustomer = LeadRefund::where('status', 1)->where('approval_status', 'pending')->pluck('id')->all();
        $expectedVendor = VendorRefund::where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 2))
            ->where('approval_status', 'pending')->pluck('lead_vendor_payment_id')->unique()->values()->all();
        $customerWaiting = collect($customer->viewData('refundsData'))->where('approval_status', 'pending')->pluck('refund_id')->sort()->values()->all();
        sort($expectedCustomer);
        $this->assertSame($expectedCustomer, $customerWaiting);
        $allVendor = collect();
        for ($page = 1; $page <= $vendorPage->viewData('vendorRefunds')->lastPage(); $page++) {
            $result = $this->get('/admin/operations/vendor-refunds?per_page=10&page='.$page)->assertOk();
            $allVendor = $allVendor->concat($result->viewData('vendorRefunds')->getCollection());
        }
        $this->sameIds($expectedVendor, $allVendor->where('approval_status', 'pending'));
        $count = count($expectedCustomer) + count($expectedVendor);
        $this->assertSame($count, $customer->viewData('refundSummary')['waiting_approval']);
        $this->assertSame($count, $vendorPage->viewData('refundSummary')['waiting_approval']);
        $this->assertGreaterThan(1, $vendorPage->viewData('vendorRefunds')->lastPage());
        foreach ($createdCustomers as $approval => $refund) {
            $name = $refund->leadFollowup->enquiry->client->name;
            $filteredCustomer = $this->get('/admin/refunds?'.http_build_query([
                'name' => $name, 'refund_date' => $refund->refund_date->toDateString(),
            ]))->assertOk();
            $this->assertSame([$refund->id], collect($filteredCustomer->viewData('refundsData'))->pluck('refund_id')->all());
            $filteredVendor = $this->get('/admin/operations/vendor-refunds?'.http_build_query([
                'customer' => $name, 'from_service_date' => $refund->refund_date->toDateString(),
                'to_service_date' => $refund->refund_date->toDateString(),
            ]))->assertOk();
            $this->sameIds([$createdPayments[$approval]->id], $filteredVendor->viewData('vendorRefunds')->getCollection());
            $this->assertSame($approval, $filteredVendor->viewData('vendorRefunds')->first()->approval_status);
            // Shared summary cards intentionally remain global across tabs and list filters.
            $this->assertSame($count, $filteredCustomer->viewData('refundSummary')['waiting_approval']);
            $this->assertSame($count, $filteredVendor->viewData('refundSummary')['waiting_approval']);
        }
        $noMatch = $this->get('/admin/operations/vendor-refunds?from_service_date=2040-01-01')->assertOk();
        $this->assertSame(0, $noMatch->viewData('vendorRefunds')->total());
        $beforeAmount = LeadRefund::findOrFail($pending[0])->only(['status', 'refund_amount', 'original_amount']);
        $this->postJson('/admin/refunds/'.$pending[0].'/approval', ['decision' => 'approve'])->assertOk();
        $this->assertSame($beforeAmount, LeadRefund::findOrFail($pending[0])->only(['status', 'refund_amount', 'original_amount']));
        $this->assertSame($count - 1, $this->get('/admin/refunds')->assertOk()->viewData('refundSummary')['waiting_approval']);
        $this->postJson('/admin/refunds/'.$pending[0].'/approval', ['decision' => 'reject', 'note' => 'Synthetic QA'])->assertOk();
        $this->assertSame($count - 1, $this->get('/admin/operations/vendor-refunds')->assertOk()->viewData('refundSummary')['waiting_approval']);
        $vendorMoney = $createdPayments['pending']->vendorRefunds()->orderBy('id')->get()
            ->map->only(['id', 'status', 'refund_amount', 'cancellation_amount'])->all();
        $this->postJson('/admin/refunds/vendor/'.$vendorPending[0].'/approval', ['decision' => 'approve'])->assertOk();
        $this->assertSame($count - 2, $this->get('/admin/refunds')->assertOk()->viewData('refundSummary')['waiting_approval']);
        $this->postJson('/admin/refunds/vendor/'.$vendorPending[0].'/approval', ['decision' => 'reject', 'note' => 'Synthetic QA'])->assertOk();
        $this->assertSame($count - 2, $this->get('/admin/refunds')->assertOk()->viewData('refundSummary')['waiting_approval']);
        $this->assertSame($vendorMoney, $createdPayments['pending']->vendorRefunds()->orderBy('id')->get()
            ->map->only(['id', 'status', 'refund_amount', 'cancellation_amount'])->all());
        $this->evidence = ['task' => 'T-037', 'customer_pending_ids' => $expectedCustomer,
            'vendor_payment_pending_ids' => $expectedVendor, 'waiting_before' => $count, 'waiting_after' => $count - 2,
            'vendor_page_count' => $vendorPage->viewData('vendorRefunds')->lastPage()];
    }

    public function test_role_routes_and_approval_permissions_remain_unchanged(): void
    {
        $followup = LeadFollowup::where('status', 2)->firstOrFail()->replicate();
        $followup->forceFill(['id' => (string) Str::uuid(), 'lead_id' => $this->lead()->id])->saveQuietly();
        $refund = LeadRefund::forceCreate(['id' => (string) Str::uuid(), 'lead_followup_id' => $followup->id,
            'original_amount' => 5000, 'refund_amount' => 1000, 'status' => 1, 'approval_status' => 'pending']);
        $roles = array_merge(UserType::ADMIN_ROLES, UserType::OPERATIONS_ROLES, UserType::ACCOUNTS_ROLES,
            [UserType::OWNER, UserType::HR, UserType::SALES_MANAGER, UserType::SALES_EXECUTIVE]);
        $matrix = [];
        foreach ($roles as $role) {
            $this->actingAs($this->user($role));
            $opsAllowed = in_array($role, array_merge(UserType::ADMIN_ROLES, UserType::OPERATIONS_ROLES, [UserType::OWNER]), true);
            $refundAllowed = $opsAllowed || in_array($role, UserType::ACCOUNTS_ROLES, true);
            foreach ([
                '/admin/operations/dashboard?cases=due' => $opsAllowed,
                '/admin/operations/queue/review' => $opsAllowed,
                '/admin/refunds' => $refundAllowed,
                '/admin/operations/vendor-refunds' => $refundAllowed,
            ] as $route => $allowed) {
                $this->get($route)->assertStatus($allowed ? 200 : 403);
                $matrix[$role][$route] = $allowed ? 200 : 403;
            }
            $canDecide = in_array($role, array_merge([UserType::SUPER_ADMIN], UserType::OPERATIONS_ROLES, UserType::ACCOUNTS_ROLES), true);
            $before = $refund->fresh()->getAttributes();
            $this->postJson('/admin/refunds/'.$refund->id.'/approval', ['decision' => 'approve'])
                ->assertStatus($canDecide ? 200 : 403);
            $matrix[$role]['approval_post'] = $canDecide ? 200 : 403;
            if (!$canDecide) {
                $this->assertSame($before, $refund->fresh()->getAttributes());
            }
            $this->assertSame(1, (int) $refund->fresh()->status);
            $this->assertSame(1000.0, (float) $refund->fresh()->refund_amount);
        }
        $this->evidence = ['role_routes' => $matrix];
    }
}
