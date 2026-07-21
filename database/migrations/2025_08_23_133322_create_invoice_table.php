<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoiceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('invoice_id', 50)->unique(); // e.g. INV-2025-0001
            $table->uuid('voucher_id');
            $table->string('company_name', 255);
            $table->string('gst_number', 50)->nullable();
            $table->text('billing_address')->nullable();
            $table->integer('status')->default(1); // 1: active, 0: inactive ,2:Completed
            // Default timestamps
            $table->timestamps(); // creates created_at & updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
}
