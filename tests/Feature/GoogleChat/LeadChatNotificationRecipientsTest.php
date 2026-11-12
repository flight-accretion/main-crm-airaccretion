<?php

namespace Tests\Feature\GoogleChat;

use App\Models\Lead;
use App\Models\LeadChatConversation as ChatConversation;
use App\Models\LeadChatMessage as ChatMessage;
use App\Models\LeadChatNotification;
use App\Models\UserType;
use App\Services\LeadChat\LeadChatNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeadChatNotificationRecipientsTest extends GoogleChatTestCase
{
    public function test_google_reply_notifies_assigned_executive_and_active_assigned_managers_only_once(): void
    {
        $roles = [];
        foreach ([UserType::SALES_EXECUTIVE, UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER, 'Admin'] as $role) {
            $roles[$role] = (string) Str::uuid();
            DB::table('user_types')->insert(['id' => $roles[$role], 'user_type' => $role]);
        }

        $makeUser = function (string $name, string $role, int $status = 1) use ($roles): string {
            $id = (string) Str::uuid();
            DB::table('users')->insert([
                'id' => $id,
                'name' => $name,
                'user_type_id' => $roles[$role],
                'status' => $status,
            ]);
            return $id;
        };

        $executive = $makeUser('Executive', UserType::SALES_EXECUTIVE);
        $assignedManager = $makeUser('Assigned Manager', UserType::SALES_MANAGER);
        $inactiveManager = $makeUser('Inactive Manager', UserType::SENIOR_SALES_MANAGER);
        $unassignedManager = $makeUser('Other Manager', UserType::SALES_MANAGER);
        $admin = $makeUser('Admin', 'Admin');

        DB::table('sales_executive_assignments')->insert([
            ['id' => (string) Str::uuid(), 'manager_id' => $assignedManager, 'sales_executive_id' => $executive, 'status' => 1],
            ['id' => (string) Str::uuid(), 'manager_id' => $inactiveManager, 'sales_executive_id' => $executive, 'status' => 0],
        ]);

        $leadId = (string) Str::uuid();
        DB::table('leads')->insert(['id' => $leadId, 'representative_user_id' => $executive]);
        $conversation = ChatConversation::create(['lead_id' => $leadId]);
        $message = ChatMessage::create([
            'lead_id' => $leadId,
            'conversation_id' => $conversation->id,
            'body' => 'Customer replied',
            'source' => 'google_chat',
        ]);

        app(LeadChatNotificationService::class)->notifyGoogleMessage(
            Lead::findOrFail($leadId),
            $conversation,
            $message,
            'Customer'
        );
        app(LeadChatNotificationService::class)->notifyGoogleMessage(
            Lead::findOrFail($leadId),
            $conversation,
            $message,
            'Customer'
        );

        $this->assertEqualsCanonicalizing(
            [$executive, $assignedManager],
            LeadChatNotification::where('message_id', $message->id)->pluck('user_id')->all()
        );
        $this->assertSame(2, LeadChatNotification::where('message_id', $message->id)->count());
        $this->assertNotContains($unassignedManager, LeadChatNotification::pluck('user_id')->all());
        $this->assertNotContains($admin, LeadChatNotification::pluck('user_id')->all());
    }


public function test_manager_owned_lead_receives_google_reply_once(): void
{
    // 1. Create Sales Manager role.
    $roleId = (string) Str::uuid();

    DB::table('user_types')->insert([
        'id' => $roleId,
        'user_type' => UserType::SALES_MANAGER,
    ]);

    // 2. Create an active Sales Manager.
    $managerId = (string) Str::uuid();

    DB::table('users')->insert([
        'id' => $managerId,
        'name' => 'Direct Lead Manager',
        'user_type_id' => $roleId,
        'status' => 1,
    ]);

    // 3. Assign the lead directly to the Sales Manager.
    $leadId = (string) Str::uuid();

    DB::table('leads')->insert([
        'id' => $leadId,
        'representative_user_id' => $managerId,
    ]);

    // 4. Create the lead's chat conversation.
    $conversation = ChatConversation::create([
        'lead_id' => $leadId,
    ]);

    // 5. Create an incoming Google Chat message.
    $message = ChatMessage::create([
        'lead_id' => $leadId,
        'conversation_id' => $conversation->id,
        'body' => 'Customer replied to manager-owned lead',
        'source' => 'google_chat',
    ]);

    $lead = Lead::findOrFail($leadId);

    $notificationService = app(
        LeadChatNotificationService::class
    );

    // 6. Process the same message twice.
    // This verifies idempotency.
    $notificationService->notifyGoogleMessage(
        $lead,
        $conversation,
        $message,
        'Customer'
    );

    $notificationService->notifyGoogleMessage(
        $lead,
        $conversation,
        $message,
        'Customer'
    );

    // 7. Manager must receive exactly one notification.
    $notifications = LeadChatNotification::query()
        ->where('user_id', $managerId)
        ->where('message_id', $message->id)
        ->get();

    $this->assertCount(1, $notifications);

    // 8. Verify the notification belongs to the correct lead.
    $notification = $notifications->first();

    $this->assertSame(
        $leadId,
        (string) $notification->lead_id
    );

    $this->assertSame(
        (string) $conversation->id,
        (string) $notification->conversation_id
    );

    $this->assertSame(
        'new_message',
        $notification->type
    );

    $this->assertSame(
        'Google Chat reply from Customer',
        $notification->title
    );

    $this->assertSame(
        'Customer replied to manager-owned lead',
        $notification->body
    );

    // 9. It should initially be unread.
    $this->assertNull($notification->read_at);

    // 10. No duplicate notifications for this message.
    $this->assertSame(
        1,
        LeadChatNotification::query()
            ->where('message_id', $message->id)
            ->count()
    );
}

}
