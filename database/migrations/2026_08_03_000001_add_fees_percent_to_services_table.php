<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddFeesPercentToServicesTable extends Migration
{
    public function up()
    {
        Schema::table('services', function (Blueprint $table) {
            if (!Schema::hasColumn('services', 'fees_percent') && !Schema::hasColumn('services', 'gst_percent')) {
                $table->decimal('fees_percent', 5, 2)->default(0)->after('service_amount');
            }
        });

        if (!Schema::hasColumn('services', 'fees_percent') && Schema::hasColumn('services', 'gst_percent')) {
            DB::statement('ALTER TABLE services RENAME COLUMN gst_percent TO fees_percent');
        }
    }

    public function down()
    {
        Schema::table('services', function (Blueprint $table) {
            if (Schema::hasColumn('services', 'fees_percent')) {
                $table->dropColumn('fees_percent');
            }
        });
    }
}
