<?php

namespace App\Services;

use App\Models\SalesExecutiveAssignment;
use App\Models\User;
use App\Models\UserType;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;

class WhatsAppConversationVisibilityService
{
    public function visibleConversationsQuery(User $user)
    {
        $visibleUserIds = $this->visibleUserIds($user);

        $query = WhatsAppConversation::query()
            ->with(['contact', 'assignedUser']);

        if ($visibleUserIds === null) {
            return $query;
        }

        if (empty($visibleUserIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn(
            'assigned_user_id',
            $visibleUserIds
        );
    }

    public function canAccessConversation(
        User $user,
        string $conversationId
    ): bool {
        return $this->visibleConversationsQuery($user)
            ->where('id', $conversationId)
            ->exists();
    }

    public function markReadForUser(
        User $user,
        string $conversationId
    ): bool {
        $conversation = $this->visibleConversationsQuery($user)
            ->where('id', $conversationId)
            ->first();

        if (
            !$conversation
            || $conversation->assigned_user_id !== $user->id
        ) {
            return false;
        }

        WhatsAppMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'incoming')
            ->whereNull('crm_read_at')
            ->update([
                'crm_read_at' => now(),
                'updated_at' => now(),
            ]);

        $conversation->unread_count = 0;
        $conversation->save();

        return true;
    }

    public function agentFilterUsers(User $user)
    {
        $visibleUserIds = $this->visibleUserIds($user);

        if ($visibleUserIds === null) {
            return User::query()
                ->with('userType')
                ->where('status', 1)
                ->whereHas('userType', function ($query) {
                    $query->whereIn(
                        'user_type',
                        UserType::SALES_ROLES
                    );
                })
                ->orderBy('name')
                ->get();
        }

        if (empty($visibleUserIds)) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $visibleUserIds)
            ->orderBy('name')
            ->get();
    }

    private function visibleUserIds(User $user): ?array
    {
        $role = optional($user->userType)->user_type;

        if ($role === UserType::SUPER_ADMIN) {
            return null;
        }

        if ($role === UserType::SALES_EXECUTIVE) {
            return [$user->id];
        }

        if (
            in_array(
                $role,
                [
                    UserType::SENIOR_SALES_MANAGER,
                    UserType::SALES_MANAGER,
                ],
                true
            )
        ) {
            $teamIds = SalesExecutiveAssignment::query()
                ->where('manager_id', $user->id)
                ->where('status', 1)
                ->pluck('sales_executive_id')
                ->filter()
                ->values()
                ->all();

            return array_values(
                array_unique(
                    array_merge([$user->id], $teamIds)
                )
            );
        }

        return [];
    }
}
