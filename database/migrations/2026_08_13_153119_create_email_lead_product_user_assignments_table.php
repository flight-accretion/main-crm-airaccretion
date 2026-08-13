<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmailLeadProductUserAssignmentsTable extends Migration
{
    public function up()
    {
        Schema::create('email_lead_product_user_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('user_id');
            $table->uuid('product_id');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();

            // Same salesperson/product cannot be mapped twice.
            $table->unique([
                'user_id',
                'product_id',
            ]);
        });
    }

    public function down()
    {
        Schema::dropIfExists(
            'email_lead_product_user_assignments'
        );
    }
}