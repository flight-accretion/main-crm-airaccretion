<?php

namespace Tests\Feature\GoogleChat;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

abstract class GoogleChatTestCase extends TestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../../../bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) {
            $app['config']->set('database.default', 'chat_test');
            $app['config']->set('database.connections.chat_test', [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
            $app['config']->set('services.google_chat.enabled', false);
            $app['config']->set('cache.default', 'array');
            $app['config']->set('queue.default', 'sync');
            $app['config']->set('session.driver', 'array');
        });
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('users', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->string('name'); $t->string('email')->nullable();
            $t->uuid('user_type_id')->nullable(); $t->integer('status')->default(1); $t->timestamps();
        });
        Schema::create('user_types', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->string('user_type'); $t->timestamps();
        });
        Schema::create('leads', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->uuid('representative_user_id')->nullable();
            $t->uuid('client_id')->nullable(); $t->timestamps();
        });
        Schema::create('clients', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->string('name'); $t->timestamps();
        });
        require_once database_path('migrations/2026_09_28_110135_create_lead_chat_tables.php');
        require_once database_path('migrations/2026_09_28_162820_add_google_sync_to_lead_chat.php');
        (new \CreateLeadChatTables)->up();
        (new \AddGoogleSyncToLeadChat)->up();
    }

    protected function harden(): void
    {
        require_once database_path('migrations/2026_09_29_180000_harden_google_chat_bridge.php');
        (new \HardenGoogleChatBridge)->up();
    }
}
