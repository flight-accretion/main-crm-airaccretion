<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSalesExecutiveAssignmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sales_executive_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('manager_id'); // Manager who assigns the sales executive
            $table->uuid('sales_executive_id'); // Sales executive being assigned
            $table->timestamp('assigned_date')->useCurrent();
            $table->text('notes')->nullable();
            $table->tinyInteger('status')->default(1); // 1=active, 0=inactive
            $table->timestamps();

            // Prevent duplicate assignments
            $table->unique(['manager_id', 'sales_executive_id'], 'unique_manager_executive_assignment');
            
            // Indexes for performance
            $table->index('manager_id');
            $table->index('sales_executive_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sales_executive_assignments');
    }
}