<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operation_case_activities')) {
            return;
        }

        Schema::create('operation_case_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operation_case_id');
            $table->uuid('lead_id');
            $table->uuid('user_id')->nullable();
            $table->string('action', 60);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['operation_case_id', 'created_at']);
            $table->index(['lead_id', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_case_activities');
    }
};
