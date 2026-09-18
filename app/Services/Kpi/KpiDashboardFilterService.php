<?php

namespace App\Services\Kpi;

use Carbon\Carbon;
use Illuminate\Http\Request;

class KpiDashboardFilterService
{
    public function fromRequest(Request $request): array
    {
        $data = $request->validate([
            'preset' => 'nullable|in:today,yesterday,last_7_days,custom',
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d|after_or_equal:from_date',
            'department' => 'nullable|in:sales,accounts,operations',
            'user_id' => 'nullable|uuid|exists:users,id',
            'lead_source' => 'nullable|in:all,whatsapp,website_form,email,ivr,manual',
        ]);

        $preset = $data['preset'] ?? 'today';
        $today = now()->startOfDay();

        [$from, $to] = match ($preset) {
            'yesterday' => [
                $today->copy()->subDay()->startOfDay(),
                $today->copy()->subDay()->endOfDay(),
            ],
            'last_7_days' => [
                $today->copy()->subDays(6)->startOfDay(),
                $today->copy()->endOfDay(),
            ],
            'custom' => [
                Carbon::parse(
                    $data['from_date'] ?? $today->toDateString()
                )->startOfDay(),
                Carbon::parse(
                    $data['to_date'] ?? $today->toDateString()
                )->endOfDay(),
            ],
            default => [
                $today->copy()->startOfDay(),
                $today->copy()->endOfDay(),
            ],
        };

        return [
            'preset' => $preset,
            'from' => $from,
            'to' => $to,
            'department' => $data['department'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'lead_source' => $data['lead_source'] ?? 'all',
        ];
    }
}
