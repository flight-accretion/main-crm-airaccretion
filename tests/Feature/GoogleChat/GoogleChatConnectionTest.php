<?php

namespace Tests\Feature\GoogleChat;

use App\Models\{Lead, User, UserType, GoogleChatIdentity, LeadChatMessage};
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
        $actor = new User(['id' => (string) Str::uuid(), 'name' => 'Sales']);
        $actor->setRelation('userType', new UserType(['user_type' => UserType::SALES_EXECUTIVE]));
        $ops = new User(['id' => (string) Str::uuid(), 'name' => 'Ops', 'status' => 1]);
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
        $this->assertStringContainsString('Passengers: 3', LeadChatMessage::first()->body);
        $this->assertSame($actor->id, DB::table('leads')->value('representative_user_id'));
    }

    public function test_cross_lead_access_is_denied(): void
    {
        [$lead, $actor, $ops] = $this->fixture();
        $lead->representative_user_id = (string) Str::uuid();
        $this->expectException(HttpException::class);
        app(LeadGoogleChatConnectionService::class)->connect($lead, $actor, $ops, 'spaces/one');
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
