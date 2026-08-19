<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVendorRefundsTable extends Migration
{
    public function up()
    {
        Schema::create('vendor_refunds', function (Blueprint $table) {

            $table->uuid('id')->primary();

            /*
            |--------------------------------------------------------------------------
            | Stable business relationship
            |--------------------------------------------------------------------------
            */

            $table->uuid('lead_id');

            /*
            |--------------------------------------------------------------------------
            | Exact vendor-payment relationship
            |--------------------------------------------------------------------------
            */

            $table->uuid('lead_vendor_payment_id');

            $table->uuid('vendor_id');

            /*
            |--------------------------------------------------------------------------
            | Optional source ride
            |--------------------------------------------------------------------------
            */

            $table->uuid('ride_id')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Refund transaction
            |--------------------------------------------------------------------------
            */

            $table->decimal('refund_amount', 15, 2)->default(0);

            $table->dateTime('refund_date')->nullable();

            $table->string('refund_type', 100)->nullable();

            $table->text('refund_reason')->nullable();

            $table->string('refund_proof')->nullable();

            /*
            |--------------------------------------------------------------------------
            | ₹0 settlement marker
            |--------------------------------------------------------------------------
            |
            | True means:
            | vendor had no payment to return and accounts has explicitly
            | closed/recorded that situation.
            |
            */

            $table->boolean('no_refund_required')
                ->default(false);

            /*
            |--------------------------------------------------------------------------
            | Audit
            |--------------------------------------------------------------------------
            */

            $table->uuid('created_by')->nullable();

            $table->timestamps();


            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index('lead_id');

            $table->index('lead_vendor_payment_id');

            $table->index('vendor_id');

            $table->index('ride_id');
        });
    }


    public function down()
    {
        Schema::dropIfExists('vendor_refunds');
    }
}