<?php

namespace App\Http\Controllers;

use App\Models\ExtraService;
use App\Models\LeadFollowup;
use App\Models\LeadRide;
use App\Models\OperationCase;
use App\Models\PaymentAuditTrail;
use App\Models\Service;
use App\Services\Operations\OperationCaseService;
use App\Services\Operations\RescheduleTrackingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class OperationsRescheduleController extends Controller
{
    private array $serviceLabels = [];
    private array $productLabels = [];
    private array $approvedPayments = [];

    public function index(Request $request)
    {
        $filters = $this->filters($request);

        $cases = OperationCase::with([
            'lead.client',
            'lead.representative',
            'lead.rides',
            'assignee', 'completedBy', 'creator', 'activities.user',
            'lead.leadFollowups' => function ($query) {
                $query->orderByDesc('created_at');
            },
        ])
            ->where('type', OperationCase::TYPE_RESCHEDULE)
            ->whereHas('lead.rides');

        $this->applyFilters($cases, $filters);

        $summary = [
            'month' => (clone $cases)->whereBetween('opened_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'fixed' => (clone $cases)->where('metadata->reschedule->state', 'fixed')->count(),
            'open' => $this->stateQuery(clone $cases, 'open')->count(),
            'expiring' => $this->stateQuery(clone $cases, 'expiring')->count(),
        ];
        $this->stateQuery($cases, $filters['segment']);

        if ($request->input('download') === 'csv') {
            return response()->streamDownload(function () use ($cases) {
                $output = fopen('php://output', 'w');
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, ['Client', 'Phone', 'Product / Service', 'Original date', 'New date', 'Window expires', 'Days remaining', 'Reason', 'Assigned to', 'Status', 'Paid', 'Total']);
                foreach ((clone $cases)->orderBy('id')->lazy(100)->chunk(100) as $chunk) {
                    $this->loadLabels($chunk);
                    foreach ($chunk as $case) {
                        $row = $this->rescheduleRow($case);
                        $values = [$row['client']?->name, $row['client']?->contact_number, $row['service'], $row['original_date']?->toDateTimeString(), $row['new_date']?->toDateTimeString(), $row['state'] === 'open' ? $row['expires_on']?->toDateString() : null, $row['state'] === 'open' ? $row['days_left'] : null, $row['tracking']['reason'] ?? null, $row['actor']?->name, $row['state'], $row['received_amount'], $row['total_amount']];
                        fputcsv($output, array_map(function ($value) {
                            return is_string($value) && preg_match('/^[\s]*[=+@-]/', $value) ? "'".$value : $value;
                        }, $values));
                    }
                }
                fclose($output);
            }, 'rescheduled-rides-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $rideStatusPaginator = $cases
            ->latest('opened_at')
            ->paginate($filters['per_page'])
            ->withQueryString();

        $this->loadLabels($rideStatusPaginator->getCollection());
        $rows = $rideStatusPaginator
            ->getCollection()
            ->map(fn (OperationCase $case) => $this->rescheduleRow($case))
            ->filter()
            ->values()
            ->all();

        return view('admin.account.rides.ride-status', [
            'ridesData' => [],
            'rescheduleRows' => $rows,
            'rescheduleSummary' => $summary,
            'rescheduleFilters' => $filters,
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
        $case->load(['activities.user', 'assignee']);
        $tracking = app(RescheduleTrackingService::class);
        $original = data_get($case->metadata, 'reschedule.original') ?? $tracking->original($ride);

        return view('admin.pages.operations.reschedules.edit', [
            'ride' => $ride,
            'case' => $case,
            'lead' => $ride->enquiry,
            'client' => optional($ride->enquiry)->client,
            'originalTrip' => $original,
            'window' => $tracking->window(data_get($original, 'from_date') ?? $ride->from_date?->toDateTimeString()),
            'resourceChecksAvailable' => app(\App\Services\Operations\BookingConflictService::class)->available(),
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
            'from_date' => 'exclude_if:no_date,1|required_unless:no_date,1|nullable|date',
            'to_date' => 'exclude_if:no_date,1|nullable|date|after_or_equal:from_date',
            'from_place' => 'nullable|string|max:255',
            'to_place' => 'nullable|string|max:255',
            'total_time' => 'nullable|numeric|min:0|max:9999',
            'no_date' => 'nullable|boolean',
            'reason' => 'nullable|string|in:client_personal,client_business,weather,maintenance,vendor_unavailable,other',
            'note' => 'nullable|string|max:5000',
            'booking_resource_key' => 'nullable|string|max:80|regex:/^[A-Za-z0-9][A-Za-z0-9._:\/\s-]*$/',
        ]);

        if (!empty($data['booking_resource_key']) && !app(\App\Services\Operations\BookingConflictService::class)->available()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['booking_resource_key' => 'Apply the booking resource migration before assigning an aircraft or vehicle.']);
        }

        app(RescheduleTrackingService::class)->save($case, $ride, $request->user(), $data);

        return redirect()
            ->route('admin.operations.reschedules.index')
            ->with('success', $request->boolean('no_date') ? 'Saved. Case remains open while awaiting a date.' : 'Reschedule saved and Operations task completed.');
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
        $case->refresh();
        $metadata = $case->metadata ?: [];
        $metadata['reschedule']['state'] = 'cancelled';
        $case->update(['metadata' => $metadata]);
        app(\App\Services\Operations\RescheduleWindowService::class)->sync($case);

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
            'segment' => 'nullable|in:all,fixed,open,expiring,pending,assessment',
            'download' => 'nullable|in:csv',
        ]);

        return [
            'from_date' => $data['from_date'] ?? null,
            'to_date' => $data['to_date'] ?? null,
            'status' => LeadFollowup::STATUS_RESCHEDULED,
            'search' => $data['search'] ?? null,
            'per_page' => (int) ($data['per_page'] ?? 20),
            'segment' => $data['segment'] ?? 'all',
        ];
    }

    private function applyFilters($cases, array $filters): void
    {
        if (!empty($filters['from_date'])) {
            $cases->whereDate('opened_at', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $cases->whereDate('opened_at', '<=', $filters['to_date']);
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);

            $cases->where(function ($query) use ($search) {
                $query
                    ->where('note', 'like', "%{$search}%")
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

    private function stateQuery($query, string $segment)
    {
        if ($segment === 'assessment') {
            $query->whereNull('completed_at')->where('metadata->reschedule->state', 'open')
                ->where('metadata->reschedule->expires_on', '<', now()->toDateString());
        } elseif ($segment === 'fixed') {
            $query->where('metadata->reschedule->state', 'fixed');
        } elseif (in_array($segment, ['open', 'expiring'], true)) {
            $query->whereNull('completed_at')->where('metadata->reschedule->state', 'open');
            if ($segment === 'expiring') {
                $query->whereBetween('metadata->reschedule->expires_on', [now()->toDateString(), now()->addDays(10)->toDateString()]);
            }
        } elseif ($segment === 'pending') {
            $query->whereNull('completed_at')->where(function ($q) {
                $q->whereNull('metadata->reschedule->state')->orWhere('metadata->reschedule->state', '!=', 'open');
            });
        }
        return $query;
    }

    public function conflicts(Request $request, LeadRide $ride)
    {
        abort_unless($this->pendingCaseForRide($ride), 404);
        $data = $request->validate([
            'booking_resource_key' => 'nullable|string|max:80|regex:/^[A-Za-z0-9][A-Za-z0-9._:\/\s-]*$/',
            'from_date' => 'nullable|date', 'to_date' => 'nullable|date|after:from_date',
        ]);
        $checker = app(\App\Services\Operations\BookingConflictService::class);
        $key = $checker->normalize($data['booking_resource_key'] ?? null);
        $status = !$checker->available() ? 'unavailable' : (!$key ? 'unverified' : (empty($data['from_date']) || empty($data['to_date']) ? 'incomplete' : 'checked'));
        $conflicts = $status === 'checked' ? $checker->conflicts($ride, $key, Carbon::parse($data['from_date']), Carbon::parse($data['to_date'])) : [];
        return response()->json(['status' => $conflicts ? 'conflict' : $status, 'conflicts' => $conflicts])->header('Cache-Control', 'no-store');
    }

    private function rescheduleRow(OperationCase $case): ?array
    {
        $ride = $this->rideForCase($case);
        $tracking = data_get($case->metadata, 'reschedule', []);
        $original = data_get($tracking, 'original.from_date');
        $window = app(RescheduleTrackingService::class)->window($original);
        $state = $tracking['state'] ?? ($case->completed_at ? 'legacy_closed' : 'pending');
        return [
            'case' => $case, 'ride' => $ride, 'id' => $ride?->id,
            'client' => $case->lead->client, 'lead_id' => $case->lead_id,
            'service' => $this->interestLabel($case),
            'original_date' => $original ? Carbon::parse($original) : null,
            'new_date' => $state === 'fixed' && data_get($tracking, 'latest.from_date') ? Carbon::parse($tracking['latest']['from_date']) : null,
            'state' => $state, 'tracking' => $tracking,
            'actor' => $case->completedBy ?: $case->assignee,
            'total_amount' => $this->totalAmountFromFollowups($case->lead->leadFollowups),
            'received_amount' => (float) $case->lead->leadFollowups->sum(fn($followup) => $this->approvedPayments[$followup->id] ?? 0),
        ] + $window;
    }

    private function loadLabels($cases): void
    {
        $leads = collect($cases)->pluck('lead')->filter();
        $serviceIds = $leads->flatMap(fn($lead) => $this->decodeIds($lead->leadFollowups->first()?->service_ids ?: $lead->service_ids))->unique()->all();
        $productIds = $leads->flatMap(fn($lead) => $this->decodeIds($lead->product_ids))->unique()->all();
        $followupIds = $leads->flatMap(fn($lead) => $lead->leadFollowups->pluck('id'))->unique()->all();
        $this->serviceLabels = $serviceIds ? Service::whereIn('id', $serviceIds)->pluck('service', 'id')->all() : [];
        $this->productLabels = $productIds ? \App\Models\Product::whereIn('id', $productIds)->pluck('product', 'id')->all() : [];
        $this->approvedPayments = $followupIds && Schema::hasTable('payment_audit_trail') ? PaymentAuditTrail::whereIn('lead_followup_id', $followupIds)
            ->where('payment_status', PaymentAuditTrail::STATUS_APPROVED)->selectRaw('lead_followup_id, SUM(paid_amount) as paid')->groupBy('lead_followup_id')->pluck('paid', 'lead_followup_id')->all() : [];
    }

    private function interestLabel(OperationCase $case): string
    {
        $lead = $case->lead;
        $products = collect($this->decodeIds($lead->product_ids))->map(fn($id) => $this->productLabels[$id] ?? null);
        $services = collect($this->decodeIds($lead->leadFollowups->first()?->service_ids ?: $lead->service_ids))->map(fn($id) => $this->serviceLabels[$id] ?? null);
        return $products->merge($services)->filter()->unique()->implode(' / ') ?: 'Not recorded';
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

            return $ride;
        }

        return $rides->count() === 1 ? $rides->first() : null;
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
        }) ?: $cases->first(function (OperationCase $case) use ($ride) {
            return !data_get($case->metadata, 'ride_id')
                && LeadRide::where('lead_id', $ride->lead_id)->count() === 1;
        });
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
            ->where('payment_status', PaymentAuditTrail::STATUS_APPROVED)
            ->sum('paid_amount');
    }
}
