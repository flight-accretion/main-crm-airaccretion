<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Support\Facades\DB;

class CrmLeadCodeService
{
    private const STATE_KEY = 'next_crm_lead_number';

    private const PREFIX = 'CRM-';

    private const FIRST_NUMBER = 1001;

    public function ensureCode(Lead $lead): string
    {
        if (!empty($lead->crm_lead_code)) {
            return (string) $lead->crm_lead_code;
        }

        return DB::transaction(function () use ($lead) {
            $lockedLead =
                Lead::query()
                    ->where('id', $lead->id)
                    ->lockForUpdate()
                    ->firstOrFail();

            if (!empty($lockedLead->crm_lead_code)) {
                $lead->crm_lead_code = $lockedLead->crm_lead_code;

                return (string) $lockedLead->crm_lead_code;
            }

            $code = self::PREFIX . $this->reserveNextNumber();

            $lockedLead->forceFill([
                'crm_lead_code' => $code,
            ]);

            $this->saveQuietly($lockedLead);

            $lead->crm_lead_code = $code;

            return $code;
        });
    }

    private function reserveNextNumber(): int
    {
        $state =
            DB::table('skyrack_lead_sync_states')
                ->where('key', self::STATE_KEY)
                ->lockForUpdate()
                ->first();

        if (!$state) {
            $number =
                max(
                    self::FIRST_NUMBER,
                    $this->highestExistingNumber() + 1
                );

            DB::table('skyrack_lead_sync_states')->insert([
                'key' => self::STATE_KEY,
                'value' => (string) ($number + 1),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $number;
        }

        $number =
            max(
                self::FIRST_NUMBER,
                (int) $state->value
            );

        DB::table('skyrack_lead_sync_states')
            ->where('key', self::STATE_KEY)
            ->update([
                'value' => (string) ($number + 1),
                'updated_at' => now(),
            ]);

        return $number;
    }

    private function highestExistingNumber(): int
    {
        return Lead::query()
            ->whereNotNull('crm_lead_code')
            ->pluck('crm_lead_code')
            ->map(function ($code) {
                if (
                    preg_match(
                        '/^' . preg_quote(self::PREFIX, '/') . '(\d+)$/',
                        (string) $code,
                        $matches
                    )
                ) {
                    return (int) $matches[1];
                }

                return 0;
            })
            ->max() ?: 0;
    }

    private function saveQuietly(Lead $lead): void
    {
        if (method_exists($lead, 'saveQuietly')) {
            $lead->saveQuietly();

            return;
        }

        Lead::withoutEvents(function () use ($lead) {
            $lead->save();
        });
    }
}
