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
            'lead_id' => ['required', 'uuid'],
            'agent_number' => ['required', 'string', 'regex:/^\+?[0-9\s-]{10,18}$/'],
            'request_id' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'max:255'],
            'remark' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
