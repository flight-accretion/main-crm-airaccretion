<?php

namespace App\Services\Operations;

class ActivityDisplayGroups
{
    public function group(iterable $activities): array
    {
        $groups = [];
        $lastKey = null;
        foreach ($activities as $activity) {
            // Group only consecutive identical system events; retain every original record.
            $key = $activity->user_id === null ? json_encode([
                $activity->getRawOriginal('created_at'), $activity->operationCase?->type,
                $activity->action, $activity->from_status, $activity->to_status,
                $activity->note, $activity->metadata,
            ]) : $activity->id;
            if ($groups && $key === $lastKey) {
                $groups[count($groups) - 1][] = $activity;
            } else {
                $groups[] = [$activity];
            }
            $lastKey = $key;
        }
        return $groups;
    }
}
