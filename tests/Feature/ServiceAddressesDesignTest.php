<?php

namespace Tests\Feature;

use App\Models\{ServiceAddress, User, UserType};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;
use Tests\TestCase;

class ServiceAddressesDesignTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'address_test', 'database.connections.address_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'cache.default' => 'array']);
        DB::purge('address_test');
        foreach (['user_types', 'users', 'products', 'services', 'countries', 'states', 'cities', 'service_addresses'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->uuid('id')->primary(); $t->integer('status')->default(1); $t->timestamps();
                if ($table === 'user_types') $t->string('user_type');
                if ($table === 'users') { $t->string('name'); $t->string('email'); $t->uuid('user_type_id'); }
                if ($table === 'products') $t->string('product');
                if ($table === 'services') { $t->string('service'); $t->text('product_ids')->nullable(); }
                if (in_array($table, ['countries', 'states', 'cities'])) $t->string('name');
                if ($table === 'countries') $t->string('phonecode')->default('91');
                if ($table === 'states') $t->uuid('country_id');
                if ($table === 'cities') $t->uuid('state_id');
                if ($table === 'service_addresses') { $t->uuid('city_id')->nullable(); $t->uuid('product_id'); $t->uuid('service_id'); $t->string('contact_person_name')->nullable(); $t->string('contact_number')->nullable(); $t->text('address'); $t->text('map_link')->nullable(); }
            });
        }
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi-tools/views'));
        $this->actor(UserType::SUPER_ADMIN);
    }

    private function actor(string $role): void
    {
        $id = (string) Str::uuid();
        DB::table('user_types')->insert(['id' => $id, 'user_type' => $role]);
        $this->actingAs(User::forceCreate(['id' => (string) Str::uuid(), 'user_type_id' => $id, 'name' => 'Address user', 'email' => Str::uuid().'@example.test', 'status' => 1]));
    }

    public function seedAddress(array $overrides = []): ServiceAddress
    {
        $ids = array_map(fn () => (string) Str::uuid(), range(1, 5));
        DB::table('products')->insert(['id' => $ids[0], 'product' => 'Helicopter']);
        DB::table('services')->insert(['id' => $ids[1], 'service' => 'Airport ride', 'product_ids' => json_encode([$ids[0]])]);
        DB::table('countries')->insert(['id' => $ids[2], 'name' => 'India']);
        DB::table('states')->insert(['id' => $ids[3], 'name' => 'Sikkim', 'country_id' => $ids[2]]);
        DB::table('cities')->insert(['id' => $ids[4], 'name' => 'Gangtok', 'state_id' => $ids[3]]);
        return ServiceAddress::create(array_merge(['id' => (string) Str::uuid(), 'product_id' => $ids[0], 'service_id' => $ids[1], 'city_id' => $ids[4], 'address' => 'Reporting helipad', 'contact_person_name' => 'Ground manager', 'contact_number' => '+91-9876543210', 'map_link' => 'https://maps.google.com/?q=helipad'], $overrides));
    }

    public function test_list_separates_contact_columns_and_has_dynamic_flags_and_popups(): void
    {
        $a = $this->seedAddress(); $b = $this->seedAddress(['contact_person_name' => '', 'status' => 0]);
        $this->get(route('admin.service-addresses.index'))->assertOk()->assertSee('data-address-page', false)
            ->assertSee('<th>Address</th>', false)->assertSee('<th>Contact person</th>', false)->assertSee('<th>Contact number</th>', false)
            ->assertSee('Activate')->assertSee('Deactivate')->assertSee('View Address')->assertSee('Shared phone')
            ->assertViewHas('addressSummary', fn ($s) => $s['total'] === 2 && $s['shared_numbers'] === 1 && $s['missing_contact'] === 1)
            ->assertViewHas('addresses', fn ($rows) => $rows->perPage() === 10);
        $this->get(route('admin.service-addresses.index', ['city_id' => $a->city_id]))->assertOk()
            ->assertViewHas('addresses', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $a->id);
    }

    public function test_existing_view_and_edit_details_endpoints_keep_record_identity(): void
    {
        $a = $this->seedAddress();
        $this->getJson('/admin/service-addresses/'.$a->id.'/view-modal')->assertOk()->assertJsonPath('serviceAddress.id', $a->id)->assertJsonPath('serviceAddress.contact_number', '+91-9876543210');
        $this->getJson(route('admin.service-addresses.edit-details', $a->id))->assertOk()->assertJsonPath('data.serviceAddress.id', $a->id);
    }

    public function test_missing_legacy_city_and_untrusted_values_are_safe(): void
    {
        $a = $this->seedAddress(['city_id' => null, 'address' => '<script>alert(1)</script>', 'map_link' => 'javascript:alert(1)']);
        $this->get(route('admin.service-addresses.index'))->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('href="javascript:', false);
        $this->getJson(route('admin.service-addresses.edit-details', $a->id))->assertOk()->assertJsonPath('data.serviceAddress.id', $a->id);
        $this->get(route('admin.service-addresses.index', ['city_id' => 'invalid']))->assertOk()->assertViewHas('addresses', fn ($rows) => $rows->total() === 0);
        $this->get(route('admin.service-addresses.index', ['status' => '0']))->assertOk()->assertViewHas('addresses', fn ($rows) => $rows->total() === 0);
    }

    public function test_validation_restores_edit_popup_with_same_target_and_input(): void
    {
        $a = $this->seedAddress();
        $this->from(route('admin.service-addresses.index'))->put(route('admin.service-addresses.update', $a->id), ['_address_id' => $a->id, 'contact_person_name' => 'Unsaved manager', 'contact_country_code' => '+44'])
            ->assertRedirect(route('admin.service-addresses.index'))->assertSessionHasErrors(['service_id', 'address'], null, 'edit');
        $this->get(route('admin.service-addresses.index'))->assertOk()->assertSee('data-restore="edit"', false)
            ->assertSee('Unsaved manager')->assertSee('value="'.$a->id.'"', false)->assertSee('value="+44"', false);
    }

    public function test_create_update_validation_and_status_contracts_are_preserved(): void
    {
        $a = $this->seedAddress(); $city = DB::table('cities')->where('id', $a->city_id)->first(); $state = DB::table('states')->where('id', $city->state_id)->first();
        $data = ['product_id' => $a->product_id, 'service_id' => $a->service_id, 'country_id' => $state->country_id, 'state_id' => $state->id, 'city_id' => $city->id, 'contact_person_name' => 'New manager', 'country_code' => '+91', 'contact_number' => '9123456789', 'address' => 'New reporting point', 'map_link' => 'https://maps.google.com/'];
        $this->post(route('admin.service-addresses.store'), $data)->assertRedirect(route('admin.service-addresses.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('service_addresses', ['address' => 'New reporting point', 'contact_number' => '+91-9123456789']);
        $data['contact_country_code'] = '+44';
        $this->put(route('admin.service-addresses.update', $a->id), $data)->assertRedirect(route('admin.service-addresses.index'))->assertSessionHasNoErrors();
        $this->assertSame('+44-9123456789', $a->fresh()->contact_number);
        $this->post(route('admin.service-addresses.store'), [])->assertSessionHasErrors(['service_id', 'address', 'city_id'], null, 'add');
        $this->patchJson(route('admin.service-addresses.toggle-status', $a))->assertOk()->assertJsonPath('status', 0);
        $this->patchJson(route('admin.service-addresses.toggle-status', $a))->assertOk()->assertJsonPath('status', 1);
        $this->actor(UserType::OPERATIONS_EXECUTIVE);
        $this->get(route('admin.service-addresses.index'))->assertOk();
        $this->actor(UserType::SALES_EXECUTIVE);
        $this->get(route('admin.service-addresses.index'))->assertForbidden();
        $this->patchJson(route('admin.service-addresses.toggle-status', $a))->assertForbidden();
    }
}
