<?php

namespace App\Services\Kpi;

use App\Models\CallSummaryIntegration;
use App\Models\IvrAgent;
use App\Models\IvrCallLog;
use App\Models\KpiOutreachAssignment;
use App\Models\User;
use Illuminate\Support\Str;

class KpiOutreachCallVerifier
{
    private const CONNECTED_STATUSES = [
        'success',
        'sucess',
        'connected',
    ];

    public function connectedCall(
        KpiOutreachAssignment $assignment,
        User $user
    ): ?CallSummaryIntegration {
        return CallSummaryIntegration::query()
            ->where('agent_user_id', $user->id)
            ->where('normalized_phone', $assignment->normalized_phone)
            ->where('direction', 'outgoing')
            ->where('call_start_at', '>=', $assignment->assigned_at)
            ->whereNotNull('summary')
            ->orderByDesc('call_start_at')
            ->first();
    }

    public function noAnswerCall(
        KpiOutreachAssignment $assignment,
        User $user
    ): ?IvrCallLog {
        $agents = IvrAgent::query()
            ->where('mapped_user_id', $user->id)
            ->where('is_active', true)
            ->get();

        if ($agents->isEmpty()) {
            return null;
        }

        $numbers = $agents
            ->pluck('vi_agent_number')
            ->filter()
            ->map(fn ($value) => $this->normalize($value))
            ->filter()
            ->unique()
            ->values();

        $names = $agents
            ->pluck('vi_agent_name')
            ->filter()
            ->map(fn ($value) => $this->normalizeAgent($value))
            ->filter()
            ->unique()
            ->values();

        $query = IvrCallLog::query()
            ->where('normalized_phone', $assignment->normalized_phone)
            ->where('call_start_at', '>=', $assignment->assigned_at);

        $outboundCodes = (array) config('kpi.outreach.outbound_ivr_codes', []);

        if ($outboundCodes) {
            $query->whereIn('call_type_code', $outboundCodes);
        }

        $logs = $query
            ->orderByDesc('call_start_at')
            ->limit(25)
            ->get();

        foreach ($logs as $log) {
            $agentMatches = false;

            if (
                $log->agent_number
                && $numbers->contains($this->normalize($log->agent_number))
            ) {
                $agentMatches = true;
            }

            if (
                !$agentMatches
                && $log->agent_name
                && $names->contains($this->normalizeAgent($log->agent_name))
            ) {
                $agentMatches = true;
            }

            if (!$agentMatches) {
                continue;
            }

            $status = Str::lower(trim((string) $log->dial_status));

            if (!in_array($status, self::CONNECTED_STATUSES, true)) {
                return $log;
            }
        }

        return null;
    }

    private function normalize(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === '') {
            return null;
        }

        return strlen($digits) > 10
            ? substr($digits, -10)
            : $digits;
    }

    private function normalizeAgent(?string $value): string
    {
        return preg_replace(
            '/[^a-z0-9]/',
            '',
            Str::lower((string) $value)
        );
    }
}
