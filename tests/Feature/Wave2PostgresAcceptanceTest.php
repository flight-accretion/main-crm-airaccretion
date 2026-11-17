<?php

namespace Tests\Feature;

use App\Models\{Lead, OperationCase, User, Vendor};
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class Wave2PostgresAcceptanceTest extends TestCase
{
    public function test_vendor_refund_requires_saved_assessment_through_authenticated_routes(): void
    {
        $ops = User::where('email', 'operations@wave1.invalid')->sole();
        $accounts = User::where('email', 'accounts@wave1.invalid')->sole();
        $sales = User::where('email', 'sales-executive@wave1.invalid')->sole();
        $client = \App\Models\Client::where('email', 'browser@wave1.invalid')->sole();
        $lead = Lead::forceCreate(['id' => (string) Str::uuid(), 'client_id' => $client->id]);
        $ride = \App\Models\LeadRide::create(['id' => (string) Str::uuid(), 'lead_id' => $lead->id,
            'from_date' => now(), 'to_date' => now()->addHour(), 'from_place' => 'Delhi', 'to_place' => 'Mumbai']);
        \App\Models\LeadFollowup::create(['id' => (string) Str::uuid(), 'lead_id' => $lead->id,
            'followed_by' => $ops->id, 'status' => \App\Models\LeadFollowup::STATUS_CANCELLED]);
        $vendor = Vendor::create(['id' => (string) Str::uuid(), 'name' => 'QA refund vendor',
            'contact_number' => '9999990015', 'status' => 1]);
        $payment = \App\Models\LeadVendorPayment::create(['id' => (string) Str::uuid(),
            'lead_id' => $lead->id, 'vendor_id' => $vendor->id, 'total_service_amount' => 70000,
            'total_vendor_service_amount' => 70000, 'payment_status' => 'partial']);
        $paid = \App\Models\VendorPayment::create(['id' => (string) Str::uuid(),
            'lead_vendor_payment_id' => $payment->id, 'payment_method' => 'Bank Transfer',
            'paid_amount' => 30000, 'paid_date' => now(), 'status' => 1]);
        $paidBefore = $paid->fresh()->getAttributes();
        $customerRefunds = \App\Models\LeadRefund::whereIn('lead_followup_id', $lead->leadFollowups()->select('id'));
        $customerBefore = $customerRefunds->get()->toArray();
        $queue = route('admin.operations.vendor-refunds.index');
        $url = route('admin.rides.ride-status.save-vendor-refund', $ride->id);
        $details = route('admin.rides.ride-status.details', $ride->id);
        $rows = \App\Models\VendorRefund::where('lead_vendor_payment_id', $payment->id);
        $assessment = ['stage' => 'cancellation', 'lead_vendor_payment_id' => $payment->id,
            'cancellation_amount' => 10000, 'refund_amount' => 0, 'refund_reason' => 'QA initial cancellation'];
        $receipt = array_replace($assessment, ['stage' => 'refund', 'refund_amount' => 5000,
            'refund_type' => 'Bank Transfer', 'refund_date' => now()->toDateString()]);
        $this->actingAs($sales)->postJson($url, $assessment)->assertForbidden();
        $this->actingAs($ops)->getJson($details)->assertOk();
        $this->postJson($url, $receipt)->assertUnprocessable()->assertJsonValidationErrors('stage');
        $this->assertFalse($rows->exists());
        $this->postJson($url, $assessment)->assertOk()->assertJsonPath('success', true);
        $saved = (clone $rows)->sole();
        $this->assertSame(\App\Models\VendorRefund::STATUS_PENDING, $saved->status);
        $this->get($queue)->assertOk()->assertSee($vendor->name);
        $this->getJson($details)->assertOk()->assertSee('QA initial cancellation');
        $edit = array_replace($assessment, ['cancellation_amount' => 12000, 'refund_reason' => 'QA edited cancellation']);
        $this->actingAs($accounts)->postJson($url, $edit)->assertOk()->assertJsonPath('success', true);
        $this->assertSame($saved->id, (clone $rows)->sole()->id);
        $this->getJson($details)->assertOk()->assertSee('QA edited cancellation');
        $receipt['refund_proof'] = \Illuminate\Http\UploadedFile::fake()->create('qa-receipt.pdf', 2, 'application/pdf');
        $this->actingAs($ops)->post($url, $receipt, ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('vendor_refunds', ['lead_vendor_payment_id' => $payment->id,
            'cancellation_amount' => 12000, 'refund_amount' => 5000]);
        $this->postJson($url, $assessment)->assertUnprocessable()->assertJsonValidationErrors('stage');
        $this->assertSame($paidBefore, $paid->fresh()->getAttributes());
        $this->assertSame($customerBefore, $customerRefunds->get()->toArray());
        $this->assertSame('paid', $payment->fresh()->payment_status);
        $this->get($queue)->assertOk()->assertSee($vendor->name);
        $audits = \App\Models\ActivityAudit::where('lead_id', $lead->id)
            ->where('module', 'vendor_refund')->get();
        $this->assertCount(3, $audits);
        $this->assertEqualsCanonicalizing([$ops->id, $accounts->id, $ops->id], $audits->pluck('actor_id')->all());
    }

    /** @dataProvider extraServiceSalesRoles */
    public function test_sales_cannot_forge_service_revision_after_partial_or_full_approval(string $role): void
    {
        $type = \App\Models\UserType::firstOrCreate(['user_type' => $role], ['id' => (string) Str::uuid(), 'status' => 1]);
        $actor = User::where('email', 'sales-executive@wave1.invalid')->sole()->replicate();
        $actor->forceFill(['id' => (string) Str::uuid(), 'email' => Str::uuid().'@wave1.invalid', 'user_type_id' => $type->id])->saveQuietly();
        $client = \App\Models\Client::where('email', 'browser@wave1.invalid')->sole();
        $service = \App\Models\Service::forceCreate(['id' => (string) Str::uuid(), 'service' => 'QA replacement', 'service_amount' => 35000, 'status' => 1]);
        foreach ([3, 4] as $status) {
            $lead = Lead::forceCreate(['id' => (string) Str::uuid(), 'client_id' => $client->id,
                'representative_user_id' => $actor->id]);
            $followup = \App\Models\LeadFollowup::create(['id' => (string) Str::uuid(),
                'lead_id' => $lead->id, 'followed_by' => $actor->id, 'status' => $status, 'total_amount' => 32000]);
            $payment = \App\Models\PaymentAuditTrail::create(['id' => (string) Str::uuid(),
                'lead_followup_id' => $followup->id, 'payment_status' => 1, 'paid_amount' => $status === 3 ? 32000 : 10000,
                'paid_date' => now(), 'payment_method' => 'Bank Transfer', 'created_by' => $actor->id]);
            $before = $payment->fresh()->getAttributes();
            $this->actingAs($actor)->postJson(route('admin.leads.follow-up.store', $lead), [
                'pricing_action' => 'revise', 'services' => [$service->id], 'total_amount' => 35000,
                'notes' => 'Synthetic pricing revision',
            ])->assertUnprocessable()->assertJsonPath('message', 'Product and Service details are locked after payment approval.');
            $this->putJson(route('admin.leads.update', $lead), ['product_ids' => [(string) Str::uuid()]])
                ->assertUnprocessable()->assertJsonValidationErrors('product_ids');
            $this->putJson(route('admin.leads.update', $lead), ['service_ids' => [$service->id]])
                ->assertUnprocessable()->assertJsonValidationErrors('product_ids');
            $this->assertSame($lead->service_ids, $lead->fresh()->service_ids);
            $this->assertSame(1, $lead->leadFollowups()->count());
            $this->assertSame($before, $payment->fresh()->getAttributes());
        }
    }

    /** @dataProvider extraServiceSalesRoles */
    public function test_independent_extra_services_reconcile_approved_balance(string $role, int $status): void
    {
        $type = \App\Models\UserType::firstOrCreate(['user_type' => $role], ['id' => (string) Str::uuid(), 'status' => 1]);
        $actor = User::where('email', 'sales-executive@wave1.invalid')->sole()->replicate();
        $actor->forceFill(['id' => (string) Str::uuid(), 'email' => Str::uuid().'@wave1.invalid',
            'user_type_id' => $type->id])->saveQuietly();
        $client = \App\Models\Client::where('email', 'browser@wave1.invalid')->sole();
        $service = \App\Models\Service::create(['id' => (string) Str::uuid(), 'service' => 'QA charter', 'service_amount' => 30000, 'status' => 1]);
        $old = \App\Models\ExtraService::create(['id' => (string) Str::uuid(), 'extra_service' => 'QA old', 'extra_service_amount' => 2000, 'status' => 1]);
        $new = \App\Models\ExtraService::create(['id' => (string) Str::uuid(), 'extra_service' => 'QA new', 'extra_service_amount' => 5000, 'status' => 1]);
        $service->extraServices()->attach([$old->id, $new->id]);
        $lead = Lead::forceCreate(['id' => (string) Str::uuid(), 'client_id' => $client->id,
            'representative_user_id' => $actor->id, 'service_ids' => [$service->id]]);
        $followup = \App\Models\LeadFollowup::create(['id' => (string) Str::uuid(), 'lead_id' => $lead->id,
            'followed_by' => $actor->id, 'status' => $status, 'service_amount' => 32000, 'total_amount' => 32000,
            'service_ids' => [$service->id], 'extra_service_ids' => [$old->id], 'service_details' => [
                ['type' => 'service', 'id' => $service->id, 'original_amount' => 30000],
                ['type' => 'extra_service', 'id' => $old->id, 'original_amount' => 2000],
            ]]);
        $approvedReceipts = $status === 3 ? 32000 : 10000;
        $payment = \App\Models\PaymentAuditTrail::create(['id' => (string) Str::uuid(),
            'lead_followup_id' => $followup->id, 'payment_status' => 1, 'paid_amount' => $approvedReceipts,
            'paid_date' => now(), 'payment_method' => 'Bank Transfer', 'created_by' => $actor->id]);
        $voucher = \App\Models\Voucher::create(['id' => (string) Str::uuid(), 'lead_id' => $lead->id,
            'created_by' => $actor->id, 'status' => 1, 'customer_sent_at' => now(), 'naration' => 'Issued QA historical voucher']);
        $invoice = \App\Models\Invoice::create(['id' => (string) Str::uuid(), 'voucher_id' => $voucher->id,
            'invoice_id' => 'QA-'.Str::uuid(), 'company_name' => 'Issued QA invoice', 'status' => 2, 'finalized_date' => now()]);
        $vendor = Vendor::create(['id' => (string) Str::uuid(), 'name' => 'QA quote vendor',
            'contact_number' => '9999990066', 'status' => 1]);
        $payable = \App\Models\LeadVendorPayment::create(['id' => (string) Str::uuid(), 'lead_id' => $lead->id,
            'vendor_id' => $vendor->id, 'voucher_id' => $voucher->id, 'total_service_amount' => 12000,
            'total_vendor_service_amount' => 12000, 'payment_status' => 'partial']);
        $vendorPayment = \App\Models\VendorPayment::create(['id' => (string) Str::uuid(),
            'lead_vendor_payment_id' => $payable->id, 'paid_amount' => 6000, 'paid_date' => now(),
            'payment_method' => 'Bank Transfer', 'status' => 1]);
        $customerRefund = \App\Models\LeadRefund::create(['id' => (string) Str::uuid(),
            'lead_followup_id' => $followup->id, 'original_amount' => 32000, 'refund_amount' => 500,
            'refund_reason' => 'Historical customer refund', 'refund_date' => now(), 'status' => 1]);
        $vendorRefund = \App\Models\VendorRefund::create(['lead_id' => $lead->id,
            'lead_vendor_payment_id' => $payable->id, 'vendor_id' => $vendor->id,
            'cancellation_amount' => 12000, 'refund_amount' => 250,
            'refund_reason' => 'Historical vendor refund', 'refund_date' => now(), 'status' => 1]);
        $protected = [$voucher, $invoice, $payable, $vendorPayment, $customerRefund, $vendorRefund];
        $financialBefore = array_map(fn ($model) => $model->fresh()->getAttributes(), $protected);
        $paymentBefore = $payment->fresh()->getAttributes();
        $followupBefore = $followup->fresh()->getAttributes();
        $url = route('admin.leads.extra-services.update', $lead);
        $payload = ['selection_submitted' => 1, 'quote_version' => hash('sha256', ''), 'extra_service_ids' => [$new->id]];
        $this->actingAs($actor);
        $this->putJson($url, $payload + ['preview' => true])->assertOk()
            ->assertJsonPath('extra_services_total', 5000)
            ->assertJsonPath('total_amount', 35000)->assertJsonPath('outstanding_balance', 35000 - $approvedReceipts);
        $this->assertNull($lead->fresh()->extra_service_quote);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->putJson($url, $payload)->assertOk()->assertJsonPath('total_amount', 35000)
                ->assertJsonPath('outstanding_balance', 35000 - $approvedReceipts)->assertJsonPath('extra_service_ids', [$new->id]);
        }
        $workspace = $this->get(route('admin.leads.follow-up.create', $lead))->assertOk();
        $workspace->assertSee('id="workspace-extra-editor" class="lw-edit-panel"', false)->assertDontSee('workspace-extra-dialog')->assertDontSee('showModal()')->assertSee('QA new')->assertSee('35,000.00')->assertDontSee('extra-services-help');
        $unrelated = $actor->replicate();
        $unrelated->forceFill(['id' => (string) Str::uuid(), 'email' => Str::uuid().'@wave1.invalid'])->saveQuietly();
        $this->actingAs($unrelated)->putJson($url, $payload)->assertForbidden();
        $this->actingAs($actor);
        $persisted = $lead->fresh()->extra_service_quote;
        $this->putJson($url, array_replace($payload, ['extra_service_ids' => []]))->assertStatus(409);
        $this->assertSame($persisted, $lead->fresh()->extra_service_quote);
        $this->assertSame([$new->id], json_decode($persisted, true)['extra_service_ids']);
        DB::statement("ALTER TABLE leads ADD CONSTRAINT qa_extra_quote_failure CHECK (id <> '{$lead->id}' OR extra_service_quote::text NOT LIKE '%{$old->id}%')");
        try {
            $this->putJson($url, array_replace($payload, ['extra_service_ids' => [$old->id], 'quote_version' => hash('sha256', $persisted)]))
                ->assertStatus(500);
            $this->assertSame($persisted, $lead->fresh()->extra_service_quote);
        } finally {
            DB::statement('ALTER TABLE leads DROP CONSTRAINT qa_extra_quote_failure');
        }
        $this->putJson($url, array_replace($payload, ['extra_service_ids' => [(string) Str::uuid()]]))
            ->assertUnprocessable()->assertJsonValidationErrors('extra_service_ids.0');
        $this->assertSame($persisted, $lead->fresh()->extra_service_quote);
        $this->putJson($url, ['selection_submitted' => 1, 'quote_version' => hash('sha256', $lead->fresh()->extra_service_quote ?? ''), 'extra_service_ids' => []])
            ->assertOk()->assertJsonPath('total_amount', 30000)->assertJsonPath('outstanding_balance', max(0, 30000 - $approvedReceipts));
        $this->assertSame($paymentBefore, $payment->fresh()->getAttributes());
        foreach ($protected as $index => $model) {
            $this->assertSame($financialBefore[$index], $model->fresh()->getAttributes(), get_class($model).' '.$model->getKey());
        }
        fwrite(STDOUT, json_encode(['snapshot_parity' => array_map(fn ($model) => [
            'table' => $model->getTable(), 'id' => $model->getKey(),
            'sha256' => hash('sha256', json_encode($model->fresh()->getAttributes())),
        ], $protected)]).PHP_EOL);
        $this->assertSame($followupBefore, $followup->fresh()->getAttributes());
        $this->assertSame(1, $lead->leadFollowups()->count());
        $this->assertSame([$service->id], $lead->fresh()->service_ids_array);
        $this->postJson(route('admin.leads.follow-up.store', $lead), [
            'pricing_action' => 'preserve', 'notes' => 'QA note after independent quote',
        ])->assertOk();
        $this->assertSame(2, $lead->leadFollowups()->count());
        $this->putJson($url, ['selection_submitted' => 1, 'quote_version' => hash('sha256', $lead->fresh()->extra_service_quote ?? ''), 'extra_service_ids' => []])
            ->assertOk()->assertJsonPath('total_amount', 30000)->assertJsonPath('outstanding_balance', max(0, 30000 - $approvedReceipts));
        $persistedQuote = json_decode($lead->fresh()->extra_service_quote, true);
        $this->assertSame([], $persistedQuote['extra_service_ids']);
        $this->assertEquals(30000, $persistedQuote['total_amount']);
        $this->get(route('admin.leads.follow-up.create', $lead))->assertOk()->assertSee('30,000.00')
            ->assertDontSee('extra-services-help')->assertDontSee('workspace-extra-dialog');
        foreach ($protected as $index => $model) {
            $this->assertSame($financialBefore[$index], $model->fresh()->getAttributes());
        }
        $this->assertSame($paymentBefore, $payment->fresh()->getAttributes());
    }

    public function extraServiceSalesRoles(): array
    {
        return [
            'executive partial' => ['Sales Executive', 4],
            'manager full' => ['Sales Manager', 3],
            'senior manager partial' => ['Senior Sales Manager', 4],
        ];
    }

    public function test_simultaneous_extra_service_saves_reject_one_conflicting_version(): void
    {
        // Separate connections need committed fixtures. Only these new IDs are removed below.
        $actor = User::where('email', 'sales-executive@wave1.invalid')->sole();
        $client = \App\Models\Client::where('email', 'browser@wave1.invalid')->sole();
        $service = \App\Models\Service::create(['id' => (string) Str::uuid(),
            'service' => 'QA concurrent charter', 'service_amount' => 30000, 'status' => 1]);
        $extras = collect([2000, 5000])->map(fn ($amount) => \App\Models\ExtraService::create([
            'id' => (string) Str::uuid(), 'extra_service' => 'QA concurrent '.$amount,
            'extra_service_amount' => $amount, 'status' => 1]));
        $service->extraServices()->attach($extras->pluck('id')->all());
        $lead = Lead::forceCreate(['id' => (string) Str::uuid(), 'client_id' => $client->id,
            'representative_user_id' => $actor->id, 'service_ids' => [$service->id]]);
        $followup = \App\Models\LeadFollowup::create(['id' => (string) Str::uuid(),
            'lead_id' => $lead->id, 'followed_by' => $actor->id, 'status' => 1,
            'total_amount' => 30000, 'service_amount' => 30000, 'service_ids' => [$service->id],
            'service_details' => [['type' => 'service', 'id' => $service->id, 'original_amount' => 30000]]]);
        DB::commit();
        $this->transactionStarted = false;
        $processes = [];
        $pipes = [];
        try {
            DB::beginTransaction();
            Lead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
            foreach ($extras as $index => $extra) {
                $processes[$index] = proc_open(
                    [PHP_BINARY, __DIR__.'/../Support/wave2_extra_services_worker.php', $lead->id, $actor->id, $extra->id],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$index]
                );
                $this->assertIsResource($processes[$index]);
                fclose($pipes[$index][0]);
            }
            $waiting = 0;
            for ($attempt = 0; $attempt < 120 && $waiting < 2; $attempt++) {
                DB::select('SELECT pg_stat_clear_snapshot()');
                $waiting = (int) DB::selectOne("SELECT count(*) AS n FROM pg_stat_activity
                    WHERE application_name = 'wave2-extra-concurrency'
                    AND wait_event_type = 'Lock' AND query LIKE '%for update%'")->n;
                if ($waiting < 2) {
                    usleep(100000);
                }
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
            $this->assertSame(2, $waiting, 'Both independent PostgreSQL sessions must reach the row lock concurrently.');
            $this->assertCount(2, array_unique(array_column($results, 'pid')));
            $this->assertEqualsCanonicalizing([200, 409], array_column($results, 'status'));
            $winner = collect($results)->firstWhere('status', 200);
            $persisted = json_decode($lead->fresh()->extra_service_quote, true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame([$winner['extra_id']], $persisted['extra_service_ids']);
            $this->assertEquals($winner['body']['total_amount'], $persisted['total_amount']);
            $this->assertSame(1, $lead->leadFollowups()->count());
            fwrite(STDOUT, json_encode(['concurrency' => array_map(fn ($row) => ['pid' => $row['pid'], 'status' => $row['status'], 'extra_id' => $row['extra_id']], $results), 'simultaneous_lock_waiters' => $waiting]).PHP_EOL);
        } finally {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                proc_terminate($process);
                proc_close($process);
            }
            DB::transaction(function () use ($lead, $followup, $service, $extras) {
                \App\Models\ActivityAudit::where('lead_id', $lead->id)->delete();
                DB::table('lead_followups')->where('id', $followup->id)->delete();
                DB::table('leads')->where('id', $lead->id)->delete();
                $service->extraServices()->detach();
                DB::table('extra_services')->whereIn('id', $extras->pluck('id'))->delete();
                DB::table('services')->where('id', $service->id)->delete();
            });
        }
    }

    /** @dataProvider remainingEditPermissions */
    public function test_existing_product_service_edit_permissions(string $role, bool $approved, int $expectedStatus, int $paymentStatus = 4): void
    {
        $type = \App\Models\UserType::firstOrCreate(['user_type' => $role],
            ['id' => (string) Str::uuid(), 'status' => 1]);
        $actor = User::where('email', 'sales-executive@wave1.invalid')->sole()->replicate();
        $actor->forceFill(['id' => (string) Str::uuid(), 'email' => Str::uuid().'@wave1.invalid',
            'user_type_id' => $type->id])->saveQuietly();
        $client = \App\Models\Client::forceCreate(['id' => (string) Str::uuid(), 'name' => 'QA editing client',
            'contact_number' => '+91-9999990068']);
        $oldProduct = \App\Models\Product::create(['id' => (string) Str::uuid(), 'product' => 'QA old product', 'status' => 1]);
        $newProduct = \App\Models\Product::create(['id' => (string) Str::uuid(), 'product' => 'QA replacement product', 'status' => 1]);
        $oldService = \App\Models\Service::create(['id' => (string) Str::uuid(), 'service' => 'QA old service',
            'service_amount' => 30000, 'status' => 1, 'product_ids' => [$oldProduct->id]]);
        $newService = \App\Models\Service::create(['id' => (string) Str::uuid(), 'service' => 'QA replacement service',
            'service_amount' => 31000, 'status' => 1, 'product_ids' => [$newProduct->id]]);
        $lead = Lead::forceCreate(['id' => (string) Str::uuid(), 'client_id' => $client->id,
            'representative_user_id' => $actor->id, 'product_ids' => [$oldProduct->id],
            'service_ids' => [$oldService->id], 'number_of_passengers' => 1]);
        $ride = \App\Models\LeadRide::create(['id' => (string) Str::uuid(), 'lead_id' => $lead->id,
            'from_date' => '2041-03-10 10:00:00', 'to_date' => '2041-03-10 12:00:00',
            'from_place' => 'Delhi', 'to_place' => 'Mumbai']);
        $followup = \App\Models\LeadFollowup::create(['id' => (string) Str::uuid(),
            'lead_id' => $lead->id, 'followed_by' => $actor->id, 'status' => $paymentStatus, 'total_amount' => 30000]);
        $audit = \App\Models\PaymentAuditTrail::create(['id' => (string) Str::uuid(),
            'lead_followup_id' => $followup->id, 'payment_status' => $approved ? 1 : 2,
            'paid_amount' => $paymentStatus === 3 ? 30000 : 10000, 'paid_date' => now(), 'payment_method' => 'Bank Transfer', 'created_by' => $actor->id]);
        $auditBefore = $audit->fresh()->getAttributes();
        $followupBefore = $followup->fresh()->getAttributes();
        $this->assertSame($approved, app(\App\Services\LeadStatusTransitionService::class)->hasApprovedPayment($lead));
        $clientBefore = $client->fresh()->getAttributes();
        $rideBefore = $ride->fresh()->getAttributes();
        $payload = ['workspace_section' => 'services-details-editor', 'name' => $client->name, 'contact_number' => '9999990068', 'contact_country_code' => '+91',
            'product_ids' => [$newProduct->id], 'service_ids' => [$newService->id],
            'representative_user_id' => $actor->id, 'number_of_passengers' => 1,
            'trips' => [['id' => $ride->id, 'from_date' => '2041-03-10 10:00:00',
                'to_date' => '2041-03-10 12:00:00', 'from_place' => 'Delhi', 'to_place' => 'Mumbai']]];
        if ($role === 'Owner') {
            foreach (['client-details-editor', 'travel-details-editor', null] as $section) {
                $this->actingAs($actor)->putJson(route('admin.leads.update', $lead),
                    array_replace($payload, ['workspace_section' => $section]))->assertForbidden();
            }
            // The same form includes other fields. Forging these must not grant other writes.
            $payload['name'] = 'Forbidden client change';
            $payload['requirement_description'] = 'Forbidden note change';
            $payload['representative_user_id'] = null;
            $payload['number_of_passengers'] = 99;
            $payload['trips'] = [];
        }
        $response = $this->actingAs($actor)->put(route('admin.leads.update', $lead), $payload)->assertStatus($expectedStatus);
        if ($expectedStatus === 302) {
            $response->assertSessionHasNoErrors();
            $this->assertNull(session('error'), (string) session('error'));
            $this->assertSame([$newProduct->id], $lead->fresh()->product_ids_array);
            $this->assertSame([$newService->id], $lead->fresh()->service_ids_array);
        } else {
            $this->assertSame([$oldProduct->id], $lead->fresh()->product_ids_array);
            $this->assertSame([$oldService->id], $lead->fresh()->service_ids_array);
            $this->putJson(route('admin.leads.extra-services.update', $lead),
                ['selection_submitted' => 1, 'quote_version' => hash('sha256', ''), 'extra_service_ids' => []])
                ->assertForbidden();
        }
        if ($role === 'Owner') {
            $this->assertSame($clientBefore, $client->fresh()->getAttributes());
            $this->assertSame($rideBefore, $ride->fresh()->getAttributes());
            $this->assertSame($actor->id, $lead->fresh()->representative_user_id);
            $this->assertEquals(1, $lead->fresh()->number_of_passengers);
            $this->assertNull($lead->fresh()->description);
            $this->putJson(route('admin.leads.extra-services.update', $lead),
                ['selection_submitted' => 1, 'quote_version' => hash('sha256', ''), 'extra_service_ids' => []])
                ->assertForbidden();
        }
        $this->assertSame($auditBefore, $audit->fresh()->getAttributes());
        $this->assertSame($followupBefore, $followup->fresh()->getAttributes());
    }

    public function remainingEditPermissions(): array
    {
        return [
            'executive rejected only' => ['Sales Executive', false, 302],
            'manager rejected only' => ['Sales Manager', false, 302],
            'senior manager rejected only' => ['Senior Sales Manager', false, 302],
            'operations approved' => ['Operations Manager', true, 302],
            'super admin approved' => ['Super Admin', true, 302],
            'owner approved partial product editor' => ['Owner', true, 302, 4],
            'owner approved full product editor' => ['Owner', true, 302, 3],
            'accounts cannot edit' => ['Accounts Manager', true, 403],
            'hr cannot edit' => ['Hr', true, 403],
        ];
    }

    public function test_hr_work_done_uses_completion_actors_and_distinct_cases(): void
    {
        $hr = User::where('email', 'hr@wave1.invalid')->sole();
        $ops = User::where('email', 'operations@wave1.invalid')->sole();
        $other = $ops->replicate();
        $other->forceFill(['id' => (string) Str::uuid(), 'email' => Str::uuid().'@wave1.invalid', 'name' => 'QA other operator'])->saveQuietly();
        $lead = Lead::whereHas('client', fn ($query) => $query->where('email', 'browser@wave1.invalid'))->sole();
        foreach ([OperationCase::TYPE_REVIEW => 'review_completed', OperationCase::TYPE_VENDOR_FOLLOWUP => 'vendor_followup_completed'] as $type => $action) {
            $case = OperationCase::create(['id' => (string) Str::uuid(), 'lead_id' => $lead->id, 'type' => $type,
                'status' => OperationCase::STATUS_COMPLETED, 'assigned_to' => $other->id,
                'completed_by' => $ops->id, 'completed_at' => '2041-03-10 12:00:00', 'opened_at' => '2041-03-01']);
            foreach ([['viewed', '2041-03-10 09:00:00'], [$action, '2041-03-10 12:00:00'],
                [$action, '2041-03-10 12:01:00'], [$action, '2041-03-11 00:00:00']] as [$event, $at]) {
                $activity = \App\Models\OperationCaseActivity::create(['operation_case_id' => $case->id,
                    'lead_id' => $lead->id, 'user_id' => $ops->id, 'action' => $event]);
                $activity->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
            }
        }
        foreach ([['viewed', '2041-03-10 15:00:00'], ['review_cancelled', '2041-03-10 15:00:00'],
            ['review_completed', '2041-03-09 23:59:59'], ['review_completed', '2041-03-11 00:00:00']] as [$action, $at]) {
            $case = OperationCase::create(['id' => (string) Str::uuid(), 'lead_id' => $lead->id,
                'type' => OperationCase::TYPE_REVIEW, 'status' => OperationCase::STATUS_PENDING,
                'assigned_to' => $ops->id, 'opened_at' => '2041-03-01']);
            $activity = \App\Models\OperationCaseActivity::create(['operation_case_id' => $case->id,
                'lead_id' => $lead->id, 'user_id' => $ops->id, 'action' => $action]);
            $activity->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
        }
        $url = '/admin/hr?preset=custom&from_date=2041-03-10&to_date=2041-03-10';
        $response = $this->actingAs($hr)->get($url)->assertOk();
        $rows = collect($response->viewData('departmentRows')['operations']->items())->keyBy(fn ($row) => $row['user']->id);
        $this->assertSame(1, $rows[$ops->id]['work']['review']);
        $this->assertSame(1, $rows[$ops->id]['work']['vendor']);
        $this->assertSame(0, $rows[$other->id]['work']['review']);
        $this->assertSame(0, $rows[$other->id]['work']['vendor']);
        $this->actingAs(User::where('email', 'sales-executive@wave1.invalid')->sole())->get($url)->assertForbidden();
    }

    public function test_add_lead_requires_deliberate_duplicate_client_selection_without_profile_updates(): void
    {
        $actor = User::where('email', 'sales-executive@wave1.invalid')->sole();
        $outsider = User::where('email', 'sales-manager@wave1.invalid')->sole();
        $product = \App\Models\Product::create(['id' => (string) Str::uuid(), 'product' => 'QA client matching', 'status' => 1]);
        $clients = [];
        foreach (['+91-9998888811', '+91 (999) 888 8811', '+91 999-888-8811', '+91-9998888822'] as $index => $phone) {
            $client = \App\Models\Client::forceCreate(['id' => (string) Str::uuid(),
                'name' => 'QA matching client '.$index, 'contact_number' => $phone, 'status' => 1]);
            $oldLead = Lead::forceCreate(['id' => (string) Str::uuid(), 'client_id' => $client->id,
                'representative_user_id' => $index === 3 ? $outsider->id : $actor->id]);
            \App\Models\LeadFollowup::create(['id' => (string) Str::uuid(),
                'lead_id' => $oldLead->id, 'followed_by' => $actor->id, 'status' => 2]);
            $clients[] = $client;
        }
        $before = array_map(fn ($client) => $client->fresh()->getAttributes(), $clients);
        $count = \App\Models\Client::count();
        $payload = ['client_id' => 'new', 'name' => 'Typed basic information',
            'contact_country_code' => '+91', 'contact_number' => '9998888811',
            'product_ids' => [$product->id], 'representative_user_id' => $actor->id,
            'number_of_passengers' => 1, 'status' => 0,
            'trips' => [['from_date' => '2041-03-10 10:00', 'to_date' => '2041-03-10 12:00',
                'from_place' => 'Delhi', 'to_place' => 'Mumbai']]];
        $url = route('admin.clients.store');
        $this->actingAs($actor)->get(route('admin.clients.create'))->assertOk()
            ->assertSee('phone-client-matches')->assertSee($clients[2]->name)->assertDontSee($clients[3]->name);
        $this->getJson(route('admin.clients.search', ['q' => 'QA matching client']))->assertOk()
            ->assertJsonFragment(['id' => $clients[2]->id])->assertJsonMissing(['id' => $clients[3]->id]);
        $this->post($url, $payload)->assertRedirect()->assertSessionHasErrors('contact_number');
        $this->assertSame($count, \App\Models\Client::count());
        $this->post($url, array_replace($payload, ['client_id' => $clients[2]->id]))
            ->assertRedirect(route('admin.clients.create'))->assertSessionHasNoErrors();
        $this->assertNull(session('error'), (string) session('error'));
        $this->assertSame(2, Lead::where('client_id', $clients[2]->id)->count());
        $this->assertSame($count, \App\Models\Client::count());
        foreach ($clients as $index => $client) {
            $this->assertSame($before[$index], $client->fresh()->getAttributes());
        }
        $this->post($url, array_replace($payload, ['client_id' => $clients[3]->id, 'contact_number' => '9998888822']))->assertForbidden();
        $this->post($url, array_replace($payload, ['contact_number' => 'invalid']))->assertSessionHasErrors('contact_number');
        $this->post($url, array_replace($payload, ['client_id' => (string) Str::uuid()]))->assertSessionHasErrors('client_id');
        $this->actingAs(User::where('email', 'hr@wave1.invalid')->sole())->post($url, $payload)->assertForbidden();
    }

    public function test_refund_status_filters_return_exact_rows_without_financial_writes(): void
    {
        $ops = User::where('email', 'operations@wave1.invalid')->sole();
        $accounts = User::where('email', 'accounts@wave1.invalid')->sole();
        $name = 'QA Refund Filter '.Str::random(12);
        $expected = [];
        foreach ([1, 2] as $status) {
            $client = \App\Models\Client::forceCreate(['id' => (string) Str::uuid(), 'name' => $name.' '.$status,
                'contact_number' => '+91-9999990070', 'status' => 1]);
            $lead = Lead::forceCreate(['id' => (string) Str::uuid(), 'client_id' => $client->id]);
            $followup = \App\Models\LeadFollowup::create(['id' => (string) Str::uuid(),
                'lead_id' => $lead->id, 'followed_by' => $ops->id, 'status' => 2, 'total_amount' => 17000]);
            $refund = \App\Models\LeadRefund::create(['id' => (string) Str::uuid(),
                'lead_followup_id' => $followup->id, 'original_amount' => 17000,
                'refund_amount' => 8500, 'refund_reason' => 'QA filter', 'refund_date' => now(), 'status' => $status]);
            $expected[$status] = $refund;
        }
        $before = array_map(fn ($refund) => $refund->fresh()->getAttributes(), $expected);
        foreach ([$ops, $accounts] as $actor) {
            foreach (['pending' => 1, 'complete' => 2] as $filter => $status) {
                for ($repeat = 0; $repeat < 2; $repeat++) {
                    $response = $this->actingAs($actor)->get(route('admin.refunds.index',
                        ['name' => $name, 'refund_status' => $filter]))->assertOk();
                    $rows = collect($response->viewData('refundsData'));
                    $this->assertSame([$expected[$status]->id], $rows->pluck('refund_id')->all());
                    $this->assertSame($filter, $response->viewData('selectedStatus'));
                }
            }
        }
        foreach ($expected as $status => $refund) {
            $this->assertSame($before[$status], $refund->fresh()->getAttributes());
        }
        $this->actingAs(User::where('email', 'hr@wave1.invalid')->sole())
            ->get(route('admin.refunds.index'))->assertForbidden();
    }

    public function test_dnp_age_boundary_fixtures_remain_read_only_on_queue_requests(): void
    {
        $sales = User::where('email', 'sales-executive@wave1.invalid')->sole();
        $client = \App\Models\Client::forceCreate(['id' => (string) Str::uuid(),
            'name' => 'QA DNP Boundary '.Str::random(10), 'contact_number' => '+91-9999990075', 'status' => 1]);
        $service = \App\Models\Service::firstOrCreate(['service' => 'Call Not Connected'],
            ['id' => (string) Str::uuid(), 'service_amount' => 0, 'status' => 1]);
        $expected = [];
        foreach ([[29, 1], [30, 1], [31, 1], [31, 4], [31, 5]] as [$age, $status]) {
            $lead = Lead::forceCreate(['id' => (string) Str::uuid(), 'client_id' => $client->id,
                'representative_user_id' => $sales->id, 'service_ids' => [$service->id]]);
            $lead->forceFill(['created_at' => now()->subDays($age)])->saveQuietly();
            $followup = \App\Models\LeadFollowup::create(['id' => (string) Str::uuid(), 'lead_id' => $lead->id,
                'followed_by' => $sales->id, 'status' => $status, 'customer_not_picked_up' => true]);
            if ($status === 4) {
                \App\Models\PaymentAuditTrail::create(['id' => (string) Str::uuid(),
                    'lead_followup_id' => $followup->id, 'payment_status' => 1, 'paid_amount' => 1000,
                    'paid_date' => now(), 'payment_method' => 'Bank Transfer', 'created_by' => $sales->id]);
            }
            $expected[$lead->id] = [$age, $followup->fresh()->getAttributes()];
        }
        $response = $this->actingAs($sales)->get(route('admin.leads.dnp', ['name' => $client->name]))->assertOk();
        $visibleExpected = array_keys(array_filter($expected, fn ($entry) => $entry[1]['status'] !== 5));
        $this->assertEqualsCanonicalizing($visibleExpected, $response->viewData('dnpLeads')->pluck('id')->all());
        foreach ($expected as $id => [$age, $attributes]) {
            if ($attributes['status'] === 5) {
                $this->assertNotContains($id, $response->viewData('dnpLeads')->pluck('id')->all());
            } else {
                $this->assertEquals($age, $response->viewData('queueSummary')['ages'][$id]);
            }
            $this->assertSame($attributes, \App\Models\LeadFollowup::findOrFail($attributes['id'])->getAttributes());
        }
        $this->actingAs(User::where('email', 'accounts@wave1.invalid')->sole())
            ->get(route('admin.leads.dnp'))->assertForbidden();
    }

    public function test_dnp_thirty_complete_days_command_dry_run_exclusions_and_dashboard_export(): void
    {
        \Carbon\Carbon::setTestNow('2042-03-10 12:00:00');
        try {
            $actor = User::where('email', 'sales-executive@wave1.invalid')->sole();
            $admin = User::where('email', 'super-admin@wave1.invalid')->sole();
            $client = \App\Models\Client::forceCreate(['id' => (string) Str::uuid(),
                'name' => 'DNP acceptance '.Str::random(10), 'contact_number' => '+91-9999990079', 'status' => 1]);
            $service = \App\Models\Service::firstOrCreate(['service' => 'Call Not Connected'],
                ['id' => (string) Str::uuid(), 'service_amount' => 0, 'status' => 1]);
            $fixtures = [];
            $before = [];
            foreach (['29 days', '30 days', '31 days', 'not DNP', 'partial paid', 'full paid', 'completed', 'cancelled', 'unassigned', 'reactivated', 'legacy'] as $name) {
                $lead = Lead::forceCreate(['id' => (string) Str::uuid(), 'client_id' => $client->id,
                    'representative_user_id' => $name === 'unassigned' ? null : $actor->id,
                    'service_ids' => $name === 'not DNP' ? [] : [$service->id]]);
                $mark = now()->subDays($name === '29 days' ? 29 : ($name === '30 days' ? 30 : 31));
                $status = ['partial paid' => 4, 'full paid' => 3, 'completed' => 5, 'cancelled' => 2][$name] ?? 1;
                $followup = \App\Models\LeadFollowup::create(['id' => (string) Str::uuid(), 'lead_id' => $lead->id,
                    'followed_by' => $actor->id, 'status' => $status,
                    'customer_not_picked_up' => !in_array($name, ['legacy', 'not DNP'], true), 'total_amount' => 32000]);
                $followup->forceFill(['created_at' => $mark, 'updated_at' => $mark])->saveQuietly();
                $before[$followup->id] = $followup->fresh()->getAttributes();
                if (in_array($name, ['partial paid', 'full paid'], true)) {
                    $audit = \App\Models\PaymentAuditTrail::create(['id' => (string) Str::uuid(),
                        'lead_followup_id' => $followup->id, 'payment_status' => 1,
                        'paid_amount' => $name === 'full paid' ? 32000 : 10000, 'paid_date' => $mark,
                        'payment_method' => 'Bank Transfer', 'created_by' => $actor->id]);
                    $payments[$audit->id] = $audit->fresh()->getAttributes();
                }
                if ($name === 'reactivated') {
                    $contact = \App\Models\LeadFollowup::create(['id' => (string) Str::uuid(), 'lead_id' => $lead->id,
                        'followed_by' => $actor->id, 'status' => 1, 'customer_not_picked_up' => false]);
                    $before[$contact->id] = $contact->fresh()->getAttributes();
                }
                $fixtures[$name] = $lead;
            }
            $lead = $fixtures['31 days'];
            $followup = $lead->leadFollowups()->sole();
        $voucher = \App\Models\Voucher::create(['id' => (string) Str::uuid(), 'lead_id' => $lead->id,
            'created_by' => $actor->id, 'status' => 1, 'customer_sent_at' => now(), 'naration' => 'Issued QA historical voucher']);
        $invoice = \App\Models\Invoice::create(['id' => (string) Str::uuid(), 'voucher_id' => $voucher->id,
            'invoice_id' => 'QA-'.Str::uuid(), 'company_name' => 'Issued QA invoice', 'status' => 2, 'finalized_date' => now()]);
        $vendor = Vendor::create(['id' => (string) Str::uuid(), 'name' => 'QA quote vendor',
            'contact_number' => '9999990066', 'status' => 1]);
        $payable = \App\Models\LeadVendorPayment::create(['id' => (string) Str::uuid(), 'lead_id' => $lead->id,
            'vendor_id' => $vendor->id, 'voucher_id' => $voucher->id, 'total_service_amount' => 12000,
            'total_vendor_service_amount' => 12000, 'payment_status' => 'partial']);
        $vendorPayment = \App\Models\VendorPayment::create(['id' => (string) Str::uuid(),
            'lead_vendor_payment_id' => $payable->id, 'paid_amount' => 6000, 'paid_date' => now(),
            'payment_method' => 'Bank Transfer', 'status' => 1]);
        $customerRefund = \App\Models\LeadRefund::create(['id' => (string) Str::uuid(),
            'lead_followup_id' => $followup->id, 'original_amount' => 32000, 'refund_amount' => 500,
            'refund_reason' => 'Historical customer refund', 'refund_date' => now(), 'status' => 1]);
        $vendorRefund = \App\Models\VendorRefund::create(['lead_id' => $lead->id,
            'lead_vendor_payment_id' => $payable->id, 'vendor_id' => $vendor->id,
            'cancellation_amount' => 12000, 'refund_amount' => 250,
            'refund_reason' => 'Historical vendor refund', 'refund_date' => now(), 'status' => 1]);
        $protected = [$voucher, $invoice, $payable, $vendorPayment, $customerRefund, $vendorRefund];
        $financialBefore = array_map(fn ($model) => $model->fresh()->getAttributes(), $protected);
            $expected = collect(['30 days', '31 days', 'unassigned'])->map(fn ($name) => $fixtures[$name]->id)->all();
            $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('lead:cancel-aged-dnp', ['--dry-run' => true]));
            $dryOutput = \Illuminate\Support\Facades\Artisan::output();
            foreach ($expected as $id) {
                $this->assertStringContainsString($id, $dryOutput);
            }
            $this->assertStringContainsString('Candidates: 3; cancelled: 0', $dryOutput);
            $this->assertSame(0, \App\Models\LeadFollowup::where('source', \App\Services\DnpCancellationService::SOURCE)->count());
            $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('lead:cancel-aged-dnp'));
            $cancelled = \App\Models\LeadFollowup::where('source', \App\Services\DnpCancellationService::SOURCE)->get();
            $this->assertEqualsCanonicalizing($expected, $cancelled->pluck('lead_id')->all());
            $this->assertTrue($cancelled->every(fn ($row) => $row->followed_by === null
                && $row->followup_note === \App\Services\DnpCancellationService::REASON));
            $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('lead:cancel-aged-dnp'));
            $this->assertSame(3, \App\Models\LeadFollowup::where('source', \App\Services\DnpCancellationService::SOURCE)->count());
            $this->assertSame(3, \App\Models\ActivityAudit::where('action', 'dnp_auto_cancelled')->count());
            fwrite(STDOUT, json_encode(['dnp_dry_run' => $dryOutput, 'cancelled_ids' => $cancelled->pluck('lead_id')->all()]).PHP_EOL);
            foreach ($before as $id => $attributes) {
                $this->assertSame($attributes, \App\Models\LeadFollowup::findOrFail($id)->getAttributes());
            }
            foreach ($payments as $id => $attributes) {
                $this->assertSame($attributes, \App\Models\PaymentAuditTrail::findOrFail($id)->getAttributes());
            }
            $note = \App\Models\LeadFollowup::create(['id' => (string) Str::uuid(),
                'lead_id' => $fixtures['31 days']->id, 'followed_by' => $actor->id, 'status' => 2,
                'followup_note' => 'Ordinary note after automatic cancellation']);
            $note->forceFill(['created_at' => now()->addSecond(), 'updated_at' => now()->addSecond()])->saveQuietly();
            foreach ($protected as $index => $model) {
                $this->assertSame($financialBefore[$index], $model->fresh()->getAttributes());
            }
            $this->assertSame(32000.0, (float) \App\Models\LeadFollowup::latestPricingSnapshot($fixtures['31 days']->id)->total_amount);
            $this->actingAs($admin);
            $page = $this->get(route('admin.leads.dnp', ['name' => $client->name]))->assertOk()->assertSee('Cancelled');
            $visible = $page->viewData('dnpLeads')->pluck('id')->all();
            foreach ($expected as $id) {
                $this->assertContains($id, $visible);
            }
            foreach (['not DNP', 'completed', 'cancelled', 'reactivated'] as $name) {
                $this->assertNotContains($fixtures[$name]->id, $visible);
            }
            $export = new \App\Exports\DnpLeadsExport(['name' => $client->name]);
            $rows = $export->collection();
            $this->assertCount(count($visible), $rows);
            $this->assertEqualsCanonicalizing($visible, $rows->pluck('lead_id')->all());
            $download = $this->get(route('admin.leads.dnp.export', ['name' => $client->name]))->assertOk();
            $workbook = \PhpOffice\PhpSpreadsheet\IOFactory::load($download->baseResponse->getFile()->getPathname());
            $decoded = $workbook->getActiveSheet()->toArray();
            $this->assertSame('Status', $decoded[0][8]);
            $this->assertSame(count($visible) + 1, count($decoded));
            $this->assertSame(3, count(array_filter(array_slice($decoded, 1), fn ($row) => $row[8] === 'Cancelled')));
            $workbook->disconnectWorksheets();
            foreach (['operations', 'hr', 'owner'] as $role) {
                $this->actingAs(User::where('email', $role.'@wave1.invalid')->sole())
                    ->get(route('admin.leads.dnp', ['name' => $client->name]))->assertOk()->assertSee('Cancelled');
            }
            foreach (['accounts', 'hr'] as $role) {
                $this->actingAs(User::where('email', $role.'@wave1.invalid')->sole())
                    ->get(route('admin.leads.dnp.export'))->assertForbidden();
            }
            $this->assertSame(3, $rows->where('status', 'Cancelled')->count());
            $this->assertSame('Status', last($export->headings()));
            foreach ($rows as $row) {
                $this->assertSame($row['status'], last($export->map($row)));
            }
            $this->actingAs($actor)->get(route('admin.leads.dnp', ['name' => $client->name]))
                ->assertOk()->assertDontSee('data-id="'.$fixtures['unassigned']->id.'"', false);
        } finally {
            \Carbon\Carbon::setTestNow();
        }
    }

    public function test_simultaneous_dnp_processors_create_one_cancellation(): void
    {
        // Separate connections need committed fixtures. Only these new IDs are removed below.
        $actor = User::where('email', 'sales-executive@wave1.invalid')->sole();
        $client = \App\Models\Client::where('email', 'browser@wave1.invalid')->sole();
        $lead = Lead::forceCreate(['id' => (string) Str::uuid(), 'client_id' => $client->id,
            'representative_user_id' => null]);
        $followup = \App\Models\LeadFollowup::create(['id' => (string) Str::uuid(),
            'lead_id' => $lead->id, 'followed_by' => $actor->id, 'status' => 1,
            'customer_not_picked_up' => true, 'total_amount' => 32000]);
        $followup->forceFill(['created_at' => now()->subDays(31), 'updated_at' => now()->subDays(31)])->saveQuietly();
        $original = $followup->fresh()->getAttributes();
        $candidates = app(\App\Services\DnpCancellationService::class)->run(true)['candidate_ids'];
        $this->assertSame([$lead->id], $candidates, 'Concurrent command fixture must be the only eligible QA lead.');
        DB::commit();
        $this->transactionStarted = false;
        $processes = [];
        $pipes = [];
        try {
            DB::beginTransaction();
            Lead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
            foreach ([0, 1] as $index) {
                $processes[$index] = proc_open(
                    [PHP_BINARY, __DIR__.'/../Support/wave2_dnp_worker.php', $lead->id],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$index]
                );
                $this->assertIsResource($processes[$index]);
                fclose($pipes[$index][0]);
            }
            $waiting = 0;
            for ($attempt = 0; $attempt < 120 && $waiting < 2; $attempt++) {
                DB::select('SELECT pg_stat_clear_snapshot()');
                $waiting = (int) DB::selectOne("SELECT count(*) AS n FROM pg_stat_activity
                    WHERE application_name = 'wave2-dnp-concurrency'
                    AND wait_event_type = 'Lock' AND query LIKE '%for update%'")->n;
                if ($waiting < 2) {
                    usleep(100000);
                }
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
            $this->assertSame(2, $waiting, 'Both independent PostgreSQL sessions must reach the row lock concurrently.');
            $this->assertCount(2, array_unique(array_column($results, 'pid')));
            $this->assertEqualsCanonicalizing([true, false], array_column($results, 'cancelled'));
            $this->assertSame(2, $lead->leadFollowups()->count());
            $this->assertSame(1, \App\Models\ActivityAudit::where('lead_id', $lead->id)->where('action', 'dnp_auto_cancelled')->count());
            $this->assertSame($original, $followup->fresh()->getAttributes());
            fwrite(STDOUT, json_encode(['dnp_concurrency' => $results, 'simultaneous_lock_waiters' => $waiting]).PHP_EOL);
        } finally {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                proc_terminate($process);
                proc_close($process);
            }
            DB::transaction(function () use ($lead, $followup) {
                \App\Models\ActivityAudit::where('lead_id', $lead->id)->delete();
                DB::table('lead_followups')->where('lead_id', $lead->id)->where('source', \App\Services\DnpCancellationService::SOURCE)->delete();
                DB::table('lead_followups')->where('id', $followup->id)->delete();
                DB::table('leads')->where('id', $lead->id)->delete();
            });
        }
    }

    public function test_incentives_count_full_booking_once_after_first_approved_installment(): void
    {
        $sales = User::where('email', 'sales-executive@wave1.invalid')->sole();
        $hr = User::where('email', 'hr@wave1.invalid')->sole();
        $accounts = User::where('email', 'accounts@wave1.invalid')->sole();
        $client = \App\Models\Client::forceCreate(['id' => (string) Str::uuid(),
            'name' => 'QA Full Booking Incentive', 'contact_number' => '+91-9999990080', 'status' => 1]);
        $product = \App\Models\Product::create(['id' => (string) Str::uuid(), 'product' => 'QA incentive service', 'status' => 1]);
        $lead = Lead::forceCreate(['id' => (string) Str::uuid(), 'client_id' => $client->id,
            'representative_user_id' => $sales->id, 'product_ids' => [$product->id]]);
        $followup = \App\Models\LeadFollowup::create(['id' => (string) Str::uuid(), 'lead_id' => $lead->id,
            'followed_by' => $sales->id, 'status' => 4, 'total_amount' => 32000, 'service_amount' => 32000]);
        $target = \App\Models\Target::create(['id' => (string) Str::uuid(), 'sales_executive_id' => $sales->id,
            'assigned_by' => $hr->id, 'year' => 2043, 'month' => 1, 'target_amount' => 10000,
            'status' => 'active', 'incentive_enabled' => true, 'incentive_basis' => 'individual',
            'incentive_multiplier' => 0.05]);
        $from = \Carbon\Carbon::parse('2043-01-01');
        $to = \Carbon\Carbon::parse('2043-01-31');
        $incentives = new \App\Services\TargetIncentiveService();
        \App\Models\PaymentAuditTrail::create(['id' => (string) Str::uuid(),
            'lead_followup_id' => $followup->id, 'payment_status' => 1, 'paid_amount' => 0,
            'paid_date' => '2042-12-15', 'payment_method' => 'Bank Transfer', 'created_by' => $sales->id]);
        $payment = \App\Models\PaymentAuditTrail::create(['id' => (string) Str::uuid(),
            'lead_followup_id' => $followup->id, 'payment_status' => 0, 'paid_amount' => 10000,
            'paid_date' => '2043-01-15', 'payment_method' => 'Bank Transfer', 'created_by' => $sales->id]);
        $this->assertFalse($incentives->ledgerForPeriod($from, $to)->contains('lead_id', $lead->id));
        $payment->update(['payment_status' => 1]);
        $ledger = $incentives->ledgerForPeriod($from, $to)->where('lead_id', $lead->id)->values();
        $this->assertCount(1, $ledger);
        $this->assertEquals(32000, $ledger->sole()['amount']);
        $this->assertEquals(1600, $incentives->calculate($target, $ledger)['incentive']);
        foreach ([['2043-01-20', 22000], ['2043-02-03', 500]] as [$date, $cash]) {
            \App\Models\PaymentAuditTrail::create(['id' => (string) Str::uuid(),
                'lead_followup_id' => $followup->id, 'payment_status' => 1, 'paid_amount' => $cash,
                'paid_date' => $date, 'payment_method' => 'Bank Transfer', 'created_by' => $sales->id]);
        }
        $ledger = $incentives->ledgerForPeriod($from, $to)->where('lead_id', $lead->id)->values();
        $this->assertCount(1, $ledger);
        $this->assertEquals(32000, $ledger->sole()['amount']);
        $this->assertFalse($incentives->ledgerForPeriod(\Carbon\Carbon::parse('2043-02-01'),
            \Carbon\Carbon::parse('2043-02-28'))->contains('lead_id', $lead->id));
        foreach ([[$hr, 'admin.targets.download-incentive'], [$accounts, 'admin.report.incentives.download']] as [$actor, $route]) {
            $response = $this->actingAs($actor)->get(route($route, [
                'year' => 2043, 'month' => 1, 'file_type' => 'csv', 'person_id' => $sales->id]))->assertOk();
            $rows = array_values(array_filter(array_map('str_getcsv', preg_split('/\r\n|\n|\r/', trim($response->streamedContent())))));
            $this->assertCount(3, $rows, $response->streamedContent());
            $this->assertSame($client->name, $rows[1][0]);
            $this->assertEquals(32000, (float) str_replace(',', '', $rows[1][7]));
            $this->assertEquals(1600, (float) $rows[1][21]);
            $excel = $this->get(route($route, [
                'year' => 2043, 'month' => 1, 'file_type' => 'excel', 'person_id' => $sales->id]))->assertOk();
            $temporary = tempnam(getenv('CRM_WAVE1_QA_ROOT'), 'group2-incentives-');
            try {
                file_put_contents($temporary, $excel->streamedContent());
                $workbook = \PhpOffice\PhpSpreadsheet\IOFactory::load($temporary);
                $this->assertSame(2, $workbook->getSheetCount());
                $decoded = $workbook->getSheetByName('Sales Detail')->toArray();
                $this->assertSame($client->name, $decoded[1][0]);
                $this->assertEquals(32000, (float) str_replace(',', '', $decoded[1][7]));
                $this->assertEquals(1600, (float) str_replace(',', '', $decoded[1][21]));
                $workbook->disconnectWorksheets();
            } finally {
                unlink($temporary);
            }
        }
        $this->actingAs($sales)->get(route('admin.targets.download-incentive', [
            'year' => 2043, 'month' => 1, 'file_type' => 'csv']))->assertForbidden();
    }

    public function test_hr_can_correct_existing_attendance_using_changed_file_without_duplicate_rows(): void
    {
        $hr = User::where('email', 'hr@wave1.invalid')->sole();
        $sales = User::where('email', 'sales-executive@wave1.invalid')->sole();
        $employee = $sales->replicate();
        $employee->forceFill(['id' => (string) Str::uuid(), 'email' => Str::uuid().'@wave1.invalid',
            'name' => 'QA Attendance Correction '.Str::random(8)])->saveQuietly();
        $date = now()->subDays(3);
        $range = ['from_date' => $date->toDateString(), 'to_date' => $date->toDateString()];
        $csv = "Date,Day,Name,In,Out,Status\n".$date->format('d/m/Y').','.$date->format('l').','.$employee->name.",10:00,18:00,P\n";
        $corrected = str_replace(',10:00,18:00,P', ',,,A', $csv);
        $csv = str_replace('\\n', "\n", $csv);
        $corrected = str_replace('\\n', "\n", $corrected);
        $this->actingAs($sales)->post(route('admin.attendance.import.preview'), $range + [
            'excel_file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('denied.csv', $csv),
        ], ['Accept' => 'application/json'])->assertForbidden();
        $records = \App\Models\AttendanceRecord::where('user_id', $employee->id)->whereDate('attendance_date', $date);
        $this->assertFalse($records->exists());
        $imports = [];
        foreach ([$csv, $corrected] as $index => $content) {
            $preview = $this->actingAs($hr)->post(route('admin.attendance.import.preview'), $range + [
                'excel_file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('correction-'.$index.'.csv', $content),
            ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('total_records', 1);
            $importId = $preview->json('import_id');
            $mapping = $preview->json('employees.0.mapping_key');
            $this->assertNotEmpty($mapping);
            $this->post(route('admin.attendance.import.confirm'), [
                'import_id' => $importId, 'correction_reason' => 'Corrected attendance evidence', 'mappings' => [$mapping => $employee->id],
            ])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertNull(session('error'), (string) session('error'));
            $record = (clone $records)->sole();
            $this->assertSame($index === 0 ? 'P' : 'A', $record->raw_status);
            $this->assertSame($importId, $record->source_import_id);
            if ($index === 0) {
                $originalId = $record->id;
                $neighbor = \App\Models\AttendanceRecord::create(['user_id' => $employee->id,
                    'source_import_id' => $importId, 'attendance_date' => $date->copy()->subDay()->toDateString(),
                    'day_name' => $date->copy()->subDay()->format('l'), 'raw_status' => 'P']);
                $neighborBefore = $neighbor->fresh()->getAttributes();
            } else {
                $this->assertSame($originalId, $record->id);
            }
            $imports[] = \App\Models\AttendanceImport::findOrFail($importId);
            $this->assertSame('completed', end($imports)->status);
        }
        $this->assertSame(1, $records->count());
        $this->assertNotSame($imports[0]->file_hash, $imports[1]->file_hash);
        $this->assertSame(1, $imports[1]->updated_records);
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('local')->exists($imports[0]->stored_path));
        $this->post(route('admin.attendance.import.preview'), $range + [
            'excel_file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('same-corrected-file.csv', $corrected),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('excel_file');
        $this->assertSame($originalId, (clone $records)->sole()->id);
        $this->assertSame('A', (clone $records)->sole()->raw_status);
        $this->assertSame($neighborBefore, $neighbor->fresh()->getAttributes());
    }

    public function test_accounts_role_routes_and_team_scope_preserve_existing_permissions(): void
    {
        $admin = User::where('email', 'super-admin@wave1.invalid')->sole();
        $base = User::where('email', 'accounts@wave1.invalid')->sole();
        $people = [];
        $grants = [];
        foreach (\App\Models\UserType::ACCOUNTS_ROLES as $role) {
            $type = \App\Models\UserType::firstOrCreate(['user_type' => $role], ['id' => (string) Str::uuid(), 'status' => 1]);
            $person = $base->replicate();
            $person->forceFill(['id' => (string) Str::uuid(), 'email' => Str::uuid().'@wave1.invalid',
                'name' => 'QA '.$role, 'user_type_id' => $type->id])->saveQuietly();
            $people[$role] = $person;
            foreach (app('router')->getRoutes() as $route) {
                $roleMiddleware = array_filter($route->gatherMiddleware(), fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'role:'));
                if (!$roleMiddleware) continue;
                $allowed = true;
                foreach ($roleMiddleware as $middleware) {
                    $roles = [];
                    foreach (explode(',', substr($middleware, 5)) as $group) {
                        $constant = 'App\Models\UserType::'.$group;
                        $roles = array_merge($roles, defined($constant) ? (array) constant($constant) : [$group]);
                    }
                    $allowed = $allowed && in_array($role, $roles, true);
                }
                if ($allowed) $grants[$role][] = implode('|', $route->methods()).' '.$route->uri();
            }
            $this->actingAs($person);
            foreach (['admin.refunds.index', 'admin.operations.vendor-refunds.index', 'admin.report.incentives'] as $route) {
                $this->get(route($route))->assertOk();
            }
            $this->postJson(route('admin.users.store'), [])->assertForbidden();
            $this->postJson(route('admin.targets.store'), [])->assertForbidden();
            $this->postJson(route('admin.kpi.team-memberships.store'), [])->assertForbidden();
            $this->assertTrue(\App\Support\RefundApproval::canDecide($person));
        }
        $this->assertSame($grants['Accounts Executive'], $grants['Accounts Manager']);
        $this->assertSame($grants['Accounts Executive'], $grants['Senior Accounts Manager']);
        $member = $base->replicate();
        $member->forceFill(['id' => (string) Str::uuid(), 'email' => Str::uuid().'@wave1.invalid',
            'user_type_id' => $people['Accounts Executive']->user_type_id])->saveQuietly();
        foreach ($people as $role => $person) {
            $request = ['department' => 'accounts', 'manager_user_id' => $person->id, 'member_user_id' => $member->id];
            $this->actingAs($admin)->post(route('admin.kpi.team-memberships.store'), $request)
                ->assertRedirect()->assertSessionHasNoErrors();
            $expected = [$person->id, $member->id];
            $actual = app(\App\Services\Kpi\KpiDataScopeService::class)->allowedUserIds($person, 'accounts');
            $this->assertEqualsCanonicalizing($expected, $actual);
        }
        fwrite(STDOUT, json_encode(['accounts_route_grants' => array_map('count', $grants)]).PHP_EOL);
    }

    private bool $transactionStarted = false;

    public function test_add_lead_persists_and_lists_country_scoped_city_uuids(): void
    {
        $actor = User::where('email', 'super-admin@wave1.invalid')->sole();
        $country = \App\Models\Country::create(['name' => 'City Acceptance A', 'status' => 1]);
        $other = \App\Models\Country::create(['name' => 'City Acceptance B', 'status' => 1]);
        $name = 'City Acceptance '.Str::random(12);
        $payload = [
            'client_id' => 'new', 'name' => 'Synthetic City Client',
            'contact_country_code' => '+91', 'country_id' => $country->id,
            'city' => $name, 'product_ids' => [\App\Models\Product::create(['id' => (string) Str::uuid(), 'product' => 'Synthetic City Product', 'status' => 1])->id],
            'representative_user_id' => $actor->id, 'number_of_passengers' => 1,
            'status' => 0, 'trips' => [[
                'from_date' => '2041-03-10 10:00', 'to_date' => '2041-03-10 12:00',
                'from_place' => 'Delhi', 'to_place' => 'Mumbai',
            ]],
        ];
        $this->actingAs($actor);
        $ids = [];
        foreach ([[$country, $name], [$country, strtoupper($name)], [$other, $name]] as $index => [$scope, $cityName]) {
            $phone = '999999008'.$index;
            $this->post(route('admin.clients.store'), array_replace($payload, [
                'contact_number' => $phone, 'country_id' => $scope->id, 'city' => $cityName,
            ]))->assertRedirect(route('admin.clients.create'))->assertSessionHasNoErrors();
            $client = \App\Models\Client::where('contact_number', '+91-'.$phone)->sole();
            $lead = Lead::where('client_id', $client->id)->sole();
            $city = \App\Models\City::where('country_id', $scope->id)
                ->whereRaw('lower(name) = lower(?)', [$name])->sole();
            $this->assertTrue(Str::isUuid($city->id));
            $this->assertSame($city->id, $client->city_id);
            $this->assertSame($client->id, $lead->client_id);
            $this->getJson(route('admin.clients.cities', $scope->id))->assertOk()
                ->assertJsonFragment(['id' => $city->id, 'name' => $city->name]);
            $ids[] = $city->id;
        }
        $this->assertSame($ids[0], $ids[1]);
        $this->assertNotSame($ids[0], $ids[2]);
    }

 
    public function test_client_city_update_creates_reuses_and_rejects_foreign_city(): void
    {
        $actor = User::where('email', 'super-admin@wave1.invalid')->sole();
        $client = \App\Models\Client::where('email', 'browser@wave1.invalid')->sole();
        $country = \App\Models\Country::create(['name' => 'Wave Two Country A', 'status' => 1]);
        $other = \App\Models\Country::create(['name' => 'Wave Two Country B', 'status' => 1]);
        $url = route('admin.client.update', $client);
        $name = 'Wave Two '.Str::random(12);
        $payload = ['name' => $client->name, 'contact_number' => '9999990077',
            'contact_country_code' => '+91', 'country_id' => $country->id, 'city' => $name];
        $this->actingAs($actor)->patch($url, $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNull(session('error'), (string) session('error'));
        $city = \App\Models\City::where('country_id', $country->id)->whereRaw('LOWER(name) = LOWER(?)', [$name])->sole();
        $this->assertSame($city->id, $client->fresh()->city_id);
        $this->patch($url, array_replace($payload, ['city' => strtoupper($name)]))->assertSessionHasNoErrors();
        $this->assertSame($city->id, $client->fresh()->city_id);
        $this->assertSame(1, \App\Models\City::where('country_id', $country->id)->whereRaw('LOWER(name) = LOWER(?)', [$name])->count());
        $this->patch($url, array_replace($payload, ['country_id' => $other->id, 'city' => $city->id]))
            ->assertRedirect()->assertSessionHasErrors('city');
        $this->assertSame($city->id, $client->fresh()->city_id);
    }

    public function test_conversion_includes_outreach_while_response_time_excludes_it(): void
    {
        $user = User::where('email', 'sales-executive@wave1.invalid')->sole();
        $source = Lead::whereHas('client', fn ($query) => $query->where('email', 'browser@wave1.invalid'))->sole();
        $date = \Carbon\Carbon::parse('2041-03-10 10:00:00');
        $metric = new \App\Models\KpiMetric();
        $scope = app(\App\Services\Kpi\SalesKpiLeadScopeService::class);
        $response = app(\App\Services\Kpi\SalesResponseTimeKpiResolver::class);
        $ordinary = $source->replicate();
        $ordinary->forceFill(['id' => (string) Str::uuid(), 'representative_user_id' => $user->id, 'created_at' => $date])->saveQuietly();
        $before = $response->resolve($user, $metric, $date, 22);
        $outreach = $ordinary->replicate();
        $outreach->forceFill(['id' => (string) Str::uuid(), 'created_at' => $date])->saveQuietly();
        \App\Models\KpiOutreachAssignment::create(['pool_id' => 1, 'user_id' => $user->id,
            'normalized_phone' => '9999990098', 'assigned_at' => $date, 'created_lead_id' => $outreach->id]);
        $followup = $source->leadFollowups()->where('status', 4)->firstOrFail()->replicate();
        $followup->forceFill(['id' => (string) Str::uuid(), 'lead_id' => $outreach->id])->saveQuietly();
        \App\Models\PaymentAuditTrail::create(['id' => (string) Str::uuid(), 'lead_followup_id' => $followup->id,
            'payment_status' => 1, 'paid_amount' => 1000, 'paid_date' => $date, 'payment_method' => 'Bank Transfer',
            'created_by' => $user->id]);
        $this->assertEqualsCanonicalizing([$ordinary->id, $outreach->id], $scope->incoming($user, $date, null, true)->pluck('id')->all());
        $this->assertSame([$ordinary->id], $scope->incoming($user, $date)->pluck('id')->all());
        $result = app(\App\Services\Kpi\SalesLeadConversionKpiResolver::class)->resolve($user, $metric, $date, 22);
        $bookedIds = \App\Models\LeadFollowup::whereIn('lead_id', [$ordinary->id, $outreach->id])
            ->whereHas('paymentAuditTrail', fn ($query) => $query
                ->where('payment_status', \App\Models\PaymentAuditTrail::STATUS_APPROVED)
                ->where('paid_amount', '>', 0)
                ->where('paid_date', '>=', $date->copy()->startOfMonth()->toDateString())
                ->where('paid_date', '<', $date->copy()->addDay()->toDateString()))
            ->distinct()->pluck('lead_id')->all();
        $this->assertSame([$outreach->id], $bookedIds);
        $this->assertSame(1, $result['actual_value']);
        $this->assertSame(2, $result['target_value']);
        $this->assertEquals(50, $result['achievement_percent']);
        $this->assertSame($before, $response->resolve($user, $metric, $date, 22));
    }

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
        $identity = DB::selectOne('SELECT current_database() AS db, current_user AS role');
        $this->assertSame('accretion_wave1_qa', $identity->db);
        $this->assertSame('accretion_wave1_qa_role', $identity->role);
        DB::beginTransaction();
        $this->transactionStarted = true;
    }

    protected function tearDown(): void
    {
        if ($this->transactionStarted) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    public function test_vendor_followup_purpose_date_rules_and_permissions(): void
    {
        $operator = User::where('email', 'operations@wave1.invalid')->sole();
        $sales = User::where('email', 'sales-executive@wave1.invalid')->sole();
        $lead = Lead::whereHas('client', fn ($query) => $query->where('email', 'browser@wave1.invalid'))->sole();
        $create = route('admin.operations.vendor-followups.store', $lead);
        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();
        $later = now()->addDays(2)->toDateString();

        foreach (['Query', 'Timeslot', 'Refund'] as $index => $purpose) {
            $vendor = Vendor::create(['id' => (string) Str::uuid(), 'name' => 'Wave2 '.$purpose,
                'contact_number' => '999999008'.$index, 'status' => 1]);
            $payload = ['vendor_id' => $vendor->id, 'purpose' => $purpose,
                'tentative_ride_at' => $today, 'followup_date' => $tomorrow];
            $cases = OperationCase::where('lead_id', $lead->id)->where('metadata->vendor_id', $vendor->id);
            $this->actingAs($sales)->postJson($create, $payload)->assertForbidden();
            $this->assertFalse($cases->exists());
            $this->actingAs($operator);
            if ($purpose !== 'Refund') {
                $this->postJson($create, $payload)->assertStatus(422)->assertJsonValidationErrors('followup_date');
                $this->assertFalse($cases->exists());
                $payload['followup_date'] = $today;
            }
            $this->post($create, $payload)->assertRedirect()->assertSessionHasNoErrors();
            $case = $cases->sole();
            $details = route('admin.operations.vendor-followups.show', $case);
            $this->get($details)->assertOk()->assertSee($purpose);
            $this->actingAs($sales)->get($details)->assertForbidden();
            $this->actingAs($operator);
            $this->assertSame($purpose, $case->metadata['purpose']);
            $this->assertSame($payload['followup_date'], $case->next_followup_at->toDateString());
            $reschedule = route('admin.operations.vendor-followups.reschedule', $case);
            $change = ['followup_date' => $later, 'reason' => 'Synthetic QA'];
            $this->actingAs($sales)->postJson($reschedule, $change)->assertForbidden();
            $this->actingAs($operator);
            if ($purpose === 'Refund') {
                $this->post($reschedule, $change)->assertRedirect()->assertSessionHasNoErrors();
                $this->assertSame($later, $case->fresh()->next_followup_at->toDateString());
            } else {
                $this->postJson($reschedule, $change)->assertStatus(422)->assertJsonValidationErrors('followup_date');
                $this->assertSame($case->getAttributes(), $case->fresh()->getAttributes());
                $case->update(['metadata' => array_replace($case->metadata, ['purpose' => 'Confirm Ride Date & Time'])]);
                $this->get(route('admin.operations.vendor-followups.show', $case))->assertOk()->assertSee('Confirm Ride Date & Time');
                $this->postJson($reschedule, $change)->assertStatus(422)->assertJsonValidationErrors('followup_date');
                $this->assertSame($case->getAttributes(), $case->fresh()->getAttributes());
            }
        }
    }

    public function test_hr_department_pages_keep_the_other_page_and_date_filters(): void
    {
        $hr = User::where('email', 'hr@wave1.invalid')->sole();
        $sales = User::where('email', 'sales-executive@wave1.invalid')->sole();
        $operations = User::where('email', 'operations@wave1.invalid')->sole();
        foreach ([$sales, $operations] as $source) {
            for ($index = 0; $index < 11; $index++) {
                $user = $source->replicate();
                $user->forceFill(['id' => (string) Str::uuid(), 'name' => 'QA pagination '.$index,
                    'email' => Str::uuid().'@wave1.invalid'])->saveQuietly();
            }
        }
        $date = now()->toDateString();
        $url = '/admin/hr?preset=custom&from_date='.$date.'&to_date='.$date;
        $this->actingAs($sales)->get($url)->assertForbidden();
        $response = $this->actingAs($hr)->get($url.'&sales_page=2&operations_page=1')->assertOk();
        $this->assertSame(4, substr_count($response->getContent(), '<table class="hr-table'));
        $pages = $response->viewData('departmentRows');
        $this->assertNotEmpty($pages['sales']->items());
        $this->assertNotEmpty($pages['operations']->items());
        $this->assertSame(2, $pages['sales']->currentPage());
        $this->assertSame(1, $pages['operations']->currentPage());
        $response->assertSee($pages['operations']->nextPageUrl());
        $next = $this->get($pages['operations']->nextPageUrl())->assertOk();
        $this->assertSame(2, $next->viewData('departmentRows')['sales']->currentPage());
        $this->assertSame(2, $next->viewData('departmentRows')['operations']->currentPage());
        $this->assertSame($date, $next->viewData('filter')['from']->toDateString());
        $previous = $this->get($next->viewData('departmentRows')['operations']->previousPageUrl())->assertOk();
        $this->assertSame(2, $previous->viewData('departmentRows')['sales']->currentPage());
        $this->assertSame(1, $previous->viewData('departmentRows')['operations']->currentPage());
        $salesNext = $this->get($url.'&sales_page=1&operations_page=2&qa_context=kept')->assertOk();
        $salesNextUrl = $salesNext->viewData('departmentRows')['sales']->nextPageUrl();
        $salesNext->assertSee($salesNextUrl);
        $this->assertStringContainsString('qa_context=kept', $salesNextUrl);
        $this->assertStringContainsString('from_date='.$date, $salesNextUrl);
        $salesSecond = $this->get($salesNextUrl)->assertOk();
        $this->assertSame(2, $salesSecond->viewData('departmentRows')['operations']->currentPage());
        $this->assertSame(2, $salesSecond->viewData('departmentRows')['sales']->currentPage());
        $salesPrevious = $this->get($salesSecond->viewData('departmentRows')['sales']->previousPageUrl())->assertOk();
        $this->assertSame(1, $salesPrevious->viewData('departmentRows')['sales']->currentPage());
        $this->assertSame(2, $salesPrevious->viewData('departmentRows')['operations']->currentPage());

        $admin = User::where('email', 'admin@wave1.invalid')->sole();
        $superAdmin = User::where('email', 'super-admin@wave1.invalid')->sole();
        $this->actingAs($admin)->get($url)->assertForbidden();
        $this->actingAs($superAdmin)->get($url)->assertOk();

        User::whereNotIn('id', [$hr->id, $superAdmin->id])->update(['status' => 0]);
        $empty = $this->actingAs($hr)->get($url)->assertOk();
        foreach (['sales', 'operations'] as $department) {
            $this->assertSame(0, $empty->viewData('departmentRows')[$department]->total());
            $this->assertSame(2, substr_count($empty->getContent(), 'No active '.$department.' employees'));
        }
    }

    public function test_first_extra_service_selection_saves_without_creating_a_quote_or_payment(): void
    {
        $actor = User::where('email', 'sales-executive@wave1.invalid')->sole();
        $client = \App\Models\Client::where('email', 'browser@wave1.invalid')->sole();
        $service = \App\Models\Service::create(['id' => (string) Str::uuid(), 'service' => 'QA draft charter', 'service_amount' => 30000, 'status' => 1]);
        $ids = [];
        foreach ([950, 0, 0, 100] as $index => $amount) {
            $ids[] = \App\Models\ExtraService::create(['id' => (string) Str::uuid(), 'extra_service' => 'QA draft extra '.$index, 'extra_service_amount' => $amount, 'status' => 1])->id;
        }
        $service->extraServices()->attach($ids);
        $lead = Lead::forceCreate(['id' => (string) Str::uuid(), 'client_id' => $client->id,
            'representative_user_id' => $actor->id, 'service_ids' => [$service->id]]);
        $url = route('admin.leads.extra-services.update', $lead);
        $this->actingAs($actor);
        $this->assertNull($lead->extra_service_quote);
        $payload = ['selection_submitted' => true, 'quote_version' => hash('sha256', ''), 'extra_service_ids' => $ids];
        $this->putJson($url, $payload)->assertOk()->assertJsonPath('extra_service_ids', $ids)->assertJsonPath('extra_services_total', 1050);
        $this->assertSame(['draft_extra_service_ids' => $ids], json_decode($lead->fresh()->extra_service_quote, true));
        $this->assertSame(0, $lead->leadFollowups()->count());
        $this->putJson($url, $payload)->assertOk();
        $this->putJson($url, array_replace($payload, ['extra_service_ids' => []]))->assertStatus(409);
        $payload['quote_version'] = hash('sha256', $lead->fresh()->extra_service_quote);
        $payload['extra_service_ids'] = array_slice($ids, 0, 3);
        $this->putJson($url, $payload)->assertOk()->assertJsonPath('extra_services_total', 950);
        $page = $this->get(route('admin.leads.follow-up.create', $lead))->assertOk();
        $page->assertSee('QA draft extra 0')->assertSee('const persistedExtraSelection =', false);
        $payload['quote_version'] = hash('sha256', $lead->fresh()->extra_service_quote);
        $payload['extra_service_ids'] = [];
        $this->putJson($url, $payload)->assertOk()->assertJsonPath('extra_service_ids', []);
        $this->assertSame(['draft_extra_service_ids' => []], json_decode($lead->fresh()->extra_service_quote, true));

        $selected = array_slice($ids, 0, 3);
        $payload['quote_version'] = hash('sha256', $lead->fresh()->extra_service_quote);
        $payload['extra_service_ids'] = $selected;
        $this->putJson($url, $payload)->assertOk();
        $details = [['type' => 'service', 'id' => $service->id, 'original_amount' => 30000, 'discount_amount' => 100]];
        foreach ($selected as $index => $id) {
            $details[] = ['type' => 'extra_service', 'id' => $id, 'parent_service_id' => $service->id,
                'original_amount' => $index === 0 ? 950 : 0, 'discount_amount' => 0];
        }
        $this->postJson(route('admin.leads.follow-up.store', $lead), [
            'status' => 4, 'notes' => 'Synthetic first payment', 'pricing_action' => 'revise',
            'services' => [$service->id], 'extra_services' => $selected, 'service_details' => json_encode($details),
            'total_amount' => 30850, 'received_amount' => 10000, 'paid_date' => now()->toDateString(),
            'payment_method' => 'Bank Transfer', 'image' => \Illuminate\Http\UploadedFile::fake()->image('qa-receipt.png'),
        ])->assertOk();
        $saved = $lead->leadFollowups()->sole();
        $this->assertEquals(30850, $saved->total_amount);
        $this->assertEquals(100, $saved->discount_amount);
        $extras = collect($saved->service_details_array)->where('type', 'extra_service')->values();
        $this->assertSame($selected, $extras->pluck('id')->all());
        $this->assertEquals([950, 0, 0], $extras->pluck('original_amount')->all());
    }
}
