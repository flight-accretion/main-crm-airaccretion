<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddP10SafePerformanceIndexes extends Migration
{
    private function definitions(): array
    {
        return [
            'leads' => [
                'p10_leads_status_created_idx' => ['status', 'created_at'],
                'p10_leads_rep_status_created_idx' => ['representative_user_id', 'status', 'created_at'],
                'p10_leads_crm_code_idx' => ['crm_lead_code'],
                'p10_leads_product_created_idx' => ['product_id', 'created_at'],
            ],
            'clients' => [
                'p10_clients_contact_number_idx' => ['contact_number'],
                'p10_clients_alternate_number_idx' => ['alternate_number'],
                'p10_clients_created_idx' => ['created_at'],
            ],
            'lead_followups' => [
                'p10_followups_next_status_idx' => ['next_followup_date', 'status'],
                'p10_followups_followed_status_idx' => ['followed_by', 'status'],
                'p10_followups_status_created_idx' => ['status', 'created_at'],
                'p10_followups_paid_date_idx' => ['paid_date'],
                'p10_followups_operation_case_created_idx' => ['operation_case_id', 'created_at'],
            ],
            'lead_rides' => [
                'p10_rides_from_date_idx' => ['from_date'],
                'p10_rides_to_date_idx' => ['to_date'],
                'p10_rides_is_tba_idx' => ['is_tba'],
                'p10_rides_status_idx' => ['status'],
                'p10_rides_lead_from_idx' => ['lead_id', 'from_date'],
            ],
            'payment_audit_trail' => [
                'p10_payment_status_created_idx' => ['payment_status', 'created_at'],
                'p10_payment_followup_status_idx' => ['lead_followup_id', 'payment_status'],
                'p10_payment_created_idx' => ['created_at'],
            ],
            'invoices' => [
                'p10_invoices_voucher_idx' => ['voucher_id'],
                'p10_invoices_status_created_idx' => ['status', 'created_at'],
                'p10_invoices_gst_idx' => ['gst_number'],
            ],
            'vouchers' => [
                'p10_vouchers_lead_idx' => ['lead_id'],
                'p10_vouchers_status_created_idx' => ['status', 'created_at'],
                'p10_vouchers_operation_user_idx' => ['operation_team_user_id'],
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->definitions() as $table => $definitions) {
            $table = $this->resolveTable($table);
            if (!Schema::hasTable($table)) {
                continue;
            }
            $indexes = DB::connection()->getDoctrineSchemaManager()->listTableIndexes($table);
            foreach ($definitions as $name => $columns) {
                foreach ($columns as $column) {
                    if (!Schema::hasColumn($table, $column)) {
                        continue 2;
                    }
                }
                foreach ($indexes as $index) {
                    // A full B-tree left prefix already supports this filter.
                    if ($index->getName() === $name || (
                        !$index->hasOption('where')
                        && (!$index->hasOption('lengths') || !array_filter($index->getOption('lengths')))
                        && array_slice($index->getColumns(), 0, count($columns)) === $columns
                    )) {
                        continue 2;
                    }
                }
                Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
                    $blueprint->index($columns, $name);
                });
                $indexes = DB::connection()->getDoctrineSchemaManager()->listTableIndexes($table);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->definitions() as $table => $definitions) {
            $table = $this->resolveTable($table);
            if (!Schema::hasTable($table)) {
                continue;
            }
            $indexes = DB::connection()->getDoctrineSchemaManager()->listTableIndexes($table);
            foreach (array_keys($definitions) as $name) {
                foreach ($indexes as $index) {
                    if ($index->getName() === $name) {
                        Schema::table($table, function (Blueprint $blueprint) use ($name) {
                            $blueprint->dropIndex($name);
                        });
                        break;
                    }
                }
            }
        }
    }

    private function resolveTable(string $table): string
    {
        if ($table === 'payment_audit_trail' && !Schema::hasTable($table)
            && Schema::hasTable('payment_audit_trails')) {
            return 'payment_audit_trails';
        }
        return $table;
    }
}
