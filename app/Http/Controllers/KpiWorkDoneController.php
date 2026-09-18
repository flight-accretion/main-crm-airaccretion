<?php

namespace App\Http\Controllers;

use App\Services\Kpi\KpiWorkDoneService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class KpiWorkDoneController extends Controller
{
    public function index(
        Request $request,
        KpiWorkDoneService $service
    ) {
        $filters = $this->filters($request);

        $result = $service->dashboard(
            $request->user(),
            $filters
        );

        return view('admin.pages.kpi.work-done', [
            'summary' => $result['summary'],
            'users' => $result['users'],
            'selectedUserId' => $result['selected_user_id'],
            'filters' => $filters,
        ]);
    }

    public function details(
        Request $request,
        KpiWorkDoneService $service
    ) {
        $data = $request->validate([
            'type' => 'required|in:completed,active,cancelled,total,outreach_completed,outreach_dnp',
            'date_type' => 'nullable|in:today,yesterday,custom',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'user_id' => 'nullable|uuid',
        ]);

        $filters = $this->filters($request);

        $result = $service->details(
            $request->user(),
            $data['type'],
            $filters
        );

        return response()->json([
            'success' => true,
            'title' => $result['title'],
            'html' => view(
                'admin.pages.kpi.partials.work-done-table',
                [
                    'rows' => $result['rows'],
                    'type' => $data['type'],
                ]
            )->render(),
        ]);
    }

    private function filters(Request $request): array
    {
        $dateType = $request->input('date_type', 'today');

        if (!in_array($dateType, ['today', 'yesterday', 'custom'], true)) {
            $dateType = 'today';
        }

        if ($dateType === 'yesterday') {
            $from = Carbon::yesterday()->startOfDay();
            $to = Carbon::yesterday()->endOfDay();
        } elseif ($dateType === 'custom') {
            $request->validate([
                'from_date' => 'required|date',
                'to_date' => 'required|date|after_or_equal:from_date',
            ]);

            $from = Carbon::parse($request->from_date)->startOfDay();
            $to = Carbon::parse($request->to_date)->endOfDay();
        } else {
            $from = Carbon::today()->startOfDay();
            $to = Carbon::today()->endOfDay();
        }

        return [
            'date_type' => $dateType,
            'from' => $from,
            'to' => $to,
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'user_id' => $request->input('user_id'),
        ];
    }
}