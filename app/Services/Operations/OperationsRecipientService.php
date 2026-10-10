<?php

namespace App\Services\Operations;

use App\Models\User;
use App\Models\UserType;
use App\Support\WhatsAppPhoneNumber;
use Illuminate\Support\Collection;

class OperationsRecipientService
{
    public function activeUsers(): Collection
    {
        return User::query()
            ->where('status', 1)
            ->whereHas('userType', function ($query) {
                $query->whereIn('user_type', UserType::OPERATIONS_ROLES);
            })
            ->whereNotNull('contact_number')
            ->get()
            ->filter(fn (User $user) => $this->normalizedNumber(
                (string) $user->contact_number
            ) !== '')
            ->values();
    }

    public function normalizedNumber(string $number): string
    {
        try {
            return WhatsAppPhoneNumber::e164($number);
        } catch (\InvalidArgumentException) {
            return '';
        }
    }
}
