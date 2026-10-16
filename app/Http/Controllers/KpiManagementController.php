<?php

namespace App\Http\Controllers;

use App\Models\KpiManualValue;
use App\Models\KpiMetric;
use App\Models\KpiTeamMembership;
use App\Models\KpiTemplate;
use App\Models\KpiUserAssignment;
use App\Models\KpiUserNonWorkingDay;
use App\Models\KpiWorkingDay;
use App\Models\User;
use App\Models\UserType;
use App\Services\Kpi\KpiTemplateAutomationService;
use App\Services\Attendance\AttendanceCalendarService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class KpiManagementController extends Controller
{
    public function index(KpiTemplateAutomationService $automation)
    {
        $templates = KpiTemplate::with('metrics')->orderBy('department')->orderBy('name')->get();
        $users = User::with('userType')->where('status', 1)->orderBy('name')->get();
        $assignments = KpiUserAssignment::with(['user', 'template'])->where('active', true)->get();
        $definitions = $automation->definitions();
        $coverage = [];
        foreach ($definitions as $department => &$definition) {
            $template = $templates->where('department', $department)->where('active', true)->sortByDesc('updated_at')->first();
            if ($template) {
                $definition['metrics'] = $template->metrics->where('active', true)->sortBy('sort_order')->map(function ($metric) {
                    return $metric->toArray() + ['score_labels' => collect($metric->score_rules)->map(fn ($value) => (string) $value)->all()];
                })->values()->all();
                $definition['updated_at'] = $template->updated_at;
            }
            $roles = match ($department) {
                'sales' => UserType::SALES_ROLES, 'operations' => UserType::OPERATIONS_ROLES,
                'accounts' => UserType::ACCOUNTS_ROLES, default => [],
            };
            $eligibleIds = $users->filter(fn ($user) => in_array(optional($user->userType)->user_type, $roles, true))->pluck('id');
            $assigned = $template ? $assignments->where('template_id', $template->id)->whereIn('user_id', $eligibleIds)
                ->filter(fn ($assignment) => $assignment->effective_from && $assignment->effective_from->lte(now()->endOfDay())
                    && (!$assignment->effective_to || $assignment->effective_to->gte(now()->startOfDay())))
                ->pluck('user_id')->unique()->count() : 0;
            $coverage[$department] = ['eligible_users' => $eligibleIds->count(), 'assigned_users' => $assigned];
        }
        unset($definition);
        return view('admin.pages.kpi.manage', [
            'templates' => $templates,
            'users' => $users,
            'assignments' => $assignments,
            'teamMemberships' => KpiTeamMembership::with([
                'manager.userType',
                'member.userType',
            ])
                ->where('active', true)
                ->orderBy('department')
                ->get(),
            'defaultKpiDefinitions' => $definitions,
            'defaultKpiCoverage' => $coverage,
        ]);
    }

    public function storeTemplate(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'department' => 'required|in:sales,operations,accounts',
            'working_days_per_month' => 'required|integer|min:1|max:31',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);

        KpiTemplate::create($data + [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
            'active' => true,
        ]);

        return back()->with('success', 'KPI template created.');
    }

    public function updateTemplate(
        Request $request,
        KpiTemplate $template
    ) {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'department' => 'required|in:sales,operations,accounts',
            'working_days_per_month' => 'required|integer|min:1|max:31',
            'active' => 'required|boolean',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);

        $template->update($data + [
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'KPI template updated.');
    }

    public function storeMetric(Request $request)
    {
        KpiMetric::create($this->metricData($request));

        return back()->with('success', 'KPI metric created.');
    }

    public function updateMetric(
        Request $request,
        KpiMetric $metric
    ) {
        $metric->update($this->metricData($request));

        return back()->with('success', 'KPI metric updated.');
    }

    public function assignUser(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'template_id' => 'required|uuid|exists:kpi_templates,id',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);

        KpiUserAssignment::where('user_id', $data['user_id'])
            ->where('active', true)
            ->update([
                'active' => false,
            ]);

        KpiUserAssignment::create($data + [
            'active' => true,
            'assigned_by' => $request->user()->id,
        ]);

        return back()->with('success', 'KPI template assigned.');
    }

    public function saveWorkingDay(Request $request)
    {
        $data = $request->validate([
            'work_date' => 'required|date',
            'is_working_day' => 'required|boolean',
            'note' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($data, $request) {
            $users = User::orderBy('id')->lockForUpdate()->get(['id']);
            KpiWorkingDay::updateOrCreate(
                ['work_date' => $data['work_date']],
                $data + ['updated_by' => $request->user()->id]
            );

            $calendar = new AttendanceCalendarService();
            foreach ($users as $user) $calendar->assertApprovedSwapsCompatible($user);
        });

        return back()->with('success', 'Working calendar updated.');
    }

    public function saveUserNonWorkingDay(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'work_date' => 'required|date',
            'reason_type' => 'required|in:weekly_off,approved_leave,approved_off',
            'note' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($data, $request) {
            $user = User::whereKey($data['user_id'])->lockForUpdate()->firstOrFail();
            KpiUserNonWorkingDay::updateOrCreate(
                ['user_id' => $data['user_id'], 'work_date' => $data['work_date']],
                $data + ['approved_by' => $request->user()->id]
            );

            (new AttendanceCalendarService())->assertApprovedSwapsCompatible($user);
        });

        return back()->with('success', 'Employee non-working day saved.');
    }

    public function saveManualValue(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'metric_id' => 'required|uuid|exists:kpi_metrics,id',
            'year' => 'required|integer|min:2025|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'value' => 'nullable|numeric',
            'rating' => 'nullable|integer|min:1|max:5',
            'note' => 'nullable|string|max:2000',
        ]);

        KpiManualValue::updateOrCreate(
            [
                'user_id' => $data['user_id'],
                'metric_id' => $data['metric_id'],
                'year' => $data['year'],
                'month' => $data['month'],
            ],
            $data + [
                'entered_by' => $request->user()->id,
            ]
        );

        return back()->with('success', 'Manual KPI value saved.');
    }

    public function syncDefaultTemplate(
        Request $request,
        KpiTemplateAutomationService $automation
    ) {
        $data = $request->validate([
            'department' => 'required|in:sales,accounts,operations',
        ]);

        if (!$automation->definition($data['department'])) {
            throw ValidationException::withMessages([
                'department' => 'Default KPI automation is not configured for this department yet.',
            ]);
        }

        $result = $automation->syncDepartment(
            $data['department'],
            $request->user()->id
        );

        return back()->with(
            'success',
            sprintf(
                '%s synced: %d metrics updated and %d unassigned users assigned.',
                $result['template_name'],
                $result['metrics_synced'],
                $result['users_assigned']
            )
        );
    }

    public function saveTeamMembership(Request $request)
    {
        $data = $request->validate([
            'department' => 'required|in:accounts,operations',
            'manager_user_id' => 'required|uuid|exists:users,id',
            'member_user_id' => 'required|uuid|exists:users,id|different:manager_user_id',
        ]);

        $manager = User::with('userType')
            ->findOrFail($data['manager_user_id']);
        $member = User::with('userType')
            ->findOrFail($data['member_user_id']);

        $managerRoles = $data['department'] === 'accounts'
            ? [UserType::SENIOR_ACCOUNTS_MANAGER, UserType::ACCOUNTS_MANAGER]
            : [UserType::SENIOR_OPERATIONS_MANAGER, UserType::OPERATIONS_MANAGER];

        $memberRoles = $data['department'] === 'accounts'
            ? UserType::ACCOUNTS_ROLES
            : UserType::OPERATIONS_ROLES;

        if (!in_array($manager->userType->user_type ?? '', $managerRoles, true)) {
            throw ValidationException::withMessages([
                'manager_user_id' => 'Selected manager does not belong to the selected department manager roles.',
            ]);
        }

        if (!in_array($member->userType->user_type ?? '', $memberRoles, true)) {
            throw ValidationException::withMessages([
                'member_user_id' => 'Selected member does not belong to the selected department.',
            ]);
        }

        KpiTeamMembership::updateOrCreate(
            [
                'department' => $data['department'],
                'manager_user_id' => $data['manager_user_id'],
                'member_user_id' => $data['member_user_id'],
            ],
            [
                'active' => true,
                'created_by' => $request->user()->id,
            ]
        );

        return back()->with('success', 'KPI team membership saved.');
    }

    public function deleteTeamMembership(KpiTeamMembership $membership)
    {
        $membership->update(['active' => false]);

        return back()->with('success', 'KPI team membership removed.');
    }

    private function metricData(Request $request): array
    {
        $data = $request->validate([
            'template_id' => 'required|uuid|exists:kpi_templates,id',
            'code' => 'required|string|max:100',
            'name' => 'required|string|max:180',
            'description' => 'nullable|string|max:2000',
            'weightage' => 'required|numeric|min:0|max:100',
            'measurement_type' => 'required|in:automatic,manual_numeric,manual_rating',
            'source_key' => 'nullable|string|max:100',
            'target_value' => 'nullable|numeric',
            'direction' => 'required|in:higher_better,lower_better',
            'score_5' => 'required|numeric',
            'score_4' => 'required|numeric',
            'score_3' => 'required|numeric',
            'score_2' => 'required|numeric',
            'score_1' => 'required|numeric',
            'sort_order' => 'nullable|integer|min:0|max:999',
            'active' => 'nullable|boolean',
        ]);

        $data['score_rules'] = [
            '5' => (float) $request->score_5,
            '4' => (float) $request->score_4,
            '3' => (float) $request->score_3,
            '2' => (float) $request->score_2,
            '1' => (float) $request->score_1,
        ];

        unset(
            $data['score_5'],
            $data['score_4'],
            $data['score_3'],
            $data['score_2'],
            $data['score_1']
        );

        $data['active'] = $request->boolean('active', true);

        return $data;
    }
}
