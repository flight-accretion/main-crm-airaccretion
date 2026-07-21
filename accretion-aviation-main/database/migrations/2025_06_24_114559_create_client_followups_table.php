<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClientFollowupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up(): void
    {
        Schema::create('client_followups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_enquiry_id');
            $table->timestamp('next_followup_date')->nullable(false);
            $table->text('followup_note')->nullable();
            $table->string('file')->nullable();
            $table->integer('status')->default(0); // 0 = Pending, 1 = Completed, 2 = Skipped
            $table->uuid('followed_by');
            $table->timestamps(); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_followups');
    }
}
