<?php

namespace App\Http\Controllers;

use App\Models\AiAgent;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AiAgentController extends Controller
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
                    'unique:ai_agents,name',
                ],

                'agent_type' => [
                    'required',

                    Rule::in([
                        'whatsapp',
                        'lead_scoring',
                        'generic',
                    ]),
                ],

                'ai_model_profile_id' => [
                    'required',
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
                    'max:30000',
                ],

                'enabled' => [
                    'nullable',
                    'boolean',
                ],
            ]);


        AiAgent::create([
            'name' =>
                trim(
                    $validated['name']
                ),

            'agent_type' =>
                $validated[
                    'agent_type'
                ],

            'ai_model_profile_id' =>
                $validated[
                    'ai_model_profile_id'
                ],

            'prompt' =>
                $validated['prompt'],

            'enabled' =>
                $request->boolean(
                    'enabled'
                ),

            'created_by' =>
                Auth::id(),

            'updated_by' =>
                Auth::id(),
        ]);


        return redirect()
            ->back()
            ->with(
                'success',
                'AI Agent created successfully.'
            );
    }


    public function update(
        Request $request,
        AiAgent $aiAgent
    ) {
        $this->ensureSuperAdmin();

        $validated =
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:150',

                    Rule::unique(
                        'ai_agents',
                        'name'
                    )->ignore(
                        $aiAgent->id
                    ),
                ],

                'agent_type' => [
                    'required',

                    Rule::in([
                        'whatsapp',
                        'lead_scoring',
                        'generic',
                    ]),
                ],

                'ai_model_profile_id' => [
                    'required',
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
                    'max:30000',
                ],

                'enabled' => [
                    'nullable',
                    'boolean',
                ],
            ]);


        $aiAgent->update([
            'name' =>
                trim(
                    $validated['name']
                ),

            'agent_type' =>
                $validated[
                    'agent_type'
                ],

            'ai_model_profile_id' =>
                $validated[
                    'ai_model_profile_id'
                ],

            'prompt' =>
                $validated['prompt'],

            'enabled' =>
                $request->boolean(
                    'enabled'
                ),

            'updated_by' =>
                Auth::id(),
        ]);


        return redirect()
            ->back()
            ->with(
                'success',
                'AI Agent updated successfully.'
            );
    }


    public function destroy(
        AiAgent $aiAgent
    ) {
        $this->ensureSuperAdmin();

        if (
            $aiAgent->isInUse()
        ) {
            return redirect()
                ->back()
                ->with(
                    'error',
                    'This AI Agent is currently assigned to a CRM feature. Select another agent before deleting it.'
                );
        }

        $aiAgent->delete();


        return redirect()
            ->back()
            ->with(
                'success',
                'AI Agent deleted successfully.'
            );
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