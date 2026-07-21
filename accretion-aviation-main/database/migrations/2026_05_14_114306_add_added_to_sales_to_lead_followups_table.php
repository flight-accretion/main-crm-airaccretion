<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAddedToSalesToLeadFollowupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lead_followups', function (Blueprint $table) {
            if (!Schema::hasColumn('lead_followups', 'added_to_sales')) {
                $table->boolean('added_to_sales')->default(false)->after('received_amount')->comment('True if extra payment was added to target sales');
            }
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
            if (Schema::hasColumn('lead_followups', 'added_to_sales')) {
                $table->dropColumn('added_to_sales');
            }
        });
    }
}
