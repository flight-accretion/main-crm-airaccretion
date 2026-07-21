<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExtraServiceIdsColumnToVendors extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->json('extra_service_ids')->nullable()->after('service_ids');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('extra_service_ids');
        });
    }
}
