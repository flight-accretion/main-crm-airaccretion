<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddContactOutcomeToLeadFollowupsTable extends Migration
{
    public function up(): void
    {
        Schema::table('lead_followups', function (Blueprint $table) {
            $table->string('contact_outcome', 30)
                ->nullable()
                ->after('followup_note');

            $table->index(
                ['lead_id', 'contact_outcome', 'created_at'],
                'lead_followups_contact_outcome_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('lead_followups', function (Blueprint $table) {
            $table->dropIndex('lead_followups_contact_outcome_idx');
            $table->dropColumn('contact_outcome');
        });
    }
}
