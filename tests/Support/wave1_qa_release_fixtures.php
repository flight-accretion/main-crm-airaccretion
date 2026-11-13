<?php

if ($connection->selectOne('SELECT current_database() AS name')->name !== 'accretion_wave1_qa') {
    throw new RuntimeException('Incorrect QA database.');
}
$manager = \App\Models\User::where('email', 'sales-manager@wave1.invalid')->firstOrFail();
$lead = \App\Models\Lead::whereHas('client', fn ($query) => $query->where('email', 'browser@wave1.invalid'))->firstOrFail();
$connection->transaction(static function () use ($manager, $lead): void {
    \App\Models\Target::firstOrCreate([
        'sales_executive_id' => $manager->id, 'year' => 2026, 'month' => 8,
    ], [
        'id' => (string) \Illuminate\Support\Str::uuid(), 'assigned_by' => $manager->id,
        'target_amount' => 10000, 'achieved_amount' => 0, 'status' => 'active',
        'incentive_enabled' => true, 'incentive_basis' => 'individual', 'incentive_multiplier' => .05,
    ]);
    $source = $lead->leadFollowups()->where('status', 4)->firstOrFail();
    $zero = $lead->leadFollowups()->where('followup_note', 'Wave1 explicit zero')->first();
    if (!$zero) {
        \Illuminate\Database\Eloquent\Model::withoutEvents(static function () use ($source): void {
            $row = $source->replicate();
            $row->forceFill(['id' => (string) \Illuminate\Support\Str::uuid(), 'status' => 10,
                'total_amount' => 0, 'service_amount' => 0, 'discount_amount' => 0, 'service_details' => [],
                'received_amount' => 0, 'pricing_revision' => true, 'followup_note' => 'Wave1 explicit zero',
            ])->save();
        });
    }
    $summary = app(\App\Services\Reports\ProfitLossSummaryService::class);
    $latest = $lead->leadFollowups()->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
    echo json_encode(['manager' => $manager->id, 'historical' => $summary->forFollowup($source),
        'current' => $summary->forFollowup($latest)]).PHP_EOL;
});
