<?php

namespace App\Services\Kpi;

use Carbon\Carbon;
use Illuminate\Http\Request;

class KpiDashboardFilterService
{
    public function fromRequest(Request $request): array
    {
        $data = $request->validate([
            'preset' =>
                'nullable|in:today,yesterday,this_month,custom',

            'from_date' =>
                'nullable|date_format:Y-m-d',

            'to_date' =>
                'nullable|date_format:Y-m-d|after_or_equal:from_date',

            'department' =>
                'nullable|in:sales,accounts,operations',

            'user_id' =>
                'nullable|uuid|exists:users,id',

            'lead_source' =>
                'nullable|in:all,whatsapp,website_form,email,ivr,manual',
        ]);

        /*
         * KPI Dashboard default:
         * first day of current month through today.
         */
        $preset = $data['preset']
            ?? 'this_month';

        $today = now()->startOfDay();

        [$from, $to] = match ($preset) {
            'today' => [
                $today->copy()->startOfDay(),
                $today->copy()->endOfDay(),
            ],

            'yesterday' => [
                $today->copy()->subDay()->startOfDay(),
                $today->copy()->subDay()->endOfDay(),
            ],

            'custom' => [
                Carbon::parse(
                    $data['from_date']
                        ?? $today->toDateString()
                )->startOfDay(),

                Carbon::parse(
                    $data['to_date']
                        ?? $today->toDateString()
                )->endOfDay(),
            ],

            default => [
                $today
                    ->copy()
                    ->startOfMonth()
                    ->startOfDay(),

                $today
                    ->copy()
                    ->endOfDay(),
            ],
        };

        return [
            'preset' => $preset,
            'from' => $from,
            'to' => $to,
            'department' =>
                $data['department'] ?? null,
            'user_id' =>
                $data['user_id'] ?? null,
            'lead_source' =>
                $data['lead_source'] ?? 'all',
        ];
    }
}