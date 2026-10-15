<?php

namespace App\Http\Controllers;

use App\Models\Target;
use App\Models\User;
use App\Models\UserType;
use App\Models\Product;
use App\Services\TargetAccessService;
use App\Services\TargetIncentiveService;
use App\Exports\TargetIncentiveExport;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TargetController extends Controller
{
    private TargetIncentiveService $incentives;

    public function __construct(TargetIncentiveService $incentives)
    {
        $this->incentives = $incentives;
    }
    /**
     * Display a listing of targets
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $userType = $user->user_type_id;

        // Get filter parameters
        $year = $request->get('year');
        $month = $request->get('month');
        $salesExecutiveId = $request->get('sales_executive_id');

        // Get assignable staff (single dropdown: includes Sales Managers and Sales Executives as per role)
        $assignableStaff = $this->getAssignableStaff($user);
        if ($request->ajax()) {
            return $this->getTargetsData($request);
        }

        // Build query for targets
        $targetsQuery = Target::with(['salesExecutive', 'assignedBy'])
            ->whereIn('sales_executive_id', $assignableStaff->pluck('id'));

        // If no filters applied, show last 3 months by default
        if (!$year && !$month && !$salesExecutiveId) {
            $currentDate = now();
            $threeMonthsAgo = $currentDate->copy()->subMonths(2)->startOfMonth();

            // Get all year-month combinations for the last 3 months
            $validPeriods = [];
            for ($i = 0; $i < 3; $i++) {
                $date = $currentDate->copy()->subMonths($i);
                $validPeriods[] = ['year' => $date->year, 'month' => $date->month];
            }

            $targetsQuery->where(function ($query) use ($validPeriods) {
                foreach ($validPeriods as $period) {
                    $query->orWhere(function ($q) use ($period) {
                        $q->where('year', $period['year'])
                            ->where('month', $period['month']);
                    });
                }
            });
        } else {
            // Apply filters if provided
            if ($year) {
                $targetsQuery->where('year', $year);
            }
            if ($month) {
                $targetsQuery->where('month', $month);
            }
            if ($salesExecutiveId) {
                $targetsQuery->where('sales_executive_id', $salesExecutiveId);
            }
        }

        $targets = $targetsQuery->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Update achieved amounts for all targets to ensure real-time accuracy
        foreach ($targets as $target) {
            $target->updateAchievedAmount();
        }

        $incentiveRows = $targets->mapWithKeys(fn ($target) => [$target->id => $this->incentives->forTarget($target)]);
        $products = Product::orderBy('product')->get(['id', 'product']);
        $access = new TargetAccessService();
        $canConfigure = $access->canConfigure($user);
        $canAssign = $access->canAssign($user);
        $unassignedStaff = $assignableStaff->whereNotIn('id', $targets->pluck('sales_executive_id'))
            ->filter(fn ($staff) => !$salesExecutiveId || $staff->id === $salesExecutiveId);
        return view('admin.targets.index', compact('year', 'month', 'salesExecutiveId', 'assignableStaff', 'targets', 'incentiveRows', 'products', 'canConfigure', 'canAssign', 'unassignedStaff'));
    }

    /**
     * Show the form for creating a new target
     */
    public function create(Request $request)
    {
        $user = Auth::user();
        $userType = $user->user_type_id;
        
        $assignableStaff = $this->getAssignableStaff($user);
        $year = $request->get('year', date('Y'));
        $month = $request->get('month', date('n'));

        return redirect()->route('admin.targets.index', [
            'year' => $year,
            'month' => $month,
        ]);
    }

    /**
     * Store a newly created target
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $userType = $user->user_type_id;
        $validator = Validator::make($request->all(), [
            'sales_executive_id' => 'required|exists:users,id',
            'year' => 'required|integer|min:2020|max:2050',
            'month' => 'required|integer|min:1|max:12',
            'target_amount' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:active,inactive',
        ], [
            'sales_executive_id.required' => 'The sales executive field is required.',
            'sales_executive_id.exists' => 'The selected sales executive is invalid.',
            'year.required' => 'The year field is required.',
            'month.required' => 'The month field is required.',
            'target_amount.required' => 'The target amount field is required.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator, 'add')
                ->withInput()
                ->with('error', 'Please correct the errors below.');
        }

        $this->abortUnlessCanAssignTargetTo($request->sales_executive_id);

        $incentiveSettings = $this->incentiveSettings($request);

        // Check if target already exists for this user, year, and month
        $existingTarget = Target::where('sales_executive_id', $request->sales_executive_id)
            ->where('year', $request->year)
            ->where('month', $request->month)
            ->first();

        if ($existingTarget) {
            return back()->withErrors(['error' => 'Target already exists for this sales executive for the selected month and year.'])->withInput();
        }

        // Verify the selected user (sales executive or manager) is assignable by current user
        $assignableStaff = $this->getAssignableStaff($user);
        if (!$assignableStaff->contains('id', $request->sales_executive_id)) {
            abort(403, 'You can only assign targets to staff within your allowed scope.');
        }

        DB::beginTransaction();
        try {
            $target = new Target();
            $target->id = (string) Str::uuid();
            $target->sales_executive_id = $request->sales_executive_id;
            $target->assigned_by = $user->id;
            $target->year = $request->year;
            $target->month = $request->month;
            $target->target_amount = $request->target_amount;
            $target->description = $request->description;
            $target->status = $request->status ?? 'active';
            $target->fill($incentiveSettings);

            $target->save();

            DB::commit();

            return redirect()->route('admin.targets.index')->with('success', 'Target assigned successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Target creation failed: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Failed to assign target: ' . $e->getMessage());
        }
    }



    /**
     * Show the form for editing the specified target
     */
    public function show(Request $request, $id)
    {
        $target = Target::with(['salesExecutive', 'assignedBy'])->findOrFail($id);
        $assignableStaff = $this->getAssignableStaff(Auth::user());

        if (!$assignableStaff->contains('id', $target->sales_executive_id)) {
            abort(403, 'Unauthorized action.');
        }

        if ($request->ajax()) {
            return response()->json(['target' => $target]);
        }

        return redirect()->route('admin.targets.index', [
            'year' => $target->year,
            'month' => $target->month,
        ]);
    }

    public function edit(Request $request, $id)
    {
        $this->abortUnlessCanConfigure();
        $target = Target::with(['salesExecutive', 'assignedBy'])->findOrFail($id);
        
        $user = Auth::user();
        $userType = $user->user_type_id;
        
        $assignableStaff = $this->getAssignableStaff($user);

        if (!$assignableStaff->contains('id', $target->sales_executive_id)) {
            abort(403, 'Unauthorized action.');
        }

        $assignable = $assignableStaff->map(function($s) {
            return [
                'id' => $s->id,
                'name' => $s->name,
                'user_type_id' => $s->user_type_id ?? null,
                'user_type' => optional($s->userType)->user_type ?? null,
            ];
        })->values();

        return response()->json([
            'target' => $target,
            'assignableStaff' => $assignable
        ]);
    }

    /**
     * Update the specified target
     */
    public function update(Request $request, $id)
    {
        $this->abortUnlessCanConfigure();
        $target = Target::findOrFail($id);
        
        $user = Auth::user();
        $userType = $user->user_type_id;
        
        $assignableStaff = $this->getAssignableStaff($user);

        if (!$assignableStaff->contains('id', $target->sales_executive_id)) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'sales_executive_id' => 'required|exists:users,id',
            'year' => 'required|integer|min:2020|max:2050',
            'month' => 'required|integer|min:1|max:12',
            'target_amount' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:active,inactive',
        ]);

        $this->abortUnlessCanAssignTargetTo($request->sales_executive_id);

        if (Target::where('sales_executive_id', $request->sales_executive_id)->where('year', $request->year)
            ->where('month', $request->month)->where('id', '!=', $target->id)->exists()) {
            throw ValidationException::withMessages(['sales_executive_id' => 'A target already exists for this employee and period.']);
        }
        $target->update(array_merge([
            'sales_executive_id' => $request->sales_executive_id,
            'year' => $request->year,
            'month' => $request->month,
            'target_amount' => $request->target_amount,
            'description' => $request->description,
            'status' => $request->status,
        ], $this->incentiveSettings($request)));

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Target updated successfully!'
            ]);
        }

        return redirect()->route('admin.targets.index')->with('success', 'Target updated successfully!');
    }

    /**
     * Remove the specified target
     */
    public function destroy(Request $request, $id)
    {
        $target = Target::findOrFail($id);
        
        $user = Auth::user();
        $userType = $user->user_type_id;
        if (! $user || ! (new TargetAccessService())->canConfigure($user)) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only HR and Super Admin can delete targets.'
                ], 403);
            }

            abort(403, 'Only HR and Super Admin can delete targets.');
        }
        
        $assignableStaff = $this->getAssignableStaff($user);

        if (!$assignableStaff->contains('id', $target->sales_executive_id)) {
            abort(403, 'Unauthorized action.');
        }

        $target->delete();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Target deleted successfully!'
            ]);
        }

        return redirect()->route('admin.targets.index')->with('success', 'Target deleted successfully!');
    }

    /**
     * Get targets data for DataTable via AJAX
     */
    public function getTargetsData(Request $request)
    {
        $user = Auth::user();
        $year = $request->get('year', date('Y'));
        $month = $request->get('month');

        // Get assignable staff based on user hierarchy
        $salesExecutives = $this->getAssignableStaff($user);
        try {
            $execIds = is_object($salesExecutives) ? $salesExecutives->pluck('id')->toArray() : [];
        } catch (\Throwable $e) {
            Log::error('Failed to log getTargetsData debug: ' . $e->getMessage());
        }
        
        $targetsQuery = Target::with(['salesExecutive', 'assignedBy'])
            ->whereIn('sales_executive_id', $salesExecutives->pluck('id'))
            ->where('year', $year);

        if ($month) {
            $targetsQuery->where('month', $month);
        }

        $targets = $targetsQuery->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Update achieved amounts for real-time accuracy
        foreach ($targets as $target) {
            $target->updateAchievedAmount();
        }

        try {
            Log::info('TargetController@getTargetsData result', [
                'targets_count' => $targets->count(),
            ]);
        } catch (\Throwable $e) {
            // ignore
        }

        return response()->json([
            'data' => $targets->map(function($target) {
                return [
                    'id' => $target->id,
                    'sales_executive' => $target->salesExecutive->name ?? 'N/A',
                    'year' => $target->year,
                    'month' => $target->month_name,
                    'target_amount' => number_format($target->target_amount, 2),
                    'achieved_amount' => number_format($target->achieved_amount, 2),
                    'achievement_percentage' => $target->target_amount > 0 ? round(($target->achieved_amount / $target->target_amount) * 100, 2) : 0,
                    'status' => ucfirst($target->status),
                    'assigned_by' => $target->assignedBy->name ?? 'N/A',
                    'description' => $target->description ?? '',
                    'actions' => $target->id
                ];
            })
        ]);
    }

    /**
     * Get sales executives for AJAX
     */
    public function getSalesExecutivesData(Request $request)
    {
        $user = Auth::user();
        // Return assignable staff for the current user (used by edit AJAX)
        $staff = $this->getAssignableStaff($user);

        return response()->json([
            'salesExecutives' => $staff->map(function($executive) {
                return [
                    'id' => $executive->id,
                    'name' => $executive->name,
                    'user_type_id' => $executive->user_type_id ?? null,
                    'user_type' => $executive->userType->user_type ?? null,
                ];
            })
        ]);
    }

    private function abortUnlessCanAssignTargetTo($userId): void
    {
        $user = auth()->user();
        $access = new TargetAccessService();
        abort_unless($user && $access->canAssign($user), 403, 'You are not allowed to assign targets.');
        abort_unless($access->staff($user)->contains('id', $userId), 403, 'You are not allowed to assign target to this employee.');
    }

    private function getAssignableStaff($user = null)
    {
        $user = $user ?? auth()->user();
        return $user ? (new TargetAccessService())->staff($user) : collect();
    }

    private function abortUnlessCanConfigure(): void
    {
        abort_unless(auth()->user() && (new TargetAccessService())->canConfigure(auth()->user()), 403, 'Only HR and Super Admin can edit targets or incentive settings.');
    }

    private function incentiveSettings(Request $request): array
    {
        $fields = ['incentive_enabled', 'incentive_multiplier', 'incentive_team_multiplier', 'incentive_basis', 'incentive_product_ids', 'incentive_all_products'];
        if (!(new TargetAccessService())->canConfigure(auth()->user())) {
            foreach ($fields as $field) abort_if($request->has($field), 403, 'Incentive configuration is restricted to HR and Super Admin.');
            return [];
        }
        // A legacy update that omits incentive fields must not clear existing settings.
        if (!$request->has('incentive_enabled')) return [];
        $request->validate([
            'incentive_enabled' => 'required|boolean',
            'incentive_multiplier' => 'nullable|required_if:incentive_enabled,1|numeric|between:0,1|regex:/^\\d+(\\.\\d{1,8})?$/',
            'incentive_team_multiplier' => 'nullable|numeric|between:0,1|regex:/^\\d+(\\.\\d{1,8})?$/',
            'incentive_basis' => 'nullable|required_if:incentive_enabled,1|in:individual,team,company',
            'incentive_all_products' => 'nullable|boolean',
            'incentive_product_ids' => 'nullable|array',
        ]);
        $enabled = $request->boolean('incentive_enabled');
        $basis = $request->input('incentive_basis');
        $ids = $request->input('incentive_product_ids', []) ?? [];
        $employee = User::with('userType')->findOrFail($request->sales_executive_id);
        if ($enabled && in_array(optional($employee->userType)->user_type, UserType::OPERATIONS_ROLES, true) && $basis !== 'company') {
            throw ValidationException::withMessages(['incentive_basis' => 'Operations incentives use company/product monthly sales only.']);
        }
        if ($enabled && $basis === 'team') {
            if ($request->input('incentive_team_multiplier') === null) {
                throw ValidationException::withMessages(['incentive_team_multiplier' => 'Enter the manager incentive multiplier for team sales.']);
            }
            if (!in_array(optional($employee->userType)->user_type, [UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER], true)) {
                throw ValidationException::withMessages(['incentive_basis' => 'Team sales requires a Sales Manager.']);
            }
        }
        if ($enabled && $basis === 'company' && !$request->boolean('incentive_all_products')) {
            $request->validate(['incentive_product_ids' => 'required|array|min:1|max:500', 'incentive_product_ids.*' => 'required|uuid|distinct']);
            if (Product::whereIn('id', $ids)->count() !== count($ids)) {
                throw ValidationException::withMessages(['incentive_product_ids' => 'Select valid CRM products or All products.']);
            }
        }
        return ['incentive_enabled' => $enabled, 'incentive_multiplier' => $enabled ? $request->incentive_multiplier : null,
            'incentive_team_multiplier' => $enabled && $basis === 'team' ? $request->incentive_team_multiplier : null,
            'incentive_basis' => $enabled ? $basis : null,
            'incentive_product_ids' => $enabled && $basis === 'company' && !$request->boolean('incentive_all_products') ? array_values($ids) : null];
    }

    public function downloadIncentive(Request $request)
    {
        $this->abortUnlessCanConfigure();
        $input = $request->validate(['year' => 'required|integer|between:2020,2050', 'month' => 'required|integer|between:1,12', 'file_type' => 'required|in:excel,csv']);
        $products = Product::pluck('product', 'id');
        $targets = Target::with('salesExecutive.userType')->whereIn('sales_executive_id', $this->getAssignableStaff()->pluck('id'))
            ->where('year', $input['year'])->where('month', $input['month'])->where('status', 'active')->where('incentive_enabled', true)->get();
        $rows = $targets->map(function ($target) use ($products) {
            $result = $this->incentives->forTarget($target);
            $achieved = $this->incentives->ledger((int) $target->year, (int) $target->month)
                ->where('owner', (string) $target->sales_executive_id)->sum('amount');
            return [$target->salesExecutive->name ?? '', optional(optional($target->salesExecutive)->userType)->user_type ?? '',
                (int) $target->year, (int) $target->month, (float) $target->target_amount, (float) $achieved,
                ucfirst($target->incentive_basis), $target->incentive_basis === 'company' ? (collect($target->incentive_product_ids)->map(fn ($id) => $products[$id] ?? $id)->implode(', ') ?: 'All products') : '',
                $result['sales_amount'], (float) $target->incentive_multiplier, $result['incentive'], $target->status,
                $result['own_sales'] ?? null, $result['team_sales'] ?? null, $target->incentive_team_multiplier === null ? null : (float) $target->incentive_team_multiplier,
                $result['own_incentive'] ?? null, $result['team_incentive'] ?? null];
        });
        $csv = $input['file_type'] === 'csv';
        $content = Excel::raw(new TargetIncentiveExport($rows), $csv ? ExcelFormat::CSV : ExcelFormat::XLSX);
        return response()->streamDownload(static function () use ($content) { echo $content; },
            sprintf('incentives-%d-%02d.%s', $input['year'], $input['month'], $csv ? 'csv' : 'xlsx'),
            ['Content-Type' => $csv ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
