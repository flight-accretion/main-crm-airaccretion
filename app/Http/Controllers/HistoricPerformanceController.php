<?php

namespace App\Http\Controllers;

use App\Exports\HistoricPerformanceSheet;
use App\Exports\HistoricPerformanceWorkbook;
use App\Models\User;
use App\Models\UserType;
use App\Services\Kpi\HistoricPerformanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as Format;

class HistoricPerformanceController extends Controller
{
    private function filters(Request $request): array
    {
        abort_unless(in_array($request->user()?->userType?->user_type, [UserType::SUPER_ADMIN, UserType::HR], true), 403);
        $data = $request->validate([
            'department'=>'nullable|in:sales,operations,accounts', 'employee_id'=>'nullable|uuid',
            'from'=>'nullable|date_format:Y-m', 'to'=>'nullable|date_format:Y-m',
            'format'=>'nullable|in:xlsx,csv','dataset'=>'nullable|in:both,kpi,target', 'scope'=>'nullable|in:selected,all',
        ]);
        $from = Carbon::createFromFormat('!Y-m', $data['from'] ?? now()->startOfMonth()->subMonths(11)->format('Y-m'));
        $to = Carbon::createFromFormat('!Y-m', $data['to'] ?? now()->format('Y-m'));
        if ($from->gt($to) || $to->gt(now()->startOfMonth()) || $from->diffInMonths($to) > 23) {
            throw ValidationException::withMessages(['from'=>'Choose up to 24 months, ending no later than the current month.']);
        }
        $roles = match ($data['department'] ?? '') {
            'sales'=>UserType::SALES_ROLES,'operations'=>UserType::OPERATIONS_ROLES,'accounts'=>UserType::ACCOUNTS_ROLES,
            default=>array_merge(UserType::SALES_ROLES,UserType::OPERATIONS_ROLES,UserType::ACCOUNTS_ROLES),
        };
        // Keep former employees available when reviewing historic performance.
        $employees = User::with('userType')->whereHas('userType', fn ($q) => $q->whereIn('user_type', $roles))->orderBy('name')->get();
        $selected = isset($data['employee_id']) ? $employees->firstWhere('id',$data['employee_id']) : $employees->first();
        if (isset($data['employee_id']) && !$selected) throw ValidationException::withMessages(['employee_id'=>'Select an employee in this department.']);
        return [$data,$from,$to,$employees,$selected];
    }

    public function index(Request $request)
    {
        [$filters,$from,$to,$employees,$selectedEmployee]=$this->filters($request);
        $rows=app(HistoricPerformanceService::class)->rows(collect($selectedEmployee ? [$selectedEmployee] : []),$from,$to);
        return view('admin.pages.kpi.history',compact('filters','from','to','employees','selectedEmployee','rows'));
    }

    public function download(Request $request)
    {
        [$filters,$from,$to,$employees,$selected]=$this->filters($request);
        $format=$filters['format'] ?? 'xlsx'; $dataset=$filters['dataset'] ?? 'both';
        if ($format==='csv' && $dataset==='both') throw ValidationException::withMessages(['dataset'=>'Select KPI history or Target history for CSV.']);
        $people=($filters['scope'] ?? 'selected')==='all' ? $employees : collect($selected ? [$selected] : []);
        $rows=collect();
        foreach ($people->chunk(25) as $chunk) $rows=$rows->concat(app(HistoricPerformanceService::class)->rows($chunk,$from,$to));
        $datasets=$dataset==='both' ? ['kpi','target'] : [$dataset];
        $export=$format==='xlsx' ? new HistoricPerformanceWorkbook($rows,$datasets) : new HistoricPerformanceSheet($rows,$dataset);
        $filename='historic-performance-'.$from->format('Y-m').'-'.$to->format('Y-m').'.'.$format;
        $content=Excel::raw($export,$format==='xlsx' ? Format::XLSX : Format::CSV);
        return response($content,200,[
            'Content-Type'=>$format==='xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=UTF-8',
            'Content-Disposition'=>'attachment; filename="'.$filename.'"',
            'Cache-Control'=>'private, no-store',
        ]);
    }
}
