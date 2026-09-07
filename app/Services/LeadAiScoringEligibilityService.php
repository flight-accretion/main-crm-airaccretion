<?php

namespace App\Services;

use App\Models\LeadFollowup;
use App\Models\UserType;
use Illuminate\Support\Str;

class LeadAiScoringEligibilityService
{
    public function eligible(
        LeadFollowup $followup
    ): bool {
        $note = trim(
            (string) $followup->followup_note
        );

        if ($note === '') {
            return false;
        }

        $normalized =
            Str::lower($note);

        /*
         * ------------------------------------------------
         * SYSTEM / AUDIT FOLLOW-UPS
         * ------------------------------------------------
         *
         * These are NOT customer conversations and must
         * never affect buying-intent score.
         */
        $excludedPrefixes = [
            'lead transferred by super admin.',
            'lead transfer accepted.',
            'payment approved',
            'payment rejected',
            'payment approval',
            'payment rejection',
            'refund approved',
            'refund rejected',
            'voucher generated',
            'voucher created',
            'lead automatically cancelled',
            'lead auto cancelled',
            'automatic cancellation',
            'duplicate lead merged',
            'lead merged',
            'repair completed',
        ];

        foreach ($excludedPrefixes as $prefix) {
            if (
                Str::startsWith(
                    $normalized,
                    $prefix
                )
            ) {
                return false;
            }
        }

        /*
         * ------------------------------------------------
         * KNOWN CUSTOMER-ENQUIRY SOURCES
         * ------------------------------------------------
         */

        if (
            Str::startsWith(
                $normalized,
                'whatsapp customer message received.'
            )
        ) {
            return true;
        }

        if (
            Str::startsWith(
                $normalized,
                'lead received automatically from email.'
            )
        ) {
            return true;
        }

        if (
            Str::startsWith(
                $normalized,
                'new vi ivr lead received.'
            )
            ||
            Str::startsWith(
                $normalized,
                'repeat vi ivr call received.'
            )
        ) {
            return true;
        }

        if (
            Str::startsWith(
                $normalized,
                'new customer enquiry received via '
            )
        ) {
            return true;
        }

        /*
         * Call-summary integration.
         */
        if (
            !empty(
                $followup->followup_recording_id
            )
        ) {
            return true;
        }

        /*
         * ------------------------------------------------
         * NORMAL MANUAL SALES FOLLOW-UP
         * ------------------------------------------------
         */

        $user = null;

        try {
            $user = $followup->relationLoaded(
                'followedBy'
            )
                ? $followup->followedBy
                : $followup
                    ->followedBy()
                    ->with('userType')
                    ->first();
        } catch (\Throwable $e) {
            $user = null;
        }

        if (
            !$user
            ||
            !$user->userType
            ||
            (int) $user->status !== 1
        ) {
            return false;
        }

        return in_array(
            $user->userType->user_type,
            UserType::SALES_ROLES,
            true
        );
    }
}