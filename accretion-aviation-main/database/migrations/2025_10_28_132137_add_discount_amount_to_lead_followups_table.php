<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDiscountAmountToLeadFollowupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lead_followups', function (Blueprint $table) {
            $table->decimal('discount_amount', 10, 2)->nullable()->after('total_amount');
            $table->decimal('service_amount', 10, 2)->nullable()->after('discount_amount');
            $table->json('service_details')->nullable()->after('service_amount');

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
            $table->dropColumn(['discount_amount', 'service_amount', 'service_details']);
        });
    }
}
