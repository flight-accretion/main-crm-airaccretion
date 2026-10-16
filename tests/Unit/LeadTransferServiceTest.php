<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\LeadTransfer;
use App\Models\User;
use App\Models\UserType;
use App\Services\LeadTransferService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use App\Models\LeadAllocationQueue;
use App\Models\LeadAuditTrail;
use App\Models\LeadAllocationLog;

class LeadTransferServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 8, 31, 13, 30, 0));

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_sales_roles_can_claim_unassigned_leads_with_audit_and_followup(): void
    {
        foreach (UserType::SALES_ROLES as $role) {
            $actor = $this->createUser($role, $role);
            $lead = $this->createLead();
            $result = app(LeadTransferService::class)->claimUnassigned($lead, $actor);
            $this->assertTrue($result['changed']);
            $this->assertSame($actor->id, $lead->fresh()->representative_user_id);
            $this->assertDatabaseHas('lead_audit_trail', ['lead_id'=>$lead->id,'new_value'=>$actor->id,'changed_by'=>$actor->id]);
            $this->assertDatabaseHas('lead_followups', ['lead_id'=>$lead->id,'followed_by'=>$actor->id,'status'=>1]);
        }
    }

    public function test_claim_rechecks_current_owner_and_repeated_claims_do_not_write_notes(): void
    {
        $actor = $this->createUser('Claimant', UserType::SALES_EXECUTIVE);
        $other = $this->createUser('New owner', UserType::SALES_EXECUTIVE);
        $stale = $this->createLead();
        Lead::whereKey($stale->id)->update(['representative_user_id'=>$other->id]);
        try { app(LeadTransferService::class)->claimUnassigned($stale,$actor); $this->fail('Stale claim should fail.'); }
        catch (ValidationException $error) { $this->assertStringContainsString('already been assigned',$error->errors()['transfer'][0]); }
        $this->assertSame($other->id,$stale->fresh()->representative_user_id);
        $this->assertSame(0,LeadFollowup::count());
        $lead=$this->createLead();app(LeadTransferService::class)->claimUnassigned($lead,$actor);
        try { app(LeadTransferService::class)->claimUnassigned($lead,$actor); $this->fail('Duplicate claim should fail.'); }
        catch (ValidationException $error) { $this->assertStringContainsString('already been assigned',$error->errors()['transfer'][0]); }
        $this->assertSame(1,LeadFollowup::count());
    }

    public function test_claim_blocks_inactive_non_sales_and_pending_transfer(): void
    {
        $lead=$this->createLead();
        foreach ([UserType::HR,UserType::ADMIN,UserType::SUPER_ADMIN,UserType::OPERATIONS_EXECUTIVE] as $role) {
            $actor=$this->createUser($role,$role);
            try { app(LeadTransferService::class)->claimUnassigned($lead,$actor);$this->fail('Non-sales claim should fail.'); }
            catch(ValidationException $error){$this->assertStringContainsString('active Sales',$error->errors()['transfer'][0]);}
        }
        $actor=$this->createUser('Inactive sales',UserType::SALES_EXECUTIVE);$actor->update(['status'=>0]);
        try{app(LeadTransferService::class)->claimUnassigned($lead,$actor);$this->fail('Inactive claim should fail.');}
        catch(ValidationException $error){$this->assertStringContainsString('active Sales',$error->errors()['transfer'][0]);}
        $actor->update(['status'=>1]);
        $pending=LeadTransfer::create(['lead_id'=>$lead->id,'from_user_id'=>$actor->id,'to_user_id'=>$actor->id,'requested_by'=>$actor->id,'status'=>'pending']);
        try{app(LeadTransferService::class)->claimUnassigned($lead,$actor);$this->fail('Pending transfer claim should fail.');}
        catch(ValidationException $error){$this->assertStringContainsString('pending transfer',$error->errors()['transfer'][0]);}
        $this->assertSame(null,$lead->fresh()->representative_user_id);
        $this->assertSame('pending',$pending->fresh()->status);$this->assertSame(0,LeadFollowup::count());
    }

    public function test_claim_endpoint_ignores_posted_recipient_and_finalizes_queue_source(): void
    {
        $actor=$this->createUser('Claimant',UserType::SALES_EXECUTIVE);
        $other=$this->createUser('Forged recipient',UserType::SALES_EXECUTIVE);
        $lead=$this->createLead();
        $queue=LeadAllocationQueue::create(['lead_id'=>$lead->id,'status'=>'queued','reason'=>'whatsapp_new_lead','attempt_count'=>1,'queued_at'=>now()]);
        $allocation=new class extends \App\Services\LeadAllocationService {
            public array $calls=[];
            public function __construct() {}
            public function finalizeManualQueuedAssignment(Lead $lead,User $salesperson,?string $queueReason=null):void {$this->calls[]=[$lead->id,$salesperson->id,$queueReason];}
        };
        $request=\Illuminate\Http\Request::create('/claim','POST',['representative_user_id'=>$other->id]);
        $request->setLaravelSession(app('session.store'));app()->instance('request',$request);$request->setUserResolver(fn()=>$actor);
        $response=app(\App\Http\Controllers\LeadTransferController::class)->claim($request,$lead,app(LeadTransferService::class),$allocation);
        $this->assertSame(302,$response->getStatusCode());
        $this->assertSame($actor->id,$lead->fresh()->representative_user_id);
        $this->assertSame('assigned',$queue->fresh()->status);$this->assertSame($actor->id,$queue->fresh()->assigned_to);
        $this->assertSame([[$lead->id,$actor->id,'whatsapp_new_lead']],$allocation->calls);
        $this->assertDatabaseHas('lead_allocation_logs',['lead_id'=>$lead->id,'salesperson_id'=>$actor->id,'result'=>'success']);
    }

    public function test_claim_route_and_controller_only_allow_sales_roles(): void
    {
        $route=app('router')->getRoutes()->getByName('admin.leads.transfer.claim');
        $lead=$this->createLead();
        foreach(array_merge(UserType::SALES_ROLES,[UserType::HR,UserType::SUPER_ADMIN,UserType::ADMIN,UserType::OPERATIONS_EXECUTIVE]) as $role){
            $actor=$this->createUser($role,$role);
            $request=\Illuminate\Http\Request::create('/claim','POST');$request->setUserResolver(fn()=>$actor);
            $allowed=true;
            foreach($route->gatherMiddleware() as $middleware){
                if(!str_starts_with($middleware,'role:'))continue;
                try{(new \App\Http\Middleware\RoleMiddleware())->handle($request,fn()=>response('ok'),...explode(',',substr($middleware,5)));}
                catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error){$this->assertSame(403,$error->getStatusCode());$allowed=false;}
            }
            $this->assertSame(in_array($role,UserType::SALES_ROLES,true),$allowed);
            if(!$allowed){
                try{app(\App\Http\Controllers\LeadTransferController::class)->claim($request,$lead,app(LeadTransferService::class),app(\App\Services\LeadAllocationService::class));$this->fail('Controller should deny non-sales.');}
                catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error){$this->assertSame(403,$error->getStatusCode());}
            }
        }
        $this->assertSame(null,$lead->fresh()->representative_user_id);
    }

    public function test_claim_rolls_back_ownership_queue_and_audit_if_followup_cannot_be_saved(): void
    {
        $actor=$this->createUser('Claimant',UserType::SALES_EXECUTIVE);$lead=$this->createLead();
        $queue=LeadAllocationQueue::create(['lead_id'=>$lead->id,'status'=>'queued','reason'=>'whatsapp_new_lead','attempt_count'=>1,'queued_at'=>now()]);
        DB::unprepared("CREATE TRIGGER reject_claim_followup BEFORE INSERT ON lead_followups BEGIN SELECT RAISE(ABORT, 'test failure'); END");
        try{app(LeadTransferService::class)->claimUnassigned($lead,$actor);$this->fail('Claim should roll back.');}
        catch(\Illuminate\Database\QueryException $error){$this->assertStringContainsString('test failure',$error->getMessage());}
        $this->assertSame(null,$lead->fresh()->representative_user_id);$this->assertSame('queued',$queue->fresh()->status);
        $this->assertSame(0,LeadAuditTrail::count());$this->assertSame(0,LeadFollowup::count());$this->assertSame(0,LeadAllocationLog::count());
    }

    public function test_stale_accept_cancels_pending_request_before_returning_error(): void
    {
        $oldOwner = $this->createUser('Pallavi Singh', UserType::SALES_MANAGER);
        $currentOwner = $this->createUser('Sourav Namdeo', UserType::SALES_EXECUTIVE);
        $requester = $this->createUser('Samarpit Sharma', UserType::SALES_EXECUTIVE);
        $superAdmin = $this->createUser('Super Admin User', UserType::SUPER_ADMIN);
        $lead = $this->createLead($currentOwner);

        $transfer = LeadTransfer::create([
            'lead_id' => $lead->id,
            'from_user_id' => $oldOwner->id,
            'to_user_id' => $requester->id,
            'requested_by' => $requester->id,
            'status' => 'pending',
            'reason' => 'Lead access requested.',
        ]);

        try {
            app(LeadTransferService::class)->accept($transfer, $superAdmin);
            $this->fail('Expected stale transfer approval to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Lead ownership has already changed. This request is no longer valid.',
                $exception->errors()['transfer'][0]
            );
        }

        $this->assertDatabaseMissing('lead_transfers', [
            'id' => $transfer->id,
        ]);

        $this->assertTransferFollowupExists(
            $lead,
            $transfer,
            'Lead Transfer Cancelled',
            'Response Note: Transfer automatically cancelled because lead ownership changed before approval.'
        );
        $this->assertSame($currentOwner->id, $lead->fresh()->representative_user_id);
    }

    public function test_stale_reject_cancels_pending_request_before_returning_error(): void
    {
        $oldOwner = $this->createUser('Pallavi Singh', UserType::SALES_MANAGER);
        $currentOwner = $this->createUser('Sourav Namdeo', UserType::SALES_EXECUTIVE);
        $requester = $this->createUser('Samarpit Sharma', UserType::SALES_EXECUTIVE);
        $superAdmin = $this->createUser('Super Admin User', UserType::SUPER_ADMIN);
        $lead = $this->createLead($currentOwner);

        $transfer = LeadTransfer::create([
            'lead_id' => $lead->id,
            'from_user_id' => $oldOwner->id,
            'to_user_id' => $requester->id,
            'requested_by' => $requester->id,
            'status' => 'pending',
            'reason' => 'Lead access requested.',
        ]);

        try {
            app(LeadTransferService::class)->reject($transfer, $superAdmin, 'Not approved.');
            $this->fail('Expected stale transfer rejection to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Lead ownership has already changed. This request is no longer valid.',
                $exception->errors()['transfer'][0]
            );
        }

        $this->assertDatabaseMissing('lead_transfers', [
            'id' => $transfer->id,
        ]);

        $this->assertTransferFollowupExists(
            $lead,
            $transfer,
            'Lead Transfer Cancelled',
            'Response Note: Transfer automatically cancelled because lead ownership changed before rejection.'
        );
        $this->assertSame($currentOwner->id, $lead->fresh()->representative_user_id);
    }

    public function test_pull_request_still_requires_owner_approval_and_records_followup(): void
    {
        $owner = $this->createUser('Pallavi Singh', UserType::SALES_MANAGER);
        $requester = $this->createUser('Samarpit Sharma', UserType::SALES_EXECUTIVE);
        $lead = $this->createLead($owner);

        $service = app(LeadTransferService::class);

        $transfer = $service->requestForSelf(
            $lead,
            $requester,
            'Need this lead for customer follow-up.'
        );

        try {
            $service->accept($transfer, $requester);
            $this->fail('Expected requester to be blocked from approving their own pull request.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'Only the current lead owner or Super Admin can approve this transfer.',
                $exception->errors()['transfer'][0]
            );
        }

        $service->accept($transfer, $owner);

        $this->assertSame(
            $requester->id,
            $lead->fresh()->representative_user_id
        );

        $followup = LeadFollowup::query()
            ->where('lead_id', $lead->id)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($followup);
        $this->assertSame($requester->id, $followup->followed_by);
        $this->assertSame(1, (int) $followup->status);
        $this->assertSame(
            now()->format('Y-m-d H:i:s'),
            $followup->next_followup_date->format('Y-m-d H:i:s')
        );
        $this->assertStringContainsString(
            '[LEAD TRANSFER:' . $transfer->id . ']',
            $followup->followup_note
        );
        $this->assertStringContainsString(
            'Lead Transfer Accepted',
            $followup->followup_note
        );
        $this->assertStringContainsString(
            'Reason: Need this lead for customer follow-up.',
            $followup->followup_note
        );

        $this->assertDatabaseMissing('lead_transfers', [
            'id' => $transfer->id,
        ]);
    }

    public function test_owner_can_offer_own_lead_to_another_sales_user_for_recipient_approval(): void
    {
        $owner = $this->createUser('Pallavi Singh', UserType::SALES_MANAGER);
        $recipient = $this->createUser('Samarpit Sharma', UserType::SALES_EXECUTIVE);
        $lead = $this->createLead($owner);

        $service = app(LeadTransferService::class);

        $transfer = $service->requestFromOwner(
            $lead,
            $recipient,
            $owner,
            'Please handle this lead from today.'
        );

        $this->assertSame('pending', $transfer->status);
        $this->assertSame($owner->id, $transfer->from_user_id);
        $this->assertSame($recipient->id, $transfer->to_user_id);
        $this->assertSame($owner->id, $transfer->requested_by);
        $this->assertSame('Please handle this lead from today.', $transfer->reason);

        try {
            $service->accept($transfer, $owner);
            $this->fail('Expected owner to be blocked from approving their own offered transfer.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'Only the requested recipient or Super Admin can approve this transfer.',
                $exception->errors()['transfer'][0]
            );
        }

        $service->accept($transfer, $recipient);

        $this->assertSame(
            $recipient->id,
            $lead->fresh()->representative_user_id
        );

        $followup = LeadFollowup::query()
            ->where('lead_id', $lead->id)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($followup);
        $this->assertSame($recipient->id, $followup->followed_by);
        $this->assertStringContainsString(
            '[LEAD TRANSFER:' . $transfer->id . ']',
            $followup->followup_note
        );
        $this->assertStringContainsString(
            'Lead Transfer Accepted',
            $followup->followup_note
        );
        $this->assertStringContainsString(
            'Transferred From: Pallavi Singh',
            $followup->followup_note
        );
        $this->assertStringContainsString(
            'Reason: Please handle this lead from today.',
            $followup->followup_note
        );

        $this->assertDatabaseMissing('lead_transfers', [
            'id' => $transfer->id,
        ]);
    }

    public function test_pending_action_count_only_counts_requests_waiting_on_that_user(): void
    {
        $owner = $this->createUser('Pallavi Singh', UserType::SALES_MANAGER);
        $pullRequester = $this->createUser('Samarpit Sharma', UserType::SALES_EXECUTIVE);
        $offerRecipient = $this->createUser('Sourav Namdeo', UserType::SALES_EXECUTIVE);
        $superAdmin = $this->createUser('Super Admin User', UserType::SUPER_ADMIN);

        $pullLead = $this->createLead($owner);
        $offerLead = $this->createLead($owner);

        LeadTransfer::create([
            'lead_id' => $pullLead->id,
            'from_user_id' => $owner->id,
            'to_user_id' => $pullRequester->id,
            'requested_by' => $pullRequester->id,
            'status' => 'pending',
            'reason' => 'Please assign this lead to me.',
        ]);

        LeadTransfer::create([
            'lead_id' => $offerLead->id,
            'from_user_id' => $owner->id,
            'to_user_id' => $offerRecipient->id,
            'requested_by' => $owner->id,
            'status' => 'pending',
            'reason' => 'Please take this lead.',
        ]);

        LeadTransfer::create([
            'lead_id' => $this->createLead($owner)->id,
            'from_user_id' => $owner->id,
            'to_user_id' => $offerRecipient->id,
            'requested_by' => $owner->id,
            'status' => 'accepted',
            'reason' => 'Already handled.',
        ]);

        $service = app(LeadTransferService::class);

        $this->assertSame(1, $service->pendingActionCountFor($owner));
        $this->assertSame(0, $service->pendingActionCountFor($pullRequester));
        $this->assertSame(1, $service->pendingActionCountFor($offerRecipient));
        $this->assertSame(2, $service->pendingActionCountFor($superAdmin));
    }

    public function test_bulk_owner_offer_route_creates_pending_transfer_request(): void
    {
        $this->withoutMiddleware(
            \App\Http\Middleware\VerifyCsrfToken::class
        );

        $owner = $this->createUser('Pallavi Singh', UserType::SALES_MANAGER);
        $recipient = $this->createUser('Samarpit Sharma', UserType::SALES_EXECUTIVE);
        $lead = $this->createLead($owner);

        $this
            ->actingAs($owner)
            ->post(
                route('admin.leads.transfer.offer-bulk'),
                [
                    'lead_ids' => [$lead->id],
                    'representative_user_id' => $recipient->id,
                    'reason' => 'Moving this lead for faster callback.',
                ]
            )
            ->assertRedirect();

        $this->assertDatabaseHas('lead_transfers', [
            'lead_id' => $lead->id,
            'from_user_id' => $owner->id,
            'to_user_id' => $recipient->id,
            'requested_by' => $owner->id,
            'status' => 'pending',
            'reason' => 'Moving this lead for faster callback.',
        ]);
    }

    public function test_pending_count_route_returns_actionable_transfer_count(): void
    {
        $owner = $this->createUser('Pallavi Singh', UserType::SALES_MANAGER);
        $requester = $this->createUser('Samarpit Sharma', UserType::SALES_EXECUTIVE);
        $lead = $this->createLead($owner);

        LeadTransfer::create([
            'lead_id' => $lead->id,
            'from_user_id' => $owner->id,
            'to_user_id' => $requester->id,
            'requested_by' => $requester->id,
            'status' => 'pending',
            'reason' => 'Please assign this lead to me.',
        ]);

        $this
            ->actingAs($owner)
            ->getJson(route('admin.leads.transfers.pending-count'))
            ->assertOk()
            ->assertJson([
                'count' => 1,
            ]);
    }

    private function assertTransferFollowupExists(
        Lead $lead,
        LeadTransfer $transfer,
        string $outcome,
        ?string $extraText = null
    ): void {
        $followup = LeadFollowup::query()
            ->where('lead_id', $lead->id)
            ->where(
                'followup_note',
                'like',
                '%[LEAD TRANSFER:' . $transfer->id . ']%'
            )
            ->first();

        $this->assertNotNull($followup);
        $this->assertStringContainsString(
            $outcome,
            $followup->followup_note
        );

        if ($extraText !== null) {
            $this->assertStringContainsString(
                $extraText,
                $followup->followup_note
            );
        }
    }

    private function createUser(string $name, string $role): User
    {
        $userType = UserType::create([
            'id' => (string) Str::uuid(),
            'user_type' => $role,
            'status' => 1,
        ]);

        return User::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'email' => Str::uuid() . '@example.test',
            'password' => 'secret',
            'user_type_id' => $userType->id,
            'status' => 1,
        ]);
    }

    private function createLead(?User $owner = null): Lead
    {
        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'Transfer Test Customer',
            'contact_number' => '919748162048',
            'status' => 1,
        ]);

        return Lead::create([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' =>
             $owner ? $owner->id : null,
            'description' => 'Transfer test lead',
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('user_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_type');
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->uuid('user_type_id')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('alternate_number')->nullable();
            $table->integer('status')->default(1);
            $table->uuid('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('representative_user_id')->nullable();
            $table->json('service_ids')->nullable();
            $table->json('product_ids')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->uuid('from_user_id');
            $table->uuid('to_user_id');
            $table->uuid('requested_by');
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->text('response_note')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->uuid('responded_by')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_followups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->timestamp('next_followup_date')->nullable();
            $table->text('followup_note')->nullable();
            $table->integer('status')->default(0);
            $table->uuid('followed_by')->nullable();
            $table->timestamps();
        });

        Schema::create(
    'lead_audit_trail',
    function (Blueprint $table) {

        $table->uuid('id')->primary();
        $table->uuid('lead_id');
        $table->string('field_name');
        $table->text('old_value')->nullable();
        $table->text('new_value')->nullable();
        $table->uuid('changed_by')->nullable();
        $table->timestamp('created_at')->nullable();
    }
);

Schema::create(
    'lead_allocation_queue',
    function (Blueprint $table) {

        $table->uuid('id')->primary();
        $table->uuid('lead_id');

        $table
            ->uuid('assigned_to')
            ->nullable();

        $table
            ->string('status')
            ->default('queued');

        $table
            ->text('reason')
            ->nullable();

        $table
            ->integer('attempt_count')
            ->default(0);

        $table
            ->timestamp('queued_at')
            ->nullable();

        $table
            ->timestamp('processed_at')
            ->nullable();

        $table->timestamps();
    }
);

Schema::create(
    'lead_allocation_logs',
    function (Blueprint $table) {

        $table->uuid('id')->primary();

        $table
            ->uuid('lead_id')
            ->nullable();

        $table
            ->uuid('salesperson_id')
            ->nullable();

        $table
            ->string('action');

        $table
            ->string('result');

        $table
            ->text('details')
            ->nullable();

        $table->timestamps();
    }
);
    }


    public function test_super_admin_can_directly_reassign_a_lead(): void
{
    $oldOwner = $this->createUser(
        'Sourav Namdeo',
        UserType::SALES_EXECUTIVE
    );

    $newOwner = $this->createUser(
        'Samarpit Sharma',
        UserType::SALES_EXECUTIVE
    );

    $requester = $this->createUser(
        'Pallavi Singh',
        UserType::SALES_MANAGER
    );

    $superAdmin = $this->createUser(
        'Super Admin User',
        UserType::SUPER_ADMIN
    );

    $lead = $this->createLead($oldOwner);

    $pendingTransfer = LeadTransfer::create([
        'lead_id' => $lead->id,
        'from_user_id' => $oldOwner->id,
        'to_user_id' => $requester->id,
        'requested_by' => $requester->id,
        'status' => 'pending',
        'reason' => 'Lead access requested.',
    ]);

    $result = app(
        LeadTransferService::class
    )->directAssign(
        $lead,
        $newOwner,
        $superAdmin
    );

    $this->assertTrue($result['changed']);
    $this->assertFalse($result['was_queued']);

    $this->assertSame(
        $newOwner->id,
        $lead->fresh()->representative_user_id
    );

    $this->assertDatabaseMissing('lead_transfers', [
        'id' => $pendingTransfer->id,
    ]);

    $this->assertTransferFollowupExists(
        $lead,
        $pendingTransfer,
        'Lead Transfer Cancelled',
        'Response Note: Cancelled because Super Admin directly reassigned the lead.'
    );

    $audit = LeadAuditTrail::where(
        'lead_id',
        $lead->id
    )->first();

    $this->assertNotNull($audit);

    $this->assertSame(
        $oldOwner->id,
        $audit->old_value
    );

    $this->assertSame(
        $newOwner->id,
        $audit->new_value
    );

$this->assertSame(
    $superAdmin->id,
    $audit->changed_by
);
}

