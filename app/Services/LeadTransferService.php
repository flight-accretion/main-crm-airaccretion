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
    public function requestTransfer(
        Lead $lead,
        User $requestedBy,
        User $toUser,
        ?string $reason = null
    ): LeadTransfer {
        if (empty($lead->representative_user_id)) {
            throw ValidationException::withMessages([
                'transfer' =>
                    'This lead does not currently have an assigned salesperson.',
            ]);
        }

        if ($lead->representative_user_id === $toUser->id) {
            throw ValidationException::withMessages([
                'transfer' =>
                    'This lead is already assigned to the selected salesperson.',
            ]);
        }

        if (!$this->isSalesUser($toUser)) {
            throw ValidationException::withMessages([
                'transfer' =>
                    'Lead can only be transferred to an active sales user.',
            ]);
        }

        if ((int) $toUser->status !== 1) {
            throw ValidationException::withMessages([
                'transfer' =>
                    'The selected salesperson is inactive.',
            ]);
        }

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
                $toUser->id,

            'requested_by' =>
                $requestedBy->id,

            'status' =>
                'pending',

            'reason' =>
                $reason,
        ]);
    }

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

            if ($transfer->to_user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'transfer' =>
                        'Only the salesperson receiving this lead can accept the transfer.',
                ]);
            }

            $lead = Lead::where(
                'id',
                $transfer->lead_id
            )
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Protect against stale transfer.
             */
            if (
                $lead->representative_user_id
                !==
                $transfer->from_user_id
            ) {
                $transfer->update([
                    'status' => 'cancelled',
                    'responded_at' => now(),
                    'responded_by' => $user->id,
                    'response_note' =>
                        'Transfer automatically cancelled because lead ownership changed before acceptance.',
                ]);

                throw ValidationException::withMessages([
                    'transfer' =>
                        'Lead ownership has already changed. This transfer request is no longer valid.',
                ]);
            }

            $oldRepresentative =
                $lead->representative_user_id;

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

            /*
             * Existing CRM audit system.
             */
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

            if ($transfer->to_user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'transfer' =>
                        'Only the salesperson receiving this lead can reject the transfer.',
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
}