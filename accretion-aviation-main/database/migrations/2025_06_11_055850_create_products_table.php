<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255)->unique(); 
            $table->decimal('price', 10, 2);
            $table->text('details')->nullable(); 
            $table->text('terms_conditions')->nullable(); 
            $table->integer('status')->default(1); 
            $table->timestamp('created_at')->useCurrent(); 
            $table->timestamp('updated_at')->useCurrent(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
}
