<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\NotificationMaster;
use App\Models\Vendor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationMasterInternalRecipientsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_active_internal_recipients_are_not_globally_removed_by_client_vendor_matches(): void
    {
        NotificationMaster::create([
            'mobile_number' => '9893995795',
            'contact_country_code' => '+91',
            'email_id' => 'suraj.jaiswal@example.test',
            'status' => 1,
        ]);

        Client::forceCreate([
            'id' => 'client-1',
            'name' => 'Matching Client',
            'email' => 'suraj.jaiswal@example.test',
            'contact_number' => '9893995795',
            'alternate_number' => null,
        ]);

        Vendor::forceCreate([
            'id' => 'vendor-1',
            'name' => 'Matching Vendor',
            'email' => 'vendor@example.test',
            'contact_number' => '919893995795',
            'status' => 1,
        ]);

        $emails = NotificationMaster::activeInternalRecipients()
            ->pluck('email_id')
            ->all();

        $this->assertContains('suraj.jaiswal@example.test', $emails);
    }

    public function test_active_internal_recipients_can_exclude_current_external_contact(): void
    {
        NotificationMaster::create([
            'mobile_number' => '9893995795',
            'contact_country_code' => '+91',
            'email_id' => 'suraj.jaiswal@example.test',
            'status' => 1,
        ]);

        $recipients = NotificationMaster::activeInternalRecipients(
            ['suraj.jaiswal@example.test'],
            ['+91 9893995795']
        );

        $this->assertCount(0, $recipients);
    }

    private function createSchema(): void
    {
        Schema::create('notification_masters', function (Blueprint $table) {
            $table->id();
            $table->string('mobile_number')->nullable();
            $table->string('email_id')->nullable();
            $table->integer('status')->default(1);
            $table->string('contact_country_code')->nullable();
            $table->uuid('country_id')->nullable();
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('alternate_number')->nullable();
            $table->timestamps();
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('contact_number')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }
}
