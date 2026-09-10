<?php

namespace App\Services\Kpi;

use App\Models\CallSummaryIntegration;
use App\Models\IvrCallLog;
use App\Models\Lead;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KpiOutreachContextService
{
    public function enrich(Collection $assignments): Collection
    {
        $phones = $assignments
            ->pluck('normalized_phone')
            ->filter()
            ->unique()
            ->values();

        if ($phones->isEmpty()) {
            return $assignments;
        }

        $summaries = CallSummaryIntegration::query()
            ->whereIn('normalized_phone', $phones)
            ->orderByDesc('call_start_at')
            ->get()
            ->groupBy('normalized_phone')
            ->map(fn ($rows) => $rows->first());

        $ivr = IvrCallLog::query()
            ->whereIn('normalized_phone', $phones)
            ->orderByDesc('call_start_at')
            ->get()
            ->groupBy('normalized_phone')
            ->map(fn ($rows) => $rows->first());

        $latestLeadByPhone = $this->latestLeadByPhone($phones->all());

        $productIds = collect($latestLeadByPhone)
            ->flatMap(function ($lead) {
                return is_array($lead->product_ids)
                    ? $lead->product_ids
                    : (json_decode($lead->product_ids ?? '[]', true) ?: []);
            })
            ->filter()
            ->unique();

        $products = Product::whereIn('id', $productIds)
            ->pluck('product', 'id');

        return $assignments->map(function ($assignment) use (
            $summaries,
            $ivr,
            $latestLeadByPhone,
            $products
        ) {
            $summary = $summaries->get($assignment->normalized_phone);
            $ivrRow = $ivr->get($assignment->normalized_phone);

            $lastCalled = collect([
                $summary?->call_start_at,
                $ivrRow?->call_start_at,
            ])->filter()->sortDesc()->first();

            $lead = $latestLeadByPhone[$assignment->normalized_phone] ?? null;
            $names = [];

            if ($lead) {
                $ids = is_array($lead->product_ids)
                    ? $lead->product_ids
                    : (json_decode($lead->product_ids ?? '[]', true) ?: []);

                foreach ($ids as $id) {
                    if ($products->has($id)) {
                        $names[] = $products[$id];
                    }
                }
            }

            $assignment->setAttribute('last_called_at_display', $lastCalled);
            $assignment->setAttribute('latest_skyrec_summary', $summary?->summary);
            $assignment->setAttribute(
                'last_product_display',
                $names ? implode(', ', array_unique($names)) : null
            );

            return $assignment;
        });
    }

    private function latestLeadByPhone(array $phones): array
    {
        if (!$phones) {
            return [];
        }

        $expr = config('database.default') === 'pgsql'
            ? "RIGHT(regexp_replace(clients.contact_number, '[^0-9]', '', 'g'), 10)"
            : "RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(clients.contact_number,'+',''),'-',''),' ',''),'(',''),')',''),10)";

        $placeholders = implode(',', array_fill(0, count($phones), '?'));

        $rows = Lead::query()
            ->join('clients', 'clients.id', '=', 'leads.client_id')
            ->whereRaw("{$expr} IN ({$placeholders})", $phones)
            ->select('leads.*', DB::raw("{$expr} AS normalized_lookup_phone"))
            ->orderByDesc('leads.created_at')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $phone = $row->normalized_lookup_phone;

            if (!isset($result[$phone])) {
                $result[$phone] = $row;
            }
        }

        return $result;
    }
}