public function test_super_admin_direct_assignment_can_record_current_followup_note(): void
{
    $oldOwner = $this->createUser(
        'Sourav Namdeo',
        UserType::SALES_EXECUTIVE
    );

    $newOwner = $this->createUser(
        'Samarpit Sharma',
        UserType::SALES_EXECUTIVE
    );

    $superAdmin = $this->createUser(
        'Super Admin User',
        UserType::SUPER_ADMIN
    );

    $lead = $this->createLead($oldOwner);

    $result = app(
        LeadTransferService::class
    )->directAssign(
        $lead,
        $newOwner,
        $superAdmin
    );

    $this->assertTrue($result['changed']);

    app(
        LeadTransferService::class
    )->recordDirectAssignmentFollowup(
        $lead->fresh(),
        $newOwner,
        $superAdmin,
        $oldOwner->id
    );

    $followup = LeadFollowup::query()
        ->where('lead_id', $lead->id)
        ->latest('created_at')
        ->first();

    $this->assertNotNull($followup);
    $this->assertSame(
        $newOwner->id,
        $followup->followed_by
    );
    $this->assertSame(1, (int) $followup->status);
    $this->assertSame(
        now()->format('Y-m-d H:i:s'),
        $followup->next_followup_date->format('Y-m-d H:i:s')
    );
    $this->assertStringContainsString(
        'Lead transferred by Super Admin.',
        $followup->followup_note
    );
    $this->assertStringContainsString(
        'From: Sourav Namdeo',
        $followup->followup_note
    );
    $this->assertStringContainsString(
        'To: Samarpit Sharma',
        $followup->followup_note
    );
    $this->assertStringContainsString(
        'Transferred at: 31-Aug-2026 01:30 PM IST',
        $followup->followup_note
    );
}

