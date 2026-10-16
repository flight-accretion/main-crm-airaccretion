<?php

namespace App\Services;

use App\Exports\SalesReportExport;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TargetIncentiveReportService
{
    private TargetIncentiveService $incentives;

    public function __construct(TargetIncentiveService $incentives)
    {
        $this->incentives = $incentives;
    }

    public function build(Collection $staff, Collection $targets, int $year, int $month, bool $singlePerson): array
    {
        if ($targets->groupBy('sales_executive_id')->contains(fn ($items) => $items->count() > 1)) {
            throw ValidationException::withMessages(['person_id' => 'Resolve duplicate active targets for this month before downloading incentives.']);
        }
        $ledger = $this->incentives->ledger($year, $month)->keyBy('lead_id');
        $configured = $targets->where('incentive_enabled', true);
        $managers = $configured->where('incentive_basis', 'team')->pluck('sales_executive_id')->unique();
        if ($managers->isNotEmpty()) $this->incentives->primeTeams($managers);
        $products = Product::pluck('product', 'id');
        $targets = $targets->keyBy('sales_executive_id');
        $summary = collect();
        $amounts = [];
        $recipients = [];
        $visibleIds = $singlePerson ? [] : $ledger->keys()->all();

        foreach ($staff as $person) {
            $target = $targets->get($person->id);
            $enabled = $target && $target->incentive_enabled;
            $ownSales = $ledger->where('owner', (string) $person->id)->sum('amount');
            $result = $enabled ? $this->incentives->calculate($target, $ledger) : null;
            $components = ['individual' => 0.0, 'own' => 0.0, 'team' => 0.0, 'company' => 0.0];
            if ($enabled) {
                $eligible = $this->incentives->eligibleSales($target, $ledger);
                if ($singlePerson) $visibleIds = array_merge($visibleIds, $eligible->keys()->all());
                if ($target->incentive_basis === 'team') {
                    $components['own'] = $result['own_incentive'];
                    $components['team'] = $result['team_incentive'];
                    $parts = [
                        ['own', $eligible->where('owner', (string) $person->id), (float) $target->incentive_multiplier],
                        ['team', $eligible->where('owner', '!=', (string) $person->id), (float) $target->incentive_team_multiplier],
                    ];
                } else {
                    $component = $target->incentive_basis === 'company' ? 'company' : 'individual';
                    $components[$component] = $result['incentive'];
                    $parts = [[$component, $eligible, (float) $target->incentive_multiplier]];
                }
                foreach ($parts as [$component, $sales, $rate]) {
                    foreach ($this->allocateCents($sales, $rate, $components[$component]) as $leadId => $cents) {
                        $amounts[$leadId][$component] = ($amounts[$leadId][$component] ?? 0) + $cents;
                        $recipients[$leadId][] = $person->name . ' (' . ucfirst($component) . ': ' . number_format($cents / 100, 2, '.', '') . ')';
                    }
                }
            } elseif ($singlePerson) {
                $visibleIds = array_merge($visibleIds, $ledger->where('owner', (string) $person->id)->keys()->all());
            }
            $summary->push([
                $person->name, optional($person->userType)->user_type ?? '', $year, $month,
                $target ? (float) $target->target_amount : null, (float) $ownSales,
                $enabled ? ucfirst($target->incentive_basis) : 'Not configured',
                $enabled && $target->incentive_basis === 'company'
                    ? (collect($target->incentive_product_ids)->map(fn ($id) => $products[$id] ?? 'Unavailable product')->implode(', ') ?: 'All products') : '',
                $result['sales_amount'] ?? null, $enabled ? (float) $target->incentive_multiplier : null,
                $result['incentive'] ?? null, $target ? $target->status : 'No active target',
                $result['own_sales'] ?? null, $result['team_sales'] ?? null,
                $enabled && $target->incentive_basis === 'team' ? (float) $target->incentive_team_multiplier : null,
                $enabled ? $components['own'] : null, $enabled ? $components['team'] : null,
                $enabled ? $components['individual'] : null, $enabled ? $components['company'] : null,
                $enabled ? 'Configured' : ($target ? 'Incentive disabled' : 'Not configured'),
            ]);
        }

        $visible = array_fill_keys(array_unique($visibleIds), true);
        $detail = collect();
        $report = new SalesReportExport(['year' => $year, 'month' => $month], $singlePerson ? collect(array_keys($visible)) : null);
        $seen = [];
        foreach ($report->generator() as $sale) {
            $id = $sale->lead_id;
            if (!isset($visible[$id])) continue;
            if (isset($seen[$id]) || !$ledger->has($id) || abs((float) $sale->sales_amount - $ledger[$id]['amount']) > 0.005) {
                throw ValidationException::withMessages(['month' => 'CRM sales changed or do not reconcile. Refresh and retry the incentive download.']);
            }
            $seen[$id] = true;
            $values = $amounts[$id] ?? [];
            $detail->push(array_merge($report->map($sale), [
                ($values['individual'] ?? 0) / 100, ($values['own'] ?? 0) / 100,
                ($values['team'] ?? 0) / 100, ($values['company'] ?? 0) / 100,
                array_sum($values) / 100, implode('; ', $recipients[$id] ?? []) ?: 'No configured incentive',
            ]));
        }
        if (count($seen) !== count($visible)) {
            throw ValidationException::withMessages(['month' => 'Some incentive sales could not be reconciled with the CRM Sales Report. Refresh and retry.']);
        }
        return ['detail' => $detail, 'summary' => $summary];
    }

    private function allocateCents(Collection $sales, float $rate, float $total): array
    {
        $cents = [];
        $fractions = [];
        foreach ($sales as $id => $sale) {
            $raw = $sale['amount'] * $rate * 100;
            $cents[$id] = (int) floor($raw + 0.00000001);
            $fractions[$id] = $raw - $cents[$id];
        }
        // Allocate fractional paise deterministically so detail equals the existing monthly payout.
        $remaining = (int) round($total * 100) - array_sum($cents);
        $ids = array_keys($cents);
        usort($ids, fn ($a, $b) => ($fractions[$b] <=> $fractions[$a]) ?: strcmp((string) $a, (string) $b));
        for ($i = 0; $i < $remaining && $ids; $i++) $cents[$ids[$i % count($ids)]]++;
        if (array_sum($cents) !== (int) round($total * 100)) {
            throw ValidationException::withMessages(['month' => 'Incentive rounding could not be reconciled.']);
        }
        return $cents;
    }
}
