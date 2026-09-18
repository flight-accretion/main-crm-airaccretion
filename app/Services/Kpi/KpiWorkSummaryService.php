<?php

namespace App\Services\Kpi;

use Carbon\Carbon;

class KpiWorkSummaryService
{
    public function __construct(
        private KpiSalesWorkService $sales,
        private KpiDashboardService $dashboard
    ) {}

    public function cardsForUsers(
        string $department,
        array $users,
        Carbon $from,
        Carbon $to,
        ?string $leadSource = 'all'
    ): array {
        if ($department === 'sales') {
            $ids = collect($users)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();

            return $this->sales->cards(
                $this->sales->summary($ids, $from, $to, $leadSource)
            );
        }

        $cards = [];

        foreach ($users as $user) {
            $result = $this->dashboard->forUser($user, $to->copy());

            foreach ($result['metrics'] ?? [] as $metric) {
                $code = (string) $metric['code'];

                if (!isset($cards[$code])) {
                    $cards[$code] = [
                        'code' => $code,
                        'label' => (string) $metric['name'],
                        'value' => 0,
                        'subtitle' => 'Existing KPI actual as of selected end date',
                    ];
                }

                $cards[$code]['value'] += (int) round(
                    (float) ($metric['actual_value'] ?? 0)
                );
            }
        }

        return array_values($cards);
    }
}
