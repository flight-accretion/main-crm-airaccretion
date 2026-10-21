<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\OperationCase;
use App\Models\User;
use App\Models\UserType;
use App\Models\Vendor;
use App\Services\Operations\VendorFollowupService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsVendorFollowupController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $base = OperationCase::query()
            ->with(['lead.client', 'lead.representative', 'lead.rideSegments', 'assignee', 'creator', 'completedBy'])
            ->where('type', OperationCase::TYPE_VENDOR_FOLLOWUP);
        $base->withMax('activities', 'created_at');

        $this->applyFilters($base, $filters);

        $countsBase = clone $base;
        $counts = [
            'open' => (clone $countsBase)->whereNull('completed_at')->count(),
            'overdue' => (clone $countsBase)->whereNull('completed_at')->whereDate('next_followup_at', '<', now()->toDateString())->count(),
            'today' => (clone $countsBase)->whereNull('completed_at')->whereDate('next_followup_at', now()->toDateString())->count(),
            'upcoming' => (clone $countsBase)->whereNull('completed_at')->whereDate('next_followup_at', '>', now()->toDateString())->count(),
            'closed' => (clone $countsBase)->whereNotNull('completed_at')->count(),
            'all' => (clone $countsBase)->count(),
            'completed' => (clone $countsBase)->whereNotNull('completed_at')->where('metadata->resolution', 'confirmed')->count(),
            'cancelled' => (clone $countsBase)->whereNotNull('completed_at')->where('metadata->resolution', 'cancelled')->count(),
            'completed_week' => (clone $countsBase)->where('metadata->resolution', 'confirmed')->whereBetween('completed_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'age_2_7' => (clone $countsBase)->whereNull('completed_at')->where('opened_at', '>=', now()->subDays(7)->startOfDay())->where('opened_at', '<', now()->subDay()->startOfDay())->count(),
            'age_over_7' => (clone $countsBase)->whereNull('completed_at')->where('opened_at', '<', now()->subDays(7)->startOfDay())->count(),
        ];

        $query = clone $base;
        $this->applyBucket($query, $filters['bucket']);

        $cases = $query
            ->orderByRaw('CASE WHEN completed_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('next_followup_at')
            ->orderByDesc('opened_at')
            ->orderBy('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('admin.pages.operations.vendor-followups.index', [
            'cases' => $cases,
            'filters' => $filters,
            'counts' => $counts,
            'canManage' => $this->canManage($request->user()),
            'vendorsById' => Vendor::whereIn('id', $cases->getCollection()->map(fn($case) => data_get($case->metadata, 'vendor_id'))->filter()->unique())->get(['id', 'name', 'contact_number'])->keyBy('id'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $query = OperationCase::query()
            ->with(['lead.client', 'assignee', 'creator', 'completedBy'])
            ->where('type', OperationCase::TYPE_VENDOR_FOLLOWUP);

        $this->applyFilters($query, $filters);
        $this->applyBucket($query, $filters['bucket']);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Lead ID', 'Client', 'Vendor', 'Purpose', 'Status', 'Resolution', 'Follow-up Date', 'Assigned To', 'Created By']);

            $query->orderByDesc('opened_at')->chunk(200, function ($cases) use ($out) {
                foreach ($cases as $case) {
                    fputcsv($out, [
                        $case->lead_id,
                        optional(optional($case->lead)->client)->name,
                        data_get($case->metadata, 'vendor_name_snapshot'),
                        data_get($case->metadata, 'purpose'),
                        $case->status,
                        data_get($case->metadata, 'resolution') ?: 'open',
                        optional($case->next_followup_at)->format('Y-m-d'),
                        optional($case->assignee)->name,
                        optional($case->creator)->name,
                    ]);
                }
            });

            fclose($out);
        }, 'vendor-followups.csv');
    }

    public function redirectCreate(Lead $lead)
    {
        return redirect()->route('admin.operations.vendor-followups.create', $lead);
    }

    public function create(Request $request, Lead $lead)
    {
        $this->abortUnlessCanManage($request);
        $lead->loadMissing(['client', 'rideSegments']);
        $tentativeRideAt = $this->tentativeRideAt($lead);

        return view('admin.pages.operations.vendor-followups.create', [
            'lead' => $lead,
            'vendors' => $this->vendors(),
            'operationUsers' => $this->operationUsers(),
            'tentativeRideAt' => $tentativeRideAt,
            'suggestedFollowupDate' => $this->suggestedFollowupDate($tentativeRideAt),
            'interestNames' => array_merge($lead->product_names ?: [], $lead->service_names ?: []),
        ]);
    }

    public function store(Request $request, Lead $lead, VendorFollowupService $service)
    {
        $this->abortUnlessCanManage($request);
        $data = $request->validate([
            'vendor_id' => ['required', 'uuid', 'exists:vendors,id'],
            'assigned_to' => ['nullable', 'uuid', 'exists:users,id'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'tentative_ride_at' => ['nullable', 'date'],
            'followup_date' => ['required', 'date', 'after_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        $vendor = Vendor::query()->where('status', 1)->findOrFail($data['vendor_id']);
        $assignedTo = !empty($data['assigned_to'])
            ? User::query()->where('status', 1)->findOrFail($data['assigned_to'])
            : $request->user();

        $case = $service->create($lead, $vendor, $request->user(), $assignedTo, $data);

        return redirect()
            ->route('admin.operations.vendor-followups.show', $case)
            ->with('success', 'Vendor Follow-up created.');
    }

    public function show(Request $request, OperationCase $case)
    {
        $this->abortUnlessVendorFollowup($case);
        $case->loadMissing(['lead.client', 'lead.representative', 'lead.rideSegments', 'assignee', 'creator', 'completedBy', 'activities.user']);

        return view('admin.pages.operations.vendor-followups.show', [
            'case' => $case,
            'canManage' => $this->canManage($request->user()),
            'isClosed' => (bool) $case->completed_at,
        ]);
    }

    public function reschedule(Request $request, OperationCase $case, VendorFollowupService $service)
    {
        $this->abortUnlessCanManage($request);
        $this->abortUnlessVendorFollowup($case);
        $data = $request->validate([
            'followup_date' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['required', 'string', 'max:5000'],
        ]);

        $service->reschedule($case, $request->user(), $data['followup_date'], $data['reason']);

        return back()->with('success', 'Vendor Follow-up rescheduled.');
    }

    public function complete(Request $request, OperationCase $case, VendorFollowupService $service)
    {
        $this->abortUnlessCanManage($request);
        $this->abortUnlessVendorFollowup($case);
        $data = $request->validate([
            'confirmed_ride_date' => ['required', 'date'],
            'confirmed_ride_time' => ['required', 'date_format:H:i'],
            'vendor_response' => ['required', 'string', 'max:5000'],
        ]);

        $service->complete($case, $request->user(), $data);

        return back()->with('success', 'Vendor Follow-up completed.');
    }

    public function cancel(Request $request, OperationCase $case, VendorFollowupService $service)
    {
        $this->abortUnlessCanManage($request);
        $this->abortUnlessVendorFollowup($case);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:5000'],
        ]);

        $service->cancel($case, $request->user(), $data['reason']);

        return redirect()
            ->route('admin.operations.vendor-followups.index')
            ->with('success', 'Vendor Follow-up cancelled.');
    }

    private function filters(Request $request): array
    {
        $data = $request->validate([
            'bucket' => ['nullable', 'in:open,overdue,today,upcoming,completed,cancelled,all,age_2_7,age_over_7'],
            'search' => ['nullable', 'string', 'max:255'],
            'vendor_id' => ['nullable', 'uuid'],
            'assigned_to' => ['nullable', 'uuid'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
        ]);

        return [
            'bucket' => $data['bucket'] ?? 'open',
            'search' => trim((string) ($data['search'] ?? '')),
            'vendor_id' => $data['vendor_id'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'per_page' => (int) ($data['per_page'] ?? 25),
        ];
    }

    private function applyFilters($query, array $filters): void
    {
        if ($filters['vendor_id']) {
            $query->where('metadata->vendor_id', $filters['vendor_id']);
        }

        if ($filters['assigned_to']) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $operator = $query->getModel()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $operator) {
                $q->whereRaw('CAST(lead_id AS TEXT) '.$operator.' ?', ["%{$search}%"])
                    ->orWhere('metadata->vendor_name_snapshot', $operator, "%{$search}%")
                    ->orWhereHas('lead.client', function ($clientQuery) use ($search, $operator) {
                        $clientQuery->where('name', $operator, "%{$search}%")
                            ->orWhere('contact_number', $operator, "%{$search}%");
                    });
            });
        }
    }

    private function applyBucket($query, string $bucket): void
    {
        if ($bucket === 'all') {
            return;
        }

        if ($bucket === 'completed') {
            $query->whereNotNull('completed_at')
                ->where('metadata->resolution', 'confirmed');
            return;
        }

        if ($bucket === 'cancelled') {
            $query->whereNotNull('completed_at')
                ->where('metadata->resolution', 'cancelled');
            return;
        }

        $query->whereNull('completed_at');

        if ($bucket === 'overdue') {
            $query->whereDate('next_followup_at', '<', now()->toDateString());
        } elseif ($bucket === 'today') {
            $query->whereDate('next_followup_at', now()->toDateString());
        } elseif ($bucket === 'upcoming') {
            $query->whereDate('next_followup_at', '>', now()->toDateString());
        } elseif ($bucket === 'age_2_7') {
            $query->where('opened_at', '>=', now()->subDays(7)->startOfDay())->where('opened_at', '<', now()->subDay()->startOfDay());
        } elseif ($bucket === 'age_over_7') {
            $query->where('opened_at', '<', now()->subDays(7)->startOfDay());
        }
    }

    private function vendors()
    {
        return Vendor::query()
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'contact_number']);
    }

    private function operationUsers()
    {
        return User::query()
            ->with('userType')
            ->where('status', 1)
            ->whereHas('userType', function ($query) {
                $query->whereIn('user_type', UserType::OPERATIONS_ROLES);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'user_type_id']);
    }

    private function tentativeRideAt(Lead $lead): ?Carbon
    {
        $ride = $lead->rideSegments
            ->filter(fn ($segment) => !empty($segment->from_date))
            ->sortBy('from_date')
            ->first();

        return $ride && $ride->from_date ? Carbon::parse($ride->from_date) : null;
    }

    private function suggestedFollowupDate(?Carbon $tentativeRideAt): Carbon
    {
        $today = now()->startOfDay();

        if (!$tentativeRideAt) {
            return $today;
        }

        $rideDate = $tentativeRideAt->copy()->startOfDay();

        return $today->diffInDays($rideDate, false) > 10
            ? $rideDate->subDays(7)
            : $today;
    }

    private function canManage(?User $user): bool
    {
        $role = optional($user?->userType)->user_type;

        return $role === UserType::SUPER_ADMIN
            || in_array($role, UserType::OPERATIONS_ROLES, true);
    }

    private function abortUnlessCanManage(Request $request): void
    {
        abort_unless($this->canManage($request->user()), 403);
    }

    private function abortUnlessVendorFollowup(OperationCase $case): void
    {
        abort_unless($case->type === OperationCase::TYPE_VENDOR_FOLLOWUP, 404);
    }
}
