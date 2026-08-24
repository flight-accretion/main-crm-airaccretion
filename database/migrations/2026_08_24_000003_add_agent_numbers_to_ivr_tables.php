<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddAgentNumbersToIvrTables extends Migration
{
    public function up()
    {
        if (Schema::hasTable('ivr_agents') && !Schema::hasColumn('ivr_agents', 'vi_agent_number')) {
            Schema::table('ivr_agents', function (Blueprint $table) {
                $table->string('vi_agent_number', 20)->nullable()->after('vi_agent_name');
            });

            $this->backfillAgentNumbersFromMappedUsers();

            Schema::table('ivr_agents', function (Blueprint $table) {
                $table->unique('vi_agent_number');
            });
        }

        if (Schema::hasTable('ivr_call_logs') && !Schema::hasColumn('ivr_call_logs', 'agent_number')) {
            Schema::table('ivr_call_logs', function (Blueprint $table) {
                $table->string('agent_number', 20)->nullable()->after('agent_name');
                $table->index('agent_number');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('ivr_call_logs') && Schema::hasColumn('ivr_call_logs', 'agent_number')) {
            Schema::table('ivr_call_logs', function (Blueprint $table) {
                $table->dropIndex(['agent_number']);
                $table->dropColumn('agent_number');
            });
        }

        if (Schema::hasTable('ivr_agents') && Schema::hasColumn('ivr_agents', 'vi_agent_number')) {
            Schema::table('ivr_agents', function (Blueprint $table) {
                $table->dropUnique(['vi_agent_number']);
                $table->dropColumn('vi_agent_number');
            });
        }
    }

    private function backfillAgentNumbersFromMappedUsers(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $used = [];
        $rows = DB::table('ivr_agents')
            ->join('users', 'users.id', '=', 'ivr_agents.mapped_user_id')
            ->whereNull('ivr_agents.vi_agent_number')
            ->select('ivr_agents.id', 'users.contact_number')
            ->orderBy('ivr_agents.id')
            ->get();

        foreach ($rows as $row) {
            $number = $this->normalizePhone($row->contact_number);

            if ($number === null || isset($used[$number])) {
                continue;
            }

            $alreadyUsed = DB::table('ivr_agents')
                ->where('vi_agent_number', $number)
                ->where('id', '<>', $row->id)
                ->exists();

            if ($alreadyUsed) {
                continue;
            }

            DB::table('ivr_agents')
                ->where('id', $row->id)
                ->update(['vi_agent_number' => $number]);

            $used[$number] = true;
        }
    }

    private function normalizePhone($value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === '') {
            return null;
        }

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }
}
