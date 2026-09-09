<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSourceTypeToEmailLeadLogsTable extends Migration
{
    public function up()
    {
        if (
            !Schema::hasTable('email_lead_logs')
            || Schema::hasColumn('email_lead_logs', 'source_type')
        ) {
            return;
        }

        Schema::table(
            'email_lead_logs',
            function (Blueprint $table) {
                $table
                    ->string(
                        'source_type',
                        50
                    )
                    ->default('email');

                $table->index(
                    'source_type'
                );
            }
        );
    }

    public function down()
    {
        if (
            !Schema::hasTable('email_lead_logs')
            || !Schema::hasColumn('email_lead_logs', 'source_type')
        ) {
            return;
        }

        Schema::table(
            'email_lead_logs',
            function (Blueprint $table) {
                $table->dropIndex([
                    'source_type',
                ]);

                $table->dropColumn(
                    'source_type'
                );
            }
        );
    }
}
