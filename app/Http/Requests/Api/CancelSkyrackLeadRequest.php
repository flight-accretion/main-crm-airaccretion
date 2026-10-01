<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CancelSkyrackLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authentication is provided by the SAME middleware as existing Skyrack endpoints.
        // The service separately verifies the actor's permission on the particular lead.
        return true;
    }

    public function rules(): array
    {
        return [
            'lead_id' => ['nullable', 'uuid'],
            'phone_number' => ['required_without:lead_id', 'string', 'max:50'],
            'agent_number' => ['nullable', 'string', 'regex:/^\+?[0-9\s-]{10,18}$/'],
            'agent_phone' => ['required_without:agent_number', 'string', 'regex:/^\+?[0-9\s-]{10,18}$/'],
            'agent_name' => ['nullable', 'string', 'max:150'],
            'direction' => ['nullable', 'string', 'in:incoming,outgoing,unknown'],
            'call_start_at' => ['nullable', 'date'],
            'call_end_at' => ['nullable', 'date', 'after_or_equal:call_start_at'],
            'followup_recording_id' => ['nullable', 'integer', 'min:0'],
            'request_id' => ['nullable', 'uuid'],
            'reason' => ['required', 'string', 'max:255'],
            'remark' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
