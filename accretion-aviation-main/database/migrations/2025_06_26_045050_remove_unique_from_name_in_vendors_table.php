<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveUniqueFromNameInVendorsTable extends Migration
{
    public function up()
    {
        Schema::table('vendors', function (Blueprint $table) {

            $table->dropUnique(['name']);
        });
    }

    public function down()
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->unique('name'); // Re-add the unique constraint if rolled back
        });
    }
}
