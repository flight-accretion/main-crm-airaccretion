<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddServiceIdsToLeadFollowupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up(): void
    {
        Schema::table('lead_followups', function (Blueprint $table) {
            $table->json('service_ids')->nullable()->after('id');
            $table->json('extra_service_ids')->nullable()->after('service_ids');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_followups', function (Blueprint $table) {
            $table->dropColumn(['service_ids', 'extra_service_ids']);
        });
    }
}
