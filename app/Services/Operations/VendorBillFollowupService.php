<?php

namespace App\Services\Operations;

use App\Models\LeadRide;
use App\Models\LeadVendorPayment;
use App\Models\OperationCase;
use App\Models\OperationCaseActivity;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VendorBillFollowupService
{
    public const PURPOSE = 'Vendor Bill Collection';
    public const KIND = 'vendor_bill';

    public function createForCompletedRide(LeadRide $ride, User $actor): int
    {
        return DB::transaction(function () use ($ride, $actor): int {
            // A row lock serializes concurrent attempts for the same ride.
            $lockedRide = LeadRide::query()->whereKey($ride->getKey())->lockForUpdate()->firstOrFail();
            $leadId = $lockedRide->lead_id;

            $vendorPayments = LeadVendorPayment::query()
                ->with('vendor')
                ->where('lead_id', $leadId)
                ->whereNotNull('vendor_id')
                ->get();

            $created = 0;
            foreach ($vendorPayments as $payment) {
                $exists = OperationCase::query()
                    ->where('type', OperationCase::TYPE_VENDOR_FOLLOWUP)
                    ->where('lead_id', $leadId)
                    ->where('metadata->kind', self::KIND)
                    ->where('metadata->ride_id', (string) $lockedRide->id)
                    ->where('metadata->vendor_payment_id', (string) $payment->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $case = OperationCase::create([
                    'lead_id' => $leadId,
                    'type' => OperationCase::TYPE_VENDOR_FOLLOWUP,
                    'status' => OperationCase::STATUS_PENDING,
                    'assigned_to' => $actor->id,
                    'created_by' => $actor->id,
                    'opened_at' => now(),
                    'next_followup_at' => now(),
                    'note' => 'Collect vendor bill for completed ride.',
                    'metadata' => [
                        'kind' => self::KIND,
                        'purpose' => self::PURPOSE,
                        'ride_id' => (string) $lockedRide->id,
                        'vendor_payment_id' => (string) $payment->id,
                        'vendor_id' => (string) $payment->vendor_id,
                        'vendor_name_snapshot' => $payment->vendor?->name ?? 'Vendor',
                        'reimbursment_from_customer' => false,
                        'bill_path' => null,
                        'resolution' => null,
                    ],
                ]);

                OperationCaseActivity::create([
                    'operation_case_id' => $case->id,
                    'lead_id' => $leadId,
                    'user_id' => $actor->id,
                    'action' => 'vendor_bill_followup_created',
                    'from_status' => null,
                    'to_status' => OperationCase::STATUS_PENDING,
                    'note' => 'Automatically created after Ride Completed.',
                    'metadata' => ['ride_id' => (string) $lockedRide->id, 'vendor_payment_id' => (string) $payment->id],
                ]);

                $created++;
            }

            return $created;
        });
    }

    public function complete(OperationCase $case, User $actor, ?UploadedFile $bill, bool $reimbursement): void
    {
        if (!$bill && !$reimbursement) {
            throw ValidationException::withMessages([
                'vendor_bill' => 'Upload the vendor bill or tick reimbursment from customer.',
            ]);
        }

        // Place files on private disk. Clean up an uploaded file if DB transaction fails.
        $path = null;
        if ($bill) {
            $path = $bill->store('vendor-bills', 'local');
            if (!$path) {
                throw ValidationException::withMessages(['vendor_bill' => 'Could not save vendor bill.']);
            }
        }

        try {
            DB::transaction(function () use ($case, $actor, $path, $bill, $reimbursement): void {
                $locked = OperationCase::query()
                    ->whereKey($case->id)
                    ->where('type', OperationCase::TYPE_VENDOR_FOLLOWUP)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless(data_get($locked->metadata, 'kind') === self::KIND, 404);
                if ($locked->completed_at) {
                    throw ValidationException::withMessages(['case' => 'Vendor bill follow-up is already completed.']);
                }

                // A missing bill is permitted only for reimbursement from customer.
                if (!$path && !$reimbursement) {
                    throw ValidationException::withMessages(['vendor_bill' => 'A vendor bill is required.']);
                }

                $meta = $locked->metadata ?? [];
                $meta['bill_path'] = $path;
                $meta['bill_original_name'] = $bill ? basename($bill->getClientOriginalName()) : null;
                $meta['bill_mime'] = $bill?->getMimeType();
                $meta['reimbursment_from_customer'] = $reimbursement;
                $meta['resolution'] = 'bill_collected';
                $meta['completed_at'] = now()->toDateTimeString();

                $locked->update([
                    'status' => OperationCase::STATUS_COMPLETED,
                    'completed_by' => $actor->id,
                    'completed_at' => now(),
                    'next_followup_at' => null,
                    'metadata' => $meta,
                ]);

                OperationCaseActivity::create([
                    'operation_case_id' => $locked->id,
                    'lead_id' => $locked->lead_id,
                    'user_id' => $actor->id,
                    'action' => 'vendor_bill_followup_completed',
                    'from_status' => OperationCase::STATUS_PENDING,
                    'to_status' => OperationCase::STATUS_COMPLETED,
                    'note' => $path ? 'Vendor bill uploaded.' : 'Reimbursment from customer; upload exempt.',
                    'metadata' => ['has_bill' => (bool) $path, 'reimbursment_from_customer' => $reimbursement],
                ]);
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }
    }
}