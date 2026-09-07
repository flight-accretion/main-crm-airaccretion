<?php

namespace App\Http\Controllers;

use App\Models\AiModelProfile;
use App\Models\LeadAiScoringSetting;
use App\Models\UserType;
use App\Models\WhatsAppAiAgentSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WhatsAppAiAgentSettingController extends Controller
{
    /**
     * Show AI Agent settings page.
     */
    public function edit()
    {
        $this->ensureSuperAdmin();

        return view(
            'admin.pages.whatsapp.ai-agent',
            [
                'setting' =>
                    WhatsAppAiAgentSetting::active(),

                'leadScoringSetting' =>
                    LeadAiScoringSetting::active(),

                /*
                 * All profiles are passed because
                 * AI Models tab also needs to display
                 * inactive profiles.
                 */
                'aiModelProfiles' =>
                    AiModelProfile::query()
                        ->orderByDesc('enabled')
                        ->orderBy('name')
                        ->get(),
            ]
        );
    }

    /**
     * Update WhatsApp AI and Lead Scoring AI settings.
     *
     * Provider, model and API key are NOT handled here.
     * They belong to AiModelProfile.
     */
    public function update(Request $request)
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            /*
             * =================================================
             * WHATSAPP AI
             * =================================================
             */

            'enabled' => [
                'nullable',
                'boolean',
            ],

            'auto_reply_enabled' => [
                'nullable',
                'boolean',
            ],

            'whatsapp_ai_model_profile_id' => [
                Rule::requiredIf(
                    fn () =>
                        $request->boolean('enabled')
                ),

                'nullable',
                'uuid',

                Rule::exists(
                    'ai_model_profiles',
                    'id'
                )->where(
                    fn ($query) =>
                        $query->where(
                            'enabled',
                            true
                        )
                ),
            ],

            'prompt' => [
                'required',
                'string',
                'max:12000',
            ],

            'buffer_seconds' => [
                'required',
                'integer',
                'min:1',
                'max:300',
            ],

            'context_message_limit' => [
                'required',
                'integer',
                'min:1',
                'max:100000',
            ],

            /*
             * =================================================
             * LEAD SCORING AI
             * =================================================
             */

            'lead_scoring_enabled' => [
                'nullable',
                'boolean',
            ],

            'lead_scoring_auto_analyse' => [
                'nullable',
                'boolean',
            ],

            'lead_scoring_ai_model_profile_id' => [
                Rule::requiredIf(
                    fn () =>
                        $request->boolean(
                            'lead_scoring_enabled'
                        )
                ),

                'nullable',
                'uuid',

                Rule::exists(
                    'ai_model_profiles',
                    'id'
                )->where(
                    fn ($query) =>
                        $query->where(
                            'enabled',
                            true
                        )
                ),
            ],

            'lead_scoring_prompt' => [
                'required',
                'string',
                'max:12000',
            ],

            'cold_max' => [
                'required',
                'integer',
                'min:0',
                'max:98',
            ],

            'neutral_max' => [
                'required',
                'integer',
                'min:1',
                'max:99',
                'gt:cold_max',
            ],
        ]);

        DB::transaction(
            function () use (
                $request,
                $validated
            ) {
                /*
                 * =============================================
                 * WHATSAPP AI SETTINGS
                 * =============================================
                 */
                $setting =
                    WhatsAppAiAgentSetting::active();

                $setting->fill([
                    'enabled' =>
                        $request->boolean(
                            'enabled'
                        ),

                    'auto_reply_enabled' =>
                        $request->boolean(
                            'auto_reply_enabled'
                        ),

                    /*
                     * NEW:
                     * Provider/model/API key now come
                     * through this reusable profile.
                     */
                    'ai_model_profile_id' =>
                        $validated[
                            'whatsapp_ai_model_profile_id'
                        ] ?? null,

                    'prompt' =>
                        $validated['prompt'],

                    'buffer_seconds' =>
                        (int) $validated[
                            'buffer_seconds'
                        ],

                    'context_message_limit' =>
                        (int) $validated[
                            'context_message_limit'
                        ],
                ]);

                $setting->save();


                /*
                 * =============================================
                 * LEAD SCORING AI SETTINGS
                 * =============================================
                 */
                $leadSetting =
                    LeadAiScoringSetting::active();

                $leadSetting->fill([
                    'enabled' =>
                        $request->boolean(
                            'lead_scoring_enabled'
                        ),

                    'auto_analyse' =>
                        $request->boolean(
                            'lead_scoring_auto_analyse'
                        ),

                    /*
                     * NEW:
                     * Lead Scoring may use Gemini,
                     * OpenAI or another supported profile.
                     */
                    'ai_model_profile_id' =>
                        $validated[
                            'lead_scoring_ai_model_profile_id'
                        ] ?? null,

                    'prompt' =>
                        $validated[
                            'lead_scoring_prompt'
                        ],

                    'cold_max' =>
                        (int) $validated[
                            'cold_max'
                        ],

                    'neutral_max' =>
                        (int) $validated[
                            'neutral_max'
                        ],

                    'updated_by' =>
                        Auth::id(),
                ]);

                /*
                 * Preserve original creator.
                 */
                if (
                    empty(
                        $leadSetting->created_by
                    )
                ) {
                    $leadSetting->created_by =
                        Auth::id();
                }

                $leadSetting->save();
            }
        );

        return redirect()
            ->route(
                'admin.whatsapp.ai-agent.edit'
            )
            ->with(
                'success',
                'AI Agent settings updated successfully.'
            );
    }

    /**
     * Only Super Admin can manage AI Agent configuration.
     */
    private function ensureSuperAdmin(): void
    {
        $role =
            optional(
                Auth::user()->userType
            )->user_type;

        abort_unless(
            $role ===
                UserType::SUPER_ADMIN,
            403
        );
    }
}