<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessLeadAiScore;
use App\Models\Lead;
use App\Models\LeadAiScore;
use App\Models\LeadAiScoringSetting;
use App\Models\UserType;
use App\Services\LeadAiCurrentFactsService;
use App\Services\LeadAiScoringEligibilityService;
use App\Services\LeadAiScoringService;
use Illuminate\Http\Request;
use function App\Helpers\getRepresentativeIds;

class LeadAiScoreController extends Controller
{
    public function show(
        Lead $lead,
        LeadAiCurrentFactsService $facts
    ) {
        $this->authorizeLead(
            $lead
        );

        if ($facts->isBookedClosed($lead)) {
            return response()->json([
                'has_score' => true,
                'status' => 'closed',
                'lifecycle' => 'booked_closed',
                'message' =>
                    'Approved payment received. AI scoring has stopped.',
            ]);
        }

        $score =
            LeadAiScore::query()
                ->with([
                    'previousScore',
                    'followup',
                ])
                ->where(
                    'lead_id',
                    $lead->id
                )
                ->orderByDesc(
                    'created_at'
                )
                ->first();

        if (!$score) {
            return response()->json([
                'has_score' => false,
            ]);
        }

        return response()->json(
            $this->payload(
                $score
            )
        );
    }

    /**
     * Used for an existing lead which has
     * follow-ups but has not yet been AI scored.
     *
     * Idempotent:
     * the same follow-up cannot create
     * another automatic score row.
     */
    public function analyse(
        Lead $lead,
        LeadAiScoringEligibilityService $eligibility,
        LeadAiScoringService $service,
        LeadAiCurrentFactsService $facts
    ) {
        $this->authorizeLead(
            $lead
        );

        $setting =
            LeadAiScoringSetting::active();

        if (!$setting->enabled) {
            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'AI lead scoring is currently disabled.',
                ],
                422
            );
        }

        if ($facts->isBookedClosed($lead)) {
            return response()->json(
                [
                    'success' => false,
                    'status' => 'closed',
                    'message' =>
                        'This lead is booked/closed because approved payment has been received.',
                ],
                422
            );
        }

        $followup =
            $lead
                ->leadFollowups()
                ->with('followedBy.userType')
                ->orderByDesc(
                    'created_at'
                )
                ->limit(30)
                ->get()
                ->first(
                    fn ($item) =>
                        $eligibility
                            ->eligible(
                                $item
                            )
                );

        if (!$followup) {
            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'No eligible customer follow-up is available for AI analysis.',
                ],
                422
            );
        }

        $score =
            $service->queueForFollowup(
                $followup,
                true
            );

        if (!$score) {
            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'Unable to queue AI analysis.',
                ],
                422
            );
        }

        return response()->json([
            'success' => true,
            'status' =>
                $score->status,
        ]);
    }

    /**
     * Phase 1:
     * only Super Admin retries failed analysis.
     */
    public function retry(
        Lead $lead,
        LeadAiCurrentFactsService $facts
    ) {
        $this->ensureSuperAdmin();

        if ($facts->isBookedClosed($lead)) {
            return response()->json(
                [
                    'success' => false,
                    'status' => 'closed',
                    'message' =>
                        'This lead is booked/closed because approved payment has been received.',
                ],
                422
            );
        }

        $score =
            LeadAiScore::query()
                ->where(
                    'lead_id',
                    $lead->id
                )
                ->where(
                    'status',
                    'failed'
                )
                ->orderByDesc(
                    'created_at'
                )
                ->first();

        if (!$score) {
            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'No failed AI analysis is available to retry.',
                ],
                422
            );
        }

        $score->update([
            'status' =>
                'pending',

            'last_error' =>
                null,
        ]);

        ProcessLeadAiScore::dispatch(
            $score->id
        );

        return response()->json([
            'success' => true,
            'status' => 'pending',
        ]);
    }

    private function payload(
        LeadAiScore $score
    ): array {
        $previous =
            $score->previousScore;

        $movement = null;

        if (
            $score->score !== null
            &&
            $previous
            &&
            $previous->score !== null
        ) {
            $movement =
                (int) $score->score
                -
                (int) $previous->score;
        }

        return [
            'has_score' => true,

            'id' =>
                $score->id,

            'status' =>
                $score->status,

            'temperature' =>
                $score->temperature,

            'score' =>
                $score->score,

            'confidence' =>
                $score->confidence,

            'score_reason' =>
                $score->score_reason,

            'summary' =>
                array_slice(
                    $score->summary ?: [],
                    0,
                    2
                ),

            'actions' =>
                $score->actions_json ?: [],

            'next_commitment' =>
                $score->next_commitment,

            'customer_ghosting' =>
                (bool) data_get(
                    $score->state_json,
                    'customer_ghosting',
                    false
                ),

            'score_change_reason' =>
                $score->score_change_reason,

            'previous_score' =>
                optional($previous)->score,

            'movement' =>
                $movement,

            'source_followup_at' =>
                optional(
                    $score->followup
                )->created_at
                    ? $score
                        ->followup
                        ->created_at
                        ->copy()
                        ->timezone(
                            'Asia/Kolkata'
                        )
                        ->format(
                            'd-M-Y h:i A'
                        )
                        . ' IST'
                    : null,

            'analysed_at' =>
                $score->processed_at
                    ? $score
                        ->processed_at
                        ->copy()
                        ->timezone(
                            'Asia/Kolkata'
                        )
                        ->format(
                            'd-M-Y h:i A'
                        )
                        . ' IST'
                    : null,
        ];
    }

    private function authorizeLead(
        Lead $lead
    ): void {
        $user = auth()->user();

        abort_unless(
            $user,
            403
        );

        $allowed =
            getRepresentativeIds(
                $user
            );

        /*
         * null means unrestricted according to
         * current CRM helper:
         * Super Admin/Admin/Operations.
         */
        if ($allowed === null) {
            return;
        }

        if (
            $allowed instanceof
                \Illuminate\Support\Collection
        ) {
            $allowed =
                $allowed->all();
        }

        $allowed =
            array_map(
                'strval',
                (array) $allowed
            );

        abort_unless(
            in_array(
                (string)
                $lead->representative_user_id,
                $allowed,
                true
            ),
            403
        );
    }

    private function ensureSuperAdmin(): void
    {
        $role =
            optional(
                auth()->user()->userType
            )->user_type;

        abort_unless(
            $role ===
                UserType::SUPER_ADMIN,
            403
        );
    }
}
