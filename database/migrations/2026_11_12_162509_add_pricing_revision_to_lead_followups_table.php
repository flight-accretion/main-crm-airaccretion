<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPricingRevisionToLeadFollowupsTable extends Migration
{
     public function up(): void
    {
        Schema::table('lead_followups', function (Blueprint $table) {
            $table->boolean('pricing_revision')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lead_followups', function (Blueprint $table) {
            $table->dropColumn('pricing_revision');
        });
    }
}
