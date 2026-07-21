<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeLeadDescriptionToText extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        // Changing column type requires doctrine/dbal installed (present in composer.json)
        Schema::table('leads', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('description', 255)->nullable()->change();
        });
    }
}
