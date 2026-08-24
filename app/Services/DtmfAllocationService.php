<?php

namespace App\Services;

use App\Models\IvrAgent;
use App\Models\IvrCallLog;
use App\Models\IvrCallType;
use App\Models\IvrDtmfRule;
use App\Models\Lead;
use App\Models\SalespersonAvailability;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DtmfAllocationService
{
    public function mappedUserForSuccessfulAgent(?string $agentNumber, ?string $agentName = null): ?User
    {
        $normalizedNumber = $this->normalizePhone($agentNumber);
        $mapping = $normalizedNumber !== ''
            ? $this->mappedAgentByNumber($normalizedNumber)
            : $this->mappedAgentByName($agentName);

        if (!$mapping || !$mapping->mappedUser || (int) $mapping->mappedUser->status !== 1) {
            return null;
        }

        return $mapping->mappedUser;
    }

    private function mappedAgentByNumber(?string $agentNumber): ?IvrAgent
    {
        $needle = $this->normalizePhone($agentNumber);
        if ($needle === '') {
            return null;
        }

        return IvrAgent::with('mappedUser')
            ->where('is_active', true)
            ->get()
            ->first(function (IvrAgent $agent) use ($needle) {
                return $this->normalizePhone($agent->vi_agent_number) === $needle;
            });
    }

    private function mappedAgentByName(?string $agentName): ?IvrAgent
    {
        $needle = $this->normalizeText($agentName);
        if ($needle === '') {
            return null;
        }

        return IvrAgent::with('mappedUser')
            ->where('is_active', true)
            ->get()
            ->first(function (IvrAgent $agent) use ($needle) {
                return $this->normalizeText($agent->vi_agent_name) === $needle;
            });
    }

    public function resolvePool(?string $callTypeId, ?string $rawDtmf): array
    {
        $rawValue = trim((string) $rawDtmf);
        $rules = IvrDtmfRule::with(['users.user'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $specificRules = $rules->filter(function (IvrDtmfRule $rule) use ($callTypeId) {
            return $callTypeId && $rule->ivr_call_type_id === $callTypeId;
        });
        $globalRules = $rules->filter(function (IvrDtmfRule $rule) {
            return empty($rule->ivr_call_type_id);
        });

        $matched = $this->findMatchingRule($specificRules, $rawValue)
            ?: $this->findMatchingRule($globalRules, $rawValue)
            ?: $this->findDefaultRule($specificRules)
            ?: $this->findDefaultRule($globalRules);

        if ($matched) {
            $ids = $matched->users
                ->sortBy('priority')
                ->filter(function ($row) { return $row->user && (int) $row->user->status === 1; })
                ->pluck('user_id')
                ->unique()
                ->values();

            if ($ids->isNotEmpty()) {
                return [
                    'user_ids' => $ids,
                    'mode' => $matched->assignment_mode ?: 'balanced',
                    'source' => 'dtmf_rule',
                    'rule_id' => $matched->id,
                ];
            }
        }

        // if ($callTypeId) {
        //     $callType = IvrCallType::with(['users.user'])->where('id', $callTypeId)->where('is_active', true)->first();
        //     if ($callType) {
        //         $ids = $callType->users
        //             ->sortBy('priority')
        //             ->filter(function ($row) { return $row->user && (int) $row->user->status === 1; })
        //             ->pluck('user_id')
        //             ->unique()
        //             ->values();
        //         if ($ids->isNotEmpty()) {
        //             return [
        //                 'user_ids' => $ids,
        //                 'mode' => $callType->assignment_mode ?: 'balanced',
        //                 'source' => 'call_type',
        //                 'rule_id' => $callType->id,
        //             ];
        //         }
        //     }
        // }

        return [
            'user_ids' => $this->allActiveSalesUserIds(),
            'mode' => 'random',
            'source' => 'sales_fallback',
            'rule_id' => null,
        ];
    }

    public function pickAvailableUser(?string $callTypeId, ?string $rawDtmf): ?User
    {
        $pool = $this->resolvePool($callTypeId, $rawDtmf);
        $ids = collect($pool['user_ids'] ?? []);
        if ($ids->isEmpty()) {
            return null;
        }

        $users = User::whereIn('id', $ids)->where('status', 1)->get();
        $users = $users->filter(function (User $user) {
            $availability = SalespersonAvailability::where('user_id', $user->id)->first();
            return $availability && $availability->is_available && $availability->is_opted_in;
        })->values();

        if ($users->isEmpty()) {
            return null;
        }

        if (($pool['mode'] ?? 'balanced') === 'random') {
            return $users->random();
        }

        return $users->sortBy(function (User $user) {
            return Lead::where('representative_user_id', $user->id)
                ->whereDate('created_at', now()->toDateString())
                ->count();
        })->first();
    }

    public function poolForCallLog(IvrCallLog $callLog): array
    {
        return $this->resolvePool($callLog->ivr_call_type_id, $callLog->raw_dtmf);
    }

    private function findMatchingRule(Collection $rules, string $rawValue): ?IvrDtmfRule
    {
        if ($rawValue === '') {
            return null;
        }

        $needle = $this->normalizeText($rawValue);
        return $rules->first(function (IvrDtmfRule $rule) use ($needle) {
            if ($rule->is_default) {
                return false;
            }
            $values = array_merge([(string) $rule->dtmf_value], is_array($rule->match_values) ? $rule->match_values : []);
            foreach ($values as $value) {
                if ($this->normalizeText($value) === $needle) {
                    return true;
                }
            }
            return false;
        });
    }

    private function findDefaultRule(Collection $rules): ?IvrDtmfRule
    {
        return $rules->first(function (IvrDtmfRule $rule) { return (bool) $rule->is_default; });
    }

    private function allActiveSalesUserIds(): Collection
    {
        return User::query()
            ->whereHas('userType', function ($query) {
                $query->whereIn('user_type', UserType::SALES_ROLES);
            })
            ->where('status', 1)
            ->pluck('id');
    }

    private function normalizeText($value): string
    {
        return Str::lower(trim((string) $value));
    }

    private function normalizePhone($value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === '') {
            return '';
        }

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }
}
