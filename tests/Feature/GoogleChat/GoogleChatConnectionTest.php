<?php

namespace Tests\Feature\GoogleChat;

use App\Models\{Lead, SalesExecutiveAssignment, User, UserType, GoogleChatIdentity, LeadChatMessage};
use App\Services\GoogleChat\{GoogleChatClient, LeadGoogleChatConnectionService};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GoogleChatConnectionTest extends GoogleChatTestCase
{
    private function fixture(): array
    {
        $this->harden();
        \Illuminate\Support\Facades\Queue::fake();
        config(['services.google_chat.enabled' => true, 'services.google_chat.allowed_spaces' => ['spaces/one'],
            'services.google_chat.integration_user' => 'users/integration']);
        // User::id is not mass assignable, so set it with forceFill.
        $actor = (new User())->forceFill(['id' => (string) Str::uuid(), 'name' => 'Sales']);
        $actor->setRelation('userType', new UserType(['user_type' => UserType::SALES_EXECUTIVE]));
        $ops = (new User())->forceFill(['id' => (string) Str::uuid(), 'name' => 'Ops', 'status' => 1]);
        $ops->setRelation('userType', new UserType(['user_type' => UserType::OPERATIONS_ROLES[0]]));
        GoogleChatIdentity::create(['google_user_name' => 'users/ops', 'user_id' => $ops->id, 'verified_at' => now()]);
        $lead = new Lead(['id' => (string) Str::uuid(), 'representative_user_id' => $actor->id]);
        DB::table('leads')->insert(['id' => $lead->id, 'representative_user_id' => $actor->id]);
        $lead->setRelation('client', null)->setRelation('leadFollowups', collect())->setRelation('rideSegments', collect());
        $lead->number_of_passengers = 3;
        $client = $this->mock(GoogleChatClient::class);
        $client->shouldReceive('enabled')->andReturn(true);
        $client->shouldReceive('listSpaceMembers')->andReturn([
            ['state' => 'JOINED', 'member' => ['name' => 'users/ops', 'type' => 'HUMAN']],
            ['state' => 'JOINED', 'member' => ['name' => 'users/integration', 'type' => 'HUMAN']],
        ]);
        return [$lead, $actor, $ops];
    }

    public function test_existing_mapping_is_reused_and_owner_unchanged(): void
    {
        [$lead, $actor, $ops] = $this->fixture();
        $service = app(LeadGoogleChatConnectionService::class);
        $first = $service->connect($lead, $actor, $ops, 'spaces/one');
        $second = $service->connect($lead, $actor, $ops, 'spaces/one');
        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->google_template_message_id, $second->google_template_message_id);
        $this->assertSame(1, LeadChatMessage::count());
        $this->assertStringContainsString('3 People', LeadChatMessage::first()->body);
        $this->assertStringContainsString('Product: N/A', LeadChatMessage::first()->body);
        $this->assertStringNotContainsString('Service:', LeadChatMessage::first()->body);
        $this->assertSame($actor->id, DB::table('leads')->value('representative_user_id'));
    }

    public function test_sales_manager_can_connect_google_chat_for_assigned_executive_lead(): void
    {
        [$lead, $salesExecutive, $ops] = $this->fixture();
        $manager = (new User())->forceFill([
            'id' => (string) Str::uuid(),
            'name' => 'Manager',
            'status' => 1,
        ]);
        $manager->setRelation('userType', new UserType(['user_type' => UserType::SALES_MANAGER]));
        SalesExecutiveAssignment::create([
            'manager_id' => $manager->id,
            'sales_executive_id' => $salesExecutive->id,
            'status' => 1,
        ]);

        $conversation = app(LeadGoogleChatConnectionService::class)
            ->connect($lead, $manager, $ops, 'spaces/one');

        $this->assertSame($lead->id, $conversation->lead_id);
        $this->assertSame(1, LeadChatMessage::count());
        $this->assertSame($manager->id, LeadChatMessage::first()->sender_user_id);
    }

    public function test_connection_uses_all_crm_products_and_keeps_full_auto_message(): void
    {
        [$lead, $actor, $ops] = $this->fixture();
        \Illuminate\Support\Facades\Schema::create('products', function ($table) {
            $table->string('id'); $table->string('product');
        });
        DB::table('products')->insert([['id'=>'p1','product'=>'Yacht Goa'],['id'=>'p2','product'=>'Plane Ride']]);
        $lead->product_ids = json_encode(['p1','p2']);
        app(LeadGoogleChatConnectionService::class)->connect($lead, $actor, $ops, 'spaces/one');
        $message = LeadChatMessage::with('task')->firstOrFail();
        $this->assertStringContainsString('Product: Yacht Goa, Plane Ride', $message->body);
        $this->assertStringNotContainsString('Service:', $message->body);
        $this->assertStringContainsString('Yacht Goa, Plane Ride', $message->task->title);
        $this->assertSame($actor->id, $message->sender_user_id);
    }

    public function test_cross_lead_access_is_denied(): void
    {
        [$lead, $actor, $ops] = $this->fixture();
        $lead->representative_user_id = (string) Str::uuid();
        $this->expectException(HttpException::class);
        app(LeadGoogleChatConnectionService::class)->connect($lead, $actor, $ops, 'spaces/one');
    }

    public function test_each_travel_row_has_its_own_date_time_and_route(): void
    {
        [$lead, $actor, $ops] = $this->fixture();
        $lead->setRelation('rideSegments', collect([
            new \App\Models\LeadRide(['from_date'=>'2026-10-26 16:00:00','to_date'=>'2026-10-26 16:00:00','from_place'=>'Bagdogra','to_place'=>'Gangtok']),
            new \App\Models\LeadRide(['from_date'=>'2026-10-28 12:00:00','to_date'=>'2026-10-28 13:00:00','from_place'=>'Gangtok','to_place'=>'Bagdogra']),
            new \App\Models\LeadRide(['from_place'=>'Mumbai','to_place'=>'Mumbai']),
        ]));
        app(LeadGoogleChatConnectionService::class)->connect($lead, $actor, $ops, 'spaces/one');
        $body = LeadChatMessage::firstOrFail()->body;
        $this->assertStringContainsString("Travel 1:\n26 Oct 2026, 04:00 PM\nBagdogra -> Gangtok", $body);
        $this->assertStringContainsString("Travel 2:\n28 Oct 2026, 12:00 PM to 28 Oct 2026, 01:00 PM\nGangtok -> Bagdogra", $body);
        $this->assertStringContainsString("Travel 3:\nDate not set\nMumbai -> Mumbai", $body);
        $this->assertStringNotContainsString('2026-10-26 16:00:00 to 2026-10-28', $body);
    }

    public function test_nonallowlisted_space_is_rejected(): void
    {
        [$lead, $actor, $ops] = $this->fixture();
        $this->expectException(HttpException::class);
        app(LeadGoogleChatConnectionService::class)->connect($lead, $actor, $ops, 'spaces/other');
    }

    public function test_options_show_labels_but_keep_allowlisted_resource_ids(): void
    {
        [$lead, $actor] = $this->fixture();
        config(['services.google_chat.space_labels' => [
            'spaces/one' => 'Accretion Aviation Enquiry', 'spaces/other' => 'Not approved',
        ]]);
        $request = \Illuminate\Http\Request::create('/');
        $request->setUserResolver(fn () => $actor);
        $response = app(\App\Http\Controllers\LeadGoogleChatConnectionController::class)
            ->options($request, $lead, app(\App\Services\LeadChat\LeadChatAccessService::class));
        $data = $response->getData(true);
        $this->assertSame(['spaces/one'], $data['spaces']);
        $this->assertSame([['id' => 'spaces/one', 'name' => 'Accretion Aviation Enquiry']], $data['space_options']);
    }
}
