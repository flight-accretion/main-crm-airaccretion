<?php

// Navigation fixtures never query the configured CRM database.
app()->instance(App\Services\SidebarPendingCounts::class, new class extends App\Services\SidebarPendingCounts {
    public function countFor(?App\Models\User $user, string $queue): ?int
    {
        if (!getenv('CRM_NAV_PENDING_COUNTS')) return null;
        return ['customer_refund' => 12, 'vendor_refund' => 3, 'account_vendor_refund' => 4,
            'payment_approval' => 25, 'invoice' => 7][$queue] ?? null;
    }
});
