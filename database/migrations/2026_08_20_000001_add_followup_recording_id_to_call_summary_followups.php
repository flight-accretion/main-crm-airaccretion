<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFollowupRecordingIdToCallSummaryFollowups extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('lead_followups')
            &&
            !Schema::hasColumn('lead_followups', 'followup_recording_id')
        ) {
            Schema::table('lead_followups', function (Blueprint $table) {
                $table
                    ->unsignedBigInteger('followup_recording_id')
                    ->nullable()
                    ->after('parent_followup_id');

                $table->unique(
                    [
                        'lead_id',
                        'followup_recording_id',
                    ],
                    'lead_followups_lead_recording_unique'
                );
            });
        }

        if (
            Schema::hasTable('call_summary_integrations')
            &&
            !Schema::hasColumn('call_summary_integrations', 'followup_recording_id')
        ) {
            Schema::table('call_summary_integrations', function (Blueprint $table) {
                $table
                    ->unsignedBigInteger('followup_recording_id')
                    ->nullable()
                    ->after('call_fingerprint');

                $table->index(
                    'followup_recording_id',
                    'call_summary_recording_idx'
                );
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('lead_followups')
            &&
            Schema::hasColumn('lead_followups', 'followup_recording_id')
        ) {
            Schema::table('lead_followups', function (Blueprint $table) {
                $table->dropUnique(
                    'lead_followups_lead_recording_unique'
                );

                $table->dropColumn(
                    'followup_recording_id'
                );
            });
        }

        if (
            Schema::hasTable('call_summary_integrations')
            &&
            Schema::hasColumn('call_summary_integrations', 'followup_recording_id')
        ) {
            Schema::table('call_summary_integrations', function (Blueprint $table) {
                $table->dropIndex(
                    'call_summary_recording_idx'
                );

                $table->dropColumn(
                    'followup_recording_id'
                );
            });
        }
    }
}
