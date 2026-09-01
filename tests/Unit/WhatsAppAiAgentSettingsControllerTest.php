<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\UserType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppAiAgentSettingsControllerTest extends TestCase
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

    public function test_super_admin_can_update_ai_agent_prompt_and_api_key(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $admin = $this->createUser(
            UserType::SUPER_ADMIN,
            'AI Admin'
        );

        $this->actingAs($admin);

        $this
            ->put(
                route('admin.whatsapp.ai-agent.update'),
                [
                    'enabled' => '1',
                    'auto_reply_enabled' => '1',
                    'provider' => 'openai',
                    'model' => 'gpt-4o-mini',
                    'prompt' => 'Use this dynamic CRM prompt.',
                    'api_key' => 'openai-dashboard-key',
                    'buffer_seconds' => '12',
                    'context_message_limit' => '4321',
                ]
            )
            ->assertRedirect(
                route('admin.whatsapp.ai-agent.edit')
            );

        $setting = DB::table('whatsapp_ai_agent_settings')
            ->first();

        $this->assertNotNull($setting);
        $this->assertSame(1, (int) $setting->enabled);
        $this->assertSame(1, (int) $setting->auto_reply_enabled);
        $this->assertSame(
            'gpt-4o-mini',
            $setting->model
        );
        $this->assertSame(
            'Use this dynamic CRM prompt.',
            $setting->prompt
        );
        $this->assertSame(12, (int) $setting->buffer_seconds);
        $this->assertSame(4321, (int) $setting->context_message_limit);
        $this->assertNotSame(
            'openai-dashboard-key',
            $setting->api_key_encrypted
        );
    }

    private function createUser(
        string $role,
        string $name
    ): User {
        $type = UserType::query()
            ->firstOrCreate(
                [
                    'user_type' => $role,
                ],
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

        Schema::create(
            'whatsapp_ai_agent_settings',
            function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->boolean('enabled')->default(false);
                $table->boolean('auto_reply_enabled')->default(false);
                $table->string('provider', 50)->default('openai');
                $table->string('model')->default('gpt-4o-mini');
                $table->text('prompt')->nullable();
                $table->text('api_key_encrypted')->nullable();
                $table->unsignedInteger('buffer_seconds')->default(10);
                $table->unsignedInteger('context_message_limit')->default(10000);
                $table->timestamps();
            }
        );
    }
}
