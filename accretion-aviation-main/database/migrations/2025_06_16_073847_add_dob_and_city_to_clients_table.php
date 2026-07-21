<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDobAndCityToClientsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up()
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('id');
            $table->string('city')->nullable()->after('date_of_birth');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['date_of_birth', 'city']);
        });
    }
}
