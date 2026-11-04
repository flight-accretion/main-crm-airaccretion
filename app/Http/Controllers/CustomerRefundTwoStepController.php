<?php

namespace App\Http\Controllers;

use App\Models\CustomerRefundSettlement;
use App\Models\CustomerRefundTransaction;
use App\Models\Lead;
use App\Models\LeadRefund;
use App\Models\LeadRide;
use App\Models\UserType;
use App\Services\ActivityAuditService;
use App\Services\Rides\CustomerRefundWorkflowService as Workflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerRefundTwoStepController extends Controller
{
    private function authorizeUser(): void
    {
        $user = auth()->user();
        abort_unless($user, 401);
        $role = optional($user->userType)->user_type;
        $allowed = array_merge(
            UserType::ADMIN_ROLES ?? [],
            UserType::ACCOUNTS_ROLES ?? [],
            UserType::OPERATIONS_ROLES ?? []
        );
        abort_unless(in_array($role, $allowed, true) || $role === UserType::OWNER, 403);
    }

    private function authorizeCorrection(): void
    {
        $this->authorizeUser();
        $role = optional(auth()->user()->userType)->user_type;
        $allowed = array_merge(UserType::ADMIN_ROLES ?? [], UserType::ACCOUNTS_ROLES ?? []);
        abort_unless(in_array($role, $allowed, true), 403);
    }

    private function ride($rideId): LeadRide
    {
        return LeadRide::findOrFail($rideId);
    }

    private function currentCancelledFollowup(Lead $lead)
    {
        $current = $lead->leadFollowups()
            ->orderByDesc('created_at')->orderByDesc('id')->first();
        if (!$current || (int) $current->status !== 2) {
            throw ValidationException::withMessages([
                'ride' => ['The latest lead status must be Cancelled.'],
            ]);
        }
        return $current;
    }

    private function assertMatchingDuplicatePayment(CustomerRefundTransaction $transaction, array $data): void
    {
        $samePayment = Workflow::cents($transaction->amount) === Workflow::cents($data['amount'])
            && (string) $transaction->refund_type === (string) $data['refund_type']
            && optional($transaction->refund_date)->format('Y-m-d') === $data['refund_date']
            && trim((string) $transaction->remarks) === trim((string) ($data['remarks'] ?? ''));

        abort_unless(
            $samePayment,
            409,
            'Request ID has already been used for a different refund payment.'
        );
    }

    public function show(string $rideId, Workflow $workflow)
    {
        $this->authorizeUser();
        $ride = $this->ride($rideId);
        $lead = Lead::findOrFail($ride->lead_id);
        $this->currentCancelledFollowup($lead);
        $settlement = CustomerRefundSettlement::with('transactions')
            ->where('lead_id', $lead->id)->first();
        $paid = $workflow->approvedPaidForFollowups($lead->leadFollowups()->pluck('id'));
        $legacy = LeadRefund::whereIn('lead_followup_id', $lead->leadFollowups()->pluck('id'))->exists();
        $role = optional(auth()->user()->userType)->user_type;
        $canCorrectPayment = in_array($role, array_merge(UserType::ADMIN_ROLES ?? [], UserType::ACCOUNTS_ROLES ?? []), true);

        return response()->json([
            'success' => true,
            'approved_paid' => Workflow::money($paid),
            'settlement' => $settlement ? $workflow->snapshot($settlement) : null,
            'legacy_blocked' => !$settlement && $legacy,
            'can_correct_payment' => $canCorrectPayment,
            'message' => !$settlement && $legacy
                ? 'Existing customer refund found. Reconcile the legacy refund in the Refund Queue before migrating to two-step processing.'
                : null,
        ]);
    }

    public function assessment(Request $request, string $rideId, Workflow $workflow)
    {
        $this->authorizeUser();
        $data = $request->validate([
            'cancellation_amount' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:4000'],
        ]);
        $ride = $this->ride($rideId);

        $assessmentEdit = null;
        $result = DB::transaction(function () use ($ride, $workflow, $data, &$assessmentEdit) {
            $lead = Lead::whereKey($ride->lead_id)->lockForUpdate()->firstOrFail();
            $followup = $this->currentCancelledFollowup($lead);
            $paid = $workflow->approvedPaidForFollowups($lead->leadFollowups()->pluck('id'));
            $charge = Workflow::cents($data['cancellation_amount']);
            if ($charge > $paid) {
                throw ValidationException::withMessages([
                    'cancellation_amount' => ['Cancellation charge cannot exceed approved customer payments.'],
                ]);
            }
            $due = $paid - $charge;
            $settlement = CustomerRefundSettlement::where('lead_id', $lead->id)
                ->lockForUpdate()->first();

            if (!$settlement) {
                $oldRefund = LeadRefund::whereIn(
                    'lead_followup_id', $lead->leadFollowups()->pluck('id')
                )->exists();
                if ($oldRefund) {
                    throw ValidationException::withMessages([
                        'refund' => ['Existing legacy refund must be reconciled before creating a two-step assessment.'],
                    ]);
                }
                $refund = LeadRefund::create([
                    'id' => (string) Str::uuid(),
                    'lead_followup_id' => $followup->id,
                    'original_amount' => Workflow::money($paid),
                    'refund_amount' => Workflow::money($due),
                    'refund_reason' => $data['reason'],
                    'status' => $due === 0 ? 2 : 0,
                ]);
                $settlement = CustomerRefundSettlement::create([
                    'id' => (string) Str::uuid(),
                    'lead_id' => $lead->id,
                    'lead_followup_id' => $followup->id,
                    'lead_refund_id' => $refund->id,
                    'approved_paid' => Workflow::money($paid),
                    'cancellation_amount' => Workflow::money($charge),
                    'refund_due' => Workflow::money($due),
                    'reason' => $data['reason'],
                    'assessed_by' => auth()->id(),
                    'assessed_at' => now(),
                ]);
            } else {
                $transactionCount = CustomerRefundTransaction::where('settlement_id', $settlement->id)->count();
                if ($transactionCount > 0 || Workflow::cents($settlement->refund_due) === 0) {
                    throw ValidationException::withMessages([
                        'refund' => ['This assessment is finalized or has recorded payments and cannot be overwritten.'],
                    ]);
                }
                $refund = LeadRefund::findOrFail($settlement->lead_refund_id);
                if ((int) $refund->status === 2) {
                    throw ValidationException::withMessages(['refund' => ['Completed refund cannot be reopened.']]);
                }
                $assessmentEdit = [
                    'old' => [
                        'approved_paid' => $settlement->approved_paid,
                        'cancellation_amount' => $settlement->cancellation_amount,
                        'refund_due' => $settlement->refund_due,
                        'reason' => $settlement->reason,
                    ],
                    'new' => [
                        'approved_paid' => Workflow::money($paid),
                        'cancellation_amount' => Workflow::money($charge),
                        'refund_due' => Workflow::money($due),
                        'reason' => $data['reason'],
                    ],
                ];
                $refund->update([
                    'original_amount' => Workflow::money($paid),
                    'refund_amount' => Workflow::money($due),
                    'refund_reason' => $data['reason'],
                    'status' => $due === 0 ? 2 : 0,
                ]);
                $settlement->update([
                    'approved_paid' => Workflow::money($paid),
                    'cancellation_amount' => Workflow::money($charge),
                    'refund_due' => Workflow::money($due),
                    'reason' => $data['reason'],
                    'assessed_by' => auth()->id(),
                    'assessed_at' => now(),
                ]);
            }
            return $workflow->snapshot($settlement->fresh('transactions'));
        }, 3);

        if ($assessmentEdit) {
            app(ActivityAuditService::class)->record(
                'customer_refund',
                'assessment_updated',
                CustomerRefundSettlement::where('lead_id', $ride->lead_id)->first(),
                $assessmentEdit['old'],
                $assessmentEdit['new'],
                ['lead_id' => $ride->lead_id, 'ride_id' => $rideId]
            );
        }

        // Preserve Operations refund queue visibility used by the legacy workflow.
        if ((float) $result['refund_due'] > 0) {
            try {
                app(\App\Services\Operations\OperationCaseService::class)->open(
                    Lead::findOrFail($ride->lead_id),
                    'refund',
                    ['source' => 'customer_two_step', 'ride_id' => $rideId],
                    (string) auth()->id()
                );
            } catch (\Throwable $e) {
                \Log::warning('Could not mirror customer refund to operations', ['ride_id' => $rideId]);
            }
        }
        return response()->json(['success' => true, 'settlement' => $result]);
    }

    public function payment(Request $request, string $rideId, Workflow $workflow)
    {
        $this->authorizeUser();
        $data = $request->validate([
            'request_id' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'refund_type' => ['required', 'string', 'max:100'],
            'refund_date' => ['required', 'date'],
            'refund_proof' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);
        $ride = $this->ride($rideId);
        $storedProof = null;
        try {
            // Duplicate request IDs must not result in a second refund payment.
            $existing = CustomerRefundTransaction::where('request_id', $data['request_id'])->first();
            if ($existing) {
                $settlement = CustomerRefundSettlement::findOrFail($existing->settlement_id);
                abort_unless((string) $settlement->lead_id === (string) $ride->lead_id, 409);
                $this->assertMatchingDuplicatePayment($existing, $data);
                return response()->json(['success' => true, 'duplicate' => true, 'settlement' => $workflow->snapshot($settlement)]);
            }

            $storedProof = $request->file('refund_proof')->store('customer-refunds', 'public');
            $snapshot = DB::transaction(function () use ($ride, $data, $storedProof, $workflow) {
                $lead = Lead::whereKey($ride->lead_id)->lockForUpdate()->firstOrFail();
                $this->currentCancelledFollowup($lead);
                $settlement = CustomerRefundSettlement::where('lead_id', $lead->id)
                    ->lockForUpdate()->first();
                if (!$settlement) {
                    throw ValidationException::withMessages(['refund' => ['Save Step 1 first.']]);
                }
                $duplicate = CustomerRefundTransaction::where('request_id', $data['request_id'])->first();
                if ($duplicate) {
                    abort_unless((string) $duplicate->settlement_id === (string) $settlement->id, 409);
                    $this->assertMatchingDuplicatePayment($duplicate, $data);
                    Storage::disk('public')->delete($storedProof);
                    return $workflow->snapshot($settlement->fresh('transactions'));
                }
                $due = Workflow::cents($settlement->refund_due);
                $paid = CustomerRefundTransaction::where('settlement_id', $settlement->id)
                    ->whereNull('reversed_at')
                    ->get(['amount'])->sum(fn ($t) => Workflow::cents($t->amount));
                $amount = Workflow::cents($data['amount']);
                if ($amount <= 0 || $amount > ($due - $paid)) {
                    throw ValidationException::withMessages(['amount' => ['Amount exceeds outstanding refund or no refund is due.']]);
                }
                CustomerRefundTransaction::create([
                    'id' => (string) Str::uuid(),
                    'settlement_id' => $settlement->id,
                    'request_id' => $data['request_id'],
                    'amount' => Workflow::money($amount),
                    'refund_type' => $data['refund_type'],
                    'refund_date' => $data['refund_date'],
                    'refund_proof' => $storedProof,
                    'remarks' => $data['remarks'] ?? null,
                    'created_by' => auth()->id(),
                ]);
                $refund = LeadRefund::findOrFail($settlement->lead_refund_id);
                $refund->update([
                    // refund_amount remains the assessed TOTAL due, not this installment.
                    'status' => ($paid + $amount >= $due) ? 2 : 1,
                    'refund_type' => $data['refund_type'],
                    'refund_date' => $data['refund_date'],
                    'refund_proof' => $storedProof,
                ]);
                return $workflow->snapshot($settlement->fresh('transactions'));
            }, 3);

            if ((float) $snapshot['remaining'] <= 0) {
                try {
                    $case = \App\Models\OperationCase::where('lead_id', $ride->lead_id)
                        ->where('type', 'refund')->whereNull('completed_at')->first();
                    if ($case) {
                        app(\App\Services\Operations\OperationCaseService::class)
                            ->complete($case, auth()->user(), 'Customer refund paid in full.');
                    }
                } catch (\Throwable $e) {
                    \Log::warning('Could not close completed customer refund case', ['ride_id' => $rideId]);
                }
            }
            return response()->json(['success' => true, 'settlement' => $snapshot]);
        } catch (\Throwable $exception) {
            if ($storedProof) {
                Storage::disk('public')->delete($storedProof);
            }
            throw $exception;
        }
    }

    public function correctPayment(Request $request, string $rideId, Workflow $workflow)
    {
        $this->authorizeCorrection();
        $data = $request->validate([
            'transaction_id' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $ride = $this->ride($rideId);

        $snapshot = DB::transaction(function () use ($ride, $data, $workflow) {
            $lead = Lead::whereKey($ride->lead_id)->lockForUpdate()->firstOrFail();
            $settlement = CustomerRefundSettlement::where('lead_id', $lead->id)
                ->lockForUpdate()->firstOrFail();
            $transaction = CustomerRefundTransaction::whereKey($data['transaction_id'])
                ->where('settlement_id', $settlement->id)->lockForUpdate()->firstOrFail();

            $current = $workflow->snapshot($settlement->fresh('transactions'));
            if (Workflow::cents($current['remaining']) === 0) {
                throw ValidationException::withMessages(['refund' => ['Completed customer refunds cannot be changed. Contact Accounts for a formal correction.']]);
            }
            if ($transaction->reversed_at) {
                throw ValidationException::withMessages(['refund' => ['This refund payment has already been corrected.']]);
            }

            $transaction->update([
                'reversed_at' => now(),
                'reversed_by' => auth()->id(),
                'reversal_reason' => $data['reason'],
            ]);
            app(ActivityAuditService::class)->record(
                'customer_refund', 'payment_reversed_for_correction', $transaction,
                ['amount' => $transaction->amount, 'method' => $transaction->refund_type],
                ['reversal_reason' => $data['reason']],
                ['lead_id' => $lead->id, 'ride_id' => $ride->id]
            );

            return $workflow->snapshot($settlement->fresh('transactions'));
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Payment marked for correction. Record the replacement payment with the correct details.',
            'settlement' => $snapshot,
        ]);
    }
}
