<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class UnifiedLeadFollowupWorkspaceTest extends TestCase
{
    public function test_followup_workspace_exposes_existing_lead_updates_without_a_second_edit_page(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = file_get_contents($root . '/app/Http/Controllers/ClientController.php');
        $view = file_get_contents($root . '/resources/views/admin/pages/follow-ups/add-follow-up.blade.php');
        $actions = file_get_contents($root . '/resources/views/admin/pages/leads/partials/list-actions.blade.php');
        $workspaceCss = file_get_contents($root . '/public/assets/admin/css/crm/leads/lead-workspace.css');

        $this->assertStringContainsString("return redirect()->route('admin.leads.follow-up.create', \$lead);", $controller);
        $this->assertStringContainsString("return redirect()->route('admin.leads.follow-up.create', \$lead)->with('success', 'Lead details updated successfully.');", $controller);
        $this->assertStringNotContainsString('id="lead-details"', $view);
        $this->assertStringContainsString('id="client-details-editor"', $view);
        $this->assertStringContainsString("route('admin.leads.update', \$lead)", $view);
        $this->assertStringContainsString('Save Client Details', $view);
        $this->assertStringContainsString('id="followup-form"', $view);
        $this->assertStringContainsString('crm-lead-workspace', $view);
        $this->assertStringContainsString('lw-lead-hero', $view);
        $this->assertStringContainsString('lead-workspace-history', $view);
        $this->assertStringContainsString('lead-workspace-chat', $view);
        $this->assertStringContainsString('lead-workspace-right-rail', $view);
        $this->assertStringContainsString('lead-workspace-actions', $view);
        $this->assertStringContainsString('Transfer Lead', $view);
        $this->assertStringContainsString('Cancel Lead', $view);
        $this->assertStringContainsString('lead-history-toggle', $view);
        $this->assertStringContainsString('Place:', $view);
        $this->assertStringContainsString('Clear', $view);
        $this->assertStringContainsString('id="travel-details-editor"', $view);
        $this->assertStringContainsString('type="datetime-local"', $view);
        $this->assertStringContainsString('Save Travel Details', $view);
        $this->assertStringContainsString('id="services-details-editor"', $view);
        $this->assertStringContainsString('Save Services', $view);
        $this->assertStringContainsString('data-followup-note', $view);
        $this->assertStringContainsString('lead-workspace.css', $view);
        $this->assertStringContainsString('.lead-workspace-layout', $workspaceCss);
        $this->assertStringContainsString('"followup registration"', $workspaceCss);
        $this->assertStringContainsString('"services-editor chat"', $workspaceCss);
        $this->assertStringContainsString('lead-workspace-right-rail', $workspaceCss);
        $this->assertStringNotContainsString("route('admin.leads.edit'", $actions);
    }
}
