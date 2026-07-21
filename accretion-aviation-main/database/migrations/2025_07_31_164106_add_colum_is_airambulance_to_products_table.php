<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumIsAirambulanceToProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('is_airambulance')
                ->nullable()
                ->default(0)
                ->after('is_private'); 
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_airambulance');
        });
    }
}
