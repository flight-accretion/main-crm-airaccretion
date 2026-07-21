<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateClientAndProductTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        // Make price nullable in products table
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->nullable()->change();
        });

        // Make email nullable in clients table
        Schema::table('clients', function (Blueprint $table) {
            $table->string('email', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Revert price back to not nullable
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->nullable(false)->change();
        });

        // Revert email back to not nullable
        Schema::table('clients', function (Blueprint $table) {
            $table->string('email', 100)->nullable(false)->change();
        });
    }
}
