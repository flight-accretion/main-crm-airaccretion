<?php

namespace App\Services\Operations;

use App\Models\Lead;
use App\Models\OperationCase;
use App\Models\OperationCaseActivity;
use App\Models\ReviewConversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationCaseService
{
    public function open(
        Lead $lead,
        string $type,
        array $metadata = [],
        ?string $createdBy = null
    ): OperationCase {
        if (!in_array($type, OperationCase::validTypes(), true)) {
            throw new \InvalidArgumentException(
                'Invalid operation type.'
            );
        }

        return DB::transaction(
            function () use (
                $lead,
                $type,
                $metadata,
                $createdBy
            ) {
                $existing = OperationCase::query()
                    ->where(
                        'lead_id',
                        $lead->id
                    )
                    ->where(
                        'type',
                        $type
                    )
                    ->whereNull(
                        'completed_at'
                    )
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $existing;
                }

                $case = OperationCase::create([
                    'lead_id' =>
                        $lead->id,

                    'type' =>
                        $type,

                    'status' =>
                        OperationCase::STATUS_PENDING,

                    'created_by' =>
                        $createdBy,

                    'opened_at' =>
                        now(),

                    'metadata' =>
                        $metadata,
                ]);

                $this->activity(
                    $case,
                    'opened',
                    null,
                    OperationCase::STATUS_PENDING,
                    $createdBy,
                    null,
                    $metadata
                );

                return $case;
            }
        );
    }


    public function start(
        OperationCase $case,
        User $user
    ): OperationCase {
        return DB::transaction(
            function () use (
                $case,
                $user
            ) {
                $case = OperationCase::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $case->id
                    );

                if ($case->completed_at) {
                    return $case;
                }

                if (
                    $case->status
                        ===
                        OperationCase::STATUS_IN_PROGRESS
                    &&
                    $case->assigned_to
                        ===
                        $user->id
                ) {
                    return $case;
                }

                $oldStatus =
                    $case->status;

                $case->update([
                    'status' =>
                        OperationCase::STATUS_IN_PROGRESS,

                    'assigned_to' =>
                        $user->id,
                ]);

                $this->activity(
                    $case,
                    'started',
                    $oldStatus,
                    OperationCase::STATUS_IN_PROGRESS,
                    $user->id
                );

                return $case->fresh();
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | NORMAL OPERATIONS CASE COMPLETION
    |--------------------------------------------------------------------------
    |
    | Review cases are deliberately blocked here.
    |
    | Review has its own workflow:
    |
    | Review Done / Cancel
    |        ↓
    | Image Collection Done / Cancel
    |        ↓
    | Whole Operation Case closes
    |
    */
    public function complete(
        OperationCase $case,
        User $user,
        ?string $note = null
    ): OperationCase {
        if (
            $case->type
            ===
            OperationCase::TYPE_REVIEW
        ) {
            throw ValidationException::withMessages([
                'case' =>
                    'Review cases must be resolved using Review Done/Cancel and Image Collection Done/Cancel.',
            ]);
        }

        return DB::transaction(
            function () use (
                $case,
                $user,
                $note
            ) {
                $case = OperationCase::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $case->id
                    );

                if ($case->completed_at) {
                    return $case;
                }

                $oldStatus =
                    $case->status;

                $case->update([
                    'status' =>
                        OperationCase::STATUS_COMPLETED,

                    'assigned_to' =>
                        $case->assigned_to
                            ?: $user->id,

                    'completed_by' =>
                        $user->id,

                    'completed_at' =>
                        now(),

                    'note' =>
                        $note,
                ]);

                $this->activity(
                    $case,
                    'completed',
                    $oldStatus,
                    OperationCase::STATUS_COMPLETED,
                    $user->id,
                    $note
                );

                return $case->fresh();
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | REVIEW - MARK AS DONE
    |--------------------------------------------------------------------------
    |
    | Review Done = KPI success.
    |
    | IMPORTANT:
    | This does NOT close the Review OperationCase.
    | It moves the Lead to Image Collection Pending.
    |
    */
    public function completeReview(
        OperationCase $case,
        User $user,
        ?string $note = null
    ): OperationCase {
        return $this->resolveReview(
            $case,
            $user,
            'completed',
            $note
        );
    }


    /*
    |--------------------------------------------------------------------------
    | REVIEW - CANCEL
    |--------------------------------------------------------------------------
    |
    | Use when Review was not received.
    |
    | Review Cancel = KPI failure.
    |
    | Lead STILL remains in Review Dashboard because Image Collection must
    | also be resolved.
    |
    */
    public function cancelReview(
        OperationCase $case,
        User $user,
        string $reason
    ): OperationCase {
        return $this->resolveReview(
            $case,
            $user,
            'cancelled',
            $reason
        );
    }


    /*
    |--------------------------------------------------------------------------
    | RESOLVE REVIEW
    |--------------------------------------------------------------------------
    */
    private function resolveReview(
        OperationCase $case,
        User $user,
        string $outcome,
        ?string $note = null
    ): OperationCase {
        if (
            $case->type
            !==
            OperationCase::TYPE_REVIEW
        ) {
            throw ValidationException::withMessages([
                'case' =>
                    'This action is only available for Review cases.',
            ]);
        }

        if (
            !in_array(
                $outcome,
                [
                    'completed',
                    'cancelled',
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'case' =>
                    'Invalid Review outcome.',
            ]);
        }

        return DB::transaction(
            function () use (
                $case,
                $user,
                $outcome,
                $note
            ) {
                $case = OperationCase::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $case->id
                    );

                /*
                |--------------------------------------------------------------------------
                | Whole workflow already closed
                |--------------------------------------------------------------------------
                */
                if ($case->completed_at) {
                    return $case;
                }

                $metadata =
                    is_array(
                        $case->metadata
                    )
                        ? $case->metadata
                        : [];


                /*
                |--------------------------------------------------------------------------
                | Backward compatibility
                |--------------------------------------------------------------------------
                |
                | Older cases created by the previous implementation may only
                | have review_completed = true without review_status.
                |
                */
                $currentReviewStatus =
                    data_get(
                        $metadata,
                        'review_status'
                    );

                if (!$currentReviewStatus) {
                    if (
                        !empty(
                            $metadata[
                                'review_completed'
                            ]
                        )
                    ) {
                        $currentReviewStatus =
                            'completed';
                    } elseif (
                        !empty(
                            $metadata[
                                'review_cancelled'
                            ]
                        )
                    ) {
                        $currentReviewStatus =
                            'cancelled';
                    } else {
                        $currentReviewStatus =
                            'pending';
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | Already resolved
                |--------------------------------------------------------------------------
                |
                | Repeating same request = safe/idempotent.
                |
                | Opposite request after resolution is blocked:
                |
                | Done -> Cancel = blocked
                | Cancel -> Done = blocked
                |
                */
                if (
                    in_array(
                        $currentReviewStatus,
                        [
                            'completed',
                            'cancelled',
                        ],
                        true
                    )
                ) {
                    if (
                        $currentReviewStatus
                        ===
                        $outcome
                    ) {
                        return $case;
                    }

                    throw ValidationException::withMessages([
                        'case' =>
                            'Review has already been resolved as '
                            .
                            strtoupper(
                                $currentReviewStatus
                            )
                            .
                            '.',
                    ]);
                }


                $oldStatus =
                    $case->status;

                $now =
                    now();

                $reviewCompleted =
                    $outcome
                    ===
                    'completed';


                /*
                |--------------------------------------------------------------------------
                | REVIEW RESULT
                |--------------------------------------------------------------------------
                */
                $metadata[
                    'review_status'
                ] =
                    $outcome;


                /*
                |--------------------------------------------------------------------------
                | Keep previous fields for backward compatibility
                |--------------------------------------------------------------------------
                */
                $metadata[
                    'review_completed'
                ] =
                    $reviewCompleted;

                $metadata[
                    'review_cancelled'
                ] =
                    !$reviewCompleted;


                $metadata[
                    'review_completed_at'
                ] =
                    $reviewCompleted
                        ? $now->toISOString()
                        : null;

                $metadata[
                    'review_completed_by'
                ] =
                    $reviewCompleted
                        ? $user->id
                        : null;


                $metadata[
                    'review_cancelled_at'
                ] =
                    !$reviewCompleted
                        ? $now->toISOString()
                        : null;

                $metadata[
                    'review_cancelled_by'
                ] =
                    !$reviewCompleted
                        ? $user->id
                        : null;

                $metadata[
                    'review_cancel_reason'
                ] =
                    !$reviewCompleted
                        ? trim(
                            (string)
                            $note
                        )
                        : null;


                /*
                |--------------------------------------------------------------------------
                | AFTER REVIEW -> IMAGE COLLECTION PENDING
                |--------------------------------------------------------------------------
                |
                | This happens whether Review was:
                |
                | completed
                | OR
                | cancelled
                |
                */
                $metadata[
                    'image_collection_status'
                ] =
                    'pending';

                $metadata[
                    'image_collection_completed'
                ] =
                    false;

                $metadata[
                    'image_collection_cancelled'
                ] =
                    false;

                $metadata[
                    'image_collection_completed_at'
                ] =
                    null;

                $metadata[
                    'image_collection_completed_by'
                ] =
                    null;

                $metadata[
                    'image_collection_cancelled_at'
                ] =
                    null;

                $metadata[
                    'image_collection_cancelled_by'
                ] =
                    null;

                $metadata[
                    'image_collection_cancel_reason'
                ] =
                    null;


                /*
                |--------------------------------------------------------------------------
                | KEEP OPERATION CASE OPEN
                |--------------------------------------------------------------------------
                |
                | Review Done/Cancel does NOT hide Lead from Review dashboard.
                |
                */
                $case->update([
                    'status' =>
                        OperationCase::STATUS_IN_PROGRESS,

                    'assigned_to' =>
                        $case->assigned_to
                            ?: $user->id,

                    'completed_by' =>
                        null,

                    'completed_at' =>
                        null,

                    'note' =>
                        $note
                            ?: $case->note,

                    'metadata' =>
                        $metadata,
                ]);


                /*
                |--------------------------------------------------------------------------
                | STOP REVIEW AUTOMATION / REMINDERS
                |--------------------------------------------------------------------------
                |
                | Once Review is either Done or Cancelled,
                | we should not keep sending Review reminders.
                |
                */
                ReviewConversation::query()
                    ->where(
                        'operation_case_id',
                        $case->id
                    )
                    ->whereNull(
                        'completed_at'
                    )
                    ->update([
                        'status' =>
                            'completed',

                        'next_reminder_at' =>
                            null,

                        'completed_at' =>
                            $now,

                        'updated_at' =>
                            $now,
                    ]);


                /*
                |--------------------------------------------------------------------------
                | KPI AUDIT EVENT
                |--------------------------------------------------------------------------
                |
                | completed = KPI success
                | cancelled = KPI failure
                |
                */
                $action =
                    $reviewCompleted
                        ? 'review_completed'
                        : 'review_cancelled';


                $this->activity(
                    $case,
                    $action,
                    $oldStatus,
                    OperationCase::STATUS_IN_PROGRESS,
                    $user->id,
                    $note,
                    [
                        'review_status' =>
                            $outcome,

                        'review_resolved_at' =>
                            $now->toISOString(),

                        'image_collection_status' =>
                            'pending',
                    ]
                );


                return $case->fresh();
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | IMAGE COLLECTION - MARK AS DONE
    |--------------------------------------------------------------------------
    |
    | Image Done = KPI success.
    |
    | This closes the whole Review workflow.
    |
    */
    public function completeImageCollection(
        OperationCase $case,
        User $user,
        ?string $note = null
    ): OperationCase {
        return $this->resolveImageCollection(
            $case,
            $user,
            'completed',
            $note
        );
    }


    /*
    |--------------------------------------------------------------------------
    | IMAGE COLLECTION - CANCEL
    |--------------------------------------------------------------------------
    |
    | Use when customer did not provide image/video.
    |
    | Image Cancel = KPI failure.
    |
    | Because this is the final Review stage, the OperationCase is still
    | completed and removed from the ACTIVE Review Dashboard.
    |
    */
    public function cancelImageCollection(
        OperationCase $case,
        User $user,
        string $reason
    ): OperationCase {
        return $this->resolveImageCollection(
            $case,
            $user,
            'cancelled',
            $reason
        );
    }


    /*
    |--------------------------------------------------------------------------
    | RESOLVE IMAGE COLLECTION
    |--------------------------------------------------------------------------
    */
    private function resolveImageCollection(
        OperationCase $case,
        User $user,
        string $outcome,
        ?string $note = null
    ): OperationCase {
        if (
            $case->type
            !==
            OperationCase::TYPE_REVIEW
        ) {
            throw ValidationException::withMessages([
                'case' =>
                    'Image Collection is only available for Review cases.',
            ]);
        }

        if (
            !in_array(
                $outcome,
                [
                    'completed',
                    'cancelled',
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'case' =>
                    'Invalid Image Collection outcome.',
            ]);
        }


        return DB::transaction(
            function () use (
                $case,
                $user,
                $outcome,
                $note
            ) {
                $case = OperationCase::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $case->id
                    );


                /*
                |--------------------------------------------------------------------------
                | Already fully completed
                |--------------------------------------------------------------------------
                */
                if ($case->completed_at) {
                    return $case;
                }


                $metadata =
                    is_array(
                        $case->metadata
                    )
                        ? $case->metadata
                        : [];


                /*
                |--------------------------------------------------------------------------
                | REVIEW MUST FIRST BE RESOLVED
                |--------------------------------------------------------------------------
                |
                | It can be either:
                |
                | completed
                | cancelled
                |
                */
                $reviewStatus =
                    data_get(
                        $metadata,
                        'review_status'
                    );

                if (!$reviewStatus) {
                    if (
                        !empty(
                            $metadata[
                                'review_completed'
                            ]
                        )
                    ) {
                        $reviewStatus =
                            'completed';
                    } elseif (
                        !empty(
                            $metadata[
                                'review_cancelled'
                            ]
                        )
                    ) {
                        $reviewStatus =
                            'cancelled';
                    } else {
                        $reviewStatus =
                            'pending';
                    }
                }


                if (
                    !in_array(
                        $reviewStatus,
                        [
                            'completed',
                            'cancelled',
                        ],
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'case' =>
                            'Resolve Review first using Mark Review as Done or Cancel Review.',
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | CURRENT IMAGE STATUS
                |--------------------------------------------------------------------------
                */
                $currentImageStatus =
                    data_get(
                        $metadata,
                        'image_collection_status'
                    );

                if (!$currentImageStatus) {
                    if (
                        !empty(
                            $metadata[
                                'image_collection_completed'
                            ]
                        )
                    ) {
                        $currentImageStatus =
                            'completed';
                    } elseif (
                        !empty(
                            $metadata[
                                'image_collection_cancelled'
                            ]
                        )
                    ) {
                        $currentImageStatus =
                            'cancelled';
                    } else {
                        $currentImageStatus =
                            'pending';
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | Already resolved
                |--------------------------------------------------------------------------
                */
                if (
                    in_array(
                        $currentImageStatus,
                        [
                            'completed',
                            'cancelled',
                        ],
                        true
                    )
                ) {
                    if (
                        $currentImageStatus
                        ===
                        $outcome
                    ) {
                        return $case;
                    }

                    throw ValidationException::withMessages([
                        'case' =>
                            'Image Collection has already been resolved as '
                            .
                            strtoupper(
                                $currentImageStatus
                            )
                            .
                            '.',
                    ]);
                }


                $oldStatus =
                    $case->status;

                $now =
                    now();

                $imageCompleted =
                    $outcome
                    ===
                    'completed';


                /*
                |--------------------------------------------------------------------------
                | IMAGE RESULT
                |--------------------------------------------------------------------------
                */
                $metadata[
                    'image_collection_status'
                ] =
                    $outcome;


                /*
                |--------------------------------------------------------------------------
                | Backward-compatible flags
                |--------------------------------------------------------------------------
                */
                $metadata[
                    'image_collection_completed'
                ] =
                    $imageCompleted;

                $metadata[
                    'image_collection_cancelled'
                ] =
                    !$imageCompleted;


                $metadata[
                    'image_collection_completed_at'
                ] =
                    $imageCompleted
                        ? $now->toISOString()
                        : null;

                $metadata[
                    'image_collection_completed_by'
                ] =
                    $imageCompleted
                        ? $user->id
                        : null;


                $metadata[
                    'image_collection_cancelled_at'
                ] =
                    !$imageCompleted
                        ? $now->toISOString()
                        : null;

                $metadata[
                    'image_collection_cancelled_by'
                ] =
                    !$imageCompleted
                        ? $user->id
                        : null;

                $metadata[
                    'image_collection_cancel_reason'
                ] =
                    !$imageCompleted
                        ? trim(
                            (string)
                            $note
                        )
                        : null;


                /*
                |--------------------------------------------------------------------------
                | FINAL REVIEW WORKFLOW COMPLETION
                |--------------------------------------------------------------------------
                |
                | Even if image collection is Cancelled,
                | there is no more pending Review work.
                |
                | Therefore:
                |
                | OperationCase = COMPLETED
                |
                | Do NOT set OperationCase = CANCELLED.
                |
                | Otherwise this Lead could incorrectly appear in the
                | Operations Cancelled business dashboard.
                |
                */
                $case->update([
                    'status' =>
                        OperationCase::STATUS_COMPLETED,

                    'assigned_to' =>
                        $case->assigned_to
                            ?: $user->id,

                    'completed_by' =>
                        $user->id,

                    'completed_at' =>
                        $now,

                    'note' =>
                        $note
                            ?: $case->note,

                    'metadata' =>
                        $metadata,
                ]);


                /*
                |--------------------------------------------------------------------------
                | KPI AUDIT EVENT
                |--------------------------------------------------------------------------
                */
                $action =
                    $imageCompleted
                        ? 'image_collection_completed'
                        : 'image_collection_cancelled';


                $this->activity(
                    $case,
                    $action,
                    $oldStatus,
                    OperationCase::STATUS_COMPLETED,
                    $user->id,
                    $note,
                    [
                        'image_collection_status' =>
                            $outcome,

                        'image_collection_resolved_at' =>
                            $now->toISOString(),

                        'review_status' =>
                            $reviewStatus,
                    ]
                );


                return $case->fresh();
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | OPERATION CASE ACTIVITY
    |--------------------------------------------------------------------------
    |
    | Append-only audit trail used by:
    |
    | Operations history
    | Operations KPI
    | Review KPI
    | Image Collection KPI
    |
    */
    private function activity(
        OperationCase $case,
        string $action,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $userId = null,
        ?string $note = null,
        array $metadata = []
    ): OperationCaseActivity {
        return OperationCaseActivity::create([
            'operation_case_id' =>
                $case->id,

            'lead_id' =>
                $case->lead_id,

            'user_id' =>
                $userId,

            'action' =>
                $action,

            'from_status' =>
                $fromStatus,

            'to_status' =>
                $toStatus,

            'note' =>
                $note,

            'metadata' =>
                $metadata,
        ]);
    }
}