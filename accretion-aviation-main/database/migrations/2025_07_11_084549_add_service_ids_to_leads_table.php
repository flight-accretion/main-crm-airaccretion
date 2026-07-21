<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddServiceIdsToLeadsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
     public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->json('service_ids')
                ->nullable()
                ->after('representative_user_id');
            $table->dropColumn('product_ids');   
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('service_ids');
        });
    }
}
