<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadPassenger;
use App\Models\User;
use App\Models\UserType;
use App\Models\Voucher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class VoucherPreRegistrationFlowTest extends TestCase
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

        view()->share('errors', new ViewErrorBag());

        $this->createSchema();
    }

    public function test_existing_voucher_form_shows_pre_registered_passengers_when_voucher_has_no_customer_passengers(): void
    {
        $this->actingAs($this->createAdminUser());

        $lead = new Lead([
            'id' => (string) Str::uuid(),
            'number_of_passengers' => 1,
        ]);
        $lead->setRelation('client', new Client([
            'id' => (string) Str::uuid(),
            'name' => 'Voucher Customer',
            'email' => 'customer@example.test',
            'contact_number' => '9999999999',
        ]));
        $lead->client->setRelation('country', null);
        $lead->client->setRelation('city', null);
        $lead->setRelation('rideSegments', collect());

        $voucher = new Voucher([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
        ]);
        $voucher->setRelation('passengers', collect([
            new LeadPassenger([
                'id' => (string) Str::uuid(),
                'name' => 'Operations Handler',
                'is_handler' => true,
                'is_additional_person' => false,
            ]),
        ]));

        $preRegisteredPassenger = new LeadPassenger([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'name' => 'Registered Passenger',
            'age' => 31,
            'weight' => 72,
            'is_handler' => false,
            'is_additional_person' => false,
        ]);

        $html = view('admin.pages.vouchers.generate-voucher', [
            'lead' => $lead,
            'voucher' => $voucher,
            'selectedServices' => collect(),
            'selectedExtraServices' => collect(),
            'allServices' => collect(),
            'allExtraServices' => collect(),
            'serviceExtraServicesMap' => [],
            'serviceAddresses' => collect(),
            'operationTeam' => collect(),
            'showHandlerSections' => false,
            'isAirAmbulance' => false,
            'preVoucherPassengers' => collect([$preRegisteredPassenger]),
            'allVendorExtraServices' => collect(),
            'allVendors' => collect(),
            'selectedVendorExtraGroups' => [],
        ])->render();

        $this->assertStringContainsString('Registered Passenger', $html);
        $this->assertStringNotContainsString('Operations Handler', $html);
    }

    public function test_lead_specific_registration_link_is_created_for_the_requested_lead(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $user = $this->createAdminUser();

        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'Multi Lead Customer',
            'email' => 'multi@example.test',
            'contact_number' => '9876543210',
            'status' => 1,
        ]);

        $requestedLead = Lead::create([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'number_of_passengers' => 1,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $otherLead = Lead::create([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'number_of_passengers' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(route('admin.leads.generate-passenger-registration-link', $requestedLead));

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Registration link generated successfully',
            ]);

        $this->assertStringContainsString(
            '/lead/register/' . $requestedLead->id,
            $response->json('link')
        );
        $this->assertDatabaseHas('lead_passengers', [
            'lead_id' => $requestedLead->id,
            'voucher_id' => null,
        ]);
        $this->assertDatabaseMissing('lead_passengers', [
            'lead_id' => $otherLead->id,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('user_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_type');
            $table->string('description')->nullable();
            $table->integer('status')->default(1);
            $table->uuid('parent_id')->nullable();
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

        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('contact_number')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id')->nullable();
            $table->uuid('representative_user_id')->nullable();
            $table->json('service_ids')->nullable();
            $table->json('product_ids')->nullable();
            $table->integer('number_of_passengers')->nullable();
            $table->text('description')->nullable();
            $table->string('occasion')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_passengers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('voucher_id')->nullable();
            $table->uuid('lead_id')->nullable();
            $table->string('registration_token')->nullable();
            $table->string('registration_slug', 64)->nullable()->unique();
            $table->dateTime('token_expires_at')->nullable();
            $table->string('name')->nullable();
            $table->integer('age')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('traveller_type')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->text('front_document')->nullable();
            $table->text('back_document')->nullable();
            $table->boolean('is_handler')->default(false);
            $table->boolean('is_additional_person')->default(false);
            $table->timestamps();
        });
    }

    private function createAdminUser(): User
    {
        $userType = UserType::forceCreate([
            'id' => (string) Str::uuid(),
            'user_type' => UserType::SUPER_ADMIN,
            'status' => 1,
        ]);

        $user = User::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin@example.test',
            'password' => 'secret',
            'user_type_id' => $userType->id,
            'status' => 1,
        ]);
        $user->setRelation('userType', $userType);

        return $user;
    }
}
