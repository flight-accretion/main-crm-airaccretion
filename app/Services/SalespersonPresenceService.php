<?php

namespace App\Services;

use App\Models\SalespersonAvailability;
use App\Models\User;
use App\Models\UserType;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SalespersonPresenceService
{
    public function isPresentToday(User $user, ?Carbon $now = null): bool
    {
        $now = $now ?: Carbon::now();

        $availability = SalespersonAvailability::query()
            ->where('user_id', $user->id)
            ->first();

        return (bool) (
            $availability
            && $availability->is_available
            && $availability->is_opted_in
            && $availability->last_response_at
            && $availability->last_response_at->isSameDay($now)
        );
    }

    public function rowsForDashboard(User $viewer, ?Carbon $now = null): Collection
    {
        $now = $now ?: Carbon::now();
        $viewer->loadMissing('userType');

        $users = $this->visibleUsers($viewer);
        $availabilityByUser = SalespersonAvailability::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->get()
            ->keyBy('user_id');

        return $users
            ->map(function (User $user) use ($availabilityByUser, $now) {
                $availability = $availabilityByUser->get($user->id);
                $isPresent = (bool) (
                    $availability
                    && $availability->is_available
                    && $availability->is_opted_in
                    && $availability->last_response_at
                    && $availability->last_response_at->isSameDay($now)
                );

                return [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->userType->user_type ?? 'N/A',
                    'status_label' => $isPresent ? 'Yes' : 'No',
                    'is_present_today' => $isPresent,
                    'state' => $availability->state ?? 'unasked',
                    'last_response_at' => $availability->last_response_at ?? null,
                    'last_response_label' => $availability && $availability->last_response_at
                        ? $availability->last_response_at->format('d M Y, h:i A')
                        : 'Not confirmed today',
                ];
            })
            ->values();
    }

    private function visibleUsers(User $viewer): Collection
    {
        $role = $viewer->userType->user_type ?? null;

        if ($role === UserType::SUPER_ADMIN) {
            return User::query()
                ->with('userType')
                ->where('status', 1)
                ->whereHas('userType', function ($query) {
                    $query->whereIn('user_type', UserType::SALES_ROLES);
                })
                ->orderBy('name')
                ->get();
        }

        return User::query()
            ->with('userType')
            ->where('id', $viewer->id)
            ->get();
    }
}
