<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChangeSelectedDateToAttendanceDateRange extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('attendance_imports', 'from_date')) {
            Schema::table(
                'attendance_imports',
                function (Blueprint $table) {

                $table->date('from_date')
                    ->nullable();
                }
            );
        }

        if (!Schema::hasColumn('attendance_imports', 'to_date')) {
            Schema::table(
                'attendance_imports',
                function (Blueprint $table) {
                $table->date('to_date')
                    ->nullable();
            }
            );
        }

        if (Schema::hasColumn('attendance_imports', 'selected_date')) {
            DB::table('attendance_imports')
                ->whereNull('from_date')
                ->update([
                    'from_date' =>
                        DB::raw('selected_date'),
                ]);

            DB::table('attendance_imports')
                ->whereNull('to_date')
                ->update([
                    'to_date' =>
                        DB::raw('selected_date'),
                ]);

            Schema::table(
                'attendance_imports',
                function (Blueprint $table) {
                    $table->date('selected_date')->nullable()->change();
                }
            );
        }
    }

    public function down()
    {
        $columns = [];

        if (Schema::hasColumn('attendance_imports', 'from_date')) {
            $columns[] = 'from_date';
        }

        if (Schema::hasColumn('attendance_imports', 'to_date')) {
            $columns[] = 'to_date';
        }

        if (!empty($columns)) {
            Schema::table(
                'attendance_imports',
                function (Blueprint $table) use ($columns) {
                    $table->dropColumn($columns);
            }
            );
        }
    }
}
