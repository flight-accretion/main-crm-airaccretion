<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('leads') || !Schema::hasColumn('leads', 'occasion')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE leads ALTER COLUMN occasion TYPE VARCHAR(255)');
            DB::statement('ALTER TABLE leads ALTER COLUMN occasion DROP NOT NULL');
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE leads MODIFY occasion VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('leads') || !Schema::hasColumn('leads', 'occasion')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE leads ALTER COLUMN occasion TYPE VARCHAR(50)');
            DB::statement('ALTER TABLE leads ALTER COLUMN occasion DROP NOT NULL');
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE leads MODIFY occasion VARCHAR(50) NULL');
        }
    }
};
