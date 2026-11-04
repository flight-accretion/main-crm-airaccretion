<?php

namespace App\Http\Controllers;

use App\Models\OperationCase;
use App\Services\Operations\OperationsActivityService;
use Illuminate\Http\Request;

class OperationsFollowupController extends Controller
{
    public function create(OperationCase $case)
    {
        $case->loadMissing('lead.client', 'assignee');
        $lead = $case->lead;

        abort_unless($lead, 404);

        return view('admin.pages.operations.followups.create', [
            'operationCase' => $case,
            'lead' => $lead,
            'client' => $lead->client,
        ]);
    }

    public function store(
        Request $request,
        OperationCase $case,
        OperationsActivityService $activity
    ) {
        $data = $request->validate([
            'notes' => [
                'nullable',
                'string',
                'max:1000',
                function ($attribute, $value, $fail) use ($request) {
                    if (
                        !$request->boolean('customer_not_picked_up')
                        && trim((string) $value) === ''
                    ) {
                        $fail('Follow-up notes are required unless Customer did not pick up is selected.');
                    }
                },
            ],
            'operation_status' => 'required|in:pending,in_progress,completed',
            'next_followup_at' => 'nullable|date_format:Y-m-d\TH:i',
            'customer_not_picked_up' => ['nullable', 'boolean'],
        ]);

        $activity->recordFollowup(
            $case,
            $request->user(),
            [
                'note' => $data['notes'] ?? null,
                'status' => $data['operation_status'],
                'next_followup_at' => $data['next_followup_at'] ?? null,
                'customer_not_picked_up' => $request->boolean('customer_not_picked_up'),
            ]
        );

        return redirect()
            ->route('admin.operations.queue', $case->type)
            ->with('success', 'Operations follow-up saved.');
    }
}
