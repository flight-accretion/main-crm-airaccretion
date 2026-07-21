<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('notification_masters', function (Blueprint $table) {
            $table->string('contact_country_code', 8)->nullable()->after('mobile_number');
            $table->unsignedBigInteger('country_id')->nullable()->after('contact_country_code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('notification_masters', function (Blueprint $table) {
            $table->dropColumn(['contact_country_code', 'country_id']);
        });
    }
};
