<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAgentPhoneToCallSummaryIntegrationsTable extends Migration
{
    public function up()
    {
        Schema::table('call_summary_integrations', function (Blueprint $table) {
            if (!Schema::hasColumn('call_summary_integrations', 'agent_phone')) {
                $table->string('agent_phone', 50)->nullable()->after('normalized_agent_name');
            }

            if (!Schema::hasColumn('call_summary_integrations', 'normalized_agent_phone')) {
                $table->string('normalized_agent_phone', 20)->nullable()->after('agent_phone');
            }
        });
    }

    public function down()
    {
        Schema::table('call_summary_integrations', function (Blueprint $table) {
            if (Schema::hasColumn('call_summary_integrations', 'normalized_agent_phone')) {
                $table->dropColumn('normalized_agent_phone');
            }

            if (Schema::hasColumn('call_summary_integrations', 'agent_phone')) {
                $table->dropColumn('agent_phone');
            }
        });
    }
}
