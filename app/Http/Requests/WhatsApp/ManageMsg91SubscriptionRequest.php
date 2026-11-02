<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class ManageMsg91SubscriptionRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user(); }
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'status' => ['required', 'in:active,inactive,suspended'],
            'expires_at' => ['nullable', 'date'],
            'payment_reference' => ['nullable', 'string', 'max:191'],
            'integrated_number' => ['nullable', 'string', 'max:30'],
            'sender_status' => ['nullable', 'in:active,inactive'],
            'verified' => ['nullable', 'boolean'],
        ];
    }
}
