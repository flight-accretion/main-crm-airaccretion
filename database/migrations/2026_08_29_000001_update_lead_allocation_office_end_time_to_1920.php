<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateLeadAllocationOfficeEndTimeTo1920 extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('lead_allocation_settings')) {
            return;
        }

        DB::table('lead_allocation_settings')
            ->where('office_end_time', '19:30')
            ->update([
                'office_end_time' => '19:20',
                'updated_at' => now(),
            ]);
    }

    public function down()
    {
        if (!Schema::hasTable('lead_allocation_settings')) {
            return;
        }

        DB::table('lead_allocation_settings')
            ->where('office_end_time', '19:20')
            ->update([
                'office_end_time' => '19:30',
                'updated_at' => now(),
            ]);
    }
}
