<?php

namespace App\Http\Controllers;

use App\Models\ExtraService;
use App\Models\LeadFollowup;
use App\Models\LeadRide;
use App\Models\OperationCase;
use App\Models\PaymentAuditTrail;
use App\Models\Service;
use App\Services\Operations\OperationCaseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class OperationsRescheduleController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->filters($request);

        $cases = OperationCase::with([
            'lead.client',
            'lead.representative',
            'lead.rides',
            'lead.vouchers.invoice',
            'lead.leadFollowups' => function ($query) {
                $query->orderByDesc('created_at');
            },
        ])
            ->where('type', OperationCase::TYPE_RESCHEDULE)
            ->whereNull('completed_at')
            ->whereHas('lead.rides');

        $this->applyFilters($cases, $filters);

        $rideStatusPaginator = $cases
            ->latest('opened_at')
            ->paginate($filters['per_page'])
            ->withQueryString();

        $ridesData = $rideStatusPaginator
            ->getCollection()
            ->map(fn (OperationCase $case) => $this->caseToRideData($case))
            ->filter()
            ->values()
            ->all();

        return view('admin.account.rides.ride-status', [
            'ridesData' => $ridesData,
            'rideStatusPaginator' => $rideStatusPaginator,
            'statusOptions' => [
                LeadFollowup::STATUS_RESCHEDULED => 'Reschedule',
            ],
            'currentFilters' => [
                'from_date' => $filters['from_date'],
                'to_date' => $filters['to_date'],
                'status' => (string) LeadFollowup::STATUS_RESCHEDULED,
                'search' => $filters['search'],
                'name' => null,
                'phone' => null,
                'product_id' => null,
                'service_id' => null,
            ],
            'products' => collect(),
            'services' => collect(),
            'operationsRescheduleMode' => true,
            'pageTitle' => 'Operations Reschedule',
            'tableTitle' => 'Pending Reschedule Tasks',
            'filterActionRoute' => route('admin.operations.reschedules.index'),
            'controlsActionRoute' => route('admin.operations.reschedules.index'),
            'resetRoute' => route('admin.operations.reschedules.index'),
            'exportRoute' => null,
        ]);
    }

    public function edit(LeadRide $ride)
    {
        $case = $this->pendingCaseForRide($ride);
        abort_unless($case, 404);

        $ride->load(['enquiry.client', 'enquiry.rides']);

        return view('admin.pages.operations.reschedules.edit', [
            'ride' => $ride,
            'case' => $case,
            'lead' => $ride->enquiry,
            'client' => optional($ride->enquiry)->client,
        ]);
    }

    public function update(
        Request $request,
        LeadRide $ride,
        OperationCaseService $service
    ) {
        $case = $this->pendingCaseForRide($ride);
        abort_unless($case, 404);

        $data = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'from_place' => 'nullable|string|max:255',
            'to_place' => 'nullable|string|max:255',
            'total_time' => 'nullable|numeric|min:0|max:9999',
            'no_date' => 'nullable|boolean',
        ]);

        $noDate = $request->boolean('no_date');

        $ride->update([
            'from_date' => $noDate ? null : (!empty($data['from_date']) ? Carbon::parse($data['from_date']) : null),
            'to_date' => $noDate ? null : (!empty($data['to_date']) ? Carbon::parse($data['to_date']) : null),
            'from_place' => $data['from_place'] ?? null,
            'to_place' => $data['to_place'] ?? null,
            'total_time' => $data['total_time'] ?? null,
            'is_tba' => $noDate,
        ]);

        $service->complete(
            $case,
            $request->user(),
            'Reschedule trip details updated from Operations dashboard.'
        );

        return redirect()
            ->route('admin.operations.reschedules.index')
            ->with('success', 'Reschedule details updated.');
    }

    public function cancel(
        Request $request,
        LeadRide $ride,
        OperationCaseService $service
    ) {
        $case = $this->pendingCaseForRide($ride);
        abort_unless($case, 404);

        $request->validate([
            'total_amount' => 'nullable|numeric|min:0',
        ]);

        $request->merge([
            'status' => LeadFollowup::STATUS_CANCELLED,
            'total_amount' => $request->input(
                'total_amount',
                $this->latestTotalAmount($ride)
            ),
        ]);

        $response = app(RideController::class)->updateRideStatus($request, $ride->id);
        $payload = method_exists($response, 'getData')
            ? $response->getData(true)
            : [];

        if ($response->getStatusCode() >= 400 || empty($payload['success'])) {
            return back()->with('error', $payload['message'] ?? 'Unable to cancel ride.');
        }

        $service->complete(
            $case,
            $request->user(),
            'Ride cancelled from Operations reschedule.'
        );

        return redirect()
            ->route('admin.operations.reschedules.index')
            ->with('success', 'Ride cancelled and Operations task completed.');
    }

    private function filters(Request $request): array
    {
        $data = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'status' => 'nullable|in:' . LeadFollowup::STATUS_RESCHEDULED,
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|in:10,20,25,50,100',
        ]);

        return [
            'from_date' => $data['from_date'] ?? null,
            'to_date' => $data['to_date'] ?? null,
            'status' => LeadFollowup::STATUS_RESCHEDULED,
            'search' => $data['search'] ?? null,
            'per_page' => (int) ($data['per_page'] ?? 20),
        ];
    }

    private function applyFilters($cases, array $filters): void
    {
        if (!empty($filters['from_date'])) {
            $cases->whereHas('lead.rides', function ($query) use ($filters) {
                $query->whereDate('from_date', '>=', $filters['from_date']);
            });
        }

        if (!empty($filters['to_date'])) {
            $cases->whereHas('lead.rides', function ($query) use ($filters) {
                $query->whereDate('from_date', '<=', $filters['to_date']);
            });
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);

            $cases->where(function ($query) use ($search) {
                $query
                    ->where('lead_id', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
                    ->orWhereHas('lead.client', function ($clientQuery) use ($search) {
                        $clientQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('contact_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('lead.representative', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }
    }

    private function caseToRideData(OperationCase $case): ?array
    {
        $lead = $case->lead;

        if (!$lead) {
            return null;
        }

        $ride = $this->rideForCase($case);

        if (!$ride) {
            return null;
        }

        $client = $lead->client;
        $latestFollowup = $lead->leadFollowups->first();
        $allRides = $lead->rides->sortBy('from_date')->values();
        $firstRide = $allRides->first() ?: $ride;
        $totalAmount = $this->totalAmountFromFollowups($lead->leadFollowups);
        $receivedAmount = $this->receivedAmount($lead->leadFollowups);

        return [
            'id' => $ride->id,
            'operation_case_id' => $case->id,
            'lead_id' => $lead->id,
            'invoice_id' => optional(optional($lead->vouchers)->first()?->invoice)->invoice_id ?? 'N/A',
            'vendor_name' => 'N/A',
            'assigned_rep' => optional($lead->representative)->name ?? 'N/A',
            'created_date' => optional($lead->created_at)->format('d-m-Y') ?? 'N/A',
            'created_date_sortable' => optional($lead->created_at)->format('Y-m-d H:i:s') ?? '0000-00-00 00:00:00',
            'client_name' => optional($client)->name ?? 'N/A',
            'company_name' => optional($client)->company_name ?: 'N/A',
            'gst_number' => optional($client)->gst_number ?: 'N/A',
            'contact_number' => optional($client)->contact_number ?? 'N/A',
            'service_date' => $firstRide->from_date ? $firstRide->from_date->format('d-m-Y') : 'N/A',
            'service_date_sortable' => $firstRide->from_date ? $firstRide->from_date->format('Y-m-d H:i:s') : '0000-00-00 00:00:00',
            'service_names' => $this->serviceNames($latestFollowup),
            'extra_service_names' => $this->extraServiceNames($latestFollowup),
            'total_amount' => $totalAmount,
            'received_amount' => $receivedAmount,
            'balance_amount' => max($totalAmount - $receivedAmount, 0),
            'payment_method' => 'N/A',
            'status' => $latestFollowup ? (int) $latestFollowup->status : LeadFollowup::STATUS_RESCHEDULED,
            'status_text' => 'Reschedule',
            'all_rides' => $allRides,
        ];
    }

    private function rideForCase(OperationCase $case): ?LeadRide
    {
        $rideId = data_get($case->metadata, 'ride_id');
        $rides = optional($case->lead)->rides ?: collect();

        if ($rideId) {
            $ride = $rides->firstWhere('id', $rideId);

            if ($ride) {
                return $ride;
            }
        }

        return $rides->sortByDesc('created_at')->first();
    }

    private function pendingCaseForRide(LeadRide $ride): ?OperationCase
    {
        $cases = OperationCase::query()
            ->where('lead_id', $ride->lead_id)
            ->where('type', OperationCase::TYPE_RESCHEDULE)
            ->whereNull('completed_at')
            ->get();

        return $cases->first(function (OperationCase $case) use ($ride) {
            return data_get($case->metadata, 'ride_id') === $ride->id;
        }) ?: $cases->first();
    }

    private function serviceNames(?LeadFollowup $followup): string
    {
        $ids = $this->decodeIds(optional($followup)->service_ids);

        if (empty($ids)) {
            return '';
        }

        return Service::whereIn('id', $ids)->pluck('service')->implode(', ');
    }

    private function extraServiceNames(?LeadFollowup $followup): string
    {
        $ids = $this->decodeIds(optional($followup)->extra_service_ids);

        if (empty($ids)) {
            return '';
        }

        return ExtraService::whereIn('id', $ids)->pluck('extra_service')->implode(', ');
    }

    private function decodeIds($ids): array
    {
        if (empty($ids)) {
            return [];
        }

        if (is_array($ids)) {
            return $ids;
        }

        if (is_string($ids)) {
            $decoded = json_decode($ids, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function totalAmountFromFollowups($followups): float
    {
        $followup = $followups->first(fn ($item) => (float) $item->total_amount > 0);

        return $followup ? (float) $followup->total_amount : 0.0;
    }

    private function latestTotalAmount(LeadRide $ride): float
    {
        $ride->loadMissing(['enquiry.leadFollowups' => function ($query) {
            $query->orderByDesc('created_at');
        }]);

        return $this->totalAmountFromFollowups($ride->enquiry->leadFollowups ?? collect());
    }

    private function receivedAmount($followups): float
    {
        $followupIds = $followups->pluck('id')->filter()->all();

        if (empty($followupIds) || !Schema::hasTable('payment_audit_trail')) {
            return 0.0;
        }

        return (float) PaymentAuditTrail::whereIn('lead_followup_id', $followupIds)
            ->where('payment_status', 1)
            ->sum('paid_amount');
    }
}
