<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChangeFollowupRecordingIdToInteger extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('lead_followups')
            &&
            Schema::hasColumn('lead_followups', 'followup_recording_id')
        ) {
            $this->dropLeadRecordingUnique();

            $this->changeRecordingIdToInteger(
                'lead_followups'
            );

            Schema::table('lead_followups', function (Blueprint $table) {
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
            Schema::hasColumn('call_summary_integrations', 'followup_recording_id')
        ) {
            $this->dropCallSummaryRecordingIndex();

            $this->changeRecordingIdToInteger(
                'call_summary_integrations'
            );

            Schema::table('call_summary_integrations', function (Blueprint $table) {
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
            $this->dropLeadRecordingUnique();

            $this->changeRecordingIdToString(
                'lead_followups'
            );

            Schema::table('lead_followups', function (Blueprint $table) {
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
            Schema::hasColumn('call_summary_integrations', 'followup_recording_id')
        ) {
            $this->dropCallSummaryRecordingIndex();

            $this->changeRecordingIdToString(
                'call_summary_integrations'
            );

            Schema::table('call_summary_integrations', function (Blueprint $table) {
                $table->index(
                    'followup_recording_id',
                    'call_summary_recording_idx'
                );
            });
        }
    }

    private function changeRecordingIdToInteger(
        string $tableName
    ): void {

        if ($this->isPostgres()) {

            DB::statement(
                "ALTER TABLE {$tableName} " .
                "ALTER COLUMN followup_recording_id TYPE BIGINT " .
                "USING NULLIF(regexp_replace(followup_recording_id::text, '[^0-9]', '', 'g'), '')::BIGINT"
            );

            return;
        }


        Schema::table($tableName, function (Blueprint $table) {
            $table
                ->unsignedBigInteger('followup_recording_id')
                ->nullable()
                ->change();
        });
    }

    private function changeRecordingIdToString(
        string $tableName
    ): void {

        if ($this->isPostgres()) {

            DB::statement(
                "ALTER TABLE {$tableName} " .
                "ALTER COLUMN followup_recording_id TYPE VARCHAR(191) " .
                "USING followup_recording_id::text"
            );

            return;
        }


        Schema::table($tableName, function (Blueprint $table) {
            $table
                ->string('followup_recording_id', 191)
                ->nullable()
                ->change();
        });
    }

    private function dropLeadRecordingUnique(): void
    {
        if ($this->isPostgres()) {

            DB::statement(
                'ALTER TABLE lead_followups DROP CONSTRAINT IF EXISTS lead_followups_lead_recording_unique'
            );

            return;
        }


        Schema::table('lead_followups', function (Blueprint $table) {
            $table->dropUnique(
                'lead_followups_lead_recording_unique'
            );
        });
    }

    private function dropCallSummaryRecordingIndex(): void
    {
        if ($this->isPostgres()) {

            DB::statement(
                'DROP INDEX IF EXISTS call_summary_recording_idx'
            );

            return;
        }


        Schema::table('call_summary_integrations', function (Blueprint $table) {
            $table->dropIndex(
                'call_summary_recording_idx'
            );
        });
    }

    private function isPostgres(): bool
    {
        return Schema::getConnection()->getDriverName()
            === 'pgsql';
    }
}
