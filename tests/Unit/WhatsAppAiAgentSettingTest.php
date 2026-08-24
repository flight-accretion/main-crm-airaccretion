<?php

namespace Tests\Unit;

use App\Models\WhatsAppAiAgentSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsAppAiAgentSettingTest extends TestCase
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

    public function test_api_key_is_encrypted_and_read_back_only_through_model_method(): void
    {
        $setting = WhatsAppAiAgentSetting::create([
            'enabled' => true,
            'auto_reply_enabled' => true,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'prompt' => 'Reply as Accretion Aviation.',
            'buffer_seconds' => 10,
            'context_message_limit' => 10000,
        ]);

        $setting->setApiKey('openai-secret-key');
        $setting->save();

        $rawStoredKey = DB::table('whatsapp_ai_agent_settings')
            ->where('id', $setting->id)
            ->value('api_key_encrypted');

        $this->assertNotSame('openai-secret-key', $rawStoredKey);
        $this->assertSame(
            'openai-secret-key',
            $setting->fresh()->apiKey()
        );
        $this->assertSame(
            'configured',
            $setting->fresh()->api_key_status
        );
        $this->assertSame(
            10000,
            $setting->fresh()->contextMessageLimit()
        );
    }
}
