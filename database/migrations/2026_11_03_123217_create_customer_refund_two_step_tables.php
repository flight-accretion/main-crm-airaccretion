<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerRefundTwoStepTables extends Migration
{
    public function up(): void
    {
        Schema::create('customer_refund_settlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->unique();
            $table->uuid('lead_followup_id');
            $table->uuid('lead_refund_id')->unique();
            $table->decimal('approved_paid', 14, 2);
            $table->decimal('cancellation_amount', 14, 2);
            $table->decimal('refund_due', 14, 2);
            $table->text('reason');
            $table->uuid('assessed_by');
            $table->timestamp('assessed_at');
            $table->timestamps();
            $table->index('lead_followup_id');
        });

        Schema::create('customer_refund_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('settlement_id')->index();
            $table->uuid('request_id')->unique();
            $table->decimal('amount', 14, 2);
            $table->string('refund_type', 100);
            $table->date('refund_date');
            $table->string('refund_proof', 500);
            $table->text('remarks')->nullable();
            $table->uuid('created_by');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_refund_transactions');
        Schema::dropIfExists('customer_refund_settlements');
    }

}
