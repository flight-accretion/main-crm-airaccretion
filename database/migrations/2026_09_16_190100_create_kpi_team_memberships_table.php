<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateKpiTeamMembershipsTable extends Migration
{
    public function up()
    {
        Schema::create('kpi_team_memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('department', 50)->index();
            $table->uuid('manager_user_id')->index();
            $table->uuid('member_user_id')->index();
            $table->boolean('active')->default(true)->index();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->unique(
                ['department', 'manager_user_id', 'member_user_id'],
                'kpi_team_manager_member_unique'
            );
        });
    }

    public function down()
    {
        Schema::dropIfExists('kpi_team_memberships');
    }
}
