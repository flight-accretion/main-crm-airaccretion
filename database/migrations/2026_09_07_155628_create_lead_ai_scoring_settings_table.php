<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeadAiScoringSettingsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('lead_ai_scoring_settings')) {
            Schema::create(
                'lead_ai_scoring_settings',
                function (Blueprint $table) {
                    $table->uuid('id')->primary();

                    $table->boolean('enabled')
                        ->default(false);

                    $table->boolean('auto_analyse')
                        ->default(true);

                    /*
                     * Null means:
                     * use global model from WhatsAppAiAgentSetting.
                     */
                    $table->string('model')
                        ->nullable();

                    $table->text('prompt')
                        ->nullable();

                    /*
                     * Default:
                     * 0-39  Cold
                     * 40-69 Neutral
                     * 70-100 Hot
                     */
                    $table->unsignedSmallInteger('cold_max')
                        ->default(39);

                    $table->unsignedSmallInteger('neutral_max')
                        ->default(69);

                    $table->uuid('created_by')
                        ->nullable();

                    $table->uuid('updated_by')
                        ->nullable();

                    $table->timestamps();
                }
            );
        }
    }

    public function down()
    {
        Schema::dropIfExists(
            'lead_ai_scoring_settings'
        );
    }
}