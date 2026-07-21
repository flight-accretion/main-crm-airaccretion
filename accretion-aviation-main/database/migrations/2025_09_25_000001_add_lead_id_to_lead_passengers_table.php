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
            $table->uuid('lead_id')->nullable()->after('voucher_id');
            $table->string('registration_token')->nullable()->after('lead_id');
            $table->timestamp('token_expires_at')->nullable()->after('registration_token');
            
            // Make voucher_id nullable since passengers can exist before voucher creation
            $table->uuid('voucher_id')->nullable()->change();
            
            // Add indexes
            $table->index('lead_id');
            $table->index('registration_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_passengers', function (Blueprint $table) {
            $table->dropIndex(['lead_id']);
            $table->dropIndex(['registration_token']);
            $table->dropColumn(['lead_id', 'registration_token', 'token_expires_at']);
            
            // Restore voucher_id as required (but be careful with existing data)
            // $table->uuid('voucher_id')->nullable(false)->change();
        });
    }
};