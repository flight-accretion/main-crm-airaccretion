<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Services\Attendance\AttendanceCorrectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceCorrectionController extends Controller
{
    public function index(Request $request, AttendanceCorrectionService $service)
    {
        $query = AttendanceRecord::with('user')->orderByDesc('attendance_date');
        if ($request->filled('date')) $query->whereDate('attendance_date', $request->date);
        if ($request->filled('user_id')) $query->where('user_id', $request->user_id);
        return view('admin.pages.attendance.corrections', [
            'records' => $query->paginate(25)->withQueryString(),
            'corrections' => DB::table('attendance_corrections')->orderByDesc('created_at')->paginate(25, ['*'], 'history_page')->withQueryString(),
            'events' => DB::table('attendance_correction_events')->orderByDesc('created_at')->limit(100)->get(),
            'reviewIds' => DB::table('attendance_payroll_reviews')->pluck('correction_id')->all(),
            'periods' => DB::table('attendance_payroll_periods')->where('closed', true)->orderByDesc('month')->get(),
            'service' => $service,
        ]);
    }

    public function store(Request $request, AttendanceRecord $record, AttendanceCorrectionService $service)
    {
        $data = $request->validate(['reason' => 'required|string|max:2000', 'version' => 'required|string|size:64',
            'user_id' => 'prohibited', 'paycode' => 'prohibited', 'attendance_date' => 'prohibited', 'source_import_id' => 'prohibited',
            'raw_status' => 'required|string|max:20', 'in_time' => 'nullable|date_format:H:i',
            'out_time' => 'nullable|date_format:H:i']);
        $changes = array_intersect_key($data, array_flip(['raw_status', 'in_time', 'out_time']));
        foreach (['in_time' => 'raw_in', 'out_time' => 'raw_out'] as $field => $raw) {
            if (array_key_exists($field, $changes)) {
                $changes[$raw] = $changes[$field];
                $changes[$field] = $changes[$field] ? \Carbon\Carbon::parse($changes[$field])->format('H:i:s') : null;
            }
        }
        $correction = $service->correct($record->id, $changes, $data['reason'], $data['version'], $request->user());
        return $request->expectsJson() ? response()->json($correction) : back()->with('success', 'Attendance correction '.$correction->status.'.');
    }

    public function decide(Request $request, string $correction, AttendanceCorrectionService $service)
    {
        $data = $request->validate(['decision' => 'required|in:approve,reject', 'reason' => 'required|string|max:2000']);
        $result = $service->decide($correction, $data['decision'] === 'approve', $data['reason'], $request->user());
        return $request->expectsJson() ? response()->json($result) : back()->with('success', 'Attendance decision recorded.');
    }

    public function reverse(Request $request, string $correction, AttendanceCorrectionService $service)
    {
        $data = $request->validate(['reason' => 'required|string|max:2000']);
        $result = $service->reverse($correction, $data['reason'], $request->user());
        return $request->expectsJson() ? response()->json($result) : back()->with('success', 'Reversal '.$result->status.'.');
    }

    public function close(Request $request, AttendanceCorrectionService $service)
    {
        $data = $request->validate(['month' => 'required|date_format:Y-m', 'reason' => 'required|string|max:2000']);
        $service->closePeriod($data['month'].'-01', $data['reason'], $request->user());
        return $request->expectsJson() ? response()->json(['closed' => true]) : back()->with('success', 'Payroll attendance period closed.');
    }
}
