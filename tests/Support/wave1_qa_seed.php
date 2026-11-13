<?php

$connection = $app['db']->connection();
if ($connection->getDatabaseName() !== 'accretion_wave1_qa'
    || $connection->selectOne('SELECT current_user AS role')->role !== 'accretion_wave1_qa_role') {
    throw new RuntimeException('Synthetic fixture isolation failed.');
}
if (!\App\Models\Voucher::whereHas('lead.client', fn ($query) => $query->where('email', 'browser@wave1.invalid'))->exists()) {
    \Illuminate\Database\Eloquent\Model::withoutEvents(static function () use ($connection): void {
        $connection->transaction(static function (): void {
            $lead = \App\Models\Lead::whereHas('client', fn ($query) => $query->where('email', 'browser@wave1.invalid'))->firstOrFail();
            $voucher = new \App\Models\Voucher();
            $voucher->forceFill(['id' => (string) \Illuminate\Support\Str::uuid(), 'lead_id' => $lead->id, 'status' => 1])->save();
            $invoice = new \App\Models\Invoice();
            $invoice->forceFill(['id' => (string) \Illuminate\Support\Str::uuid(), 'voucher_id' => $voucher->id,
                'invoice_id' => 'INV-WAVE1-SYNTHETIC', 'status' => 1])->save();
            $vendor = new \App\Models\Vendor();
            $vendor->forceFill(['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Wave1 QA Vendor',
                'email' => 'vendor@wave1.invalid', 'contact_number' => '9999990002', 'status' => 1])->save();
            $payment = new \App\Models\LeadVendorPayment();
            $payment->forceFill(['id' => (string) \Illuminate\Support\Str::uuid(), 'voucher_id' => $voucher->id,
                'lead_id' => $lead->id, 'vendor_id' => $vendor->id,
                'total_service_amount' => 17000, 'total_vendor_service_amount' => 12000, 'payment_status' => 'unpaid'])->save();
            echo json_encode(['synthetic_voucher' => $voucher->id, 'vendor_payment' => $payment->id]).PHP_EOL;
        });
    });
}
if (!\Illuminate\Support\Facades\Schema::hasColumn('lead_followups', 'pricing_revision')) {
    throw new RuntimeException('Pricing revision migration must complete first.');
}
$roles = [
    'super-admin' => \App\Models\UserType::SUPER_ADMIN,
    'admin' => \App\Models\UserType::ADMIN,
    'owner' => \App\Models\UserType::OWNER,
    'sales-manager' => \App\Models\UserType::SALES_MANAGER,
    'sales-executive' => \App\Models\UserType::SALES_EXECUTIVE,
    'operations' => \App\Models\UserType::OPERATIONS_MANAGER,
    'accounts' => \App\Models\UserType::ACCOUNTS_MANAGER,
    'hr' => \App\Models\UserType::HR,
];
\Illuminate\Database\Eloquent\Model::withoutEvents(static function () use ($roles): void {
    foreach ($roles as $slug => $role) {
        $type = \App\Models\UserType::firstOrCreate(['user_type' => $role], [
            'id' => (string) \Illuminate\Support\Str::uuid(), 'status' => 1,
        ]);
        $user = \App\Models\User::firstOrNew(['email' => $slug.'@wave1.invalid']);
        if ($user->exists) {
            continue;
        }
        $user->forceFill([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'QA '. $role, 'user_type_id' => $type->id, 'status' => 1,
            'password' => password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT),
        ])->save();
    }
});
echo json_encode(['database' => $connection->getDatabaseName(),
    'migrations' => $connection->table('migrations')->count(),
    'pricing_revision' => true, 'synthetic_users' => \App\Models\User::where('email', 'like', '%@wave1.invalid')->count()]).PHP_EOL;
if (!\App\Models\Client::where('email', 'browser@wave1.invalid')->exists()) {
    \Illuminate\Database\Eloquent\Model::withoutEvents(static function () use ($connection): void {
        $connection->transaction(static function (): void {
            $id = static fn () => (string) \Illuminate\Support\Str::uuid();
            $owner = \App\Models\User::where('email', 'sales-manager@wave1.invalid')->firstOrFail();
            $client = new \App\Models\Client();
            $client->forceFill(['id' => $id(), 'name' => 'Wave1 Synthetic Customer', 'email' => 'browser@wave1.invalid',
                'contact_number' => '+91-9999990001', 'status' => 1])->save();
            $lead = new \App\Models\Lead();
            $lead->forceFill(['id' => $id(), 'client_id' => $client->id,
                'representative_user_id' => $owner->id, 'service_ids' => [], 'product_ids' => [],
                'number_of_passengers' => 2])->save();
            $followup = new \App\Models\LeadFollowup();
            $followup->forceFill(['id' => $id(), 'lead_id' => $lead->id, 'followed_by' => $owner->id,
                'status' => 4, 'service_amount' => 18000, 'discount_amount' => 1000, 'total_amount' => 17000,
                'received_amount' => 8500, 'pricing_revision' => true,
                'service_ids' => [], 'extra_service_ids' => [],
                'service_details' => [['original_amount' => 18000, 'discount_amount' => 1000, 'final_amount' => 17000]],
                'next_followup_date' => now(), 'created_at' => '2026-08-21 10:00:00', 'updated_at' => '2026-08-21 10:00:00'])->save();
            $payment = new \App\Models\PaymentAuditTrail();
            $payment->forceFill(['id' => $id(), 'lead_followup_id' => $followup->id, 'paid_amount' => 8500,
                'paid_date' => '2026-08-21 10:30:00', 'payment_method' => 'Bank Transfer',
                'payment_status' => 1, 'created_by' => $owner->id])->save();
            echo json_encode(['synthetic_lead' => $lead->id, 'followup' => $followup->id]).PHP_EOL;
        });
    });
}
$lead = \App\Models\Lead::whereHas('client', fn ($query) => $query->where('email', 'browser@wave1.invalid'))->first();
if ($lead) {
    file_put_contents(getenv('CRM_WAVE1_QA_ROOT').'/fixtures.json', json_encode([
        'lead_id' => $lead->id, 'voucher_id' => $lead->vouchers()->value('id'),
    ]), LOCK_EX);
}
