<?php

namespace Tests\Feature;

use App\Models\{Client, Lead, LeadFollowup, PaymentAuditTrail, User, UserType};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\IsolatedWave2PostgresTestCase;

class Wave2LeadStatusPostgresAcceptanceTest extends IsolatedWave2PostgresTestCase
{
    private function actor(string $role): User
    {
        $type = UserType::firstOrCreate(['user_type' => $role], ['id' => (string) Str::uuid(), 'status' => 1]);
        return User::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'QA status ' . $role,
            'email' => Str::uuid() . '@wave1.invalid',
            'password' => bcrypt('synthetic-only'),
            'user_type_id' => $type->id,
            'status' => 1
        ]);
    }

    private function booking(User $actor, int $status = 1): Lead
    {
        $client = Client::where('email', 'browser@wave1.invalid')->sole();
        $lead = Lead::forceCreate([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' => $actor->id
        ]);
        $history = LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'followed_by' => $actor->id,
            'status' => $status,
            'total_amount' => 32000,
            'next_followup_date' => now()->format('Y-m-d H:i:s')
        ]);
        $history->forceFill(['created_at' => now()->subMinute(), 'updated_at' => now()->subMinute()])->saveQuietly();
        return $lead;
    }

    private function payload(int $status): array
    {
        return [
            'status' => $status,
            'notes' => 'Synthetic status check',
            'pricing_action' => 'preserve',
            'next_followup_date' => now()->addDay()->format('Y-m-d H:i')
        ];
    }

    public function test_owner_has_only_field_scoped_status_write_and_terminal_guard(): void
    {
        $owner = $this->actor(UserType::OWNER);
        $lead = $this->booking($owner);
        $url = '/admin/leads/' . $lead->id . '/status';
        $before = $lead->fresh()->getAttributes();
        $payload = [
            'status' => 10,
            'notes' => 'Owner synthetic follow-up',
            'next_followup_date' => now()->addDay()->format('Y-m-d H:i')
        ];
        $this->actingAs($owner)->postJson($url, $payload)->assertOk();
        $this->assertSame(10, (int) $lead->leadFollowups()->latest('created_at')->latest('id')->value('status'));
        $this->assertSame($before, $lead->fresh()->getAttributes());
        foreach (['total_amount' => 0, 'product_ids' => [], 'received_amount' => 100, 'customer_not_picked_up' => true] as $key => $value) {
            $this->postJson($url, $payload + [$key => $value])->assertUnprocessable();
        }
        $this->postJson(route('admin.leads.follow-up.store', $lead), $this->payload(1))->assertForbidden();
        $this->deleteJson(route('admin.leads.destroy', $lead))->assertForbidden();
        foreach ([3, 4, 5] as $status)
            $this->postJson($url, array_replace($payload, ['status' => $status]))->assertUnprocessable();
        $completed = $this->booking($owner, 5);
        $this->postJson('/admin/leads/' . $completed->id . '/status', array_replace($payload, ['status' => 1]))->assertUnprocessable();
    }

    public function test_today_filter_narrows_latest_eligible_activity_without_resurrecting_closed_history(): void
    {
        $sales = $this->actor(UserType::SALES_EXECUTIVE);
        $active = $this->booking($sales);
        $salesFollowup = $this->booking($sales, 10);
        foreach ([2, 5, 11] as $status)
            $this->booking($sales, $status);
        $this->booking($this->actor(UserType::SALES_EXECUTIVE), 10);
        $url = route('admin.upcoming-follow-up.index');
        $this->actingAs($sales);
        $ids = fn($response) => $response->assertOk()->viewData('arrFollowUps')->pluck('lead_id')->sort()->values()->all();
        $expected = collect([$active->id, $salesFollowup->id])->sort()->values()->all();
        $this->assertSame($expected, $ids($this->get($url)));
        $this->assertSame([$salesFollowup->id], $ids($this->get($url . '?status=10')));
        $this->assertSame([], $ids($this->get($url . '?status=11')));
        $this->assertSame([], $ids($this->get($url . '?status=5')));
        $this->getJson($url . '?status=999')->assertUnprocessable();
        $this->get($url . '?status=10')->assertSee('name="status"', false)->assertSee('Sales Follow-up');
    }

    public function test_sales_and_operations_transition_validation_and_payment_audit_integrity(): void
    {
        foreach (array_merge(UserType::SALES_ROLES, UserType::OPERATIONS_ROLES, UserType::ADMIN_ROLES) as $role) {
            $actor = $this->actor($role);
            $lead = $this->booking($actor);
            $followup = $lead->leadFollowups()->first();
            $payment = PaymentAuditTrail::create([
                'id' => (string) Str::uuid(),
                'lead_followup_id' => $followup->id,
                'payment_status' => 1,
                'paid_amount' => 10000,
                'paid_date' => now(),
                'payment_method' => 'Bank Transfer',
                'created_by' => $actor->id
            ]);
            $before = $payment->fresh()->getAttributes();
            $url = route('admin.leads.follow-up.store', $lead);
            $this->actingAs($actor)->postJson($url, $this->payload(10))->assertOk();
            $this->postJson($url, $this->payload(11))->assertOk();
            $this->postJson($url, array_replace($this->payload(10), ['next_followup_date' => null]))->assertUnprocessable();
            $this->postJson($url, array_replace($this->payload(11), ['notes' => '']))->assertUnprocessable();
            $this->postJson($url, $this->payload(5))->assertUnprocessable();
            if (in_array($role, UserType::SALES_ROLES, true)) {
                foreach ([1, 2] as $status)
                    $this->postJson($url, $this->payload($status))->assertUnprocessable();
            }
            foreach ([3, 4] as $status)
                $this->postJson($url, $this->payload($status))->assertUnprocessable();
            $this->assertSame($before, $payment->fresh()->getAttributes());
            $this->assertSame(1, PaymentAuditTrail::where('lead_followup_id', $followup->id)->count());
        }
    }

    public function test_all_thirteen_role_read_boundaries_and_status_filter_labels(): void
    {
        $roles = array_merge(
            UserType::ADMIN_ROLES,
            [UserType::OWNER],
            UserType::SALES_ROLES,
            UserType::OPERATIONS_ROLES,
            UserType::ACCOUNTS_ROLES,
            [UserType::HR]
        );
        foreach ($roles as $role) {
            $actor = $this->actor($role);
            $lead = $this->booking($actor);
            $this->actingAs($actor);
            $canLead = !in_array($role, array_merge(UserType::ACCOUNTS_ROLES, [UserType::HR]), true);
            $workspace = $this->get(route('admin.leads.follow-up.create', $lead));
            $workspace->assertStatus($canLead ? 200 : 403);
            if (in_array($role, [UserType::OWNER, UserType::SALES_EXECUTIVE, UserType::OPERATIONS_MANAGER], true)) {
                file_put_contents(getenv('CRM_WAVE1_QA_ROOT') . '/group3-' . Str::slug($role) . '.html', $workspace->getContent());
            }
            $list = $this->get(route('admin.clients.index', ['status' => 1]));
            $list->assertStatus($canLead || $role === UserType::HR ? 200 : 403);
           
            if ($list->status() === 200) {
                $expectedStatuses = LeadFollowup::statusLabels();

                if (in_array($role, UserType::SALES_ROLES, true)) {
                    $expectedStatuses = array_intersect_key(
                        $expectedStatuses,
                        array_flip([0, 1, 2, 3, 4, 5, 10, 11])
                    );
                }

                $this->assertSame(
                    $expectedStatuses,
                    $list->viewData('statusOptions')
                );
            }
            $todayAllowed = in_array($role, array_merge(UserType::ADMIN_ROLES, UserType::SALES_ROLES, [UserType::HR, UserType::OWNER]), true);
            $this->get(route('admin.upcoming-follow-up.index', ['status' => 10]))->assertStatus($todayAllowed ? 200 : 403);
            if (!$canLead)
                $this->postJson(route('admin.leads.follow-up.store', $lead), $this->payload(10))->assertForbidden();
        }
    }

    public function test_latest_status_filter_breaks_timestamp_ties_and_never_exposes_another_sales_users_lead(): void
    {
        $sales = $this->actor(UserType::SALES_EXECUTIVE);
        $lead = $this->booking($sales, 10);
        $first = $lead->leadFollowups()->first();
        $first->forceFill(['created_at' => now()->subMinute()])->saveQuietly();
        $second = LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'followed_by' => $sales->id,
            'status' => 11
        ]);
        $second->forceFill(['created_at' => $first->fresh()->created_at])->saveQuietly();
        $latest = $lead->leadFollowups()->orderByDesc('created_at')->orderByDesc('id')->first();
        $otherStatus = (int) $latest->status === 10 ? 11 : 10;
        $foreign = $this->booking($this->actor(UserType::SALES_EXECUTIVE), (int) $latest->status);
        $this->actingAs($sales);
        $ids = fn($status) => $this->get(route('admin.clients.index', ['status' => $status]))
            ->assertOk()->viewData('leads')->pluck('id')->all();
        $this->assertSame([$lead->id], $ids($latest->status));
        $this->assertSame([], $ids($otherStatus));
        $this->getJson(route('admin.clients.index', ['status' => 999]))->assertUnprocessable();
        $this->postJson(route('admin.leads.follow-up.store', $foreign), $this->payload(10))->assertForbidden();
    }

    public function test_legitimate_installments_remain_pending_attempts_and_completed_business_status_stays_terminal(): void
    {
        foreach (array_merge(UserType::SALES_ROLES, UserType::OPERATIONS_ROLES) as $role) {
            $actor = $this->actor($role);
            $lead = $this->booking($actor, 4);
            $service = \App\Models\Service::where('status', 1)->firstOrFail();
            $initial = $lead->leadFollowups()->first();
            $initial->update(['service_ids' => [$service->id], 'pricing_revision' => true]);
            $approved = PaymentAuditTrail::create([
                'id' => (string) Str::uuid(),
                'lead_followup_id' => $initial->id,
                'payment_status' => 1,
                'paid_amount' => 10000,
                'paid_date' => now(),
                'payment_method' => 'Bank Transfer',
                'created_by' => $actor->id
            ]);
            $snapshot = $approved->fresh()->getAttributes();
            $this->actingAs($actor);
            foreach ([4, 3] as $status) {
                $payload = $this->payload($status) + [
                    'received_amount' => 1000,
                    'payment_method' => 'Bank Transfer',
                    'paid_date' => now()->toDateString(),
                    'image' => \Illuminate\Http\UploadedFile::fake()->image('synthetic.png'),
                    'payment_status' => 1
                ];
                $saved = $this->postJson(route('admin.leads.follow-up.store', $lead), $payload)->assertOk();
                $attempt = PaymentAuditTrail::where('lead_followup_id', $saved->json('data.id'))->sole();
                $this->assertSame($status, (int) $attempt->payment_status);
                $this->assertEquals(1000, $attempt->paid_amount);
            }
            $this->assertSame($snapshot, $approved->fresh()->getAttributes());
            $completed = $this->booking($actor, 5);
            $url = route('admin.leads.follow-up.store', $completed);
            $this->postJson($url, $this->payload(10))->assertOk();
            $this->postJson($url, $this->payload(1))->assertUnprocessable();
            $this->assertSame(5, app(\App\Services\LeadStatusTransitionService::class)->displayedBusinessStatus($completed));
        }
    }

    public function test_partial_full_and_rejected_only_sales_locks_and_owner_approval_states(): void
    {
        foreach (UserType::SALES_ROLES as $role) {
            $actor = $this->actor($role);
            foreach ([[10000, 1], [32000, 1], [10000, 2]] as [$amount, $approval]) {
                $lead = $this->booking($actor, 4);
                PaymentAuditTrail::create([
                    'id' => (string) Str::uuid(),
                    'lead_followup_id' => $lead->leadFollowups()->first()->id,
                    'payment_status' => $approval,
                    'paid_amount' => $amount,
                    'paid_date' => now(),
                    'payment_method' => 'Bank Transfer',
                    'created_by' => $actor->id
                ]);
                $this->actingAs($actor);
                $view = $this->get(route('admin.leads.follow-up.create', $lead))->assertOk();
                $this->assertSame($approval === 1 ? [3, 4, 10, 11] : [1, 2, 3, 4, 10, 11], $view->viewData('allowedStatusOptions'));
                foreach ([1, 2] as $status)
                    $this->postJson(route('admin.leads.follow-up.store', $lead), $this->payload($status))
                        ->assertStatus($approval === 1 ? 422 : 200);
            }
        }
        $owner = $this->actor(UserType::OWNER);
        foreach ([10000, 32000] as $amount) {
            $lead = $this->booking($owner, 4);
            $payment = PaymentAuditTrail::create([
                'id' => (string) Str::uuid(),
                'lead_followup_id' => $lead->leadFollowups()->first()->id,
                'payment_status' => 1,
                'paid_amount' => $amount,
                'paid_date' => now(),
                'payment_method' => 'Bank Transfer',
                'created_by' => $owner->id
            ]);
            $before = $payment->fresh()->getAttributes();
            $this->actingAs($owner)->postJson('/admin/leads/' . $lead->id . '/status', [
                'status' => 10,
                'notes' => 'Owner approved booking follow-up',
                'next_followup_date' => now()->addDay()->format('Y-m-d H:i')
            ])->assertOk();
            $this->assertSame($before, $payment->fresh()->getAttributes());
        }
    }

    public function test_manager_team_scope_and_status_export_match_exact_filtered_leads(): void
    {
        $manager = $this->actor(UserType::SALES_MANAGER);
        $sales = $this->actor(UserType::SALES_EXECUTIVE);
        \App\Models\SalesExecutiveAssignment::create(['manager_id' => $manager->id, 'sales_executive_id' => $sales->id, 'status' => 1]);
        $own = $this->booking($manager, 10);
        $team = $this->booking($sales, 10);
        $foreign = $this->booking($this->actor(UserType::SALES_EXECUTIVE), 10);
        $this->actingAs($manager);
        $url = route('admin.upcoming-follow-up.index', ['status' => 10, 'representative_user_id' => $sales->id, 'from_date' => now()->toDateString()]);
        $response = $this->get($url)->assertOk();
        $this->assertSame([$team->id], $response->viewData('arrFollowUps')->pluck('lead_id')->all());
        $response->assertSee('name="status"', false)->assertSee('value="10" selected', false);
        $denied = $this->get(route('admin.upcoming-follow-up.index', ['status' => 10, 'representative_user_id' => $foreign->representative_user_id]))->assertOk();
        $this->assertCount(0, $denied->viewData('arrFollowUps'));
        $this->postJson(route('admin.leads.follow-up.store', $foreign), $this->payload(10))->assertForbidden();
        foreach ([$own, $team, $foreign] as $lead) {
            $client = Client::forceCreate(['id' => (string) Str::uuid(), 'name' => 'QA Export ' . $lead->id, 'status' => 1]);
            $lead->update(['client_id' => $client->id]);
        }
        $admin = $this->actor(UserType::SUPER_ADMIN);
        $filters = ['status' => 10, 'representative_user_id' => $sales->id];
        $this->actingAs($admin);
        $this->assertSame([$team->id], $this->get(route('admin.clients.index', $filters))->assertOk()->viewData('leads')->pluck('id')->all());
        $csv = $this->get(route('admin.leads.export', $filters + ['format' => 'csv']))->assertOk()->streamedContent();
        $rows = array_values(array_filter(array_map('str_getcsv', preg_split('/\r\n|\n|\r/', trim($csv)))));
        $this->assertCount(2, $rows);
        $this->assertSame('QA Export ' . $team->id, $rows[1][1]);
        $xlsx = $this->get(route('admin.leads.export', $filters))->assertOk();
        $bytes = $xlsx->streamedContent();
        $temp = tempnam(getenv('CRM_WAVE1_QA_ROOT'), 'status-export-');
        try {
            file_put_contents($temp, $bytes);
            $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($temp);
            $decoded = $book->getActiveSheet()->toArray();
            $this->assertCount(2, $decoded);
            $this->assertSame('QA Export ' . $team->id, $decoded[1][1]);
            $book->disconnectWorksheets();
        } finally {
            unlink($temp);
        }
    }

    public function test_status_only_actions_preserve_persisted_financial_and_historical_snapshots(): void
    {
        $actor = $this->actor(UserType::OPERATIONS_MANAGER);
        $lead = $this->booking($actor, 4);
        $initial = $lead->leadFollowups()->first();
        $tables = ['payment_audit_trail', 'vouchers', 'invoices', 'lead_vendor_payments', 'vendor_payments', 'lead_refunds', 'vendor_refunds'];
        $read = fn($table) => DB::table($table)->orderBy('id')->get()->map(fn($row) => (array) $row)->all();
        $before = [];
        foreach ($tables as $table)
            $before[$table] = $read($table);
        $this->assertNotEmpty($before['vouchers']);
        $this->assertNotEmpty($before['invoices']);
        $this->assertNotEmpty($before['lead_vendor_payments']);
        $history = $initial->fresh()->getAttributes();
        foreach ([10, 11] as $status) {
            $this->actingAs($actor)->postJson(route('admin.leads.follow-up.store', $lead), $this->payload($status))->assertOk();
        }
        foreach ($tables as $table)
            $this->assertSame($before[$table], $read($table), $table . ' changed during status-only actions');
        $this->assertSame($history, $initial->fresh()->getAttributes());
        $this->assertSame(4, app(\App\Services\LeadStatusTransitionService::class)->displayedBusinessStatus($lead));
    }
}
