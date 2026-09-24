<?php

namespace App\Http\Controllers;

use App\Models\AiAgent;
use App\Models\AiModelProfile;
use App\Models\LeadAiScoringSetting;
use App\Models\UserType;
use App\Models\WhatsAppAiAgentSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WhatsAppAiAgentSettingController extends Controller
{
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

                'aiModelProfiles' =>
                    AiModelProfile::query()
                        ->with('agents')
                        ->orderByDesc(
                            'enabled'
                        )
                        ->orderBy('name')
                        ->get(),

                'aiAgents' =>
                    AiAgent::query()
                        ->with(
                            'modelProfile'
                        )
                        ->orderByDesc(
                            'enabled'
                        )
                        ->orderBy('name')
                        ->get(),
            ]
        );
    }


    public function update(
        Request $request
    ) {
        $this->ensureSuperAdmin();

        $usesLegacyDirectSettings =
            !$request->has('whatsapp_ai_agent_id')
            && $request->hasAny([
                'provider',
                'model',
                'prompt',
                'api_key',
            ]);

        $validated =
            $request->validate(array_merge(
                [
                'enabled' => [
                    'nullable',
                    'boolean',
                ],

                'auto_reply_enabled' => [
                    'nullable',
                    'boolean',
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
                ],
                $usesLegacyDirectSettings
                    ? [
                        'provider' => [
                            'required',
                            'string',
                            Rule::in([
                                'openai',
                                'gemini',
                            ]),
                        ],

                        'model' => [
                            'required',
                            'string',
                            'max:100',
                        ],

                        'prompt' => [
                            'required',
                            'string',
                        ],

                        'api_key' => [
                            'nullable',
                            'string',
                        ],
                    ]
                    : [
                'whatsapp_ai_agent_id' => [
                    Rule::requiredIf(
                        fn () =>
                            $request
                                ->boolean(
                                    'enabled'
                                )
                    ),

                    'nullable',
                    'uuid',
                    'exists:ai_agents,id',
                ],

                'lead_scoring_enabled' => [
                    'nullable',
                    'boolean',
                ],

                'lead_scoring_auto_analyse' => [
                    'nullable',
                    'boolean',
                ],

                'lead_scoring_ai_agent_id' => [
                    Rule::requiredIf(
                        fn () =>
                            $request
                                ->boolean(
                                    'lead_scoring_enabled'
                                )
                    ),

                    'nullable',
                    'uuid',
                    'exists:ai_agents,id',
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
                    ]
            ));


        /*
         * Make sure feature cannot select
         * wrong agent type.
         */
        if (!$usesLegacyDirectSettings) {
            $this->validateAgent(
                $validated[
                    'whatsapp_ai_agent_id'
                ] ?? null,

                [
                    'whatsapp',
                    'generic',
                ],

                'whatsapp_ai_agent_id'
            );


            $this->validateAgent(
                $validated[
                    'lead_scoring_ai_agent_id'
                ] ?? null,

                [
                    'lead_scoring',
                    'generic',
                ],

                'lead_scoring_ai_agent_id'
            );
        }


        DB::transaction(
            function () use (
                $request,
                $validated,
                $usesLegacyDirectSettings
            ) {

                /*
                 * ============================================
                 * WHATSAPP SETTINGS
                 * ============================================
                 */
                $setting =
                    WhatsAppAiAgentSetting::active();

                $settingPayload = [
                    'enabled' =>
                        $request->boolean(
                            'enabled'
                        ),

                    'auto_reply_enabled' =>
                        $request->boolean(
                            'auto_reply_enabled'
                        ),

                    'buffer_seconds' =>
                        (int) $validated[
                            'buffer_seconds'
                        ],

                    'context_message_limit' =>
                        (int) $validated[
                            'context_message_limit'
                        ],
                ];

                if (
                    Schema::hasColumn(
                        'whatsapp_ai_agent_settings',
                        'ai_agent_id'
                    )
                ) {
                    $settingPayload['ai_agent_id'] =
                        $usesLegacyDirectSettings
                            ? null
                            : ($validated['whatsapp_ai_agent_id'] ?? null);
                }

                $setting->fill($settingPayload);

                if ($usesLegacyDirectSettings) {
                    $setting->fill([
                        'provider' => $validated['provider'],
                        'model' => $validated['model'],
                        'prompt' => $validated['prompt'],
                    ]);

                    if (!empty($validated['api_key'])) {
                        $setting->setApiKey($validated['api_key']);
                    }
                }

                $setting->save();

                if ($usesLegacyDirectSettings) {
                    return;
                }

                /*
                 * ============================================
                 * LEAD SCORING SETTINGS
                 * ============================================
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

                    'ai_agent_id' =>
                        $validated[
                            'lead_scoring_ai_agent_id'
                        ] ?? null,

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


                if (
                    empty(
                        $leadSetting
                            ->created_by
                    )
                ) {
                    $leadSetting
                        ->created_by =
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
                'AI Agent assignments updated successfully.'
            );
    }


    private function validateAgent(
        ?string $id,
        array $allowedTypes,
        string $field
    ): void {
        if (!$id) {
            return;
        }

        $agent =
            AiAgent::query()
                ->with(
                    'modelProfile'
                )
                ->find($id);


        if (!$agent) {
            throw ValidationException::withMessages([
                $field =>
                    'Selected AI Agent does not exist.',
            ]);
        }


        if (!$agent->enabled) {
            throw ValidationException::withMessages([
                $field =>
                    'Selected AI Agent is inactive.',
            ]);
        }


        if (
            !in_array(
                $agent->agent_type,
                $allowedTypes,
                true
            )
        ) {
            throw ValidationException::withMessages([
                $field =>
                    'Selected AI Agent is not compatible with this feature.',
            ]);
        }


        if (!$agent->modelProfile) {
            throw ValidationException::withMessages([
                $field =>
                    'Selected AI Agent has no AI Model Profile.',
            ]);
        }


        if (!$agent->modelProfile->enabled) {
            throw ValidationException::withMessages([
                $field =>
                    'The AI Model Profile assigned to this agent is inactive.',
            ]);
        }
    }


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
