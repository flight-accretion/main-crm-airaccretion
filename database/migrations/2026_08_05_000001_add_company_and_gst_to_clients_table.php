<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('clients', 'company_name')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('company_name')->nullable();
            });
        }

        if (!Schema::hasColumn('clients', 'gst_number')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('gst_number')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('clients', 'gst_number')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('gst_number');
            });
        }

        if (Schema::hasColumn('clients', 'company_name')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('company_name');
            });
        }
    }
};
