<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOperationsLeadAssignmentsTable extends Migration
{
    public function up()
    {
        Schema::create('operations_lead_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->uuid('operations_user_id');
            $table->uuid('assigned_by')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['lead_id', 'is_active']);
            $table->index(['operations_user_id', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('operations_lead_assignments');
    }
}
