<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lead_passengers', function (Blueprint $table) {
            $table->boolean('is_handler')->nullable()->default(false)->after('back_document');
            $table->boolean('is_additional_person')->nullable()->default(false)->after('is_handler');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_passengers', function (Blueprint $table) {
            $table->dropColumn(['is_handler', 'is_additional_person']);
        });
    }
};
