<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateVoucherTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
      public function up(): void
    {
        // Drop table if it already exists
        Schema::dropIfExists('vouchers');

        // Create new vouchers table with only required fields
        Schema::create('vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->nullable();
            $table->uuid('operation_team_user_id')->nullable();
            $table->text('extra_upload')->nullable();
            $table->text('naration')->nullable();
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
