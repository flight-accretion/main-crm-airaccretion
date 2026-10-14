<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ride_reminder_logs')) {
            return;
        }

        // Keep legacy history, but let the current hours_before/channel writer omit obsolete fields.
        foreach (['reminder_type', 'template_name', 'sent_at'] as $column) {
            if (!Schema::hasColumn('ride_reminder_logs', $column)) {
                continue;
            }
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE ride_reminder_logs ALTER COLUMN "' . $column . '" DROP NOT NULL');
            } else {
                // Preserve the existing column type/default while relaxing only nullability.
                $manager = DB::connection()->getDoctrineSchemaManager();
                $before = $manager->introspectTable('ride_reminder_logs');
                $after = clone $before;
                $after->getColumn($column)->setNotnull(false);
                $diff = $manager->createComparator()->compareTables($before, $after);
                foreach ($manager->getDatabasePlatform()->getAlterTableSQL($diff) as $sql) {
                    DB::statement($sql);
                }
            }
        }
    }

    public function down(): void
    {
        // Reintroducing NOT NULL would break current deliveries and existing pending rows.
    }
};
