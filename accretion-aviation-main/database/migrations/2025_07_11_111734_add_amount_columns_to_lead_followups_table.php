<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAmountColumnsToLeadFollowupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
       public function up(): void
    {
        Schema::table('lead_followups', function (Blueprint $table) {
            $table->decimal('total_amount', 15, 2)->nullable()->after('lead_id');
            $table->decimal('received_amount', 15, 2)->nullable()->after('total_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_followups', function (Blueprint $table) {
            $table->dropColumn(['total_amount', 'received_amount']);
        });
    }

}
