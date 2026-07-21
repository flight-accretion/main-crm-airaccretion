<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeContactNumberColumnTypeInVendorsTable extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('contact_number',20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->integer('contact_number')->change();
        });
    }
}
