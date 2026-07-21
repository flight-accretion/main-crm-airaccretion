<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeadVendorPaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
     public function up(): void
    {
        Schema::create('lead_vendor_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('voucher_id')->nullable();
            $table->uuid('lead_id')->nullable();
            $table->uuid('vendor_id')->nullable();
            $table->decimal('total_service_amount', 10, 2)->nullable();
            $table->decimal('total_vendor_service_amount', 10, 2)->nullable();
            $table->string('payment_status')->nullable(); // Paid, Pending, Failed
            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_vendor_payments');
    }
}
