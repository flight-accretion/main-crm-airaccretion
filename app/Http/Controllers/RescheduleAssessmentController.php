<?php

namespace App\Http\Controllers;

use App\Models\OperationCase;
use App\Models\UserType;
use App\Services\Operations\RescheduleWindowService;
use Illuminate\Http\Request;

class RescheduleAssessmentController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(config('operations.reschedule_window_automation', false), 404);
        $filters = $request->validate(['state' => 'nullable|in:pending,completed', 'search' => 'nullable|string|max:255']);
        $cases = OperationCase::with(['lead.client', 'assignee', 'completedBy'])
            ->where('metadata->source', RescheduleWindowService::ASSESSMENT_SOURCE);
        if (($filters['state'] ?? 'pending') === 'completed') $cases->whereNotNull('completed_at');
        else $cases->whereNull('completed_at');
        if (!empty($filters['search'])) $cases->whereHas('lead.client', fn($q) => $q->where('name', 'like', '%'.$filters['search'].'%')->orWhere('contact_number', 'like', '%'.$filters['search'].'%'));
        $cases = $cases->oldest('opened_at')->orderBy('id')->paginate(20)->withQueryString();
        $parents = OperationCase::whereIn('id', $cases->pluck('metadata')->map(fn($metadata) => $metadata['reschedule_case_id'] ?? null)->filter())->get()->keyBy('id');
        return view('admin.pages.operations.reschedules.assessments', [
            'cases' => $cases, 'parents' => $parents, 'filters' => $filters,
            'canDecide' => in_array($request->user()->userType?->user_type, array_merge(UserType::ACCOUNTS_ROLES, UserType::ADMIN_ROLES), true),
        ]);
    }

    public function update(Request $request, OperationCase $case, RescheduleWindowService $service)
    {
        abort_unless(data_get($case->metadata, 'source') === RescheduleWindowService::ASSESSMENT_SOURCE, 404);
        $data = $request->validate(['decision' => 'required|in:refund_review_required,no_refund,more_information', 'note' => 'required|string|max:5000']);
        $service->assess($case, $request->user(), $data['decision'], trim($data['note']));
        return back()->with('success', 'Assessment recorded. No payment or cancellation was created.');
    }
}
