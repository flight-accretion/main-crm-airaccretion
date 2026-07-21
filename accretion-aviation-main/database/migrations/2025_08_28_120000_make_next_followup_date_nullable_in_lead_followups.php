<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class MakeNextFollowupDateNullableInLeadFollowups extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Use raw SQL per connection driver to avoid Doctrine DBAL column type mapping issues.
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE lead_followups ALTER COLUMN next_followup_date DROP NOT NULL');
        } elseif ($driver === 'mysql') {
            // MySQL alters require the column definition; keep TIMESTAMP as original migration used.
            DB::statement('ALTER TABLE lead_followups MODIFY next_followup_date TIMESTAMP NULL');
        } else {
            // Fallback: attempt schema change (may require doctrine/dbal)
            Schema::table('lead_followups', function (Blueprint $table) {
                $table->dateTime('next_followup_date')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE lead_followups ALTER COLUMN next_followup_date SET NOT NULL');
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE lead_followups MODIFY next_followup_date TIMESTAMP NOT NULL');
        } else {
            Schema::table('lead_followups', function (Blueprint $table) {
                $table->dateTime('next_followup_date')->nullable(false)->change();
            });
        }
    }
}
