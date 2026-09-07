<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeadAiScoresTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('lead_ai_scores')) {
            Schema::create(
                'lead_ai_scores',
                function (Blueprint $table) {
                    $table->uuid('id')->primary();

                    $table->uuid('lead_id')
                        ->index();

                    /*
                     * One automatic AI state for
                     * one follow-up.
                     */
                    $table->uuid('followup_id')
                        ->unique();

                    $table->uuid('previous_score_id')
                        ->nullable()
                        ->index();

                    $table->string(
                        'status',
                        20
                    )->default('pending');

                    $table->string(
                        'temperature',
                        20
                    )->nullable();

                    $table->unsignedSmallInteger('score')
                        ->nullable();

                    $table->unsignedSmallInteger('confidence')
                        ->nullable();

                    $table->json('summary')
                        ->nullable();

                    $table->text('suggested_action')
                        ->nullable();

                    $table->text('score_change_reason')
                        ->nullable();

                    /*
                     * Internal persistent AI memory.
                     */
                    $table->json('state_json')
                        ->nullable();

                    $table->string(
                        'input_hash',
                        64
                    )->nullable()
                        ->index();

                    $table->string('model')
                        ->nullable();

                    $table->uuid('analysed_by')
                        ->nullable();

                    $table->unsignedInteger('attempt_count')
                        ->default(0);

                    $table->text('last_error')
                        ->nullable();

                    $table->timestamp('processed_at')
                        ->nullable();

                    $table->timestamps();

                    $table->index([
                        'lead_id',
                        'status',
                    ]);
                }
            );
        }
    }

    public function down()
    {
        Schema::dropIfExists(
            'lead_ai_scores'
        );
    }
}