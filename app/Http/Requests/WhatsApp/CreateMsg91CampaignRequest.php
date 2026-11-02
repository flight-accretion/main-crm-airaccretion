<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class CreateMsg91CampaignRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user(); }
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'template_id' => ['required', 'uuid'],
            'lead_ids' => ['required', 'array', 'min:1', 'max:5000'],
            'lead_ids.*' => ['uuid'],
        ];
    }
}
