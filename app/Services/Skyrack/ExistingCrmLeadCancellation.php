<?php

namespace App\Services\Skyrack;

use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\PaymentAuditTrail;
use App\Models\User;
use App\Models\UserType;
use App\Services\Kpi\KpiActivityRecorder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExistingCrmLeadCancellation
{
    public function actor($request): ?Authenticatable
    {
        $agentNumber = preg_replace(
            '/\D+/',
            '',
            (string) $request->input('agent_number', '')
        );

        if (strlen($agentNumber) < 10) {
            return null;
        }

        $agentNumber = substr($agentNumber, -10);

        $matches = User::query()
            ->whereNotNull('contact_number')
            ->get()
            ->filter(function ($user) use ($agentNumber) {
                $storedNumber = preg_replace(
                    '/\D+/',
                    '',
                    (string) $user->contact_number
                );

                return strlen($storedNumber) >= 10
                    && substr($storedNumber, -10) === $agentNumber;
            });

        if ($matches->count() !== 1) {
            return null;
        }

        return $matches->first();
    }

    public function actorKey(Authenticatable $actor): string
    {
        return (string) $actor->getAuthIdentifier();
    }

    public function assertMayCancel(Authenticatable $actor, Lead $lead): void
    {
        $role = $actor->userType->user_type ?? null;

        if (in_array($role, UserType::ADMIN_ROLES, true)) {
            return;
        }

        if ((string) $lead->representative_user_id === (string) $actor->getAuthIdentifier()) {
            return;
        }

        throw new HttpException(403, 'Agent is not allowed to cancel this lead.');
    }

    public function isAlreadyCancelled(Lead $lead): bool
    {
        $latest = $this->latestFollowup($lead);

        return $latest
            && (int) $latest->status === LeadFollowup::STATUS_CANCELLED;
    }

    public function hasAnyPayment(Lead $lead): bool
    {
        $followupIds = $lead->leadFollowups()->pluck('id');

        if ($followupIds->isEmpty()) {
            return false;
        }

        if (PaymentAuditTrail::query()
            ->whereIn('lead_followup_id', $followupIds)
            ->where('payment_status', 1)
            ->where('paid_amount', '>', 0)
            ->exists()) {
            return true;
        }

        return $lead->leadFollowups()
            ->whereIn('status', [
                LeadFollowup::STATUS_FULL_PAYMENT_RECEIVED,
                LeadFollowup::STATUS_PARTIAL_PAYMENT_RECEIVED,
                LeadFollowup::STATUS_CONFIRMED,
                LeadFollowup::STATUS_APPROVED,
            ])
            ->exists();
    }

    public function cancel(Lead $lead, Authenticatable $actor, string $reason, ?string $remark): void
    {
        $latest = $this->latestFollowup($lead);
        $previousStatus = $latest?->status;

        $note = trim(implode(' ', array_filter([
            'Lead cancelled from Skyrack.',
            'Reason: ' . $reason,
            $remark ? 'Remark: ' . $remark : null,
        ])));

        $followup = LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'parent_followup_id' => $latest?->id,
            'lead_id' => $lead->id,
            'next_followup_date' => null,
            'followup_note' => $note,
            'status' => LeadFollowup::STATUS_CANCELLED,
            'followed_by' => (string) $actor->getAuthIdentifier(),
            'file' => $latest?->file,
            'service_ids' => $latest?->service_ids,
            'extra_service_ids' => $latest?->extra_service_ids,
            'service_amount' => $latest?->service_amount,
            'discount_amount' => $latest?->discount_amount,
            'service_details' => $latest?->service_details,
            'total_amount' => $latest?->total_amount,
            'received_amount' => $latest?->received_amount,
            'payment_method' => $latest?->payment_method,
            'paid_date' => $latest?->paid_date,
        ]);

        if ($actor instanceof User) {
            app(KpiActivityRecorder::class)->recordSalesFollowup(
                $followup,
                $previousStatus,
                $actor,
                'skyrack'
            );
        }
    }

    private function latestFollowup(Lead $lead): ?LeadFollowup
    {
        return $lead->leadFollowups()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }
}
