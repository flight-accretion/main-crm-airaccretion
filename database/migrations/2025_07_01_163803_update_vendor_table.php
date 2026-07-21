<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateVendorTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->json('product_ids')->nullable()->after('id')->comment('List of associated product UUIDs');
            $table->json('service_ids')->nullable()->after('product_ids')->comment('List of associated service UUIDs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['product_ids', 'service_ids']);
        });
    }
}
