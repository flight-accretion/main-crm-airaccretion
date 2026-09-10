<?php

namespace App\Http\Controllers;

use App\Models\KpiOutreachAssignment;
use App\Services\Kpi\KpiOutreachContextService;
use App\Services\Kpi\KpiOutreachService;
use Illuminate\Http\Request;

class KpiOutreachController extends Controller
{
    public function index(
        Request $request,
        KpiOutreachService $service,
        KpiOutreachContextService $context
    ) {
        $user = $request->user();

        $service->releaseAssignmentsThatNowHaveActiveLeads($user);
        $service->ensureStandardQueue($user);

        $standard = KpiOutreachAssignment::with('pool')
            ->where('user_id', $user->id)
            ->where('allocation_type', 'standard')
            ->where('status', 'pending')
            ->orderBy('assigned_at')
            ->get();

        $extra = KpiOutreachAssignment::with('pool')
            ->where('user_id', $user->id)
            ->where('allocation_type', 'extra')
            ->where('status', 'pending')
            ->orderBy('assigned_at')
            ->get();

        return view('admin.pages.kpi.outreach', [
            'standard' => $context->enrich($standard),
            'extra' => $context->enrich($extra),
            'standardCompletedToday' => $service->standardCompletedToday($user),
            'standardLocked' => $service->standardActionsLocked($user),
            'canRequestExtra' => $service->canRequestExtra($user),
        ]);
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
