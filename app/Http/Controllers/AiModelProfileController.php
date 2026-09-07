<?php

namespace App\Http\Controllers;

use App\Models\AiModelProfile;
use App\Models\UserType;
use App\Services\Ai\AiProviderManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AiModelProfileController
    extends Controller
{
    public function store(
        Request $request
    ) {
        $this->ensureSuperAdmin();

        $validated =
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:150',
                    'unique:ai_model_profiles,name',
                ],

                'provider' => [
                    'required',
                    Rule::in([
                        'openai',
                        'gemini',
                    ]),
                ],

                'model' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'api_key' => [
                    'required',
                    'string',
                    'max:4000',
                ],

                'enabled' => [
                    'nullable',
                    'boolean',
                ],
            ]);

        $profile =
            new AiModelProfile();

        $profile->fill([
            'name' =>
                trim(
                    $validated['name']
                ),

            'provider' =>
                $validated['provider'],

            'model' =>
                trim(
                    $validated['model']
                ),

            'enabled' =>
                $request->boolean(
                    'enabled'
                ),

            'created_by' =>
                Auth::id(),

            'updated_by' =>
                Auth::id(),
        ]);

        $profile->setApiKey(
            $validated['api_key']
        );

        $profile->save();

        return redirect()
            ->back()
            ->with(
                'success',
                'AI Model Profile created successfully.'
            );
    }

    public function update(
        Request $request,
        AiModelProfile $aiModelProfile
    ) {
        $this->ensureSuperAdmin();

        $validated =
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:150',

                    Rule::unique(
                        'ai_model_profiles',
                        'name'
                    )->ignore(
                        $aiModelProfile->id
                    ),
                ],

                'provider' => [
                    'required',
                    Rule::in([
                        'openai',
                        'gemini',
                    ]),
                ],

                'model' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'api_key' => [
                    'nullable',
                    'string',
                    'max:4000',
                ],

                'clear_api_key' => [
                    'nullable',
                    'boolean',
                ],

                'enabled' => [
                    'nullable',
                    'boolean',
                ],
            ]);

        $aiModelProfile->fill([
            'name' =>
                trim(
                    $validated['name']
                ),

            'provider' =>
                $validated['provider'],

            'model' =>
                trim(
                    $validated['model']
                ),

            'enabled' =>
                $request->boolean(
                    'enabled'
                ),

            'updated_by' =>
                Auth::id(),
        ]);

        if (
            $request->boolean(
                'clear_api_key'
            )
        ) {
            $aiModelProfile
                ->clearApiKey();
        }

        if (
            $request->filled(
                'api_key'
            )
        ) {
            $aiModelProfile
                ->setApiKey(
                    $request->input(
                        'api_key'
                    )
                );
        }

        $aiModelProfile->save();

        return redirect()
            ->back()
            ->with(
                'success',
                'AI Model Profile updated successfully.'
            );
    }

    public function destroy(
        AiModelProfile $aiModelProfile
    ) {
        $this->ensureSuperAdmin();

        if (
            $aiModelProfile->isInUse()
        ) {
            return redirect()
                ->back()
                ->with(
                    'error',
                    'This AI Model Profile is currently used by an AI Agent. Select another profile before deleting it.'
                );
        }

        $aiModelProfile->delete();

        return redirect()
            ->back()
            ->with(
                'success',
                'AI Model Profile deleted successfully.'
            );
    }

    public function testConnection(
        Request $request,
        AiProviderManager $providers
    ) {
        $this->ensureSuperAdmin();

        $validated =
            $request->validate([
                'profile_id' => [
                    'nullable',
                    'uuid',
                    'exists:ai_model_profiles,id',
                ],

                'provider' => [
                    'required',
                    Rule::in([
                        'openai',
                        'gemini',
                    ]),
                ],

                'model' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'api_key' => [
                    'nullable',
                    'string',
                    'max:4000',
                ],
            ]);

        $apiKey =
            trim(
                (string) (
                    $validated['api_key']
                    ?? ''
                )
            );

        if (
            $apiKey === ''
            &&
            !empty(
                $validated['profile_id']
            )
        ) {
            $profile =
                AiModelProfile::query()
                    ->find(
                        $validated[
                            'profile_id'
                        ]
                    );

            $apiKey =
                (string)
                optional($profile)
                    ->apiKey();
        }

        try {
            $providers->test(
                $validated['provider'],
                $validated['model'],
                $apiKey
            );

            return response()->json([
                'success' => true,

                'message' =>
                    'AI Model connection successful.',
            ]);
        } catch (\Throwable $e) {
            return response()->json(
                [
                    'success' =>
                        false,

                    'message' =>
                        'Connection failed. Check the API key and model.',
                ],
                422
            );
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