<?php

namespace App\Http\Controllers;

use App\Models\LeadAiScore;
use App\Models\LeadAiScoringSetting;
use App\Models\OperationCase;
use App\Services\LeadAiCurrentFactsService;
use App\Services\Operations\OperationsActivityService;
use Illuminate\Http\Request;

class OperationsFollowupController extends Controller
{
    public function create(OperationCase $case)
    {
        $case->loadMissing('lead.client');
        $lead = $case->lead;

        abort_unless($lead, 404);

        $leadFollowupData = app(ClientController::class)
            ->buildLeadFollowupViewData($lead);

        $latestAiScore = LeadAiScore::query()
            ->with('previousScore')
            ->where('lead_id', $lead->id)
            ->orderByDesc('created_at')
            ->first();

        return view('admin.pages.follow-ups.add-follow-up', array_merge(
            [
                'client' => $lead->client,
                'lead' => $lead,
                'latestAiScore' => $latestAiScore,
                'leadAiScoringSetting' => LeadAiScoringSetting::active(),
                'aiBookedClosed' => app(LeadAiCurrentFactsService::class)
                    ->isBookedClosed($lead),
                'operationsMode' => true,
                'operationCase' => $case,
            ],
            [
                'clientInfo' => $leadFollowupData['clientInfo'],
                'followups' => $leadFollowupData['followups'],
                'services' => $leadFollowupData['services'],
                'allExtraServices' => $leadFollowupData['allExtraServices'],
                'selectedServices' => $leadFollowupData['selectedServiceIds'],
                'selectedExtraServices' => $leadFollowupData['selectedExtraServiceIds'],
                'selectedServiceModels' => $leadFollowupData['selectedServiceModels'],
                'selectedExtraServiceModels' => $leadFollowupData['selectedExtraServiceModels'],
                'servicePrices' => $leadFollowupData['servicePrices'],
                'serviceFeePercents' => $leadFollowupData['serviceFeePercents'],
                'extraServicePrices' => $leadFollowupData['extraServicePrices'],
                'serviceExtraServicesMap' => $leadFollowupData['serviceExtraServicesMap'],
                'lastFollowupTotalAmount' => $leadFollowupData['lastFollowupTotalAmount'],
                'lastFollowupServiceAmount' => $leadFollowupData['lastFollowupServiceAmount'],
                'lastFollowupDiscountAmount' => $leadFollowupData['lastFollowupDiscountAmount'],
                'lastFollowupServiceDetails' => $leadFollowupData['lastFollowupServiceDetails'],
                'lastFollowupServiceIds' => $leadFollowupData['lastFollowupServiceIds'],
                'lastFollowupExtraServiceIds' => $leadFollowupData['lastFollowupExtraServiceIds'],
                'totalServiceAmount' => $leadFollowupData['totalServiceAmount'],
                'totalExtraServiceAmount' => $leadFollowupData['totalExtraServiceAmount'],
                'totalAmount' => $leadFollowupData['totalAmount'],
                'isStoredAmount' => $leadFollowupData['isStoredAmount'],
            ]
        ));
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
