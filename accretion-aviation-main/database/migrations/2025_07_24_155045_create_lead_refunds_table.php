<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeadRefundsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
     public function up(): void
    {
        Schema::create('lead_refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_followup_id');
            $table->decimal('original_amount', 15, 2)->nullable();
            $table->decimal('refund_amount', 15, 2)->nullable();
            $table->string('refund_type')->nullable(); // e.g., bank transfer, UPI, cash
            $table->dateTime('refund_date')->nullable();
            $table->text('refund_reason')->nullable();
            $table->string('refund_proof')->nullable();
            $table->integer('status')->nullable(); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_refunds');
    }
}
