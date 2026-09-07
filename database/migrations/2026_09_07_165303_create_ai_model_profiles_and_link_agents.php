<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CreateAiModelProfilesAndLinkAgents extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('ai_model_profiles')) {
            Schema::create('ai_model_profiles', function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->string('name', 150)->unique();

                $table->string('provider', 50);

                $table->string('model', 255);

                $table->text('api_key_encrypted')
                    ->nullable();

                $table->boolean('enabled')
                    ->default(true);

                $table->uuid('created_by')
                    ->nullable();

                $table->uuid('updated_by')
                    ->nullable();

                $table->timestamps();
            });
        }

        if (
            Schema::hasTable('whatsapp_ai_agent_settings')
            &&
            !Schema::hasColumn(
                'whatsapp_ai_agent_settings',
                'ai_model_profile_id'
            )
        ) {
            Schema::table(
                'whatsapp_ai_agent_settings',
                function (Blueprint $table) {
                    $table->uuid('ai_model_profile_id')
                        ->nullable()
                        ->index();
                }
            );
        }

        if (
            Schema::hasTable('lead_ai_scoring_settings')
            &&
            !Schema::hasColumn(
                'lead_ai_scoring_settings',
                'ai_model_profile_id'
            )
        ) {
            Schema::table(
                'lead_ai_scoring_settings',
                function (Blueprint $table) {
                    $table->uuid('ai_model_profile_id')
                        ->nullable()
                        ->index();
                }
            );
        }

        /*
         * -------------------------------------------------
         * MIGRATE EXISTING WHATSAPP OPENAI CONFIGURATION
         * -------------------------------------------------
         *
         * Copy encrypted key directly.
         * No need to decrypt/re-encrypt.
         */
        if (
            Schema::hasTable('whatsapp_ai_agent_settings')
            &&
            Schema::hasColumn(
                'whatsapp_ai_agent_settings',
                'ai_model_profile_id'
            )
        ) {
            $setting = DB::table(
                'whatsapp_ai_agent_settings'
            )->first();

            if (
                $setting
                &&
                empty($setting->ai_model_profile_id)
            ) {
                $existingProfile =
                    DB::table('ai_model_profiles')
                        ->where(
                            'name',
                            'WhatsApp OpenAI'
                        )
                        ->first();

                if ($existingProfile) {
                    $profileId =
                        $existingProfile->id;
                } else {
                    $profileId =
                        (string) Str::uuid();

                    DB::table(
                        'ai_model_profiles'
                    )->insert([
                        'id' =>
                            $profileId,

                        'name' =>
                            'WhatsApp OpenAI',

                        'provider' =>
                            $setting->provider
                                ?: 'openai',

                        'model' =>
                            $setting->model
                                ?: 'gpt-4o-mini',

                        'api_key_encrypted' =>
                            $setting
                                ->api_key_encrypted
                                ?? null,

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
                        'ai_model_profile_id' =>
                            $profileId,
                    ]);
            }
        }
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
                'ai_model_profile_id'
            )
        ) {
            Schema::table(
                'lead_ai_scoring_settings',
                function (Blueprint $table) {
                    $table->dropColumn(
                        'ai_model_profile_id'
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
                'ai_model_profile_id'
            )
        ) {
            Schema::table(
                'whatsapp_ai_agent_settings',
                function (Blueprint $table) {
                    $table->dropColumn(
                        'ai_model_profile_id'
                    );
                }
            );
        }

        Schema::dropIfExists(
            'ai_model_profiles'
        );
    }
}