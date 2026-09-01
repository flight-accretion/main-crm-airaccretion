<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasTable('lead_followups')
            || !Schema::hasColumn('lead_followups', 'followed_by')
        ) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(
                'ALTER TABLE lead_followups ALTER COLUMN followed_by DROP NOT NULL'
            );

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                'ALTER TABLE lead_followups MODIFY followed_by CHAR(36) NULL'
            );
        }
    }

    public function down(): void
    {
        if (
            !Schema::hasTable('lead_followups')
            || !Schema::hasColumn('lead_followups', 'followed_by')
        ) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(
                'ALTER TABLE lead_followups ALTER COLUMN followed_by SET NOT NULL'
            );

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                'ALTER TABLE lead_followups MODIFY followed_by CHAR(36) NOT NULL'
            );
        }
    }
};
