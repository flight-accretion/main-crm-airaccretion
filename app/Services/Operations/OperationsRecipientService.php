<?php

namespace App\Services\Operations;

use App\Models\User;
use App\Models\UserType;
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
        $digits = preg_replace('/\D+/', '', $number);

        if (!$digits) {
            return '';
        }

        if (strlen($digits) === 10) {
            $digits = '91' . $digits;
        }

        return strlen($digits) >= 11 && strlen($digits) <= 15
            ? '+' . $digits
            : '';
    }
}
