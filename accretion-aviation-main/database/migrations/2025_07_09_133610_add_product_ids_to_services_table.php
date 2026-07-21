<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProductIdsToServicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->json('product_ids')
                ->nullable()
                ->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('product_ids');
        });
    }
}
