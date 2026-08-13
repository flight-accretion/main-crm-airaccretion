<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Models\LeadTransfer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class LeadPendingTransferTest extends TestCase
{
    public function test_pending_transfers_relation_uses_timestamp_order_without_uuid_aggregate(): void
    {
        $lead = new Lead();
        $lead->id = '43ea9443-5080-46dc-bd2c-3c3854d79876';

        $relation = $lead->pendingTransfers();
        $sql = strtolower($relation->toBase()->toSql());

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertStringContainsString('order by', $sql);
        $this->assertStringContainsString('created_at', $sql);
        $this->assertStringNotContainsString('max(', $sql);
    }

    public function test_pending_transfer_attribute_returns_loaded_newest_pending_transfer(): void
    {
        $lead = new Lead();

        $newestTransfer = new LeadTransfer([
            'id' => '11111111-1111-4111-8111-111111111111',
            'status' => 'pending',
        ]);
        $newestTransfer->created_at = Carbon::parse('2026-08-13 10:00:00');

        $olderTransfer = new LeadTransfer([
            'id' => '22222222-2222-4222-8222-222222222222',
            'status' => 'pending',
        ]);
        $olderTransfer->created_at = Carbon::parse('2026-08-13 09:00:00');

        $lead->setRelation(
            'pendingTransfers',
            collect([
                $newestTransfer,
                $olderTransfer,
            ])
        );

        $this->assertSame($newestTransfer, $lead->pendingTransfer);
    }
}
