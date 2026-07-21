<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVouchersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary(); 
            $table->uuid('lead_id')->nullable();
            $table->json('product_ids')->nullable();
            $table->json('vendor_ids')->nullable();
            $table->uuid('operation_team_user_id')->nullable(); 
            $table->timestamp('ride_date')->nullable();
            $table->text('ride_address')->nullable();
            $table->string('ride_contact_number', 20)->nullable();
            $table->text('extra_upload')->nullable();
            $table->boolean('is_tba')->default(0);
            $table->timestamp('ride_from_time')->nullable(); 
            $table->timestamp('ride_to_time')->nullable();
            $table->decimal('total_time', 5, 2)->nullable(); 
            $table->uuid('service_address_id')->nullable(); 
            $table->integer('status')->default(1); // 1 = Active, 0 = Inactive
            $table->uuid('created_by')->nullable(); // FK to users table
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
}
