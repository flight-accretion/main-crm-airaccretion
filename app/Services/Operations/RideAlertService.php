<?php

namespace App\Services\Operations;

use App\Jobs\Operations\SendOperationRideAlert;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\RideAlertNotification;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class RideAlertService
{
    public function __construct(
        private RideAlertDataResolver $resolver,
        private OperationsRecipientService $recipients,
        private VendorFollowupAutomationService $followups
    ) {
    }

    public function syncForLead(
        Lead $lead,
        ?CarbonInterface $previousRideDate = null
    ): void {
        if (!config('services.operations_ride_alert.enabled', true)) {
            return;
        }

        $data = $this->resolver->resolve($lead);
        $rideDate = $data['ride_date'];

        if (!$rideDate) {
            $this->cancelPendingForLead($lead);
            return;
        }

        $dateChanged = $previousRideDate
            && !$previousRideDate->isSameDay($rideDate);

        if ($dateChanged) {
            $this->syncChangedDateFollowup($lead, $rideDate);
            RideAlertNotification::query()
                ->where('lead_id', $lead->id)
                ->whereIn('status', ['pending', 'failed'])
                ->whereDate('ride_date', '!=', $rideDate->toDateString())
                ->update(['status' => 'cancelled']);
        } else {
            $this->followups->syncRideConfirmation($lead, $rideDate);
        }

        [$type, $dueAt] = $this->alertTypeAndDueAt($rideDate, $dateChanged);

        foreach ($this->recipients->activeUsers() as $user) {
            $number = $this->recipients->normalizedNumber(
                (string) $user->contact_number
            );

            if ($number === '') {
                continue;
            }

            $notification = $this->upsert(
                $lead,
                $data,
                (string) $user->id,
                $number,
                $type,
                $dueAt
            );

            if ($notification && $notification->due_at->lte(now())) {
                SendOperationRideAlert::dispatch($notification->id)->afterCommit();
            }
        }
    }

    public function cancelPendingForLead(Lead $lead): void
    {
        RideAlertNotification::query()
            ->where('lead_id', $lead->id)
            ->whereIn('status', ['pending', 'failed'])
            ->update(['status' => 'cancelled']);
    }

    private function syncChangedDateFollowup(
        Lead $lead,
        CarbonInterface $rideDate
    ): void {
        $completedConfirmation = LeadFollowup::query()
            ->where('lead_id', $lead->id)
            ->where('followup_type', 'ride_confirmation')
            ->where('status', LeadFollowup::STATUS_CONFIRMED)
            ->exists();

        if ($completedConfirmation) {
            $this->followups->createNewForReschedule($lead, $rideDate);
            return;
        }

        $this->followups->syncRideConfirmation(
            $lead,
            $rideDate,
            'Ride date updated. Vendor reconfirmation required.'
        );
    }

    private function alertTypeAndDueAt(
        CarbonInterface $rideDate,
        bool $dateChanged
    ): array {
        $now = now('Asia/Kolkata');
        $threeDayAt = $rideDate
            ->copy()
            ->timezone('Asia/Kolkata')
            ->startOfDay()
            ->subDays((int) config('services.operations_ride_alert.days_before', 3))
            ->setTime(10, 0);

        if ($dateChanged) {
            return ['date_changed', $now];
        }

        if ($threeDayAt->lte($now)) {
            return ['late_date', $now];
        }

        return ['three_day', $threeDayAt];
    }

    private function upsert(
        Lead $lead,
        array $data,
        string $recipientUserId,
        string $number,
        string $type,
        CarbonInterface $dueAt
    ): ?RideAlertNotification {
        return DB::transaction(function () use (
            $lead,
            $data,
            $recipientUserId,
            $number,
            $type,
            $dueAt
        ) {
            $existing = RideAlertNotification::query()
                ->where('lead_id', $lead->id)
                ->where('recipient_number', $number)
                ->whereDate('ride_date', $data['ride_date']->toDateString())
                ->where('alert_type', $type)
                ->lockForUpdate()
                ->first();

            $variables = [
                $data['customer_name'],
                $data['service_name'],
                $data['ride_date']->format('d-m-Y'),
                $data['ride_time'],
                (string) $data['guests'],
                $data['vendor'],
                $data['location'],
                $data['lead_id'],
                $data['crm_link'],
            ];

            if ($existing) {
                if ($existing->status === 'sent') {
                    return $existing;
                }

                $existing->update([
                    'recipient_user_id' => $recipientUserId,
                    'ride_id' => $data['ride_id'],
                    'due_at' => $dueAt,
                    'template_name' => config(
                        'services.operations_ride_alert.template',
                        'ride_alert_noti'
                    ),
                    'template_variables' => $variables,
                    'status' => 'pending',
                    'failed_at' => null,
                    'failure_reason' => null,
                ]);

                return $existing->fresh();
            }

            try {
                return RideAlertNotification::create([
                    'lead_id' => $lead->id,
                    'ride_id' => $data['ride_id'],
                    'recipient_user_id' => $recipientUserId,
                    'recipient_number' => $number,
                    'alert_type' => $type,
                    'ride_date' => $data['ride_date']->toDateString(),
                    'due_at' => $dueAt,
                    'status' => 'pending',
                    'template_name' => config(
                        'services.operations_ride_alert.template',
                        'ride_alert_noti'
                    ),
                    'template_variables' => $variables,
                ]);
            } catch (QueryException) {
                return RideAlertNotification::query()
                    ->where('lead_id', $lead->id)
                    ->where('recipient_number', $number)
                    ->whereDate('ride_date', $data['ride_date']->toDateString())
                    ->where('alert_type', $type)
                    ->first();
            }
        });
    }
}
