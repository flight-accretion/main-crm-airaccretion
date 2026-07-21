<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropColumnServiceIdFromExtraServices extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('extra_services', function (Blueprint $table) {
            if (Schema::hasColumn('extra_services', 'service_id')) {
                $table->dropColumn('service_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('extra_services', function (Blueprint $table) {
            if (!Schema::hasColumn('extra_services', 'service_id')) {
                $table->uuid('service_id')->nullable();
            }
        });
    }
}
