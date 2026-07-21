<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up()
    {
        // Make name column nullable using MySQL syntax
        DB::statement("ALTER TABLE lead_passengers MODIFY COLUMN name VARCHAR(255) NULL");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revert name column to NOT NULL
        DB::statement("ALTER TABLE lead_passengers MODIFY COLUMN name VARCHAR(255) NOT NULL");
    }
};
