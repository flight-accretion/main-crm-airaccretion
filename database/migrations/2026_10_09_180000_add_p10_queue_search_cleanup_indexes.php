<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddP10QueueSearchCleanupIndexes extends Migration
{
    public function up(): void
    {
        $this->addIndexIfColumnsExist('jobs', 'p10_jobs_queue_available_idx', ['queue', 'available_at']);
        $this->addIndexIfColumnsExist('jobs', 'p10_jobs_queue_reserved_idx', ['queue', 'reserved_at']);
        $this->addIndexIfColumnsExist('jobs', 'p10_jobs_created_idx', ['created_at']);

        $this->addIndexIfColumnsExist('failed_jobs', 'p10_failed_jobs_failed_at_idx', ['failed_at']);

        $this->addIndexIfColumnsExist('leads', 'p10_leads_crm_code_search_idx', ['crm_lead_code']);
        $this->addIndexIfColumnsExist('clients', 'p10_clients_phone_email_idx', ['contact_number', 'email']);

        $this->addIndexIfColumnsExist('invoices', 'p10_invoices_invoice_id_idx', ['invoice_id']);
        $this->addIndexIfColumnsExist('vouchers', 'p10_vouchers_registration_token_idx', ['registration_token']);

        $this->addIndexIfColumnsExist('google_chat_events', 'p10_google_chat_events_created_idx', ['created_at']);
        $this->addIndexIfColumnsExist('google_chat_events', 'p10_google_chat_events_status_created_idx', ['status', 'created_at']);

        $this->addIndexIfColumnsExist('whatsapp_messages', 'p10_whatsapp_messages_conversation_created_idx', ['conversation_id', 'created_at']);
        $this->addIndexIfColumnsExist('whatsapp_messages', 'p10_whatsapp_messages_created_idx', ['created_at']);

        $this->addIndexIfColumnsExist('lead_chat_messages', 'p10_lead_chat_messages_conversation_created_idx', ['conversation_id', 'created_at']);
        $this->addIndexIfColumnsExist('lead_chat_messages', 'p10_lead_chat_messages_lead_created_idx', ['lead_id', 'created_at']);
    }

    public function down(): void
    {
        foreach ([
            'p10_jobs_queue_available_idx' => 'jobs',
            'p10_jobs_queue_reserved_idx' => 'jobs',
            'p10_jobs_created_idx' => 'jobs',
            'p10_failed_jobs_failed_at_idx' => 'failed_jobs',
            'p10_leads_crm_code_search_idx' => 'leads',
            'p10_clients_phone_email_idx' => 'clients',
            'p10_invoices_invoice_id_idx' => 'invoices',
            'p10_vouchers_registration_token_idx' => 'vouchers',
            'p10_google_chat_events_created_idx' => 'google_chat_events',
            'p10_google_chat_events_status_created_idx' => 'google_chat_events',
            'p10_whatsapp_messages_conversation_created_idx' => 'whatsapp_messages',
            'p10_whatsapp_messages_created_idx' => 'whatsapp_messages',
            'p10_lead_chat_messages_conversation_created_idx' => 'lead_chat_messages',
            'p10_lead_chat_messages_lead_created_idx' => 'lead_chat_messages',
        ] as $indexName => $table) {
            $this->dropIndexIfExists($table, $indexName);
        }
    }

    private function addIndexIfColumnsExist(string $table, string $indexName, array $columns): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return;
            }
        }

        if ($this->indexExists($indexName) || $this->equivalentIndexExists($table, $columns)) {
            return;
        }

        $wrappedTable = $this->wrapIdentifier($table);
        $wrappedIndex = $this->wrapIdentifier($indexName);
        $wrappedColumns = implode(', ', array_map([$this, 'wrapIdentifier'], $columns));

        DB::statement("CREATE INDEX {$wrappedIndex} ON {$wrappedTable} ({$wrappedColumns})");
    }

    private function equivalentIndexExists(string $table, array $columns): bool
    {
        foreach (DB::connection()->getDoctrineSchemaManager()->listTableIndexes($table) as $index) {
            if (!$index->hasOption('where')
                && (!$index->hasOption('lengths') || !array_filter($index->getOption('lengths')))
                && array_slice($index->getColumns(), 0, count($columns)) === $columns) {
                return true;
            }
        }
        return false;
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!$this->indexExists($indexName)) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'DROP INDEX '
                . $this->wrapIdentifier($indexName)
                . ' ON '
                . $this->wrapIdentifier($table)
            );

            return;
        }

        DB::statement('DROP INDEX ' . $this->wrapIdentifier($indexName));
    }

    private function indexExists(string $indexName): bool
    {
        if (DB::getDriverName() === 'pgsql') {
            return (bool) DB::selectOne(
                'select 1 from pg_indexes where indexname = ? limit 1',
                [$indexName]
            );
        }

        if (DB::getDriverName() === 'mysql') {
            return (bool) DB::selectOne(
                'select 1 from information_schema.statistics where table_schema = database() and index_name = ? limit 1',
                [$indexName]
            );
        }

        return false;
    }

    private function wrapIdentifier(string $identifier): string
    {
        if (DB::getDriverName() === 'mysql') {
            return '`' . str_replace('`', '``', $identifier) . '`';
        }

        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
