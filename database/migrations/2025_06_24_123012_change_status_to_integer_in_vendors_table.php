<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ChangeStatusToIntegerInVendorsTable extends Migration
{
    public function up()
    {
        // For MySQL/MariaDB, use MODIFY COLUMN
        DB::statement('ALTER TABLE vendors MODIFY COLUMN status INT DEFAULT 1');
    }

    public function down()
    {
        // Revert to boolean equivalent
        DB::statement('ALTER TABLE vendors MODIFY COLUMN status BOOLEAN DEFAULT 0');
    }
}
