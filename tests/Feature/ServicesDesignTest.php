<?php

namespace Tests\Feature;

use App\Models\{ExtraService, Product, Service, User, UserType};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;
use Tests\TestCase;

class ServicesDesignTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'services_test', 'database.connections.services_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ], 'cache.default' => 'array']);
        DB::purge('services_test');
        Schema::create('user_types', function (Blueprint $t) { $t->uuid('id')->primary(); $t->string('user_type'); $t->integer('status')->default(1); $t->timestamps(); });
        Schema::create('users', function (Blueprint $t) { $t->uuid('id')->primary(); $t->uuid('user_type_id'); $t->string('name'); $t->string('email'); $t->string('password')->nullable(); $t->integer('status')->default(1); $t->timestamps(); });
        Schema::create('products', function (Blueprint $t) { $t->uuid('id')->primary(); $t->string('product'); $t->integer('status')->default(1); $t->timestamps(); });
        Schema::create('services', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->string('service'); $t->text('product_ids')->nullable();
            $t->integer('service_amount')->default(0); $t->decimal('fees_percent', 5, 2)->default(0);
            $t->text('description')->nullable(); $t->text('terms_and_conditions')->nullable();
            $t->text('booking_email_note')->nullable(); $t->integer('status')->default(1); $t->timestamps();
        });
        Schema::create('extra_services', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->string('extra_service'); $t->string('description')->nullable();
            $t->integer('extra_service_amount')->default(0); $t->string('usage_scope')->default('customer');
            $t->integer('status')->default(1); $t->timestamps();
        });
        Schema::create('service_extra_service', function (Blueprint $t) {
            $t->uuid('service_id'); $t->uuid('extra_service_id'); $t->timestamps();
        });
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi-tools/views'));
        $this->actor(UserType::SUPER_ADMIN);
    }

    protected function tearDown(): void
    {
        DB::disconnect('services_test');
        parent::tearDown();
    }

    public function test_list_has_dynamic_summary_filters_popup_and_both_status_actions(): void
    {
        $product = $this->product();
        $zero = $this->service($product, ['service' => 'Zero priced ride', 'service_amount' => 0]);
        $paid = $this->service($product, ['service' => 'Paid ride', 'service_amount' => 118000, 'fees_percent' => 18, 'status' => 0]);
        $extra = $this->extra(); $paid->extraServices()->attach($extra);
        $response = $this->get(route('admin.services.index'))->assertOk()->assertSee('data-service-view', false)
            ->assertSee('class="sc-filters"', false)->assertSee('name="product_id"', false)->assertDontSee('class="sc-chips"', false)
            ->assertSee('<option selected>10</option>', false)
            ->assertSee('Deactivate')->assertSee('Activate')
            ->assertSee('96,760.00')->assertSee('Price missing')->assertViewHas('serviceSummary', fn ($s) => $s['total'] === 2 && $s['active'] === 1 && $s['missing_price'] === 1 && $s['no_extras'] === 1);
        $this->assertMatchesRegularExpression('/View\s+Service/', $response->getContent());
        $this->get(route('admin.services.index', ['service' => 'paid', 'status' => '0', 'product_id' => $product->id]))
            ->assertOk()->assertViewHas('services', fn ($s) => $s->count() === 1 && $s->first()->id === $paid->id);
    }

    public function test_popup_and_standalone_view_keep_all_service_information(): void
    {
        $service = $this->service($this->product(), ['booking_email_note' => 'Bring your ID', 'terms_and_conditions' => '<p>Existing terms</p>', 'description' => 'Full description']);
        $service->extraServices()->attach($this->extra());
        $this->getJson(route('admin.services.view', $service), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('id', $service->id)
            ->assertJsonStructure(['html']);
        $this->get(route('admin.services.view', $service))->assertOk()->assertSee('Bring your ID')
            ->assertSee('Existing terms')->assertSee('Full description')->assertSee('Airport pickup')->assertSee('Created');
    }

    public function test_create_and_update_preserve_fields_and_customer_extra_links(): void
    {
        $product = $this->product(); $extra = $this->extra();
        $this->get(route('admin.services.create'))->assertOk()->assertSee('data-service-form', false)
            ->assertSee('name="booking_email_note"', false)->assertSee('name="terms_and_conditions"', false);
        $data = ['service' => 'Gangtok helicopter ride', 'service_amount' => 118000, 'fees_percent' => 18,
            'product_id' => $product->id, 'extra_service_ids' => [$extra->id, $extra->id],
            'description' => 'Description', 'booking_email_note' => 'Bring ID', 'terms_and_conditions' => '<p>Weather terms</p>'];
        $this->post(route('admin.services.store'), $data)->assertRedirect(route('admin.services.create'))->assertSessionHasNoErrors();
        $service = Service::firstOrFail();
        $this->assertEquals(96760, $service->amount_after_fees);
        $this->assertSame([$product->id], $service->product_ids);
        $this->assertSame('Bring ID', $service->booking_email_note);
        $this->assertSame('<p>Weather terms</p>', $service->terms_and_conditions);
        $this->assertSame(1, $service->extraServices()->count());
        $this->get(route('admin.services.edit', $service))->assertOk()->assertSee('data-service-form', false)->assertSee('Bring ID');
        $data['service_amount'] = 0; $data['extra_service_ids'] = [];
        $this->put(route('admin.services.update', $service), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(0, $service->fresh()->service_amount);
        $this->assertSame(0, $service->extraServices()->count());
    }

    public function test_validation_and_permissions_and_status_delete_contracts_remain(): void
    {
        $service = $this->service($this->product());
        $this->post(route('admin.services.store'), ['service' => 'Invalid', 'fees_percent' => 101])->assertSessionHasErrors(['fees_percent', 'product_id', 'service_amount']);
        $this->patchJson(route('services.toggle-status', $service))->assertOk()->assertJsonPath('new_status', false);
        $this->patchJson(route('services.toggle-status', $service))->assertOk()->assertJsonPath('new_status', true);
        $this->actor(UserType::OPERATIONS_EXECUTIVE);
        $this->get(route('admin.services.index'))->assertOk()->assertDontSee('data-service-delete', false)->assertSee('Deactivate');
        $this->actor(UserType::SALES_EXECUTIVE);
        $this->get(route('admin.services.index'))->assertForbidden();
        $this->patchJson(route('services.toggle-status', $service))->assertForbidden();
        $this->actor(UserType::SUPER_ADMIN);
        $this->deleteJson(route('admin.services.destroy', $service))->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('services', ['id' => $service->id]);
    }

    public function test_validation_repopulation_keeps_selected_fields_and_escapes_editor_source(): void
    {
        $product = $this->product(); $extra = $this->extra();
        $html = $this->withSession(['_old_input' => [
            'product_id' => $product->id, 'extra_service_ids' => [$extra->id], 'service' => 'Old service',
            'terms_and_conditions' => '</textarea><script>unexpected()</script>', 'booking_email_note' => 'Previous note',
        ]])->get(route('admin.services.create'))->assertOk()->assertSee('Previous note')
            ->assertDontSee('<script>unexpected()</script>', false)->getContent();
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $this->assertSame($product->id, $xpath->query('//select[@name="product_id"]/option[@selected]')->item(0)->getAttribute('value'));
        $this->assertSame($extra->id, $xpath->query('//input[@name="extra_service_ids[]"][@checked]')->item(0)->getAttribute('value'));
        $this->assertStringNotContainsString('@checked', $html);
        $this->assertStringNotContainsString('@selected', $html);
    }

    public function test_zero_price_can_still_be_saved_active_and_vendor_only_extras_are_rejected(): void
    {
        $product = $this->product(); $extra = $this->extra();
        $data = ['service' => 'Zero price service', 'service_amount' => 0, 'fees_percent' => 100, 'product_id' => $product->id];
        $this->post(route('admin.services.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('services', ['service' => 'Zero price service', 'status' => 1, 'service_amount' => 0]);
        $extra->update(['usage_scope' => ExtraService::SCOPE_VENDOR]);
        $data['extra_service_ids'] = [$extra->id];
        $this->post(route('admin.services.store'), $data)->assertSessionHasErrors('extra_service_ids.0');
        $this->assertDatabaseCount('services', 1);
    }

    private function actor(string $role): void
    {
        $type = UserType::forceCreate(['id' => (string) Str::uuid(), 'user_type' => $role]);
        $this->actingAs(User::forceCreate(['id' => (string) Str::uuid(), 'user_type_id' => $type->id, 'name' => 'Catalog user', 'email' => Str::uuid().'@example.test']));
    }
    private function product(): Product { return Product::forceCreate(['id' => (string) Str::uuid(), 'product' => 'Helicopter transfer']); }
    private function service(Product $p, array $data = []): Service { return Service::create(array_merge(['id' => (string) Str::uuid(), 'service' => 'Helicopter ride', 'product_ids' => [$p->id], 'service_amount' => 3500, 'fees_percent' => 18, 'status' => 1], $data)); }
    private function extra(): ExtraService { return ExtraService::forceCreate(['id' => (string) Str::uuid(), 'extra_service' => 'Airport pickup', 'extra_service_amount' => 3500, 'usage_scope' => ExtraService::SCOPE_CUSTOMER, 'status' => 1]); }
}
