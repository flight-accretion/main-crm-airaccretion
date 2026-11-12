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
}
