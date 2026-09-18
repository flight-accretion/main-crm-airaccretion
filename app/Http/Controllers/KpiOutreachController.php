<?php

namespace App\Http\Controllers;

use App\Models\KpiOutreachAssignment;
use App\Models\UserType;
use App\Services\Kpi\KpiDashboardService;
use App\Services\Kpi\KpiDataScopeService;
use App\Services\Kpi\KpiOutreachContextService;
use App\Services\Kpi\KpiOutreachService;
use Illuminate\Http\Request;

class KpiOutreachController extends Controller
{
public function index(
    Request $request,
    KpiOutreachService $service,
    KpiOutreachContextService $context,
    KpiDashboardService $dashboard,
    KpiDataScopeService $scope
) {
    $user = $request
        ->user()
        ->load('userType');

    $role =
        $user->userType->user_type
        ?? '';


    /*
    |--------------------------------------------------------------------------
    | SALES USER
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | Existing behaviour is preserved exactly.
    |
    | - release invalid assignments
    | - ensure rolling queue
    | - standard queue
    | - extra queue
    | - DNP / Remark / Fresh Lead
    |
    */

    if (
        in_array(
            $role,
            UserType::SALES_ROLES,
            true
        )
    ) {

        $filters = $request->validate([
            'number' =>
                'nullable|string|max:30',

            'date' =>
                'nullable|date_format:Y-m-d',
        ]);


        $service
            ->releaseAssignmentsThatNowHaveActiveLeads(
                $user
            );


        $service
            ->ensureStandardQueue(
                $user
            );


        return view(
            'admin.pages.kpi.outreach',
            [
                'standard' =>
                    $context->enrich(
                        $service
                            ->pendingAssignments(
                                $user,
                                'standard',
                                $filters
                            )
                    ),

                'extra' =>
                    $context->enrich(
                        $service
                            ->pendingAssignments(
                                $user,
                                'extra',
                                $filters
                            )
                    ),

                'standardCompletedToday' =>
                    $service
                        ->standardCompletedToday(
                            $user
                        ),

                'standardLocked' =>
                    $service
                        ->standardActionsLocked(
                            $user
                        ),

                'canRequestExtra' =>
                    $service
                        ->canRequestExtra(
                            $user
                        ),

                'dailyTarget' =>
                    $service
                        ->dailyTarget(
                            $user
                        ),

                'extraBatchSize' =>
                    $service
                        ->extraBatchSize(),

                'filters' =>
                    $filters,

                /*
                 * Normal salesperson mode.
                 */
                'isAdminMonitor' =>
                    false,

                'availableSalesUsers' =>
                    collect(),

                'selectedSalesUser' =>
                    $user,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SUPER ADMIN / ADMIN MONITOR MODE
    |--------------------------------------------------------------------------
    |
    | Critical:
    |
    | DO NOT call:
    |
    | ensureStandardQueue($admin)
    |
    | Otherwise 50 customer numbers may get reserved
    | against the Admin account.
    |
    | Admin only views an existing Sales user's queue.
    |
    */

    if (
        $scope->isAdmin(
            $user
        )
    ) {

        $validated =
            $request->validate([
                'user_id' =>
                    'nullable|uuid',

                'number' =>
                    'nullable|string|max:30',

                'date' =>
                    'nullable|date_format:Y-m-d',
            ]);


        $availableSalesUsers =
            $scope
                ->allowedUsers(
                    $user,
                    'sales'
                )
                ->values();


        $requestedUserId =
            $validated['user_id']
            ?? null;


        /*
         * Default to first permitted Sales user
         * when Admin first opens the page.
         */
        $selectedSalesUser =
            $requestedUserId
                ? $availableSalesUsers
                    ->firstWhere(
                        'id',
                        $requestedUserId
                    )
                : $availableSalesUsers
                    ->first();


        /*
         * Forged user ID cannot escape
         * Admin's permitted Sales scope.
         */
        if (
            $requestedUserId
            &&
            !$selectedSalesUser
        ) {
            abort(403);
        }


        $filters = [
            'number' =>
                $validated['number']
                ?? null,

            'date' =>
                $validated['date']
                ?? null,
        ];


        /*
         * No Sales users configured.
         */
        if (
            !$selectedSalesUser
        ) {

            return view(
                'admin.pages.kpi.outreach',
                [
                    'standard' =>
                        collect(),

                    'extra' =>
                        collect(),

                    'standardCompletedToday' =>
                        0,

                    'standardLocked' =>
                        true,

                    'canRequestExtra' =>
                        false,

                    'dailyTarget' =>
                        0,

                    'extraBatchSize' =>
                        $service
                            ->extraBatchSize(),

                    'filters' =>
                        $filters,

                    'isAdminMonitor' =>
                        true,

                    'availableSalesUsers' =>
                        $availableSalesUsers,

                    'selectedSalesUser' =>
                        null,
                ]
            );
        }


        /*
         * IMPORTANT:
         *
         * We READ the Sales user's existing queue.
         *
         * We intentionally do NOT call:
         *
         * releaseAssignmentsThatNowHaveActiveLeads()
         * ensureStandardQueue()
         *
         * because Admin viewing must not mutate
         * somebody else's outreach queue.
         */

        return view(
            'admin.pages.kpi.outreach',
            [
                'standard' =>
                    $context->enrich(
                        $service
                            ->pendingAssignments(
                                $selectedSalesUser,
                                'standard',
                                $filters
                            )
                    ),

                'extra' =>
                    $context->enrich(
                        $service
                            ->pendingAssignments(
                                $selectedSalesUser,
                                'extra',
                                $filters
                            )
                    ),

                'standardCompletedToday' =>
                    $service
                        ->standardCompletedToday(
                            $selectedSalesUser
                        ),

                /*
                 * Admin monitor mode must not
                 * perform salesperson actions.
                 */
                'standardLocked' =>
                    true,

                'canRequestExtra' =>
                    false,

                'dailyTarget' =>
                    $service
                        ->dailyTarget(
                            $selectedSalesUser
                        ),

                'extraBatchSize' =>
                    $service
                        ->extraBatchSize(),

                'filters' =>
                    $filters,

                'isAdminMonitor' =>
                    true,

                'availableSalesUsers' =>
                    $availableSalesUsers,

                'selectedSalesUser' =>
                    $selectedSalesUser,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ACCOUNTS / OPERATIONS
    |--------------------------------------------------------------------------
    |
    | Preserve existing department Daily Activity.
    |
    */

    $department =
        $scope->departmentFor(
            $user
        );


    if (
        !in_array(
            $department,
            [
                'accounts',
                'operations',
            ],
            true
        )
    ) {

        return redirect()
            ->route(
                'admin.kpi.index'
            );
    }


    $team =
        $scope->allowedUsers(
            $user,
            $department
        );


    $asOf =
        now()->endOfDay();


    return view(
        'admin.pages.kpi.daily-activity',
        [
            'department' =>
                $department,

            'team' =>
                $team->map(
                    fn ($teamUser) =>
                        $dashboard
                            ->forUser(
                                $teamUser,
                                $asOf
                            )
                ),

            'asOf' =>
                $asOf,
        ]
    );
}

    public function dnp(
        Request $request,
        KpiOutreachAssignment $assignment,
        KpiOutreachService $service
    ) {
        $request->validate([
            'dnp' => 'accepted',
        ]);

        $service->completeDnp($assignment, $request->user());

        return back()->with(
            'success',
            'DNP verified and KPI outreach action completed.'
        );
    }

    public function remark(
        Request $request,
        KpiOutreachAssignment $assignment,
        KpiOutreachService $service
    ) {
        $data = $request->validate([
            'remark' => 'required|string|max:1000',
        ]);

        $service->completeRemark(
            $assignment,
            $request->user(),
            $data['remark']
        );

        return back()->with(
            'success',
            'Connected call verified and remark recorded.'
        );
    }

    public function extra(
        Request $request,
        KpiOutreachService $service
    ) {
        $batch = $service->requestExtra($request->user());

        return back()->with(
            'success',
            "{$batch->allocated_count} extra numbers allocated."
        );
    }

    public function createLead(
        Request $request,
        KpiOutreachAssignment $assignment
    ) {
        if (
            $assignment->user_id !== $request->user()->id
            || $assignment->status !== 'pending'
        ) {
            abort(403);
        }

        return redirect()->route('admin.clients.create', [
            'outreach_assignment' => $assignment->id,
        ]);
    }
}
