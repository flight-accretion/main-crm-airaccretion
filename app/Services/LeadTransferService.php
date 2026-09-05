<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadAuditTrail;
use App\Models\LeadFollowup;
use App\Models\LeadTransfer;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\LeadAllocationQueue;
use App\Models\LeadAllocationLog;

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
        $staleOwnershipMessage = null;

        DB::transaction(function () use (
            $transfer,
            $user,
            &$staleOwnershipMessage
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

                $staleOwnershipMessage =
                    'Lead ownership has already changed. This request is no longer valid.';

                return;
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

        if ($staleOwnershipMessage) {
            throw ValidationException::withMessages([
                'transfer' =>
                    $staleOwnershipMessage,
            ]);
        }
    }

    /**
     * Current owner or Super Admin can reject.
     */
    public function reject(
        LeadTransfer $transfer,
        User $user,
        ?string $note = null
    ): void {
        $staleOwnershipMessage = null;

        DB::transaction(function () use (
            $transfer,
            $user,
            $note,
            &$staleOwnershipMessage
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

                $staleOwnershipMessage =
                    'Lead ownership has already changed. This request is no longer valid.';

                return;
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

        if ($staleOwnershipMessage) {
            throw ValidationException::withMessages([
                'transfer' =>
                    $staleOwnershipMessage,
            ]);
        }
    }

    /**
 * Directly assign/reassign a lead by Super Admin.
 *
 * This is different from the normal request/approval
 * transfer workflow.
 *
 * Returns information needed by the caller to complete
 * queued lead source handoff.
 */
public function directAssign(
    Lead $lead,
    User $toUser,
    User $actor
): array {

    /*
     * Direct reassignment is Super Admin only.
     */
    if (!$this->isSuperAdmin($actor)) {

        throw ValidationException::withMessages([
            'transfer' =>
                'Only Super Admin can directly assign or transfer leads.',
        ]);
    }

    /*
     * Destination must be an active Sales user.
     */
    if (
        !$this->isSalesUser($toUser)
        ||
        (int) $toUser->status !== 1
    ) {

        throw ValidationException::withMessages([
            'transfer' =>
                'The selected representative must be an active Sales Manager or Sales Executive.',
        ]);
    }

    return DB::transaction(
        function () use (
            $lead,
            $toUser,
            $actor
        ) {

            /*
             * Lock ownership while changing it.
             */
            $lockedLead = Lead::where(
                    'id',
                    $lead->id
                )
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Nothing to change.
             */
            if (
                (string)
                    $lockedLead->representative_user_id
                ===
                (string) $toUser->id
            ) {

                return [
                    'changed' => false,
                    'was_queued' => false,
                    'queue_reason' => null,
                    'old_representative_id' =>
                        $lockedLead->representative_user_id,
                ];
            }

            $oldRepresentative =
                $lockedLead->representative_user_id;

            /*
             * Capture queue source before closing the queue.
             *
             * We preserve the original reason because it tells
             * us whether this came from WhatsApp, Email, IVR, etc.
             */
            $queuedItem =
                LeadAllocationQueue::query()
                    ->where(
                        'lead_id',
                        $lockedLead->id
                    )
                    ->where(
                        'status',
                        'queued'
                    )
                    ->orderByDesc(
                        'queued_at'
                    )
                    ->lockForUpdate()
                    ->first();

            $queueReason =
                $queuedItem
                    ? $queuedItem->reason
                    : null;

            /*
             * Any old pending transfer request becomes invalid
             * because Super Admin is changing ownership now.
             */
            LeadTransfer::query()
                ->where(
                    'lead_id',
                    $lockedLead->id
                )
                ->where(
                    'status',
                    'pending'
                )
                ->update([
                    'status' =>
                        'cancelled',

                    'responded_at' =>
                        now(),

                    'responded_by' =>
                        $actor->id,

                    'response_note' =>
                        'Cancelled because Super Admin directly reassigned the lead.',
                ]);

            /*
             * Change ownership.
             */
            $lockedLead->representative_user_id =
                $toUser->id;

            $lockedLead->save();

            /*
             * If this was waiting in auto allocation,
             * remove it from the live queue immediately.
             *
             * Preserve queue reason/source.
             */
            $queuedCount =
                LeadAllocationQueue::query()
                    ->where(
                        'lead_id',
                        $lockedLead->id
                    )
                    ->where(
                        'status',
                        'queued'
                    )
                    ->update([
                        'assigned_to' =>
                            $toUser->id,

                        'status' =>
                            'assigned',

                        'processed_at' =>
                            now(),
                    ]);

                    if ($queuedCount > 0) {

                    LeadAllocationLog::create([
                        'lead_id' =>
                            $lockedLead->id,

                        'salesperson_id' =>
                            $toUser->id,

                        'action' =>
                            'assigned',

                        'result' =>
                            'success',

                        'details' =>
                            'Assigned manually by Super Admin from lead queue',
                    ]);
                }

            /*
             * Keep ownership history.
             */
            LeadAuditTrail::create([
                'id' =>
                    (string)
                    \Illuminate\Support\Str::uuid(),

                'lead_id' =>
                    $lockedLead->id,

                'field_name' =>
                    'representative_user_id',

                'old_value' =>
                    $oldRepresentative,

                'new_value' =>
                    $toUser->id,

                'changed_by' =>
                    $actor->id,

                'created_at' =>
                    now(),
            ]);

            return [
                'changed' =>
                    true,

                'was_queued' =>
                    $queuedCount > 0,

                'queue_reason' =>
                    $queueReason,

                'old_representative_id' =>
                    $oldRepresentative,
            ];
        }
    );
}

public function recordDirectAssignmentFollowup(
    Lead $lead,
    User $toUser,
    User $actor,
    ?string $fromUserId = null
): LeadFollowup {
    if (!$this->isSuperAdmin($actor)) {
        throw ValidationException::withMessages([
            'transfer' =>
                'Only Super Admin can record direct lead transfer follow-ups.',
        ]);
    }

    if (
        !$this->isSalesUser($toUser)
        ||
        (int) $toUser->status !== 1
    ) {
        throw ValidationException::withMessages([
            'transfer' =>
                'The selected representative must be an active Sales Manager or Sales Executive.',
        ]);
    }

    $fromUser = $fromUserId
        ? User::query()->find($fromUserId)
        : null;

    $transferredAt = now();

    return LeadFollowup::create([
        'id' =>
            (string) \Illuminate\Support\Str::uuid(),

        'lead_id' =>
            $lead->id,

        'next_followup_date' =>
            $transferredAt,

        'followup_note' =>
            implode(PHP_EOL, [
                'Lead transferred by Super Admin.',
                'From: ' . (
                    optional($fromUser)->name
                    ?: 'Unassigned'
                ),
                'To: ' . $toUser->name,
                'Transferred at: '
                    . $transferredAt->format('d-M-Y h:i A')
                    . ' IST',
                'Transferred by: ' . $actor->name,
            ]),

        'followed_by' =>
            $toUser->id,

        'status' =>
            1,
    ]);
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
