<?php

namespace App\Http\Controllers;

use App\Models\OperationCase;
use App\Models\OperationCaseActivity;
use App\Models\User;
use App\Models\UserType;
use App\Services\Operations\OperationCaseService;
use Illuminate\Http\Request;

class OperationsDashboardController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $open = OperationCase::query()->whereNull('completed_at');
        $activity = OperationCaseActivity::with(['operationCase.lead.client', 'user']);

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
            'reviewCount' => (clone $open)->where('type', 'review')->count(),
            'rescheduleCount' => (clone $open)->where('type', 'reschedule')->count(),
            'refundCount' => (clone $open)->where('type', 'refund')->count(),
            'cancelledCount' => (clone $open)->where('type', 'cancelled')->count(),
            'recentActivity' => $activity
                ->latest()
                ->paginate($filters['per_page'])
                ->withQueryString(),
            'filters' => $filters,
            'operationUsers' => $this->operationUsers(),
            'caseTypes' => OperationCase::validTypes(),
            'caseStatuses' => $this->caseStatuses(),
        ]);
    }

    public function queue(Request $request, string $type)
    {
        abort_unless(in_array($type, OperationCase::validTypes(), true), 404);

        $filters = $this->filters($request);
        $cases = OperationCase::with(['lead.client', 'lead.representative', 'assignee'])
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

        return view('admin.pages.operations.queue', [
            'cases' => $cases,
            'type' => $type,
            'filters' => $filters,
            'operationUsers' => $this->operationUsers(),
            'caseStatuses' => $this->caseStatuses(),
        ]);
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
        $cases = OperationCase::with(['lead.client', 'lead.representative', 'assignee'])
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

        return view('admin.pages.operations.history', [
            'cases' => $cases,
            'filters' => $filters,
            'operationUsers' => $this->operationUsers(),
            'caseTypes' => OperationCase::validTypes(),
        ]);
    }

    private function filters(Request $request): array
    {
        $data = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'type' => 'nullable|in:' . implode(',', OperationCase::validTypes()),
            'status' => 'nullable|in:' . implode(',', $this->caseStatuses()),
            'operations_user_id' => 'nullable|uuid',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|in:10,25,50,100',
        ]);

        return [
            'from_date' => $data['from_date'] ?? null,
            'to_date' => $data['to_date'] ?? null,
            'type' => $data['type'] ?? null,
            'status' => $data['status'] ?? null,
            'operations_user_id' => $data['operations_user_id'] ?? null,
            'search' => $data['search'] ?? null,
            'per_page' => (int) ($data['per_page'] ?? 25),
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
                            ->orWhere('contact_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('lead.representative', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }
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
}
