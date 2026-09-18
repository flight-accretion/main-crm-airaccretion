<?php

namespace App\Services\Kpi;

use Illuminate\Database\Query\Builder;

class KpiLeadSourceFilterService
{
    public function options(): array
    {
        return [
            'all' => 'All Sources',
            'whatsapp' => 'WhatsApp',
            'website_form' => 'Website Form',
            'email' => 'Email',
            'ivr' => 'IVR',
            'manual' => 'Manual / Other',
        ];
    }

    public function apply(
        Builder $query,
        string $leadAlias,
        ?string $source
    ): Builder {
        if (!$source || $source === 'all') {
            return $query;
        }

        if ($source === 'whatsapp') {
            return $query->whereExists(function ($sub) use ($leadAlias) {
                $sub->selectRaw('1')
                    ->from('whatsapp_lead_integrations as wli')
                    ->whereColumn('wli.lead_id', "{$leadAlias}.id");
            });
        }

        if (in_array($source, ['email', 'website_form'], true)) {
            return $query->whereExists(
                function ($sub) use ($leadAlias, $source) {
                    $sub->selectRaw('1')
                        ->from('email_lead_logs as ell')
                        ->whereColumn('ell.lead_id', "{$leadAlias}.id")
                        ->where('ell.source_type', $source);
                }
            );
        }

        if ($source === 'ivr') {
            return $query->whereExists(function ($sub) use ($leadAlias) {
                $sub->selectRaw('1')
                    ->from('ivr_call_logs as icl')
                    ->whereColumn('icl.lead_id', "{$leadAlias}.id");
            });
        }

        if ($source === 'manual') {
            return $query
                ->whereNotExists(function ($sub) use ($leadAlias) {
                    $sub->selectRaw('1')
                        ->from('whatsapp_lead_integrations as wli')
                        ->whereColumn('wli.lead_id', "{$leadAlias}.id");
                })
                ->whereNotExists(function ($sub) use ($leadAlias) {
                    $sub->selectRaw('1')
                        ->from('email_lead_logs as ell')
                        ->whereColumn('ell.lead_id', "{$leadAlias}.id");
                })
                ->whereNotExists(function ($sub) use ($leadAlias) {
                    $sub->selectRaw('1')
                        ->from('ivr_call_logs as icl')
                        ->whereColumn('icl.lead_id', "{$leadAlias}.id");
                });
        }

        return $query;
    }
}
