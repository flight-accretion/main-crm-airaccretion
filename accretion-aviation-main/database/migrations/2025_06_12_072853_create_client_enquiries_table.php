<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClientEnquiriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up(): void
    {
        Schema::create('client_enquiries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id'); 
            $table->uuid('representative_user_id');
            $table->json('product_ids');
            $table->integer('number_of_passengers')->nullable();
            $table->string('description', 255)->nullable();
            $table->string('occasion', 50)->nullable();
            $table->timestamp('next_follow_up')->nullable();
            $table->integer('enquiry_status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_enquiries');
    }
}
