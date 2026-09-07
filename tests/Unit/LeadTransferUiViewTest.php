<?php

namespace Tests\Unit;

use Tests\TestCase;

class LeadTransferUiViewTest extends TestCase
{
    public function test_sidebar_has_live_lead_transfer_pending_count_and_notification_sound(): void
    {
        $source = file_get_contents(
            resource_path('views/admin/layouts/header.blade.php')
        );

        $this->assertStringContainsString(
            'lead-transfer-pending-count',
            $source
        );
        $this->assertStringContainsString(
            'admin.leads.transfers.pending-count',
            $source
        );
        $this->assertStringContainsString(
            'refreshLeadTransferPendingCount',
            $source
        );
        $this->assertStringContainsString(
            'playLeadTransferNotificationSound',
            $source
        );
    }

    public function test_report_view_keeps_request_flow_and_adds_self_transfer_flow(): void
    {
        $source = file_get_contents(
            resource_path('views/admin/report/admin_report.blade.php')
        );

        $this->assertStringContainsString(
            'admin.leads.transfer.bulk',
            $source
        );
        $this->assertStringContainsString(
            'admin.leads.transfer.offer-bulk',
            $source
        );
        $this->assertStringContainsString(
            'lead-transfer-offer-modal',
            $source
        );
        $this->assertStringContainsString(
            'lead-transfer-to-user',
            $source
        );
        $this->assertStringContainsString(
            'lead-transfer-reason',
            $source
        );
    }
}
