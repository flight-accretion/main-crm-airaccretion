<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeadAuditTrailTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lead_audit_trail', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->string('field_name', 100);
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->uuid('changed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index('lead_id');
            $table->index('changed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_audit_trail');
    }
}
