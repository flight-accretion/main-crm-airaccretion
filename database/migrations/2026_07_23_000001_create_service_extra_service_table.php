<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_extra_service', function (Blueprint $table) {
            $table->uuid('service_id');
            $table->uuid('extra_service_id');
            $table->timestamps();

            $table->primary(['service_id', 'extra_service_id']);
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
            $table->foreign('extra_service_id')->references('id')->on('extra_services')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_extra_service');
    }
};
