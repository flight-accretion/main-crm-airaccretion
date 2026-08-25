<?php

namespace App\Services;

use App\Models\Lead;

class ActiveLeadService
{
    private const NEW_LEAD_ALLOWED_FOLLOWUP_STATUSES = [2, 5];

    /**
     * Normalize any phone representation to digits only.
     *
     * Examples:
     * +91-9876543210 -> 9876543210
     * 91 9876543210 -> 9876543210
     * 9876543210     -> 9876543210
     */
    public function normalizePhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (empty($digits)) {
            return null;
        }

        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        return $digits;
    }

    /**
     * Find the latest duplicate-blocking lead for this phone.
     *
     * CRM rule:
     * Latest follow-up status 2 (Cancelled) or 5 (Confirm/Complete) means
     * a fresh lead can be created. Any other latest status is still treated
     * as the same lead journey, so new activity should become a follow-up.
     *
     * A freshly-created unassigned lead without a follow-up is also
     * considered active so automation cannot create duplicates while
     * it is waiting in the allocation queue.
     */
    public function findByPhone(?string $phone): ?Lead
    {
        $phone = $this->normalizePhone($phone);

        if (!$phone) {
            return null;
        }

        $phoneExpression = $this->digitsSqlExpression(
            'clients.contact_number'
        );

        $leads = Lead::query()
            ->join(
                'clients',
                'clients.id',
                '=',
                'leads.client_id'
            )
            ->whereRaw(
                "{$phoneExpression} LIKE ?",
                ['%' . $phone]
            )
            ->select('leads.*')
            ->orderByDesc('leads.created_at')
            ->get();

        foreach ($leads as $lead) {
            $latestFollowup = $lead->leadFollowups()
                ->orderByDesc('created_at')
                ->first();

            /*
             * Existing lead that should receive a follow-up instead
             * of creating a duplicate.
             */
            if (
                $latestFollowup &&
                !in_array(
                    (int) $latestFollowup->status,
                    self::NEW_LEAD_ALLOWED_FOLLOWUP_STATUSES,
                    true
                )
            ) {
                return $lead;
            }

            /*
             * Automation may have just created a lead and queued it
             * before its initial follow-up exists.
             */
            if (
                !$latestFollowup &&
                empty($lead->representative_user_id)
            ) {
                return $lead;
            }
        }

        return null;
    }

    private function digitsSqlExpression(string $column): string
    {
        if (config('database.default') === 'pgsql') {
            return "regexp_replace({$column}, '[^0-9]', '', 'g')";
        }

        return
            "REPLACE(" .
            "REPLACE(" .
            "REPLACE(" .
            "REPLACE(" .
            "REPLACE({$column}, '+', '')," .
            " '-', '')," .
            " ' ', '')," .
            " '(', '')," .
            " ')', '')";
    }
}
