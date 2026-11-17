<?php

namespace Tests\Feature;

use App\Models\{Client, Lead, LeadFollowup, LeadPassenger, LeadRefund, LeadRide, LeadVendorPayment, LeadVendorPaymentDetail, PaymentAuditTrail, Product, SalesExecutiveAssignment, Service, User, UserType, Vendor, VendorPayment, Voucher};
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/** Authenticated HTTP acceptance. All fixtures are rolled back on the dedicated QA database. */
class Wave1ReportsPostgresAcceptanceTest extends TestCase
{
    private bool $transactionStarted = false;
    private array $evidence = [];

    public function createApplication()
    {
        if (!getenv('CRM_WAVE1_QA_ROOT')) {
            $this->markTestSkipped('Requires the existing isolated PostgreSQL QA bootstrap.');
        }
        $app = require __DIR__.'/../Support/wave1_qa_bootstrap.php';
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(['pgsql'], array_keys(config('database.connections')));
        $identity = DB::selectOne('SELECT current_database() AS db, current_user AS role');
        $this->assertSame('accretion_wave1_qa', $identity->db);
        $this->assertSame('accretion_wave1_qa_role', $identity->role);
        DB::beginTransaction();
        $this->transactionStarted = true;
        Carbon::setTestNow(Carbon::parse('2026-11-20 12:00:00'));
    }

    protected function tearDown(): void
    {
        if ($this->evidence) {
            fwrite(STDOUT, json_encode($this->evidence, JSON_UNESCAPED_SLASHES).PHP_EOL);
        }
        if ($this->transactionStarted) {
            DB::rollBack();
        }
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role): User
    {
        $type = UserType::firstOrCreate(['user_type' => $role], ['id' => (string) Str::uuid(), 'status' => 1]);
        $user = User::where('email', 'operations@wave1.invalid')->firstOrFail()->replicate();
        $user->forceFill(['id' => (string) Str::uuid(), 'user_type_id' => $type->id,
            'email' => Str::uuid().'@wave1.invalid', 'name' => 'Report QA '.$role])->saveQuietly();
        return $user;
    }

    private function lead(?User $owner = null, ?string $name = null): Lead
    {
        $client = Client::where('email', 'browser@wave1.invalid')->firstOrFail()->replicate();
        $client->forceFill(['id' => (string) Str::uuid(), 'name' => $name ?? 'Report QA '.Str::uuid(),
            'email' => Str::uuid().'@wave1.invalid'])->saveQuietly();
        $lead = Lead::whereHas('client', fn ($q) => $q->where('email', 'browser@wave1.invalid'))->firstOrFail()->replicate();
        $lead->forceFill(['id' => (string) Str::uuid(), 'client_id' => $client->id,
            'representative_user_id' => $owner?->id, 'service_ids' => [], 'product_ids' => []])->saveQuietly();
        return $lead;
    }

    private function followup(Lead $lead, array $attributes = []): LeadFollowup
    {
        $row = LeadFollowup::whereHas('enquiry.client', fn ($q) => $q->where('email', 'browser@wave1.invalid'))
            ->where('status', 4)->firstOrFail()->replicate();
        $row->forceFill($attributes + ['id' => (string) Str::uuid(), 'lead_id' => $lead->id,
            'followed_by' => $lead->representative_user_id, 'status' => 4,
            'total_amount' => 5000, 'service_amount' => 5000, 'discount_amount' => 0,
            'service_ids' => [], 'extra_service_ids' => [], 'service_details' => [], 'received_amount' => 0,
            'pricing_revision' => true, 'created_at' => now(), 'updated_at' => now(), 'added_to_sales' => false]);
        $row->saveQuietly();
        return $row;
    }

    private function audit(LeadFollowup $followup, float $amount, int $status = 1): void
    {
        $row = new PaymentAuditTrail();
        $row->forceFill(['id' => (string) Str::uuid(), 'lead_followup_id' => $followup->id,
            'paid_amount' => $amount, 'payment_status' => $status, 'paid_date' => now(),
            'payment_method' => 'Bank Transfer', 'created_by' => $followup->followed_by])->saveQuietly();
    }

