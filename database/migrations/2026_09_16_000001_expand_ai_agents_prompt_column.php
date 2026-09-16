<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasTable('ai_agents')
            || !Schema::hasColumn('ai_agents', 'prompt')
        ) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if (
            in_array(
                $driver,
                [
                    'mysql',
                    'mariadb',
                ],
                true
            )
        ) {
            DB::statement(
                'ALTER TABLE ai_agents MODIFY prompt MEDIUMTEXT NOT NULL'
            );
        }
    }

    public function down(): void
    {
        if (
            !Schema::hasTable('ai_agents')
            || !Schema::hasColumn('ai_agents', 'prompt')
        ) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if (
            in_array(
                $driver,
                [
                    'mysql',
                    'mariadb',
                ],
                true
            )
        ) {
            DB::statement(
                'ALTER TABLE ai_agents MODIFY prompt TEXT NOT NULL'
            );
        }
    }
};
