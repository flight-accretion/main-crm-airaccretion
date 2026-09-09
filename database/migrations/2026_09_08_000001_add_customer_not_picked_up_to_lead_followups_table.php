<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddCustomerNotPickedUpToLeadFollowupsTable extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('lead_followups')
            && !Schema::hasColumn(
                'lead_followups',
                'customer_not_picked_up'
            )
        ) {
            Schema::table('lead_followups', function (Blueprint $table) {
                $table->boolean('customer_not_picked_up')
                    ->default(false);
            });
        }

        if (
            Schema::hasColumn('lead_followups', 'customer_not_picked_up')
            && Schema::hasColumn('lead_followups', 'contact_outcome')
        ) {
            DB::table('lead_followups')
                ->where('contact_outcome', 'no_answer')
                ->update([
                    'customer_not_picked_up' =>
                        true,
                ]);
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('lead_followups')
            && Schema::hasColumn(
                'lead_followups',
                'customer_not_picked_up'
            )
        ) {
            Schema::table('lead_followups', function (Blueprint $table) {
                $table->dropColumn('customer_not_picked_up');
            });
        }
    }
}