public function test_super_admin_direct_assignment_route_records_transfer_followup(): void
{
    $this->withoutMiddleware(
        \App\Http\Middleware\VerifyCsrfToken::class
    );

    $oldOwner = $this->createUser(
        'Sourav Namdeo',
        UserType::SALES_EXECUTIVE
    );

    $newOwner = $this->createUser(
        'Samarpit Sharma',
        UserType::SALES_EXECUTIVE
    );

    $superAdmin = $this->createUser(
        'Super Admin User',
        UserType::SUPER_ADMIN
    );

    $lead = $this->createLead($oldOwner);

    $this
        ->actingAs($superAdmin)
        ->post(
            route('admin.leads.transfer.direct-assign'),
            [
                'lead_ids' => [$lead->id],
                'representative_user_id' => $newOwner->id,
            ]
        )
        ->assertRedirect();

    $this->assertSame(
        $newOwner->id,
        $lead->fresh()->representative_user_id
    );

    $followup = LeadFollowup::query()
        ->where('lead_id', $lead->id)
        ->latest('created_at')
        ->first();

    $this->assertNotNull($followup);
    $this->assertSame(
        $newOwner->id,
        $followup->followed_by
    );
    $this->assertStringContainsString(
        'Lead transferred by Super Admin.',
        $followup->followup_note
    );
}

