<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentAuditTrailTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('payment_audit_trail', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_followup_id')->nullable();
            $table->decimal('paid_amount', 15, 2)->nullable();
            $table->timestamp('paid_date')->nullable();
            $table->string('payment_method')->nullable()->comment('e.g., UPI, Cash, Bank Transfer');
            $table->text('file')->nullable()->comment('Attachment or receipt file path');
            $table->text('narration')->nullable()->comment('Narration or notes about the payment');
            $table->integer('payment_status')->nullable();
            $table->uuid('created_by')->nullable()->comment('User who created this audit entry');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_audit_trail');
    }
}
