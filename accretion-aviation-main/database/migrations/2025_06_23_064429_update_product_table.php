<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateProductTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('name', 'product');
            $table->renameColumn('details', 'description');
            $table->renameColumn('price', 'amount');
            $table->json('service_ids')->nullable()->after('terms_conditions');
            $table->string('display_image', 255)->nullable()->after('service_ids');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('product', 'name');
            $table->dropColumn('service_ids');
        });
    }
}
