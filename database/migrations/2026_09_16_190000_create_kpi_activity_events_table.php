<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateKpiActivityEventsTable extends Migration
{
    public function up()
    {
        Schema::create('kpi_activity_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_key', 190)->unique();
            $table->string('department', 50)->index();
            $table->uuid('user_id')->index();
            $table->string('entity_type', 50)->index();
            $table->string('entity_id', 64)->index();
            $table->string('event_type', 80)->index();
            $table->string('source_record_id', 64)->nullable()->index();
            $table->string('source', 50)->default('human_ui');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(
                ['department', 'user_id', 'occurred_at'],
                'kpi_activity_dept_user_date_idx'
            );

            $table->index(
                ['entity_type', 'entity_id', 'occurred_at'],
                'kpi_activity_entity_date_idx'
            );
        });
    }

    public function down()
    {
        Schema::dropIfExists('kpi_activity_events');
    }
}
