<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\PaymentAuditTrail;
use App\Models\SalesExecutiveAssignment;
use App\Models\Target;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TargetIncentiveService
{
    private array $ledgers = [];
    private array $teams = [];

    public function forTarget(Target $target): array
    {
        return $this->calculate($target, $target->incentive_enabled && $target->status === 'active'
            ? $this->ledger((int) $target->year, (int) $target->month) : collect());
    }

    public function calculate(Target $target, Collection $ledger): array
    {
        if (!$target->incentive_enabled || $target->status !== 'active') {
            return ['sales_amount' => 0.0, 'incentive' => 0.0];
        }
        $basis = $target->incentive_basis;
        $owner = (string) $target->sales_executive_id;
        $team = $basis === 'team' ? $this->team($owner) : [$owner];
        $productIds = $target->incentive_product_ids ?? [];
        $amount = $ledger->filter(function ($sale) use ($basis, $team, $productIds) {
            if ($basis === 'company') {
                return !$productIds || (bool) array_intersect($productIds, $sale['products']);
            }
            return in_array($sale['owner'], $team, true);
        })->sum('amount');

        if ($basis === 'team') {
            $ownSales = $ledger->where('owner', $owner)->sum('amount');
            $teamSales = max(0, $amount - $ownSales);
            $ownIncentive = round($ownSales * (float) $target->incentive_multiplier, 2);
            $teamIncentive = round($teamSales * (float) $target->incentive_team_multiplier, 2);
            return ['sales_amount' => round($amount, 2), 'own_sales' => round($ownSales, 2), 'team_sales' => round($teamSales, 2),
                'own_incentive' => $ownIncentive, 'team_incentive' => $teamIncentive, 'incentive' => round($ownIncentive + $teamIncentive, 2)];
        }
        return ['sales_amount' => round($amount, 2), 'incentive' => round($amount * (float) $target->incentive_multiplier, 2)];
    }

    protected function team(string $manager): array
    {
        return $this->teams[$manager] ??= SalesExecutiveAssignment::active()->forManager($manager)
            ->pluck('sales_executive_id')->push($manager)->map(fn ($id) => (string) $id)->unique()->values()->all();
    }

    public function ledger(int $year, int $month): Collection
    {
        $key = "$year-$month";
        if (isset($this->ledgers[$key])) return $this->ledgers[$key];
        $from = Carbon::create($year, $month, 1)->startOfDay();
        $paidFollowups = PaymentAuditTrail::where('payment_status', PaymentAuditTrail::STATUS_APPROVED)
            ->where('paid_date', '>=', $from->toDateString())->where('paid_date', '<', $from->copy()->addMonth()->toDateString())
            ->select('lead_followup_id');
        $paidLeads = LeadFollowup::whereIn('id', $paidFollowups)->select('lead_id');
        $ledger = collect();

        // One sale per paid lead; match Target's existing qualifying-follow-up and net-sale rules.
        Lead::whereIn('id', $paidLeads)->select(['id', 'representative_user_id', 'product_ids'])
            ->chunkById(250, function ($leads) use ($ledger) {
                $ids = $leads->pluck('id');
                $latest = LeadFollowup::whereIn('lead_id', $ids)->whereIn('status', LeadFollowup::salesAmountStatuses())
                    ->orderByDesc('created_at')->orderByDesc('id')->get()->unique('lead_id')->keyBy('lead_id');
                $refunds = DB::table('lead_refunds')->join('lead_followups', 'lead_followups.id', '=', 'lead_refunds.lead_followup_id')
                    ->whereIn('lead_followups.lead_id', $ids)->whereIn('lead_refunds.status', [1, 2])
                    ->groupBy('lead_followups.lead_id')->selectRaw('lead_followups.lead_id, SUM(lead_refunds.refund_amount) AS refunded')
                    ->pluck('refunded', 'lead_id');
                foreach ($leads as $lead) {
                    $followup = $latest->get($lead->id);
                    if (!$followup) continue;
                    $ledger->push(['lead_id' => (string) $lead->id, 'owner' => (string) $lead->representative_user_id,
                        'products' => $lead->product_ids_array,
                        'amount' => SalesAmountCalculator::salesAmountForFollowup($followup, (float) $refunds->get($lead->id, 0))]);
                }
            });
        return $this->ledgers[$key] = $ledger;
    }
}
