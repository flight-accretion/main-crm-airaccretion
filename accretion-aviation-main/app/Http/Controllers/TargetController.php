<?php

namespace App\Http\Controllers;

use App\Models\Target;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TargetController extends Controller
{
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

        return view('admin.targets.index', compact('year', 'month', 'salesExecutiveId', 'assignableStaff', 'targets'));
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

        // There is no separate create blade; index holds the add form. Keep compatibility.
        return view('admin.targets.create', compact('assignableStaff', 'year', 'month'));
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
    public function edit(Request $request, $id)
    {
        $target = Target::with(['salesExecutive', 'assignedBy'])->findOrFail($id);
        
        $user = Auth::user();
        $userType = $user->user_type_id;
        
        $assignableStaff = $this->getAssignableStaff($user);

        if (!$assignableStaff->contains('id', $target->sales_executive_id)) {
            abort(403, 'Unauthorized action.');
        }

        if ($request->ajax()) {
            // Return a lightweight DTO for assignable staff to avoid sending nested relation objects
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

        return view('admin.targets.edit', compact('target', 'assignableStaff'));
    }

    /**
     * Update the specified target
     */
    public function update(Request $request, $id)
    {
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

        $target->update([
            'sales_executive_id' => $request->sales_executive_id,
            'year' => $request->year,
            'month' => $request->month,
            'target_amount' => $request->target_amount,
            'description' => $request->description,
            'status' => $request->status,
        ]);

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
        // Only Super Admin users are allowed to delete targets
        if (! $user || ! ($user instanceof User && $user->isSuperAdmin())) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Super Admin can delete targets.'
                ], 403);
            }

            abort(403, 'Only Super Admin can delete targets.');
        }
        
        $salesExecutives = $this->getSalesExecutives($user);
        
        if (!$salesExecutives->contains('id', $target->sales_executive_id)) {
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

    /**
     * Get sales executives based on user hierarchy
     */
    /**
     * Get assignable staff (single list used for dropdown). Rules:
     * - Admin/Super Admin: all Sales Managers + all Sales Executives
     * - Sales Manager / Senior Sales Manager: all Sales Managers + executives assigned to current manager
     * - Sales Executive: only themselves
     */
    private function getAssignableStaff($user)
    {
        $currentType = $user->userType->user_type ?? null;

        // Get user type ids for managers and executives
        $managerTypes = [UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER];
        $managerTypeIds = UserType::whereIn('user_type', $managerTypes)->pluck('id')->toArray();

        $salesExecType = UserType::where('user_type', UserType::SALES_EXECUTIVE)->first();

        // Admins: return managers + all execs
        if (in_array($currentType, [UserType::SUPER_ADMIN, UserType::ADMIN])) {
            $managers = User::whereIn('user_type_id', $managerTypeIds)->where('status', 1)->with('userType')->orderBy('name')->get();
            $executives = $salesExecType ? User::where('user_type_id', $salesExecType->id)->where('status', 1)->with('userType')->orderBy('name')->get() : collect();
            return $managers->merge($executives);
        }

        // Sales Manager: include ONLY the logged-in manager + executives assigned to this manager
        if (in_array($currentType, [UserType::SENIOR_SALES_MANAGER, UserType::SALES_MANAGER])) {
            // include only the current user as the manager option
            $currentManager = User::where('id', $user->id)->where('status', 1)->with('userType')->get();
            $assignedExecutives = \App\Models\SalesExecutiveAssignment::getSalesExecutivesForManager($user->id);
            return $currentManager->merge($assignedExecutives);
        }

        // Sales Executive: only themselves
        return collect([$user]);
    }
    
}
