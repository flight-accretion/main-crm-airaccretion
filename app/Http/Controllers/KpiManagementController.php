<?php

namespace App\Http\Controllers;

use App\Models\KpiManualValue;
use App\Models\KpiMetric;
use App\Models\KpiTemplate;
use App\Models\KpiUserAssignment;
use App\Models\KpiUserNonWorkingDay;
use App\Models\KpiWorkingDay;
use App\Models\User;
use Illuminate\Http\Request;

class KpiManagementController extends Controller
{
    public function index()
    {
        return view('admin.pages.kpi.manage', [
            'templates' => KpiTemplate::with('metrics')
                ->orderBy('department')
                ->orderBy('name')
                ->get(),
            'users' => User::with('userType')
                ->where('status', 1)
                ->orderBy('name')
                ->get(),
            'assignments' => KpiUserAssignment::with(['user', 'template'])
                ->where('active', true)
                ->get(),
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

        KpiWorkingDay::updateOrCreate(
            [
                'work_date' => $data['work_date'],
            ],
            $data + [
                'updated_by' => $request->user()->id,
            ]
        );

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

        KpiUserNonWorkingDay::updateOrCreate(
            [
                'user_id' => $data['user_id'],
                'work_date' => $data['work_date'],
            ],
            $data + [
                'approved_by' => $request->user()->id,
            ]
        );

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
