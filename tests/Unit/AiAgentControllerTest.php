<?php

namespace Tests\Unit;

use App\Models\AiAgent;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiAgentControllerTest extends TestCase
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

    public function test_super_admin_can_store_large_ai_agent_prompt(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $admin = $this->createUser(
            UserType::SUPER_ADMIN,
            'AI Admin'
        );
        $profileId = $this->createModelProfile();
        $largePrompt = implode(
            PHP_EOL,
            array_fill(
                0,
                2400,
                'Prompt line for WhatsApp AI.'
            )
        );

        $this->actingAs($admin);

        $this
            ->from(route('admin.whatsapp.ai-agent.edit'))
            ->post(
                route('admin.whatsapp.ai-agents.store'),
                [
                    'name' => 'Large WhatsApp Prompt Agent',
                    'agent_type' => 'whatsapp',
                    'ai_model_profile_id' => $profileId,
                    'prompt' => $largePrompt,
                    'enabled' => '1',
                ]
            )
            ->assertRedirect(route('admin.whatsapp.ai-agent.edit'));

        $this->assertSame(
            $largePrompt,
            AiAgent::query()
                ->where('name', 'Large WhatsApp Prompt Agent')
                ->value('prompt')
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

    private function createModelProfile(): string
    {
        $id = (string) Str::uuid();

        DB::table('ai_model_profiles')
            ->insert([
                'id' => $id,
                'name' => 'OpenAI Production',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return $id;
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

        Schema::create('ai_model_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('provider', 50);
            $table->string('model');
            $table->text('api_key_encrypted')->nullable();
            $table->boolean('enabled')->default(true);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150)->unique();
            $table->string('agent_type', 50)->default('generic');
            $table->uuid('ai_model_profile_id')->nullable();
            $table->text('prompt');
            $table->boolean('enabled')->default(true);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });
    }
}
