<?php

namespace Tests\Unit;

use App\Http\Controllers\SendMessageController;
use App\Http\Controllers\VoucherController;
use App\Models\Lead;
use App\Models\LeadRide;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

class VoucherTravelTbaFlowTest extends TestCase
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

    public function test_voucher_tba_update_preserves_existing_stored_ride_times(): void
    {
        $lead = Lead::create([
            'id' => (string) Str::uuid(),
        ]);

        $ride = LeadRide::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'from_date' => '2026-10-04 16:30:00',
            'to_date' => '2026-10-04 17:00:00',
            'total_time' => '0.50',
            'is_tba' => false,
        ]);

        $request = Request::create('/admin/vouchers/store', 'POST', [
            'rides' => [
                [
                    'is_tba' => '1',
                    'service_date' => '2026-10-04',
                    'total_time' => '0.50',
                ],
            ],
        ]);

        $lead->load('rideSegments');

        $method = new ReflectionMethod(VoucherController::class, 'updateTravelInformation');
        $method->setAccessible(true);
        $method->invoke(new VoucherController(new SendMessageController()), $request, $lead);

        $ride->refresh();

        $this->assertTrue((bool) $ride->is_tba);
        $this->assertSame('2026-10-04 16:30:00', $ride->from_date->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-04 17:00:00', $ride->to_date->format('Y-m-d H:i:s'));
        $this->assertSame('0.50', number_format((float) $ride->total_time, 2, '.', ''));
    }

    public function test_voucher_tba_ui_does_not_require_or_clear_ride_time_inputs(): void
    {
        $contents = file_get_contents(
            resource_path('views/admin/pages/vouchers/generate-voucher.blade.php')
        );
        $tbaBlock = (string) Str::of($contents)->between(
            '// Handle TBA checkbox functionality',
            '// Initialize TBA state on page load'
        );

        $this->assertStringNotContainsString('data-segment="{{ $index }}" required', $contents);
        $this->assertStringContainsString(".prop('disabled', true)", $tbaBlock);
        $this->assertStringNotContainsString(".val('')", $tbaBlock);
        $this->assertStringNotContainsString("Clear any calculated total time", $tbaBlock);
    }

    private function createSchema(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestamps();
        });

        Schema::create('lead_rides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->nullable();
            $table->dateTime('from_date')->nullable();
            $table->dateTime('to_date')->nullable();
            $table->string('from_place')->nullable();
            $table->string('to_place')->nullable();
            $table->uuid('service_address_id')->nullable();
            $table->boolean('is_tba')->default(false);
            $table->decimal('total_time', 5, 2)->nullable();
            $table->timestamps();
        });
    }
}
