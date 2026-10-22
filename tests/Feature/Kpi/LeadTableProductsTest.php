<?php

namespace Tests\Feature\Kpi;

use App\Models\Lead;
use App\Models\UserType;
use App\Services\LeadTableProducts;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LeadTableProductsTest extends KpiFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('products', function ($table) {
            $table->uuid('id')->primary();
            $table->string('product');
            $table->integer('status')->default(1);
        });
    }

    public function test_multiple_archived_and_legacy_encoded_products_use_one_read_only_query(): void
    {
        $first = (string) Str::uuid(); $second = (string) Str::uuid();
        DB::table('products')->insert([
            ['id' => $first, 'product' => 'Helicopter', 'status' => 1],
            ['id' => $second, 'product' => 'Archived Yacht', 'status' => 0],
        ]);
        $leads = collect();
        foreach (range(1, 30) as $i) {
            $lead = new Lead(['id' => 'lead-'.$i]);
            $lead->setRawAttributes(['id' => 'lead-'.$i, 'product_ids' => json_encode(json_encode([$second, $first, $second, (string) Str::uuid()]))], true);
            $leads->push($lead);
        }
        DB::enableQueryLog(); DB::flushQueryLog();
        $names = app(LeadTableProducts::class)->forLeads($leads);
        $queries = DB::getQueryLog(); DB::disableQueryLog();
        $this->assertSame('Archived Yacht, Helicopter', $names['lead-1']);
        $this->assertCount(30, $names);
        $this->assertCount(1, $queries);
        $this->assertStringStartsWith('select', strtolower($queries[0]['query']));
        $this->assertFalse($leads->first()->isDirty());
    }

    public function test_missing_product_never_falls_back_to_a_service(): void
    {
        $lead = new Lead(['id' => 'empty', 'product_ids' => [], 'service_ids' => ['service-id']]);
        $missing = new Lead(['id' => 'missing', 'product_ids' => [(string) Str::uuid()]]);
        $this->assertSame(['empty' => '', 'missing' => ''], app(LeadTableProducts::class)->forLeads([$lead, $missing, null]));
        $this->assertSame([], app(LeadTableProducts::class)->forLeadIds([]));
        $this->assertSame([], app(LeadTableProducts::class)->forRelatedLeads([(object) ['lead' => null]]));
    }

    public function test_pagination_and_duplicate_rows_only_resolve_visible_leads(): void
    {
        $actor = $this->createUserWithRole('Product Sales', UserType::SALES_EXECUTIVE);
        $visible = $this->createLeadFor($actor);
        $outside = $this->createLeadFor($actor, 'Other customer');
        $product = (string) Str::uuid();
        DB::table('products')->insert(['id' => $product, 'product' => 'Private Plane']);
        DB::table('leads')->update(['product_ids' => json_encode([$product])]);
        $rows = new LengthAwarePaginator([['lead_id' => $visible], ['lead_id' => $visible]], 100, 2);
        DB::enableQueryLog(); DB::flushQueryLog();
        $names = app(LeadTableProducts::class)->forRecords($rows);
        $queries = DB::getQueryLog(); DB::disableQueryLog();
        $this->assertSame([$visible => 'Private Plane'], $names);
        $this->assertArrayNotHasKey($outside, $names);
        $this->assertCount(2, $queries);
        foreach ($queries as $query) $this->assertStringStartsWith('select', strtolower($query['query']));
    }

    public function test_work_table_escapes_product_and_preserves_service_data(): void
    {
        $product = (string) Str::uuid();
        DB::table('products')->insert(['id' => $product, 'product' => '<script>alert(1)</script>']);
        $lead = new Lead(['id' => (string) Str::uuid(), 'product_ids' => [$product], 'service_ids' => ['unchanged-service']]);
        $lead->setRelation('client', null)->setRelation('rideSegments', collect());
        $case = (object) ['id' => 'case', 'lead' => $lead, 'lead_id' => $lead->id, 'type' => 'review',
            'assignee' => null, 'completedBy' => null, 'opened_at' => now(), 'completed_at' => null, 'status' => 'pending'];
        $rows = new LengthAwarePaginator([$case], 1, 10);
        $html = view('admin.pages.operations.partials.work-details', compact('rows'))->render();
        $this->assertStringContainsString('<th>Product</th>', $html);
        $this->assertStringContainsString('data-label="Product"', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('<th>Service date</th>', $html);
        $this->assertSame(['unchanged-service'], $lead->service_ids_array);
    }
}
