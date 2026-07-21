<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNotNullAndUniqueConstraintToContactNumberInVendorsTable  extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('contact_number')->unique()->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropUnique(['contact_number']);
            $table->string('contact_number')->nullable()->change();
        });
    }
}
