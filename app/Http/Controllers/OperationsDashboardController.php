<?php

namespace App\Http\Controllers;

use App\Models\LeadFollowup;
use App\Models\OperationCase;
use App\Models\OperationCaseActivity;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Models\UserType;
use App\Services\Operations\OperationCaseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OperationsDashboardController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $open = OperationCase::query()->whereNull('completed_at');
        $activity = OperationCaseActivity::with(['operationCase.lead.client', 'user']);

        $this->applyCaseFilters(
            $open,
            $filters,
            'opened_at'
        );

        if (!empty($filters['type'])) {
            $activity->whereHas('operationCase', function ($query) use ($filters) {
                $query->where('type', $filters['type']);
            });
        }

        if (!empty($filters['status'])) {
            $activity->where(function ($query) use ($filters) {
                $query
                    ->where('to_status', $filters['status'])
                    ->orWhere('from_status', $filters['status']);
            });
        }

        if (!empty($filters['operations_user_id'])) {
            $activity->where('user_id', $filters['operations_user_id']);
        }

        if (!empty($filters['from_date'])) {
            $activity->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $activity->whereDate('created_at', '<=', $filters['to_date']);
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);

            $activity->where(function ($query) use ($search) {
                $query
                    ->where('action', 'like', "%{$search}%")
                    ->orWhereHas('operationCase.lead.client', function ($clientQuery) use ($search) {
                        $clientQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        return view('admin.pages.operations.dashboard', [
            'customerCallCount' => (clone $open)->where('type', 'customer_call')->count(),
            'reviewCount' => (clone $open)->where('type', 'review')->count(),
            'rescheduleCount' => (clone $open)->where('type', 'reschedule')->count(),
            'refundCount' => (clone $open)->where('type', 'refund')->count(),
            'recentActivity' => $activity
                ->latest()
                ->paginate($filters['per_page'])
                ->withQueryString(),
            'filters' => $filters,
            'operationUsers' => $this->operationUsers(),
            'caseTypes' => $this->visibleCaseTypes(),
            'caseStatuses' => $this->caseStatuses(),
        ]);
    }

    public function queue(Request $request, string $type)
    {
        abort_unless(in_array($type, OperationCase::validTypes(), true), 404);

        $filters = $this->filters($request);
        $cases = OperationCase::with(['lead.client', 'lead.representative', 'lead.rideSegments', 'assignee'])
            ->where('type', $type)
            ->whereNull('completed_at');

        $this->applyCaseFilters(
            $cases,
            $filters,
            'opened_at'
        );

        $cases = $cases
            ->latest('opened_at')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('admin.pages.operations.queue', array_merge([
            'cases' => $cases,
            'type' => $type,
            'filters' => $filters,
            'operationUsers' => $this->operationUsers(),
            'caseStatuses' => $this->caseStatuses(),
        ], $this->leadTableData($cases)));
    }

    public function start(OperationCase $case, OperationCaseService $service)
    {
        $service->start($case, auth()->user());

        return back()->with('success', 'Operations case started.');
    }

    public function complete(
        Request $request,
        OperationCase $case,
        OperationCaseService $service
    ) {
        $data = $request->validate([
            'note' => 'nullable|string|max:5000',
        ]);

        $service->complete($case, auth()->user(), $data['note'] ?? null);

        return back()->with('success', 'Operations case completed.');
    }

    public function history(Request $request)
    {
        $filters = $this->filters($request);
        $cases = OperationCase::with(['lead.client', 'lead.representative', 'lead.rideSegments', 'assignee'])
            ->whereNotNull('completed_at');

        $this->applyCaseFilters(
            $cases,
            $filters,
            'completed_at'
        );

        $cases = $cases
            ->latest('completed_at')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('admin.pages.operations.history', array_merge([
            'cases' => $cases,
            'filters' => $filters,
            'operationUsers' => $this->operationUsers(),
            'caseTypes' => $this->visibleCaseTypes(),
            'caseStatuses' => $this->caseStatuses(),
        ], $this->leadTableData($cases)));
    }

    private function filters(Request $request): array
    {
        $data = $request->validate([
            'date_filter' => 'nullable|in:today,yesterday,monthly,custom',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'type' => 'nullable|in:' . implode(',', OperationCase::validTypes()),
            'status' => 'nullable|in:' . implode(',', $this->caseStatuses()),
            'operations_user_id' => 'nullable|uuid',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|in:10,25,50,100',
            'from_service_date' => 'nullable|date',
            'to_service_date' => 'nullable|date|after_or_equal:from_service_date',
            'from_created_date' => 'nullable|date',
            'to_created_date' => 'nullable|date|after_or_equal:from_created_date',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'representative_user_id' => 'nullable|uuid',
            'lead_status' => 'nullable|string|max:10',
            'service_ids' => 'nullable|string|max:64',
            'product_ids' => 'nullable|string|max:64',
        ]);

        [$dateFilter, $fromDate, $toDate] = $this->dateRangeFilters($data);

        return [
            'date_filter' => $dateFilter,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'type' => $data['type'] ?? null,
            'status' => $data['status'] ?? null,
            'operations_user_id' => $data['operations_user_id'] ?? null,
            'search' => $data['search'] ?? null,
            'per_page' => (int) ($data['per_page'] ?? 25),
            'from_service_date' => $data['from_service_date'] ?? null,
            'to_service_date' => $data['to_service_date'] ?? null,
            'from_created_date' => $data['from_created_date'] ?? null,
            'to_created_date' => $data['to_created_date'] ?? null,
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'representative_user_id' => $data['representative_user_id'] ?? null,
            'lead_status' => $data['lead_status'] ?? null,
            'service_ids' => $data['service_ids'] ?? null,
            'product_ids' => $data['product_ids'] ?? null,
        ];
    }

    private function dateRangeFilters(array $data): array
    {
        $dateFilter = $data['date_filter'] ?? null;
        $today = Carbon::now();

        if ($dateFilter === 'today') {
            return [
                $dateFilter,
                $today->toDateString(),
                $today->toDateString(),
            ];
        }

        if ($dateFilter === 'yesterday') {
            $yesterday = $today->copy()->subDay();

            return [
                $dateFilter,
                $yesterday->toDateString(),
                $yesterday->toDateString(),
            ];
        }

        if ($dateFilter === 'monthly') {
            return [
                $dateFilter,
                $today->copy()->startOfMonth()->toDateString(),
                $today->copy()->endOfMonth()->toDateString(),
            ];
        }

        $fromDate = $data['from_date'] ?? null;
        $toDate = $data['to_date'] ?? null;

        return [
            $dateFilter ?: (($fromDate || $toDate) ? 'custom' : null),
            $fromDate,
            $toDate,
        ];
    }

    private function applyCaseFilters($query, array $filters, string $dateColumn): void
    {
        if (!empty($filters['from_date'])) {
            $query->whereDate($dateColumn, '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate($dateColumn, '<=', $filters['to_date']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['operations_user_id'])) {
            $query->where('assigned_to', $filters['operations_user_id']);
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);

            $query->where(function ($query) use ($search) {
                $query
                    ->where('lead_id', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
                    ->orWhereHas('lead.client', function ($clientQuery) use ($search) {
                        $clientQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('contact_number', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('lead.representative', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $this->applyLeadFilters($query, $filters);
    }

    /**
     * Same lead filters as the Leads page, applied to the lead behind a case.
     */
    private function applyLeadFilters($query, array $filters): void
    {
        if (!empty($filters['from_service_date'])) {
            $from = Carbon::parse($filters['from_service_date'])->startOfDay();

            $query->whereHas('lead.rideSegments', fn ($q) => $q->where('from_date', '>=', $from));
        }

        if (!empty($filters['to_service_date'])) {
            $to = Carbon::parse($filters['to_service_date'])->endOfDay();

            $query->whereHas('lead.rideSegments', fn ($q) => $q->where('to_date', '<=', $to));
        }

        if (!empty($filters['from_created_date'])) {
            $from = Carbon::parse($filters['from_created_date'])->startOfDay();

            $query->whereHas('lead', fn ($q) => $q->where('created_at', '>=', $from));
        }

        if (!empty($filters['to_created_date'])) {
            $to = Carbon::parse($filters['to_created_date'])->endOfDay();

            $query->whereHas('lead', fn ($q) => $q->where('created_at', '<=', $to));
        }

        foreach (['name' => 'name', 'email' => 'email', 'phone' => 'contact_number'] as $input => $column) {
            if (!empty($filters[$input])) {
                $query->whereHas(
                    'lead.client',
                    fn ($q) => $q->where($column, 'like', '%' . $filters[$input] . '%')
                );
            }
        }

        if (!empty($filters['representative_user_id'])) {
            $query->whereHas(
                'lead',
                fn ($q) => $q->where('representative_user_id', $filters['representative_user_id'])
            );
        }

        // Lead status = status of the lead's latest follow-up ('na' = no follow-up yet).
        if (isset($filters['lead_status']) && $filters['lead_status'] !== '') {
            $status = strtolower(trim($filters['lead_status']));

            $query->whereHas('lead', function ($leadQuery) use ($status) {
                if (in_array($status, ['na', 'n/a'], true)) {
                    $leadQuery->whereNotExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('lead_followups as lf')
                            ->whereColumn('lf.lead_id', 'leads.id');
                    });

                    return;
                }

                $leadQuery->whereIn('leads.id', function ($sub) use ($status) {
                    $sub->select('lf.lead_id')
                        ->from('lead_followups as lf')
                        ->where('lf.status', $status)
                        ->whereRaw(
                            'lf.created_at = (SELECT MAX(lf2.created_at) FROM lead_followups as lf2 WHERE lf2.lead_id = lf.lead_id)'
                        );
                });
            });
        }

        foreach (['service_ids', 'product_ids'] as $column) {
            if (!empty($filters[$column])) {
                // Ids are UUIDs stored inside a JSON array, so a text match is enough.
                $query->whereHas(
                    'lead',
                    fn ($q) => $q->whereRaw(
                        'CAST(leads.' . $column . ' AS TEXT) LIKE ?',
                        ['%' . $filters[$column] . '%']
                    )
                );
            }
        }
    }

    /**
     * Data the Leads-style table and filter card need.
     */
    private function leadTableData($cases): array
    {
        $leadIds = collect($cases->items())->pluck('lead_id')->filter()->unique()->all();

        $latestFollowups = LeadFollowup::query()
            ->whereIn('lead_id', $leadIds)
            ->select('id', 'lead_id', 'status')
            ->orderByDesc('created_at')
            ->get()
            ->unique('lead_id')
            ->keyBy('lead_id');

        return [
            'latestFollowups' => $latestFollowups,
            'services' => Service::where('status', 1)->orderBy('service')->get(['id', 'service']),
            'products' => Product::where('status', 1)->orderBy('product')->get(['id', 'product']),
            'staff' => User::query()
                ->where('status', 1)
                ->whereHas('userType', fn ($q) => $q->whereIn('user_type', UserType::SALES_ROLES))
                ->orderBy('name')
                ->get(['id', 'name']),
            'leadStatusOptions' => [
                0 => 'Initiated',
                1 => 'Active',
                2 => 'Cancelled',
                3 => 'Full Payment Received',
                4 => 'Partial Payment Received',
                5 => 'Confirmed',
                6 => 'Pending',
                7 => 'Reschedule',
                8 => 'Approved',
                9 => 'Rejected',
            ],
        ];
    }

    private function operationUsers()
    {
        return User::query()
            ->with('userType')
            ->where('status', 1)
            ->whereHas('userType', function ($query) {
                $query->whereIn(
                    'user_type',
                    UserType::OPERATIONS_ROLES
                );
            })
            ->orderBy('name')
            ->get(['id', 'name', 'user_type_id']);
    }

    private function caseStatuses(): array
    {
        return [
            OperationCase::STATUS_PENDING,
            OperationCase::STATUS_IN_PROGRESS,
            OperationCase::STATUS_COMPLETED,
        ];
    }

    private function visibleCaseTypes(): array
    {
        return array_values(array_filter(
            OperationCase::validTypes(),
            fn (string $type) => $type !== OperationCase::TYPE_CANCELLED
        ));
    }
}
