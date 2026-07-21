<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRefundInvoiceIdToLeadRefundsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lead_refunds', function (Blueprint $table) {
            $table->string('refund_invoice_id', 50)->nullable()->after('refund_proof');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lead_refunds', function (Blueprint $table) {
            $table->dropColumn('refund_invoice_id');
        });
    }
}
