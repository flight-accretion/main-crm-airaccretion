<?php

namespace App\Http\Requests;

use App\Models\UserType;
use Illuminate\Foundation\Http\FormRequest;

class BulkLeadCancelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && optional($user->userType)->user_type === UserType::SUPER_ADMIN;
    }

    public function rules(): array
    {
        return [
            'lead_ids' => ['required', 'array', 'min:1', 'max:100'],
            'lead_ids.*' => ['required', 'uuid', 'distinct'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'operation_id' => ['required', 'uuid'],
        ];
    }
}
