<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClientsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100)->nullable(false); 
            $table->string('email', 100)->unique()->nullable(false);
            $table->string('contact_number', 20)->nullable();
            $table->string('alternate_number', 20)->nullable();
            $table->uuid('country_id')->nullable();
            $table->text('address')->nullable();
            $table->text('description')->nullable();
            $table->integer('status')->default(1);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->uuid('created_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
}