    private function sameIds(array $expected, array $actual): void
    {
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual);
    }

    public function test_hr_distinct_lead_volume_filters_and_role_scope(): void
    {
        $hr = $this->user(UserType::HR);
        $admin = $this->user(UserType::ADMIN);
        $manager = $this->user(UserType::SALES_MANAGER);
        $executive = $this->user(UserType::SALES_EXECUTIVE);
        $foreign = $this->user(UserType::SALES_EXECUTIVE);
        SalesExecutiveAssignment::create(['id' => (string) Str::uuid(), 'manager_id' => $manager->id,
            'sales_executive_id' => $executive->id, 'status' => 1]);
        $product = Product::create(['id' => (string) Str::uuid(), 'product' => 'HR QA product', 'status' => 1]);
        $service = Service::create(['id' => (string) Str::uuid(), 'service' => 'HR QA service',
            'product_ids' => [$product->id], 'service_amount' => 100, 'status' => 1]);
        $expected = [];
        $measurements = [];
        foreach ([1, 100, 500] as $size) {
            while (count($expected) < $size) {
                $lead = $this->lead($executive);
                $lead->update(['service_ids' => [$service->id]]);
                $this->followup($lead, ['status' => 1, 'next_followup_date' => now()->subDay(), 'created_at' => now()->subHours(2)]);
                $this->followup($lead, ['status' => 1, 'next_followup_date' => now(), 'created_at' => now()->subHour()]);
                $expected[] = $lead->id;
            }
            $this->actingAs($hr);
            DB::enableQueryLog();
            DB::flushQueryLog();
            $started = microtime(true);
            $response = $this->get('/admin/upcoming-follow-up?representative_user_id='.$executive->id)->assertOk();
            $elapsed = round((microtime(true) - $started) * 1000, 1);
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            $rows = $response->viewData('arrFollowUps');
            $this->sameIds($expected, $rows->pluck('lead_id')->all());
            $this->assertSame($size, $rows->pluck('lead_id')->unique()->count());
            $measurements[$size] = ['queries' => count($queries), 'milliseconds' => $elapsed,
                'followup_queries' => count(array_filter($queries, fn ($q) => str_contains($q['query'], 'from "lead_followups"')))];
        }
        $this->assertLessThanOrEqual($measurements[100]['queries'] + 2, $measurements[500]['queries']);
        $outside = $this->lead($foreign);
        $this->followup($outside, ['status' => 1, 'next_followup_date' => now()]);
        $future = $this->lead($executive);
        $this->followup($future, ['status' => 1, 'next_followup_date' => now()->addDay()]);
        $cancelled = $this->lead($executive);
        $this->followup($cancelled, ['status' => 1, 'next_followup_date' => now(), 'created_at' => now()->subHour()]);
        $this->followup($cancelled, ['status' => 2, 'next_followup_date' => now()]);
        foreach ([$hr, $admin, $manager, $executive] as $actor) {
            $this->actingAs($actor);
            $response = $this->get('/admin/upcoming-follow-up?representative_user_id='.$executive->id.'&product_id='.$product->id)->assertOk();
            $this->sameIds($expected, $response->viewData('arrFollowUps')->pluck('lead_id')->all());
        }
        $this->actingAs($manager);
        $this->assertCount(0, $this->get('/admin/upcoming-follow-up?representative_user_id='.$foreign->id)->assertOk()->viewData('arrFollowUps'));
        $this->actingAs($hr);
        $foreignRows = $this->get('/admin/upcoming-follow-up?representative_user_id='.$foreign->id)->assertOk()->viewData('arrFollowUps');
        $this->sameIds([$outside->id], $foreignRows->pluck('lead_id')->all());
        $empty = $this->get('/admin/upcoming-follow-up?representative_user_id='.$executive->id.'&from_date=2026-11-01')->assertOk();
        $this->assertCount(0, $empty->viewData('arrFollowUps'));
        $tomorrow = $this->get('/admin/upcoming-follow-up?representative_user_id='.$executive->id.'&from_date=2026-11-21')->assertOk();
        $this->sameIds(array_merge($expected, [$future->id]), $tomorrow->viewData('arrFollowUps')->pluck('lead_id')->all());
        $this->evidence = ['task' => 'T-017', 'volume' => $measurements, 'lead_ids' => $expected,
            'pagination' => 'Existing endpoint returns the complete filtered collection.'];
    }

    public function test_add_to_sales_reload_filters_duplicate_and_audit_preservation(): void
    {
        $actor = $this->user(UserType::ACCOUNTS_MANAGER);
        $lead = $this->lead($actor);
        $row = $this->followup($lead, ['total_amount' => 35000, 'service_amount' => 35000, 'received_amount' => 40000]);
        $this->audit($row, 40000);
        $audits = PaymentAuditTrail::where('lead_followup_id', $row->id)->get()->toJson();
        $this->actingAs($actor);
        $url = '/admin/account/exceptional?from_date=2026-11-20&to_date=2026-11-20';
        $before = $this->get($url)->assertOk();
        $baselineCount = $before->viewData('addedToSalesCount');
        $this->assertTrue($before->viewData('payments')->contains('lead_id', $lead->id));
        $action = '/admin/account/exceptional/'.$row->id.'/add-to-sales';
        $this->postJson($action)->assertOk()->assertJsonPath('extra_added', 5000)->assertJsonPath('new_total', 40000);
        $this->assertSame(40000.0, (float) $row->fresh()->total_amount);
        $this->assertTrue((bool) $row->fresh()->added_to_sales);
        $this->assertSame(4, (int) $row->fresh()->status);
        $after = $this->get($url)->assertOk();
        $this->assertSame($baselineCount + 1, $after->viewData('addedToSalesCount'));
        $this->assertFalse($after->viewData('payments')->contains('lead_id', $lead->id));
        $this->assertSame($baselineCount + 1, $this->get($url)->assertOk()->viewData('addedToSalesCount'));
        $otherDate = $this->get('/admin/account/exceptional?from_date=2026-11-21&to_date=2026-11-21')->assertOk();
        $this->assertSame(0, $otherDate->viewData('addedToSalesCount'));
        $this->postJson($action)->assertStatus(422);
        $this->assertSame($audits, PaymentAuditTrail::where('lead_followup_id', $row->id)->get()->toJson());
        $this->actingAs($this->user(UserType::SALES_EXECUTIVE))->postJson($action)->assertForbidden();
        $this->evidence = ['task' => 'T-042', 'lead_id' => $lead->id, 'followup_id' => $row->id,
            'revenue' => [35000, 40000], 'added_count' => [$baselineCount, $baselineCount + 1], 'audit_unchanged' => true];
    }

    public function test_mixed_overpayments_and_history_survive_date_filters(): void
    {
        $actor = $this->user(UserType::ACCOUNTS_MANAGER);
        $this->actingAs($actor);
        $rows = [];
        foreach (['open', 'zero', 'legacy', 'rejected', 'refunded', 'added'] as $kind) {
            $lead = $this->lead($actor);
            $old = $this->followup($lead, ['total_amount' => 5000, 'service_amount' => 5000, 'received_amount' => 8500,
                'created_at' => now()->subDay()]);
            $this->audit($old, 8500, $kind === 'rejected' ? 2 : 1);
            if (in_array($kind, ['zero', 'legacy'], true)) {
                $this->followup($lead, ['total_amount' => 0, 'service_amount' => 0, 'received_amount' => 0,
                    'pricing_revision' => $kind === 'zero' ? true : null, 'status' => 10]);
            }
            if ($kind === 'refunded') {
                LeadRefund::forceCreate(['id' => (string) Str::uuid(), 'lead_followup_id' => $old->id,
                    'original_amount' => 5000, 'refund_amount' => 3500, 'status' => 2]);
            }
            if ($kind === 'added') {
                $this->postJson('/admin/account/exceptional/'.$old->id.'/add-to-sales')->assertOk();
            }
            $rows[$kind] = ['lead' => $lead, 'followup' => $old];
        }
        $fixtureIds = array_map(fn ($row) => $row['lead']->id, $rows);
        $audits = PaymentAuditTrail::whereIn('lead_followup_id', LeadFollowup::whereIn('lead_id', $fixtureIds)->pluck('id'))->orderBy('id')->get()->toJson();
        $all = $this->get('/admin/account/exceptional')->assertOk()->viewData('payments')->whereIn('lead_id', $fixtureIds)->keyBy('lead_id');
        $this->sameIds(array_map(fn ($kind) => $rows[$kind]['lead']->id, ['open', 'zero', 'legacy', 'refunded']), $all->keys()->all());
        $this->assertSame(-8500.0, $all[$rows['zero']['lead']->id]->balance);
        $this->assertSame(-3500.0, $all[$rows['legacy']['lead']->id]->balance);
        $this->assertTrue($all[$rows['refunded']['lead']->id]->has_refund_note);
        $openIds = $all->filter(fn ($row) => !$row->has_refund_note && !$row->added_to_sales)->keys()->all();
        $this->sameIds(array_map(fn ($kind) => $rows[$kind]['lead']->id, ['open', 'zero', 'legacy']), $openIds);
        $dated = $this->get('/admin/account/exceptional?from_date=2026-11-20&to_date=2026-11-20')->assertOk()
            ->viewData('payments')->whereIn('lead_id', $fixtureIds)->keyBy('lead_id');
        // Activity on the selected day must retain the booking's earlier price and approved receipts.
        $this->sameIds([$rows['zero']['lead']->id, $rows['legacy']['lead']->id], $dated->keys()->all());
        $this->assertSame(-8500.0, $dated[$rows['zero']['lead']->id]->balance);
        $this->assertSame(-3500.0, $dated[$rows['legacy']['lead']->id]->balance);
        $this->assertSame($audits, PaymentAuditTrail::whereIn('lead_followup_id', LeadFollowup::whereIn('lead_id', $fixtureIds)->pluck('id'))->orderBy('id')->get()->toJson());
        $this->evidence = ['task' => 'T-043', 'kind_lead_ids' => $fixtureIds, 'open_ids' => $openIds,
            'balances' => $all->map->balance->all(), 'dated_ids' => $dated->keys()->all(), 'pagination' => 'No server pagination implemented.'];
    }

    public function test_vendor_report_filter_matrix_matches_html_csv_and_xlsx_ids(): void
    {
        $this->actingAs($this->user(UserType::ACCOUNTS_MANAGER));
        $marker = 'VendorMatrix'.Str::uuid();
        $vendor = Vendor::create(['id' => (string) Str::uuid(), 'name' => $marker, 'contact_number' => '9999990044', 'status' => 1]);
        $service = Service::create(['id' => (string) Str::uuid(), 'service' => $marker, 'status' => 1, 'service_amount' => 1000]);
        $fixture = [];
        foreach (['paid', 'partial', 'unpaid'] as $i => $status) {
            $lead = $this->lead(null, $marker.' '.$status);
            $rideDate = '2026-11-'.(18 + $i);
            LeadRide::create(['id' => (string) Str::uuid(), 'lead_id' => $lead->id,
                'from_date' => $rideDate.' 10:00:00', 'to_date' => $rideDate.' 11:00:00']);
            $payment = LeadVendorPayment::create(['id' => (string) Str::uuid(), 'lead_id' => $lead->id, 'vendor_id' => $vendor->id,
                'total_service_amount' => 1000, 'total_vendor_service_amount' => 1000, 'payment_status' => $status]);
            LeadVendorPaymentDetail::create(['id' => (string) Str::uuid(), 'lead_vendor_payment_id' => $payment->id,
                'service_id' => $service->id, 'service_amount' => 1000, 'vendor_service_amount' => 1000, 'is_extra_service' => false, 'status' => 1]);
            if ($status !== 'unpaid') {
                VendorPayment::create(['lead_vendor_payment_id' => $payment->id, 'paid_amount' => $status === 'paid' ? 1000 : 400,
                    'payment_method' => 'Bank Transfer', 'paid_date' => '2026-11-'.(17 + $i), 'status' => 1]);
            }
            $fixture[$status] = ['payment' => $payment, 'lead' => $lead, 'client_name' => $lead->client->name];
        }
        $all = array_map(fn ($row) => $row['payment']->id, $fixture);
        $matrix = [
            'all' => [[], array_values($all)],
            'paid' => [['status' => 'paid'], [$all['paid']]],
            'partial' => [['status' => 'partial'], [$all['partial']]],
            'unpaid' => [['status' => 'unpaid'], [$all['unpaid']]],
            'payment_date' => [['from_payment_date' => '2026-11-18', 'to_payment_date' => '2026-11-18'], [$all['partial']]],
            'payment_from' => [['from_payment_date' => '2026-11-18'], [$all['partial']]],
            'service_date' => [['service_date_from' => '2026-11-20', 'service_date_to' => '2026-11-20'], [$all['unpaid']]],
            'client' => [['client_id' => $fixture['paid']['lead']->client_id], [$all['paid']]],
            'vendor' => [['vendor_id' => $vendor->id], array_values($all)],
            'service' => [['service_id' => $service->id], array_values($all)],
            'search' => [['search' => $fixture['partial']['client_name']], [$all['partial']]],
            'combined' => [['service_id' => $service->id, 'status' => 'partial', 'service_date_from' => '2026-11-19',
                'service_date_to' => '2026-11-19', 'from_payment_date' => '2026-11-18', 'to_payment_date' => '2026-11-18'], [$all['partial']]],
            'empty' => [['service_date_from' => '2040-01-01'], []],
        ];
        $byName = array_column(array_map(fn ($row) => ['name' => $row['client_name'], 'id' => $row['payment']->id], $fixture), 'id', 'name');
        $results = [];
        foreach ($matrix as $name => [$filters, $expected]) {
            $filters += ['search' => $marker];
            $html = $this->get('/admin/report/vendor-payments?'.http_build_query($filters))->assertOk();
            $rows = $html->viewData('rows');
            $this->sameIds($expected, $rows->map(fn ($row) => $byName[$row->client_name])->all());
            $htmlCosts = $rows->mapWithKeys(fn ($row) => [$byName[$row->client_name] => (float) $row->vendor_service_cost])->all();
            foreach (['csv', 'xlsx'] as $format) {
                $response = $this->get('/admin/report/vendor-payments/export?'.http_build_query($filters + ['format' => $format]))->assertOk();
                $bytes = $response->baseResponse->getFile()->getPathname();
                $decoded = IOFactory::load($bytes)->getActiveSheet()->toArray();
                array_shift($decoded);
                $ids = [];
                foreach ($decoded as $row) {
                    $ids[] = $byName[$row[4]];
                    $this->assertSame($htmlCosts[$byName[$row[4]]], (float) str_replace(',', '', (string) $row[7]));
                }
                $this->sameIds($expected, $ids);
            }
            $results[$name] = $expected;
        }
        $this->actingAs($this->user(UserType::SALES_EXECUTIVE))
            ->get('/admin/report/vendor-payments/export?format=csv&search='.$marker)->assertForbidden();
        $this->evidence = ['task' => 'T-052', 'filter_ids' => $results,
            'identity_mapping' => 'Unique synthetic client names map one-to-one to vendor-payment IDs.'];
    }

    public function test_voucher_accepts_same_lead_unlinked_passenger_and_rolls_back_foreign_lead(): void
    {
        $actor = $this->user(UserType::OPERATIONS_MANAGER);
        $this->actingAs($actor);
        $lead = $this->lead();
        $booking = $this->followup($lead, ['total_amount' => 16000, 'service_amount' => 18000,
            'discount_amount' => 2000, 'followed_by' => $actor->id]);
        $this->audit($booking, 8500);
        $approvedPayment = PaymentAuditTrail::where('lead_followup_id', $booking->id)->sole();
        $voucher = Voucher::create(['id' => (string) Str::uuid(), 'lead_id' => $lead->id,
            'operation_team_user_id' => $actor->id, 'status' => 1]);
        $passenger = LeadPassenger::create(['id' => (string) Str::uuid(), 'lead_id' => $lead->id, 'name' => 'Same Lead Passenger', 'age' => 30]);
        $foreign = LeadPassenger::create(['id' => (string) Str::uuid(), 'lead_id' => $this->lead()->id, 'name' => 'Other Lead Passenger', 'age' => 30]);
        $service = Service::where('service', 'Wave One Invoice QA')->firstOrFail();
        $vendor = Vendor::where('email', 'invoice-vendor@wave1.invalid')->firstOrFail();
        $payload = ['lead_id' => $lead->id, 'client_name' => $lead->client->name, 'client_email' => $lead->client->email,
            'client_phone' => '9999990001', 'operation_team_user_id' => $actor->id,
            'services' => [['service_id' => $service->id, 'vendor_id' => $vendor->id, 'amount' => 16000, 'vendor_amount' => 12000]],
            'passengers' => [['id' => $passenger->id, 'name' => $passenger->name, 'age' => 30]]];
        $this->post('/admin/vouchers/store', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull(session('error'), (string) session('error'));
        $this->assertSame($voucher->id, $passenger->fresh()->voucher_id);
        $this->assertSame(1, $voucher->passengers()->count());
        $before = ['passenger' => $passenger->fresh()->getAttributes(), 'foreign' => $foreign->fresh()->getAttributes(),
            'lead' => $lead->fresh()->getAttributes(), 'followups' => $lead->leadFollowups()->orderBy('id')->get()->toJson(),
            'vendor' => $voucher->vendorPayments()->orderBy('id')->get()->toJson()];
        $payload['passengers'][0]['id'] = $foreign->id;
        $this->post('/admin/vouchers/store', $payload)->assertRedirect()->assertSessionHas('error');
        $this->assertSame($before, ['passenger' => $passenger->fresh()->getAttributes(), 'foreign' => $foreign->fresh()->getAttributes(),
            'lead' => $lead->fresh()->getAttributes(), 'followups' => $lead->leadFollowups()->orderBy('id')->get()->toJson(),
            'vendor' => $voucher->vendorPayments()->orderBy('id')->get()->toJson()]);
        $this->assertNull($foreign->fresh()->voucher_id);
        $this->assertSame($approvedPayment->getAttributes(), $approvedPayment->fresh()->getAttributes());
        $this->evidence = ['passenger_allowed' => $passenger->id, 'passenger_denied' => $foreign->id,
            'voucher_id' => $voucher->id, 'rollback_verified' => true];
    }
}
