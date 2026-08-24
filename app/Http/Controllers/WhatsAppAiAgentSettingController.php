<?php

namespace App\Http\Controllers;

use App\Models\UserType;
use App\Models\WhatsAppAiAgentSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class WhatsAppAiAgentSettingController extends Controller
{
    public function edit()
    {
        $this->ensureSuperAdmin();

        return view(
            'admin.pages.whatsapp.ai-agent',
            [
                'setting' => WhatsAppAiAgentSetting::active(),
            ]
        );
    }

    public function update(Request $request)
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'enabled' => [
                'nullable',
                'boolean',
            ],
            'auto_reply_enabled' => [
                'nullable',
                'boolean',
            ],
            'provider' => [
                'required',
                Rule::in([
                    'openai',
                ]),
            ],
            'model' => [
                'required',
                'string',
                'max:255',
            ],
            'prompt' => [
                'required',
                'string',
                'max:12000',
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
            'buffer_seconds' => [
                'required',
                'integer',
                'min:1',
                'max:300',
            ],
        ]);

        $setting = WhatsAppAiAgentSetting::active();

        $setting->fill([
            'enabled' => $request->boolean('enabled'),
            'auto_reply_enabled' =>
                $request->boolean('auto_reply_enabled'),
            'provider' => $validated['provider'],
            'model' => $validated['model'],
            'prompt' => $validated['prompt'],
            'buffer_seconds' => (int) $validated['buffer_seconds'],
        ]);

        if ($request->boolean('clear_api_key')) {
            $setting->clearApiKey();
        }

        if ($request->filled('api_key')) {
            $setting->setApiKey($request->input('api_key'));
        }

        $setting->save();

        return redirect()
            ->route('admin.whatsapp.ai-agent.edit')
            ->with(
                'success',
                'WhatsApp AI agent settings updated successfully.'
            );
    }

    private function ensureSuperAdmin(): void
    {
        $role = optional(Auth::user()->userType)->user_type;

        abort_unless($role === UserType::SUPER_ADMIN, 403);
    }
}
