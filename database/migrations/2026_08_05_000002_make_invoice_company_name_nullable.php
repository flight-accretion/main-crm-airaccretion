<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('invoices', 'company_name')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('company_name', 255)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'company_name')) {
            DB::table('invoices')
                ->whereNull('company_name')
                ->update(['company_name' => '']);

            Schema::table('invoices', function (Blueprint $table) {
                $table->string('company_name', 255)->nullable(false)->change();
            });
        }
    }
};
