<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\PaymentAuditTrail;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Validation\ValidationException;

/**
 * Central rules for deriving a lead's status from follow-up history.
 * Statuses 10 and 11 are workflow actions only: they must not replace the
 * payment/commercial status displayed in lead lists.
 */
class LeadStatusTransitionService
{
    public function latestActivityStatus(Lead $lead): ?int
    {
        $status = $lead->leadFollowups()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('status');

        return $status === null ? null : (int) $status;
    }

    public function displayedBusinessStatus(Lead $lead): ?int
    {
        $status = $lead->leadFollowups()
            ->whereNotIn('status', LeadFollowup::FOLLOWUP_ONLY_STATUSES)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('status');

        return $status === null ? null : (int) $status;
    }

    public function hasApprovedPayment(Lead $lead): bool
    {
        return PaymentAuditTrail::query()
            ->where('payment_status', PaymentAuditTrail::STATUS_APPROVED)
            ->whereHas('leadFollowup', function ($query) use ($lead) {
                $query->where('lead_id', $lead->id);
            })
            ->exists();
    }

    public function latestPaymentAttemptStatus(Lead $lead): ?int
    {
        $status = PaymentAuditTrail::query()
            ->whereHas('leadFollowup', function ($query) use ($lead) {
                $query->where('lead_id', $lead->id);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('payment_status');

        return $status === null ? null : (int) $status;
    }

    public function latestPaymentAttemptIsRejected(Lead $lead): bool
    {
        return $this->latestPaymentAttemptStatus($lead) === PaymentAuditTrail::STATUS_REJECTED;
    }

    public function isTerminalForInbound(Lead $lead): bool
    {
        $status = $this->displayedBusinessStatus($lead);

        return $status !== null && in_array($status, LeadFollowup::INBOUND_TERMINAL_STATUSES, true);
    }

    public function allowedSalesStatuses(Lead $lead): array
    {
        if ($this->hasApprovedPayment($lead)) {
            return [
                LeadFollowup::STATUS_FULL_PAYMENT_RECEIVED,
                LeadFollowup::STATUS_PARTIAL_PAYMENT_RECEIVED,
                LeadFollowup::STATUS_SALES_FOLLOW_UP,
                LeadFollowup::STATUS_CLOSE_FOLLOW_UP,
            ];
        }

        return [
            LeadFollowup::STATUS_ACTIVE,
            LeadFollowup::STATUS_CANCELLED,
            LeadFollowup::STATUS_FULL_PAYMENT_RECEIVED,
            LeadFollowup::STATUS_PARTIAL_PAYMENT_RECEIVED,
            LeadFollowup::STATUS_SALES_FOLLOW_UP,
            LeadFollowup::STATUS_CLOSE_FOLLOW_UP,
        ];
    }

    public function assertManualTransition(Lead $lead, User $user, int $toStatus, ?string $note, $nextFollowupDate): void
    {
        $role = optional($user->userType)->user_type;
        $isSales = in_array($role, UserType::SALES_ROLES, true);

        if ($toStatus === LeadFollowup::STATUS_RIDE_COMPLETED) {
            throw ValidationException::withMessages([
                'status' => 'Ride Completed can only be set through the authorized Ride Status workflow.',
            ]);
        }

        if ($isSales && !in_array($toStatus, $this->allowedSalesStatuses($lead), true)) {
            throw ValidationException::withMessages([
                'status' => 'After an approved payment, Sales can only record a payment, Sales Follow-up, or Close Follow-up.',
            ]);
        }

        if ($toStatus === LeadFollowup::STATUS_SALES_FOLLOW_UP && empty($nextFollowupDate)) {
            throw ValidationException::withMessages([
                'next_followup_date' => 'Next follow-up date and time are required for Sales Follow-up.',
            ]);
        }

        if ($toStatus === LeadFollowup::STATUS_CLOSE_FOLLOW_UP && trim((string) $note) === '') {
            throw ValidationException::withMessages([
                'notes' => 'A closing remark is required for Close Follow-up.',
            ]);
        }
    }
}
