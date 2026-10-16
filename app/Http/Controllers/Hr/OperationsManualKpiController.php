<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\HrOperationsManualKpiSubmission;
use App\Models\KpiManualValue;
use App\Models\KpiMetric;
use App\Models\KpiUserAssignment;
use Carbon\Carbon;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationsManualKpiController extends Controller
{
    private const ALLOWED_CODES = [
        'operations_alternate_options',
        'operations_ivr_software',
        'operations_training',
        'operations_task_completion',
    ];

    public function index(Request $request)
    {
        $canReview = $this->authorizeActor($request);
        $request->validate([
            'year' => 'nullable|integer|min:2025|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
            'history_year' => 'nullable|integer|min:2025|max:2100',
            'history_month' => 'nullable|integer|min:1|max:12',
            'history_user_id' => 'nullable|uuid',
        ]);
        $periodYear = (int) $request->query('year', now()->year);
        $periodMonth = (int) $request->query('month', now()->month);
        $metrics = $this->manualMetrics();
        $submissions = $this->submissions();
        if (!$canReview) {
            $submissions->where('user_id', $request->user()->id);
            $templateId = $this->assignedTemplate($request->user()->id, $periodYear, $periodMonth);
            $metrics = $metrics->where('template_id', $templateId)->values();
        }
        $history = clone $submissions;
        foreach (['history_year' => 'year', 'history_month' => 'month', 'history_user_id' => 'user_id'] as $filter => $column) {
            if ($request->filled($filter)) $history->where($column, $request->query($filter));
        }

        return view('admin.pages.hr.operations-manual-kpi', [
            'canReview' => $canReview,
            'users' => $canReview ? $this->operationUsers() : collect([$request->user()]),
            'metrics' => $metrics,
            'year' => $periodYear,
            'month' => $periodMonth,
            'pendingSubmissions' => (clone $submissions)
                ->where('status', HrOperationsManualKpiSubmission::STATUS_PENDING)
                ->latest('submitted_at')
                ->get(),
            'historySubmissions' => $history
                ->whereIn('status', [
                    HrOperationsManualKpiSubmission::STATUS_APPROVED,
                    HrOperationsManualKpiSubmission::STATUS_REJECTED,
                    HrOperationsManualKpiSubmission::STATUS_DRAFT,
                ])
                ->latest('updated_at')
                ->limit(75)
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $canReview = $this->authorizeActor($request);
        abort_if(!$canReview && (string) $request->input('user_id') !== (string) $request->user()->id, 403);
        $data = $this->validatedSubmission($request);
        if (!$canReview) {
            $metric = $this->approvedMetric($data['metric_id']);
            abort_unless($metric->active && $metric->template->active
                && $metric->template_id === $this->assignedTemplate($data['user_id'], $data['year'], $data['month']), 403);
            if ($metric->measurement_type === 'manual_rating' && ($data['value'] < 1 || $data['value'] > 5)) {
                throw ValidationException::withMessages(['value' => 'Enter a rating between 1 and 5.']);
            }
        }
        $status = $request->input('action') === 'draft'
            ? HrOperationsManualKpiSubmission::STATUS_DRAFT
            : HrOperationsManualKpiSubmission::STATUS_PENDING;

        HrOperationsManualKpiSubmission::create($data + [
            'status' => $status,
            'submitted_by' => $request->user()->id,
            'submitted_at' => $status === HrOperationsManualKpiSubmission::STATUS_PENDING
                ? now()
                : null,
        ]);

        return back()->with(
            'success',
            $status === HrOperationsManualKpiSubmission::STATUS_DRAFT
                ? 'Operations manual KPI draft saved.'
                : 'Operations manual KPI submitted for approval.'
        );
    }

    public function approve(
        Request $request,
        HrOperationsManualKpiSubmission $submission
    ) {
        abort_unless($this->authorizeActor($request), 403);
        abort_unless(
            $submission->status === HrOperationsManualKpiSubmission::STATUS_PENDING,
            422,
            'Only pending manual KPI submissions can be approved.'
        );

        $request->validate([
            'review_note' => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($request, $submission) {
            $submission = HrOperationsManualKpiSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            abort_unless($submission->status === HrOperationsManualKpiSubmission::STATUS_PENDING, 422);
            $metric = $this->approvedMetric($submission->metric_id);

            $manualValue = KpiManualValue::updateOrCreate(
                [
                    'user_id' => $submission->user_id,
                    'metric_id' => $metric->id,
                    'year' => $submission->year,
                    'month' => $submission->month,
                ],
                [
                    'value' => $metric->measurement_type === 'manual_numeric'
                        ? $submission->value
                        : null,
                    'rating' => $metric->measurement_type === 'manual_rating'
                        ? min(5, max(1, (int) round($submission->value)))
                        : null,
                    'note' => $submission->note,
                    'entered_by' => $request->user()->id,
                ]
            );

            $submission->update([
                'status' => HrOperationsManualKpiSubmission::STATUS_APPROVED,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'review_note' => $request->input('review_note'),
                'manual_value_id' => $manualValue->id,
            ]);
        });

        return back()->with('success', 'Operations manual KPI approved and applied.');
    }

    public function reject(
        Request $request,
        HrOperationsManualKpiSubmission $submission
    ) {
        abort_unless($this->authorizeActor($request), 403);
        abort_unless(
            $submission->status === HrOperationsManualKpiSubmission::STATUS_PENDING,
            422,
            'Only pending manual KPI submissions can be rejected.'
        );

        $data = $request->validate([
            'review_note' => 'required|string|max:2000',
        ]);

        DB::transaction(function () use ($request, $submission, $data) {
            $submission = HrOperationsManualKpiSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            abort_unless($submission->status === HrOperationsManualKpiSubmission::STATUS_PENDING, 422);
            $submission->update([
                'status' => HrOperationsManualKpiSubmission::STATUS_REJECTED,
                'rejected_by' => $request->user()->id,
                'rejected_at' => now(),
                'review_note' => $data['review_note'],
            ]);
        });

        return back()->with('success', 'Operations manual KPI rejected.');
    }

    private function validatedSubmission(Request $request): array
    {
        $data = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'metric_id' => 'required|uuid|exists:kpi_metrics,id',
            'year' => 'required|integer|min:2025|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'value' => 'required|numeric|min:0|max:100',
            'note' => 'nullable|string|max:2000',
        ]);

        $this->assertOperationsUser($data['user_id']);
        $this->approvedMetric($data['metric_id']);

        return $data;
    }

    private function assertOperationsUser(string $userId): void
    {
        $isOperationsUser = User::query()
            ->whereKey($userId)
            ->whereHas('userType', function ($query) {
                $query->whereIn('user_type', UserType::OPERATIONS_ROLES);
            })
            ->exists();

        if (!$isOperationsUser) {
            throw ValidationException::withMessages([
                'user_id' => 'Selected employee must belong to Operations.',
            ]);
        }
    }

    private function approvedMetric(string $metricId): KpiMetric
    {
        $metric = KpiMetric::query()
            ->with('template')
            ->findOrFail($metricId);

        if (
            !$metric->template
            || $metric->template->department !== 'operations'
            || !in_array($metric->code, self::ALLOWED_CODES, true)
            || !in_array($metric->measurement_type, ['manual_numeric', 'manual_rating'], true)
        ) {
            throw ValidationException::withMessages([
                'metric_id' => 'Selected KPI is not an approved Operations manual KPI.',
            ]);
        }

        return $metric;
    }

    private function operationUsers()
    {
        return User::with('userType')
            ->where('status', 1)
            ->whereHas('userType', function ($query) {
                $query->whereIn('user_type', UserType::OPERATIONS_ROLES);
            })
            ->orderBy('name')
            ->get();
    }

    private function manualMetrics()
    {
        return KpiMetric::query()
            ->with('template')
            ->whereIn('code', self::ALLOWED_CODES)
            ->whereIn('measurement_type', ['manual_numeric', 'manual_rating'])
            ->where('active', true)
            ->whereHas('template', function ($query) {
                $query
                    ->where('department', 'operations')
                    ->where('active', true);
            })
            ->orderBy('sort_order')
            ->get();
    }

    private function submissions()
    {
        return HrOperationsManualKpiSubmission::with([
            'user.userType',
            'metric',
            'submitter',
            'approver',
            'rejecter',
        ]);
    }

    private function authorizeActor(Request $request): bool
    {
        $user = $request->user();
        $role = $user?->userType?->user_type;
        $canReview = in_array($role, [UserType::HR, UserType::SUPER_ADMIN], true);
        abort_unless($user && (int) $user->status === 1
            && ($canReview || in_array($role, UserType::OPERATIONS_ROLES, true)), 403);
        return $canReview;
    }

    private function assignedTemplate(string $userId, int $year, int $month): ?string
    {
        $date = Carbon::create($year, $month, 1)->endOfMonth();
        if ($date->isFuture()) $date = $date->isSameMonth(now()) ? now() : $date->startOfMonth();
        return KpiUserAssignment::where('user_id', $userId)->where('active', true)
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date->toDateString());
            })->orderByDesc('effective_from')->value('template_id');
    }
}
