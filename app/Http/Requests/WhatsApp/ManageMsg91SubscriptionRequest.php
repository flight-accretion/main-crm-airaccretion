<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class ManageMsg91SubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }


    public function rules(): array
    {
        return [

            'user_id' => [
                'required',
                'uuid',
                'exists:users,id',
            ],


            /*
             * Sender access only.
             */
            'sender_status' => [
                'required',
                'in:active,inactive',
            ],


            /*
             * MSG91 integrated WhatsApp number.
             */
            'integrated_number' => [
                'nullable',
                'string',
                'max:30',
            ],

        ];
    }


    public function messages(): array
    {
        return [

            'user_id.required' =>
                'Please select a user.',

            'user_id.exists' =>
                'The selected user was not found.',

            'sender_status.required' =>
                'Please select sender status.',

            'sender_status.in' =>
                'Sender status must be Active or Inactive.',

            'integrated_number.max' =>
                'Integrated number is too long.',

        ];
    }
}