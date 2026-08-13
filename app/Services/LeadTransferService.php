<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadAuditTrail;
use App\Models\LeadTransfer;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadTransferService
{
    /**
     * Request ownership of another salesperson's lead.
     *
     * from_user_id = current lead owner
     * to_user_id = requester
     * requested_by = requester
     */
    public function requestForSelf(
        Lead $lead,
        User $requester,
        ?string $reason = null
    ): LeadTransfer {
        return DB::transaction(function () use (
            $lead,
            $requester,
            $reason
        ) {
            $lead = Lead::where('id', $lead->id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Transfer system should not handle unassigned leads.
             * Existing allocation system handles those.
             */
            if (empty($lead->representative_user_id)) {
                throw ValidationException::withMessages([
                    'transfer' =>
                        'This lead is currently unassigned and cannot be requested through lead transfer.',
                ]);
            }

            /*
             * User cannot request their own lead.
             */
            if (
                (string) $lead->representative_user_id
                ===
                (string) $requester->id
            ) {
                throw ValidationException::withMessages([
                    'transfer' =>
                        'This lead is already assigned to you.',
                ]);
            }

            /*
             * Only active sales users can request a lead for themselves.
             */
            if (!$this->isSalesUser($requester)) {
                throw ValidationException::withMessages([
                    'transfer' =>
                        'Only Sales Executives or Sales Managers can request lead ownership.',
                ]);
            }

            if ((int) $requester->status !== 1) {
                throw ValidationException::withMessages([
                    'transfer' =>
                        'Your CRM user account is inactive.',
                ]);
            }

            /*
             * Only one pending transfer per lead.
             */
            $pendingExists = LeadTransfer::where(
                'lead_id',
                $lead->id
            )
                ->where('status', 'pending')
                ->exists();

            if ($pendingExists) {
                throw ValidationException::withMessages([
                    'transfer' =>
                        'A transfer request is already pending for this lead.',
                ]);
            }

            return LeadTransfer::create([
                'lead_id' =>
                    $lead->id,

                'from_user_id' =>
                    $lead->representative_user_id,

                'to_user_id' =>
                    $requester->id,

                'requested_by' =>
                    $requester->id,

                'status' =>
                    'pending',

                'reason' =>
                    $reason,
            ]);
        });
    }

    /**
     * Current owner or Super Admin can approve.
     */
    public function accept(
        LeadTransfer $transfer,
        User $user
    ): void {
        DB::transaction(function () use (
            $transfer,
            $user
        ) {
            $transfer = LeadTransfer::where(
                'id',
                $transfer->id
            )
                ->lockForUpdate()
                ->firstOrFail();

            if ($transfer->status !== 'pending') {
                throw ValidationException::withMessages([
                    'transfer' =>
                        'This transfer request has already been processed.',
                ]);
            }

            $isCurrentOwner =
                (string) $transfer->from_user_id
                ===
                (string) $user->id;

            $isSuperAdmin =
                $this->isSuperAdmin($user);

            if (!$isCurrentOwner && !$isSuperAdmin) {
                throw ValidationException::withMessages([
                    'transfer' =>
                        'Only the current lead owner or Super Admin can approve this transfer.',
                ]);
            }

            $lead = Lead::where(
                'id',
                $transfer->lead_id
            )
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Protect against stale requests.
             */
            if (
                (string) $lead->representative_user_id
                !==
                (string) $transfer->from_user_id
            ) {
                $transfer->update([
                    'status' =>
                        'cancelled',

                    'responded_at' =>
                        now(),

                    'responded_by' =>
                        $user->id,

                    'response_note' =>
                        'Transfer automatically cancelled because lead ownership changed before approval.',
                ]);

                throw ValidationException::withMessages([
                    'transfer' =>
                        'Lead ownership has already changed. This request is no longer valid.',
                ]);
            }

            $oldRepresentative =
                $lead->representative_user_id;

            /*
             * Assign to the person who REQUESTED the lead.
             */
            $lead->representative_user_id =
                $transfer->to_user_id;

            $lead->save();

            $transfer->update([
                'status' =>
                    'accepted',

                'responded_at' =>
                    now(),

                'responded_by' =>
                    $user->id,
            ]);

            LeadAuditTrail::create([
                'id' =>
                    (string) \Illuminate\Support\Str::uuid(),

                'lead_id' =>
                    $lead->id,

                'field_name' =>
                    'representative_user_id',

                'old_value' =>
                    $oldRepresentative,

                'new_value' =>
                    $transfer->to_user_id,

                'changed_by' =>
                    $user->id,

                'created_at' =>
                    now(),
            ]);
        });
    }

    /**
     * Current owner or Super Admin can reject.
     */
    public function reject(
        LeadTransfer $transfer,
        User $user,
        ?string $note = null
    ): void {
        DB::transaction(function () use (
            $transfer,
            $user,
            $note
        ) {
            $transfer = LeadTransfer::where(
                'id',
                $transfer->id
            )
                ->lockForUpdate()
                ->firstOrFail();

            if ($transfer->status !== 'pending') {
                throw ValidationException::withMessages([
                    'transfer' =>
                        'This transfer request has already been processed.',
                ]);
            }

            $isCurrentOwner =
                (string) $transfer->from_user_id
                ===
                (string) $user->id;

            $isSuperAdmin =
                $this->isSuperAdmin($user);

            if (!$isCurrentOwner && !$isSuperAdmin) {
                throw ValidationException::withMessages([
                    'transfer' =>
                        'Only the current lead owner or Super Admin can reject this transfer.',
                ]);
            }

            /*
             * Also protect rejection against stale ownership.
             */
            $lead = Lead::where(
                'id',
                $transfer->lead_id
            )
                ->lockForUpdate()
                ->firstOrFail();

            if (
                (string) $lead->representative_user_id
                !==
                (string) $transfer->from_user_id
            ) {
                $transfer->update([
                    'status' =>
                        'cancelled',

                    'responded_at' =>
                        now(),

                    'responded_by' =>
                        $user->id,

                    'response_note' =>
                        'Transfer automatically cancelled because lead ownership changed before rejection.',
                ]);

                throw ValidationException::withMessages([
                    'transfer' =>
                        'Lead ownership has already changed. This request is no longer valid.',
                ]);
            }

            $transfer->update([
                'status' =>
                    'rejected',

                'responded_at' =>
                    now(),

                'responded_by' =>
                    $user->id,

                'response_note' =>
                    $note,
            ]);
        });
    }

    private function isSalesUser(User $user): bool
    {
        if (!$user->userType) {
            return false;
        }

        return in_array(
            $user->userType->user_type,
            UserType::SALES_ROLES,
            true
        );
    }

    private function isSuperAdmin(User $user): bool
    {
        return $user->userType
            &&
            $user->userType->user_type
                === UserType::SUPER_ADMIN;
    }
}