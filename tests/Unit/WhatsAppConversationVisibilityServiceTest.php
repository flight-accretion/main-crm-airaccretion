<?php

namespace Tests\Unit;

use App\Models\SalesExecutiveAssignment;
use App\Models\User;
use App\Models\UserType;
use App\Services\WhatsAppConversationVisibilityService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppConversationVisibilityServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set(
            'database.connections.sqlite',
            [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ]
        );

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    public function test_salesperson_can_only_access_own_conversation(): void
    {
        $owner = $this->createUser(UserType::SALES_EXECUTIVE, 'Owner');
        $other = $this->createUser(UserType::SALES_EXECUTIVE, 'Other');
        $ownConversationId = $this->createConversation($owner);
        $otherConversationId = $this->createConversation($other);

        $service = app(WhatsAppConversationVisibilityService::class);

        $visibleIds = $service->visibleConversationsQuery($owner)
            ->pluck('id')
            ->all();

        $this->assertSame([$ownConversationId], $visibleIds);
        $this->assertTrue($service->canAccessConversation($owner, $ownConversationId));
        $this->assertFalse($service->canAccessConversation($owner, $otherConversationId));
    }

    public function test_manager_can_access_own_and_assigned_team_conversations_only(): void
    {
        $manager = $this->createUser(UserType::SALES_MANAGER, 'Manager');
        $teamMember = $this->createUser(UserType::SALES_EXECUTIVE, 'Team');
        $outside = $this->createUser(UserType::SALES_EXECUTIVE, 'Outside');

        SalesExecutiveAssignment::create([
            'manager_id' => $manager->id,
            'sales_executive_id' => $teamMember->id,
            'status' => 1,
        ]);

        $managerConversationId = $this->createConversation($manager);
        $teamConversationId = $this->createConversation($teamMember);
        $outsideConversationId = $this->createConversation($outside);

        $service = app(WhatsAppConversationVisibilityService::class);

        $visibleIds = $service->visibleConversationsQuery($manager)
            ->orderBy('assigned_user_id')
            ->pluck('id')
            ->all();

        sort($visibleIds);
        $expected = [$managerConversationId, $teamConversationId];
        sort($expected);

        $this->assertSame($expected, $visibleIds);
        $this->assertTrue($service->canAccessConversation($manager, $managerConversationId));
        $this->assertTrue($service->canAccessConversation($manager, $teamConversationId));
        $this->assertFalse($service->canAccessConversation($manager, $outsideConversationId));
    }

    public function test_super_admin_can_access_all_conversations_but_admin_cannot_by_default(): void
    {
        $superAdmin = $this->createUser(UserType::SUPER_ADMIN, 'Super');
        $admin = $this->createUser(UserType::ADMIN, 'Admin');
        $owner = $this->createUser(UserType::SALES_EXECUTIVE, 'Owner');
        $conversationId = $this->createConversation($owner);

        $service = app(WhatsAppConversationVisibilityService::class);

        $this->assertTrue($service->canAccessConversation($superAdmin, $conversationId));
        $this->assertFalse($service->canAccessConversation($admin, $conversationId));
    }

    public function test_only_assigned_salesperson_marking_read_clears_unread(): void
    {
        $manager = $this->createUser(UserType::SALES_MANAGER, 'Manager');
        $owner = $this->createUser(UserType::SALES_EXECUTIVE, 'Owner');

        SalesExecutiveAssignment::create([
            'manager_id' => $manager->id,
            'sales_executive_id' => $owner->id,
            'status' => 1,
        ]);

        $conversationId = $this->createConversation($owner, 2);

        $service = app(WhatsAppConversationVisibilityService::class);

        $this->assertFalse($service->markReadForUser($manager, $conversationId));
        $this->assertDatabaseHas(
            'whatsapp_conversations',
            [
                'id' => $conversationId,
                'unread_count' => 2,
            ]
        );

        $this->assertTrue($service->markReadForUser($owner, $conversationId));
        $this->assertDatabaseHas(
            'whatsapp_conversations',
            [
                'id' => $conversationId,
                'unread_count' => 0,
            ]
        );
    }

    private function createUser(string $role, string $name): User
    {
        $type = UserType::query()
            ->firstOrCreate(
                ['user_type' => $role],
                [
                    'id' => (string) Str::uuid(),
                    'status' => 1,
                ]
            );

        return User::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'email' => Str::uuid() . '@example.test',
            'password' => 'secret',
            'user_type_id' => $type->id,
            'status' => 1,
        ]);
    }

    private function createConversation(
        User $assignedUser,
        int $unreadCount = 0
    ): string {
        $contactId = (string) Str::uuid();
        $conversationId = (string) Str::uuid();

        DB::table('whatsapp_contacts')->insert([
            'id' => $contactId,
            'name' => $assignedUser->name . ' Contact',
            'normalized_phone' => substr(preg_replace('/\D+/', '', $contactId), 0, 10)
                ?: substr(str_replace('-', '', $contactId), 0, 10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('whatsapp_conversations')->insert([
            'id' => $conversationId,
            'contact_id' => $contactId,
            'assigned_user_id' => $assignedUser->id,
            'status' => 'open',
            'last_message_at' => now(),
            'unread_count' => $unreadCount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $conversationId;
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

        Schema::create('sales_executive_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('manager_id');
            $table->uuid('sales_executive_id');
            $table->timestamp('assigned_date')->nullable();
            $table->text('notes')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('normalized_phone', 30)->unique();
            $table->string('raw_phone', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contact_id');
            $table->uuid('lead_id')->nullable();
            $table->uuid('assigned_user_id')->nullable();
            $table->string('whatcrm_chat_id')->nullable();
            $table->string('status', 30)->default('open');
            $table->text('last_message')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id');
            $table->timestamp('crm_read_at')->nullable();
            $table->timestamps();
        });
    }
}
