<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaymentMethodAndPaidDateToLeadFollowupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lead_followups', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('received_amount');
            $table->date('paid_date')->nullable()->after('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lead_followups', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'paid_date']);
        });
    }
}
