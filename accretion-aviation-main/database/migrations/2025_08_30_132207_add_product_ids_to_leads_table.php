<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProductIdsToLeadsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->json('product_ids')->nullable()->after('service_ids');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('product_ids');
        });
    }
}
