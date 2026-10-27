<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ClientDirectoryRedesignTest extends TestCase
{
    public function test_client_directory_keeps_dynamic_summary_filters_and_drawers(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/resources/views/admin/pages/clients/index-client.blade.php');
        $controller = file_get_contents($root . '/app/Http/Controllers/ClientController.php');

        self::assertStringContainsString('crm-client-stats', $view);
        self::assertStringContainsString('name="status"', $view);
        self::assertStringContainsString('name="profile"', $view);
        self::assertStringContainsString('data-client-view-drawer', $view);
        self::assertStringContainsString('data-client-add-panel', $view);
        self::assertStringContainsString("route('admin.client.data'", $view);
        self::assertStringContainsString("'incomplete' =>", $controller);
        self::assertStringContainsString("'lead_summary' =>", $controller);
    }
}
