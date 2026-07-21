<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserType;
use App\Models\SalesExecutiveAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesExecutiveManagementController extends Controller
{
    /**
     * Display a listing of sales executive assignments
     */
    public function index()
    {
        $user = Auth::user();
        $userType = $user->userType->user_type ?? '';

        // Check if user can access this module
        if (!$this->canAccessModule($userType)) {
            abort(403, 'Unauthorized access');
        }

        // Get assignments based on user role
        if (in_array($userType, [UserType::SUPER_ADMIN, UserType::ADMIN])) {
            // Admin can see all assignments
            $assignments = SalesExecutiveAssignment::with(['manager.userType', 'salesExecutive.userType'])
                ->active()
                ->orderBy('assigned_date', 'desc')
                ->get();
        } else {
            // Managers can only see their own assignments
            $assignments = SalesExecutiveAssignment::with(['manager.userType', 'salesExecutive.userType'])
                ->forManager($user->id)
                ->active()
                ->orderBy('assigned_date', 'desc')
                ->get();
        }

        // Get available managers and sales executives for the form
        $managers = $this->getAvailableManagers($user, $userType);
        $salesExecutives = $this->getAvailableSalesExecutives();

        return view('admin.pages.sales-executive-management.index', compact('assignments', 'managers', 'salesExecutives'));
    }



    /**
     * Store a newly created assignment
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $userType = $user->userType->user_type ?? '';

        if (!$this->canAssignExecutives($userType)) {
            abort(403, 'Unauthorized to assign sales executives');
        }

        $request->validate([
            'manager_id' => 'required|exists:users,id',
            'sales_executive_id' => 'required|exists:users,id',
            'notes' => 'nullable|string|max:1000'
        ]);

        // Additional validation: ensure sales_executive_id is actually a sales executive
        $salesExecutive = User::with('userType')->find($request->sales_executive_id);
        if (!$salesExecutive || $salesExecutive->userType->user_type !== UserType::SALES_EXECUTIVE) {
            return back()->withErrors(['sales_executive_id' => 'Selected user is not a Sales Executive.']);
        }

        // Check if assignment already exists (including inactive).
        // If an active assignment exists, block. If an inactive assignment exists, reactivate it instead of inserting a duplicate.
        $existingAssignment = SalesExecutiveAssignment::where('manager_id', $request->manager_id)
            ->where('sales_executive_id', $request->sales_executive_id)
            ->first();

        if ($existingAssignment) {
            if ((int) $existingAssignment->status === 1) {
                return back()->withErrors(['sales_executive_id' => 'This sales executive is already assigned to the selected manager.']);
            }

            // Reactivate the previous (inactive) assignment and update notes/assigned_date
            try {
                $existingAssignment->update([
                    'status' => 1,
                    'notes' => $request->notes,
                    'assigned_date' => now(),
                ]);

                return redirect()->route('admin.sales-executive-management.index')
                    ->with('success', 'Sales Executive assigned successfully!');
            } catch (\Exception $e) {
                Log::error('Error reactivating sales executive assignment: ' . $e->getMessage());
                return back()->withErrors(['error' => 'Failed to assign sales executive. Please try again.']);
            }
        }

        // For non-admin users, ensure they can only assign to themselves
        if (!in_array($userType, [UserType::SUPER_ADMIN, UserType::ADMIN]) && $request->manager_id !== $user->id) {
            return back()->withErrors(['manager_id' => 'You can only assign sales executives to yourself.']);
        }

        try {
            SalesExecutiveAssignment::create([
                'manager_id' => $request->manager_id,
                'sales_executive_id' => $request->sales_executive_id,
                'notes' => $request->notes,
                'assigned_date' => now(),
                'status' => 1
            ]);

            return redirect()->route('admin.sales-executive-management.index')
                ->with('success', 'Sales Executive assigned successfully!');
        } catch (\Exception $e) {
            Log::error('Error creating sales executive assignment: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to assign sales executive. Please try again.']);
        }
    }

    /**
     * Remove an assignment
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $userType = $user->userType->user_type ?? '';

        $assignment = SalesExecutiveAssignment::findOrFail($id);

        // Check permissions
        if (!in_array($userType, [UserType::SUPER_ADMIN, UserType::ADMIN]) && $assignment->manager_id !== $user->id) {
            abort(403, 'Unauthorized to remove this assignment');
        }

        try {
            $assignment->update(['status' => 0]); // Soft delete by setting status to inactive
            return redirect()->route('admin.sales-executive-management.index')
                ->with('success', 'Sales Executive assignment removed successfully!');
        } catch (\Exception $e) {
            Log::error('Error removing sales executive assignment: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to remove assignment. Please try again.']);
        }
    }

    /**
     * Get sales executives assigned to current manager (for filtering leads)
     */
    public function getAssignedSalesExecutives(Request $request)
    {
        $user = Auth::user();
        $assignedExecutives = SalesExecutiveAssignment::getSalesExecutivesForManager($user->id);
        
        return response()->json([
            'executives' => $assignedExecutives->map(function ($executive) {
                return [
                    'id' => $executive->id,
                    'name' => $executive->name,
                    'email' => $executive->email
                ];
            })
        ]);
    }

    /**
     * Check if user can access this module
     */
    private function canAccessModule($userType)
    {
        return in_array($userType, [
            UserType::SUPER_ADMIN,
            UserType::ADMIN,
            UserType::SENIOR_SALES_MANAGER,
            UserType::SALES_MANAGER
        ]);
    }

    /**
     * Check if user can assign sales executives
     */
    private function canAssignExecutives($userType)
    {
        return in_array($userType, [
            UserType::SUPER_ADMIN,
            UserType::ADMIN,
            UserType::SENIOR_SALES_MANAGER,
            UserType::SALES_MANAGER
        ]);
    }

    /**
     * Get available managers based on user type
     */
    private function getAvailableManagers($user, $userType)
    {
        if (in_array($userType, [UserType::SUPER_ADMIN, UserType::ADMIN])) {
            // Admin can assign to any manager
            return User::whereHas('userType', function ($query) {
                $query->whereIn('user_type', [
                    UserType::SENIOR_SALES_MANAGER,
                    UserType::SALES_MANAGER
                ]);
            })->where('status', 1)->get();
        } else {
            // Managers can only assign to themselves
            return collect([$user]);
        }
    }

    /**
     * Get available sales executives
     */
    private function getAvailableSalesExecutives()
    {
        return User::whereHas('userType', function ($query) {
            $query->where('user_type', UserType::SALES_EXECUTIVE);
        })->where('status', 1)->get();
    }
}