public function test_super_admin_direct_assignment_closes_active_queue(): void
{
    $newOwner = $this->createUser(
        'Sourav Namdeo',
        UserType::SALES_EXECUTIVE
    );

    $superAdmin = $this->createUser(
        'Super Admin User',
        UserType::SUPER_ADMIN
    );

    $lead = $this->createLead(null);

    $queue = LeadAllocationQueue::create([
        'lead_id' => $lead->id,
        'status' => 'queued',
        'reason' => 'whatsapp_new_lead',
        'attempt_count' => 3,
        'queued_at' => now(),
    ]);

    $result = app(
        LeadTransferService::class
    )->directAssign(
        $lead,
        $newOwner,
        $superAdmin
    );

    $this->assertTrue($result['changed']);
    $this->assertTrue($result['was_queued']);

    $this->assertSame(
        'whatsapp_new_lead',
        $result['queue_reason']
    );

    $queue->refresh();

    $this->assertSame(
        'assigned',
        $queue->status
    );

    $this->assertSame(
        $newOwner->id,
        $queue->assigned_to
    );

    $this->assertNotNull(
        $queue->processed_at
    );

    $this->assertSame(
        $newOwner->id,
        $lead->fresh()->representative_user_id
    );

    $allocationLog =
    LeadAllocationLog::where(
        'lead_id',
        $lead->id
    )
    ->where(
        'salesperson_id',
        $newOwner->id
    )
    ->first();

$this->assertNotNull(
    $allocationLog
);

$this->assertSame(
    'assigned',
    $allocationLog->action
);

$this->assertSame(
    'success',
    $allocationLog->result
);

$this->assertSame(
    'Assigned manually by Super Admin from lead queue',
    $allocationLog->details
);
}

