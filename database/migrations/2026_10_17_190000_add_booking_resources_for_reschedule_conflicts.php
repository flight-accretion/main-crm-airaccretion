<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ride_booking_resources', function (Blueprint $table) {
            $table->string('resource_key', 80)->primary();
            $table->timestamps();
        });
        Schema::table('lead_rides', function (Blueprint $table) {
            $table->string('booking_resource_key', 80)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('lead_rides', function (Blueprint $table) {
            $table->dropIndex(['booking_resource_key']);
            $table->dropColumn('booking_resource_key');
        });
        Schema::dropIfExists('ride_booking_resources');
    }
};
