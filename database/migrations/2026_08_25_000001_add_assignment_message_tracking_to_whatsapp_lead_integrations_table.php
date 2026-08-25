<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAssignmentMessageTrackingToWhatsappLeadIntegrationsTable extends Migration
{
    public function up()
    {
        Schema::table(
            'whatsapp_lead_integrations',
            function (Blueprint $table) {
                $table->timestamp('assignment_message_sent_at')
                    ->nullable()
                    ->after('assigned_at');

                $table->text('assignment_message_error')
                    ->nullable()
                    ->after('assignment_message_sent_at');
            }
        );
    }

    public function down()
    {
        Schema::table(
            'whatsapp_lead_integrations',
            function (Blueprint $table) {
                $table->dropColumn([
                    'assignment_message_sent_at',
                    'assignment_message_error',
                ]);
            }
        );
    }
}