public function test_non_super_admin_cannot_directly_assign_leads(): void
{
    $oldOwner = $this->createUser(
        'Sourav Namdeo',
        UserType::SALES_EXECUTIVE
    );

    $newOwner = $this->createUser(
        'Samarpit Sharma',
        UserType::SALES_EXECUTIVE
    );

    $salesManager = $this->createUser(
        'Sales Manager',
        UserType::SALES_MANAGER
    );

    $lead = $this->createLead($oldOwner);

    $this->expectException(
        ValidationException::class
    );

    app(
        LeadTransferService::class
    )->directAssign(
        $lead,
        $newOwner,
        $salesManager
    );
}

public function test_direct_assignment_rejects_non_sales_destination(): void
{
    $oldOwner = $this->createUser(
        'Sourav Namdeo',
        UserType::SALES_EXECUTIVE
    );

    $adminDestination = $this->createUser(
        'Admin User',
        UserType::ADMIN
    );

    $superAdmin = $this->createUser(
        'Super Admin User',
        UserType::SUPER_ADMIN
    );

    $lead = $this->createLead($oldOwner);

    $this->expectException(
        ValidationException::class
    );

    app(
        LeadTransferService::class
    )->directAssign(
        $lead,
        $adminDestination,
        $superAdmin
    );
}
}
