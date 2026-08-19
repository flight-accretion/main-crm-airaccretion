<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCancellationAmountToVendorRefundsTable extends Migration
{
    public function up()
    {
        Schema::table('vendor_refunds', function (Blueprint $table) {

            $table->decimal(
                'cancellation_amount',
                15,
                2
            )
            ->default(0)
            ->after('ride_id');

        });
    }


    public function down()
    {
        Schema::table('vendor_refunds', function (Blueprint $table) {

            $table->dropColumn(
                'cancellation_amount'
            );

        });
    }
}