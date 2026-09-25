<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCompletionFieldsToVendorRefundsTable extends Migration
{
    public function up()
    {
        Schema::table('vendor_refunds', function (Blueprint $table) {
            if (!Schema::hasColumn('vendor_refunds', 'status')) {
                $table
                    ->unsignedTinyInteger('status')
                    ->default(1)
                    ->after('no_refund_required')
                    ->index('vendor_refunds_status_index');
            }

            if (!Schema::hasColumn('vendor_refunds', 'completed_at')) {
                $table
                    ->timestamp('completed_at')
                    ->nullable()
                    ->after('status');
            }

            if (!Schema::hasColumn('vendor_refunds', 'completed_by')) {
                $table
                    ->uuid('completed_by')
                    ->nullable()
                    ->after('completed_at');
            }
        });
    }


    public function down()
    {
        Schema::table('vendor_refunds', function (Blueprint $table) {
            foreach (
                [
                    'completed_by',
                    'completed_at',
                    'status',
                ] as $column
            ) {
                if (Schema::hasColumn('vendor_refunds', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
