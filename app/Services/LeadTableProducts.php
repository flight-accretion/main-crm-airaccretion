<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Product;

class LeadTableProducts
{
    /** Resolve only the visible records, without a product query for each row. */
    public function forLeads(iterable $leads): array
    {
        $idsByLead = [];
        foreach ($leads as $lead) {
            if ($lead instanceof Lead) {
                $idsByLead[$lead->id] = $lead->product_ids_array;
            }
        }

        $ids = collect($idsByLead)->flatten()->unique()->values();
        // Archived products must still be readable on historical CRM records.
        $products = $ids->isEmpty() ? collect() : Product::whereIn('id', $ids)->pluck('product', 'id');

        return array_map(fn ($ids) => collect($ids)->unique()
            ->map(fn ($id) => $products->get($id))->filter()->implode(', '), $idsByLead);
    }

    public function forLeadIds(iterable $ids): array
    {
        $ids = collect($ids)->filter()->unique()->values();

        return $ids->isEmpty() ? [] : $this->forLeads(
            Lead::whereIn('id', $ids)->get(['id', 'product_ids'])
        );
    }

    public function forRecords(iterable $records, string $leadIdPath = 'lead_id'): array
    {
        $ids = [];
        foreach ($records as $record) {
            $ids[] = data_get($record, $leadIdPath);
        }

        return $this->forLeadIds($ids);
    }

    public function forRelatedLeads(iterable $records, string $relation = 'lead'): array
    {
        $leads = [];
        foreach ($records as $record) {
            $leads[] = data_get($record, $relation);
        }

        return $this->forLeads($leads);
    }
}
