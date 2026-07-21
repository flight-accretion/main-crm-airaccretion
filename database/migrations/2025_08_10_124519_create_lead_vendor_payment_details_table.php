<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeadVendorPaymentDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('lead_vendor_payment_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_vendor_payment_id');
            $table->uuid('service_id')->nullable();
            $table->decimal('service_amount', 10, 2)->nullable();
            $table->decimal('vendor_service_amount', 10, 2)->nullable();
            $table->boolean('is_extra_service')->default(false);
            $table->integer('status')->default(1); // 1 = Active, 0 = Inactive
            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_vendor_payment_details');
    }
}
