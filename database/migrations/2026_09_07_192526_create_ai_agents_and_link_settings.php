<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CreateAiAgentsAndLinkSettings extends Migration
{
    public function up()
    {
        /*
         * =====================================================
         * AI AGENTS
         * =====================================================
         */
        if (!Schema::hasTable('ai_agents')) {
            Schema::create('ai_agents', function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->string('name', 150)
                    ->unique();

                /*
                 * whatsapp
                 * lead_scoring
                 * generic
                 *
                 * Later you can add:
                 * booking_email
                 * call_summary
                 * kpi
                 */
                $table->string(
                    'agent_type',
                    50
                )->default('generic');

                $table->uuid(
                    'ai_model_profile_id'
                )
                    ->nullable()
                    ->index();

                $table->text('prompt');

                $table->boolean('enabled')
                    ->default(true);

                $table->uuid('created_by')
                    ->nullable();

                $table->uuid('updated_by')
                    ->nullable();

                $table->timestamps();
            });
        }


        /*
         * =====================================================
         * WHATSAPP -> AGENT
         * =====================================================
         */
        if (
            Schema::hasTable(
                'whatsapp_ai_agent_settings'
            )
            &&
            !Schema::hasColumn(
                'whatsapp_ai_agent_settings',
                'ai_agent_id'
            )
        ) {
            Schema::table(
                'whatsapp_ai_agent_settings',
                function (Blueprint $table) {
                    $table->uuid('ai_agent_id')
                        ->nullable()
                        ->index();
                }
            );
        }


        /*
         * =====================================================
         * LEAD SCORING -> AGENT
         * =====================================================
         */
        if (
            Schema::hasTable(
                'lead_ai_scoring_settings'
            )
            &&
            !Schema::hasColumn(
                'lead_ai_scoring_settings',
                'ai_agent_id'
            )
        ) {
            Schema::table(
                'lead_ai_scoring_settings',
                function (Blueprint $table) {
                    $table->uuid('ai_agent_id')
                        ->nullable()
                        ->index();
                }
            );
        }


        /*
         * =====================================================
         * MIGRATE EXISTING WHATSAPP PROMPT + MODEL PROFILE
         * =====================================================
         */
        $this->migrateWhatsAppAgent();


        /*
         * =====================================================
         * MIGRATE EXISTING LEAD SCORING PROMPT + PROFILE
         * =====================================================
         */
        $this->migrateLeadScoringAgent();
    }


    private function migrateWhatsAppAgent(): void
    {
        if (
            !Schema::hasTable(
                'whatsapp_ai_agent_settings'
            )
        ) {
            return;
        }

        $setting =
            DB::table(
                'whatsapp_ai_agent_settings'
            )->first();

        if (!$setting) {
            return;
        }

        if (
            !empty(
                $setting->ai_agent_id
            )
        ) {
            return;
        }


        $existing =
            DB::table('ai_agents')
                ->where(
                    'name',
                    'WhatsApp Sales Agent'
                )
                ->first();


        if ($existing) {
            $agentId =
                $existing->id;
        } else {
            $agentId =
                (string) Str::uuid();

            DB::table(
                'ai_agents'
            )->insert([
                'id' =>
                    $agentId,

                'name' =>
                    'WhatsApp Sales Agent',

                'agent_type' =>
                    'whatsapp',

                'ai_model_profile_id' =>
                    $setting
                        ->ai_model_profile_id
                    ?? null,

                'prompt' =>
                    $setting->prompt
                    ?? '',

                'enabled' =>
                    true,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);
        }


        DB::table(
            'whatsapp_ai_agent_settings'
        )
            ->where(
                'id',
                $setting->id
            )
            ->update([
                'ai_agent_id' =>
                    $agentId,
            ]);
    }


    private function migrateLeadScoringAgent(): void
    {
        if (
            !Schema::hasTable(
                'lead_ai_scoring_settings'
            )
        ) {
            return;
        }

        $setting =
            DB::table(
                'lead_ai_scoring_settings'
            )->first();

        if (!$setting) {
            return;
        }

        if (
            !empty(
                $setting->ai_agent_id
            )
        ) {
            return;
        }


        $existing =
            DB::table('ai_agents')
                ->where(
                    'name',
                    'Lead Scoring Agent'
                )
                ->first();


        if ($existing) {
            $agentId =
                $existing->id;
        } else {
            $agentId =
                (string) Str::uuid();

            DB::table(
                'ai_agents'
            )->insert([
                'id' =>
                    $agentId,

                'name' =>
                    'Lead Scoring Agent',

                'agent_type' =>
                    'lead_scoring',

                'ai_model_profile_id' =>
                    $setting
                        ->ai_model_profile_id
                    ?? null,

                'prompt' =>
                    $setting->prompt
                    ?? '',

                'enabled' =>
                    true,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);
        }


        DB::table(
            'lead_ai_scoring_settings'
        )
            ->where(
                'id',
                $setting->id
            )
            ->update([
                'ai_agent_id' =>
                    $agentId,
            ]);
    }


    public function down()
    {
        if (
            Schema::hasTable(
                'lead_ai_scoring_settings'
            )
            &&
            Schema::hasColumn(
                'lead_ai_scoring_settings',
                'ai_agent_id'
            )
        ) {
            Schema::table(
                'lead_ai_scoring_settings',
                function (Blueprint $table) {
                    $table->dropColumn(
                        'ai_agent_id'
                    );
                }
            );
        }


        if (
            Schema::hasTable(
                'whatsapp_ai_agent_settings'
            )
            &&
            Schema::hasColumn(
                'whatsapp_ai_agent_settings',
                'ai_agent_id'
            )
        ) {
            Schema::table(
                'whatsapp_ai_agent_settings',
                function (Blueprint $table) {
                    $table->dropColumn(
                        'ai_agent_id'
                    );
                }
            );
        }


        Schema::dropIfExists(
            'ai_agents'
        );
    }
}