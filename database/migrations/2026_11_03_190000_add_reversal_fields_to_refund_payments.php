<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReversalFieldsToRefundPayments extends Migration
{
    public function up()
    {
        Schema::table('customer_refund_transactions', function (Blueprint $table) {
            $table->timestamp('reversed_at')->nullable()->index();
            $table->uuid('reversed_by')->nullable();
            $table->text('reversal_reason')->nullable();
        });

        Schema::table('vendor_refunds', function (Blueprint $table) {
            $table->timestamp('reversed_at')->nullable()->index();
            $table->uuid('reversed_by')->nullable();
            $table->text('reversal_reason')->nullable();
        });
    }

    public function down()
    {
        Schema::table('customer_refund_transactions', function (Blueprint $table) {
            $table->dropColumn(['reversed_at', 'reversed_by', 'reversal_reason']);
        });

        Schema::table('vendor_refunds', function (Blueprint $table) {
            $table->dropColumn(['reversed_at', 'reversed_by', 'reversal_reason']);
        });
    }
}
