<?php

namespace Tests\Feature;

use App\Models\{Client, Lead, LeadFollowup, PaymentAuditTrail, User};
use App\Services\TargetIncentiveService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\Support\IsolatedWave2PostgresTestCase;

class Wave2HistoricPerformancePostgresAcceptanceTest extends IsolatedWave2PostgresTestCase
{
    public function test_first_positive_approved_paid_date_uses_timestamp_cutoff_without_recounting_installments(): void
    {
        $sales = User::where('email', 'sales-executive@wave1.invalid')->sole();
        $client = Client::where('email', 'browser@wave1.invalid')->sole();
        $lead = Lead::forceCreate(['id' => (string) Str::uuid(), 'client_id' => $client->id, 'representative_user_id' => $sales->id]);
        $followup = LeadFollowup::create(['id' => (string) Str::uuid(), 'lead_id' => $lead->id, 'followed_by' => $sales->id,
            'status' => 4, 'total_amount' => 32000, 'service_amount' => 32000]);
        $payment = PaymentAuditTrail::create(['id' => (string) Str::uuid(), 'lead_followup_id' => $followup->id,
            'payment_status' => 1, 'paid_amount' => 10000, 'paid_date' => '2047-09-18',
            'payment_method' => 'Bank Transfer', 'created_by' => $sales->id]);
        PaymentAuditTrail::create(['id' => (string) Str::uuid(), 'lead_followup_id' => $followup->id,
            'payment_status' => 1, 'paid_amount' => 22000, 'paid_date' => '2047-09-19 12:00:00',
            'payment_method' => 'Bank Transfer', 'created_by' => $sales->id]);
        $before = PaymentAuditTrail::where('lead_followup_id', $followup->id)->orderBy('id')->get()->toArray();
        $service = new TargetIncentiveService();
        $ledger = fn ($from, $to) => $service->ledgerForPeriod(Carbon::parse($from), Carbon::parse($to))
            ->where('lead_id', $lead->id)->values();
        $this->assertSame('2047-09-18 00:00:00', $payment->fresh()->paid_date->format('Y-m-d H:i:s'));
        $this->assertCount(0, $ledger('2047-09-01', '2047-09-17'));
        $recognized = $ledger('2047-09-18', '2047-09-30');
        $this->assertSame([$lead->id], $recognized->pluck('lead_id')->all());
        $this->assertEquals(32000, $recognized->sole()['amount']);
        $this->assertCount(0, $ledger('2047-09-19', '2047-09-30'));
        $this->assertSame($before, PaymentAuditTrail::where('lead_followup_id', $followup->id)->orderBy('id')->get()->toArray());
    }
}
