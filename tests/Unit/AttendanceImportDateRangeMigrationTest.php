<?php

namespace Tests\Unit;

use Tests\TestCase;

class AttendanceImportDateRangeMigrationTest extends TestCase
{
    public function test_base_attendance_import_migration_uses_date_range_columns(): void
    {
        $source = file_get_contents(
            database_path('migrations/2026_09_18_193505_create_attendance_import_tables.php')
        );

        $this->assertStringContainsString("\$table->date('from_date')", $source);
        $this->assertStringContainsString("\$table->date('to_date')", $source);
        $this->assertStringContainsString("['status', 'from_date', 'to_date']", $source);
        $this->assertStringNotContainsString("\$table->date('selected_date');", $source);
    }

    public function test_pending_range_migration_guards_existing_columns(): void
    {
        $source = file_get_contents(
            database_path('migrations/2026_09_19_122050_change_selected_date_to_attendance_date_range.php')
        );

        $this->assertStringContainsString("Schema::hasColumn('attendance_imports', 'from_date')", $source);
        $this->assertStringContainsString("Schema::hasColumn('attendance_imports', 'to_date')", $source);
        $this->assertStringContainsString("Schema::hasColumn('attendance_imports', 'selected_date')", $source);
        $this->assertStringContainsString("whereNull('from_date')", $source);
        $this->assertStringContainsString("whereNull('to_date')", $source);
        $this->assertStringContainsString("nullable()->change()", $source);
    }

    public function test_attendance_preview_validation_messages_use_date_range_language(): void
    {
        $source = file_get_contents(
            app_path('Http/Controllers/AttendanceImportController.php')
        );

        $this->assertStringContainsString('Please select the From Date.', $source);
        $this->assertStringContainsString('Please select the To Date.', $source);
        $this->assertStringContainsString('To Date cannot be before From Date.', $source);
        $this->assertStringNotContainsString('Please select the attendance date.', $source);
    }
}
