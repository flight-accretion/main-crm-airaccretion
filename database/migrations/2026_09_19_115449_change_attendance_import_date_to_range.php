<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeAttendanceImportDateToRange extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('attendance_imports', 'from_date')) {
            Schema::table(
                'attendance_imports',
                function (Blueprint $table) {
                $table->date('from_date')
                    ->nullable()
                    ->after('id');
                }
            );
        }

        if (!Schema::hasColumn('attendance_imports', 'to_date')) {
            Schema::table(
                'attendance_imports',
                function (Blueprint $table) {
                $table->date('to_date')
                    ->nullable()
                    ->after('from_date');
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
