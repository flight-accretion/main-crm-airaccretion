<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\User;
use App\Models\UserType;
use App\Services\Operations\OperationsLeadAssignmentService;
use Illuminate\Http\Request;
use RuntimeException;

class OperationsLeadAssignmentController extends Controller
{
    public function store(
        Request $request,
        Lead $lead,
        OperationsLeadAssignmentService $service
    ) {
        $viewer = $request->user();
        $viewer?->loadMissing('userType');

        abort_unless(
            $viewer
            && (
                $viewer->isSuperAdmin()
                || in_array(
                    optional($viewer->userType)->user_type,
                    [
                        UserType::ADMIN,
                        UserType::OPERATIONS_MANAGER,
                        UserType::SENIOR_OPERATIONS_MANAGER,
                    ],
                    true
                )
            ),
            403
        );

        $validated = $request->validate([
            'operations_user_id' => 'required|uuid|exists:users,id',
            'reassignment_reason' => 'nullable|string|max:1000',
        ]);

        $operationsUser = User::with('userType')
            ->findOrFail($validated['operations_user_id']);

        try {
            $service->assign(
                $lead,
                $operationsUser,
                $viewer,
                $validated['reassignment_reason'] ?? null
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'operations_user_id' => $exception->getMessage(),
            ]);
        }

        return back()->with('success', 'Operations handler updated successfully.');
    }
}
