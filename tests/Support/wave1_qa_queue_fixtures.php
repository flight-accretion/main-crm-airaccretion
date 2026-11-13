<?php

$connection = $app['db']->connection();
if ($connection->getDatabaseName() !== 'accretion_wave1_qa'
    || $connection->selectOne('SELECT current_user AS role')->role !== 'accretion_wave1_qa_role') {
    throw new RuntimeException('Queue fixture isolation check failed.');
}
\Illuminate\Database\Eloquent\Model::withoutEvents(static function () use ($connection): void {
    $connection->transaction(static function (): void {
        $base = \App\Models\Lead::whereHas('client', fn ($query) => $query->where('email', 'browser@wave1.invalid'))->firstOrFail();
        $operator = \App\Models\User::where('email', 'operations@wave1.invalid')->firstOrFail();
        $cases = [
            ['review', 'QA review assigned', $operator->id, null],
            ['review', 'QA review unassigned', null, null],
            ['customer_call', 'QA call today', $operator->id, now()->startOfDay()->addHours(12)],
            ['vendor_followup', 'QA vendor undated', $operator->id, null],
            ['reschedule', 'QA future reschedule', $operator->id, now()->addDays(2)],
        ];
        foreach ($cases as [$type, $note, $assignedTo, $due]) {
            if (\App\Models\OperationCase::where('note', $note)->exists()) {
                continue;
            }
            $case = new \App\Models\OperationCase();
            $case->forceFill(['id' => (string) \Illuminate\Support\Str::uuid(), 'lead_id' => $base->id,
                'type' => $type, 'status' => 'pending', 'assigned_to' => $assignedTo,
                'created_by' => $operator->id, 'opened_at' => now(), 'next_followup_at' => $due, 'note' => $note])->save();
        }
        if (!\App\Models\Client::where('email', 'refund@wave1.invalid')->exists()) {
            $client = $base->client->replicate();
            $client->forceFill(['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Wave1 Refund Customer', 'email' => 'refund@wave1.invalid'])->save();
            $lead = $base->replicate();
            $lead->forceFill(['id' => (string) \Illuminate\Support\Str::uuid(), 'client_id' => $client->id])->save();
            $followup = new \App\Models\LeadFollowup();
            $followup->forceFill(['id' => (string) \Illuminate\Support\Str::uuid(), 'lead_id' => $lead->id,
                'status' => 2, 'total_amount' => 5000, 'followed_by' => $operator->id,
                'received_amount' => 5000, 'pricing_revision' => true, 'service_ids' => [],
                'extra_service_ids' => [], 'followup_note' => 'Synthetic cancelled refund case'])->save();
            $audit = new \App\Models\PaymentAuditTrail();
            $audit->forceFill(['id' => (string) \Illuminate\Support\Str::uuid(), 'lead_followup_id' => $followup->id,
                'paid_amount' => 5000, 'paid_date' => now(), 'payment_method' => 'Bank Transfer',
                'payment_status' => 1, 'created_by' => $operator->id])->save();
            $refund = new \App\Models\LeadRefund();
            $refund->forceFill(['id' => (string) \Illuminate\Support\Str::uuid(), 'lead_followup_id' => $followup->id,
                'original_amount' => 5000, 'refund_amount' => 1000, 'refund_reason' => 'Synthetic QA refund',
                'status' => 1, 'approval_status' => 'pending'])->save();
            $payment = \App\Models\LeadVendorPayment::where('lead_id', $base->id)->firstOrFail()->replicate();
            $payment->forceFill(['id' => (string) \Illuminate\Support\Str::uuid(), 'lead_id' => $lead->id, 'voucher_id' => null])->save();
            foreach ([1, 2] as $index) {
                $vendorRefund = new \App\Models\VendorRefund();
                $vendorRefund->forceFill(['id' => (string) \Illuminate\Support\Str::uuid(), 'lead_id' => $lead->id,
                    'lead_vendor_payment_id' => $payment->id, 'vendor_id' => $payment->vendor_id,
                    'cancellation_amount' => 4000, 'refund_amount' => 500,
                    'status' => 1, 'approval_status' => 'pending', 'refund_reason' => 'Synthetic vendor refund '.$index])->save();
            }
        }
    });
});
echo json_encode(['cases' => \App\Models\OperationCase::where('note', 'like', 'QA %')->count(),
    'refund_summary' => app(\App\Services\Operations\RefundSummaryService::class)->summary()]).PHP_EOL;
