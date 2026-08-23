<?php

namespace App\Services;

use App\Models\User;
use App\Models\WhatCrmAgentMapping;

class WhatCrmAgentResolver
{
    public function resolve(
        ?string $agentUserId,
        ?string $agentName,
        ?string $whatCrmAgentId
    ): ?User {
        if ($agentUserId) {
            $user = User::query()
                ->whereKey($agentUserId)
                ->where('status', 1)
                ->first();

            if ($user) {
                return $user;
            }
        }

        if (!$agentName && !$whatCrmAgentId) {
            return null;
        }

        $mapping = WhatCrmAgentMapping::query()
            ->with('crmUser')
            ->where('is_active', true)
            ->when(
                $whatCrmAgentId,
                function ($query) use ($whatCrmAgentId) {
                    $query->where(
                        'whatcrm_agent_id',
                        $whatCrmAgentId
                    );
                }
            )
            ->when(
                !$whatCrmAgentId && $agentName,
                function ($query) use ($agentName) {
                    $query->whereRaw(
                        'LOWER(whatcrm_agent_name) = ?',
                        [mb_strtolower(trim($agentName))]
                    );
                }
            )
            ->first();

        if (
            $mapping
            && $mapping->crmUser
            && (int) $mapping->crmUser->status === 1
        ) {
            return $mapping->crmUser;
        }

        return null;
    }
}
