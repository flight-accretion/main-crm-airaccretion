<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateProductsToRemoveColumnsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop columns
            $table->dropColumn(['description', 'amount', 'terms_conditions']);

            // Add JSON column
             $table->json('user_ids')
                ->nullable()
                ->comment('only assigned staff can handle this product');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Add back the dropped columns
            $table->text('description')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->text('terms_conditions')->nullable();

            // Drop the added column
            $table->dropColumn('user_ids');
        });
    }
}
