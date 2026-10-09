<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RemoveDuplicateCrmLeadCodeIndex extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('leads')) {
            return;
        }
        $indexes = DB::connection()->getDoctrineSchemaManager()->listTableIndexes('leads');
        $redundantName = 'p10_leads_crm_code_search_idx';
        if (!isset($indexes[$redundantName])) {
            return;
        }
        foreach ($indexes as $name => $index) {
            if ($name !== $redundantName
                && !$index->hasOption('where')
                && (!$index->hasOption('lengths') || !array_filter($index->getOption('lengths')))
                && $index->getColumns() === ['crm_lead_code']) {
                Schema::table('leads', fn (Blueprint $table) => $table->dropIndex($redundantName));
                return;
            }
        }
    }

    public function down(): void
    {
        // Do not recreate a redundant index when rolling back an optimization.
    }
}
