<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadTransfer;
use App\Models\UserType;
use App\Services\LeadTransferService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Services\LeadAllocationService;
use function App\Helpers\getRepresentativeIds;

class LeadTransferController extends Controller
{
    public function search(Request $request)
    {
        $actor = $request->user();
        $role = optional($actor->userType)->user_type;
        $isSuperAdmin = $role === UserType::SUPER_ADMIN;
        abort_unless((int) $actor->status === 1 && ($isSuperAdmin || in_array($role, UserType::SALES_ROLES, true)), 403);
        $input = $request->validate(['search' => 'required|string|min:3|max:100']);
        $term = trim($input['search']);
        // Name prefixes and meaningful phone fragments only; no wildcard browsing.
        if (mb_strlen($term) < 3 || preg_match('/[%_\\\\]/u', $term)) {
            throw ValidationException::withMessages(['search' => 'Enter at least 3 characters without wildcard symbols.']);
        }
        $phone = preg_replace('/[\s()+-]/', '', $term);
        $isPhone = ctype_digit($phone);
        if ($isPhone && strlen($phone) < 6) {
            throw ValidationException::withMessages(['search' => 'Enter at least 6 digits of the phone number.']);
        }
        $leads = Lead::query()->select(['id', 'client_id', 'representative_user_id', 'product_ids'])
            ->with(['client:id,name,contact_number', 'representative:id,name',
                'rideSegments:id,lead_id,from_date', 'pendingTransfers:id,lead_id,requested_by'])
            ->whereHas('client', function ($query) use ($term, $phone, $isPhone) {
                if ($isPhone) $query->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(contact_number, ' ', ''), '+', ''), '-', ''), '(', ''), ')', '') LIKE ?", ['%' . $phone . '%']);
                else $query->whereRaw('LOWER(name) LIKE ?', [mb_strtolower($term) . '%']);
            })->orderByDesc('created_at')->limit(20)->get();
        $productIds = $leads->flatMap(fn ($lead) => $lead->product_ids_array)->unique()->values();
        $products = \App\Models\Product::whereIn('id', $productIds)->pluck('product', 'id');
        $rows = $leads->map(function ($lead) use ($actor, $isSuperAdmin, $products) {
            $own = (string) $lead->representative_user_id === (string) $actor->id;
            $pending = $lead->pendingTransfers->first();
            $action = $pending ? 'pending' : (empty($lead->representative_user_id) ? ($isSuperAdmin ? 'assign' : 'claim') : ($isSuperAdmin ? 'assign' : ($own ? 'offer' : 'request')));
            return ['id' => $lead->id, 'name' => optional($lead->client)->name ?? 'N/A',
                'phone' => optional($lead->client)->contact_number ?? 'N/A',
                'products' => collect($lead->product_ids_array)->map(fn ($id) => $products[$id] ?? null)->filter()->implode(', ') ?: 'N/A',
                'service_date' => optional($lead->rideSegments->sortBy('from_date')->first())->from_date,
                'owner' => optional($lead->representative)->name ?? 'Unassigned', 'own' => $own, 'action' => $action,
                'action_url' => $action === 'request' ? route('admin.leads.transfer.store', $lead->id)
                    : ($action === 'offer' ? route('admin.leads.transfer.offer-bulk')
                    : ($action === 'assign' ? route('admin.leads.transfer.direct-assign')
                    : ($action === 'claim' ? route('admin.leads.transfer.claim', $lead->id) : null)))];
        });
        $recipients = User::where('status', 1)->where('id', '!=', $actor->id)
            ->whereHas('userType', fn ($query) => $query->whereIn('user_type', UserType::SALES_ROLES))
            ->orderBy('name')->get(['id', 'name']);
        return response()->json(['leads' => $rows, 'recipients' => $recipients->map->only(['id', 'name']), 'limit' => 20])
            ->header('Cache-Control', 'private, no-store');
    }

    public function claim(Request $request, Lead $lead, LeadTransferService $service, LeadAllocationService $allocationService)
    {
        $actor = $request->user();
        abort_unless($actor && (int) $actor->status === 1
            && in_array(optional($actor->userType)->user_type, UserType::SALES_ROLES, true), 403);
        try {
            // The recipient is always the authenticated actor, never a posted user ID.
            $result = $service->claimUnassigned($lead, $actor);
        } catch (ValidationException $error) {
            return back()->withErrors($error->errors());
        }

        $warning = false;
        if ($result['was_queued'] ?? false) {
            try {
                $allocationService->finalizeManualQueuedAssignment($lead->fresh(), $actor, $result['queue_reason'] ?? null);
            } catch (\Throwable $error) {
                $warning = true;
                \Log::error('Self-assigned queued lead handoff failed', ['lead_id' => $lead->id, 'user_id' => $actor->id, 'error' => $error->getMessage()]);
            }
        }
        return back()->with('success', 'Lead assigned to you successfully.' . ($warning ? ' Source handoff notification needs review.' : ''));
    }

    /**
     * Transfer Leads page.
     */
    public function index()
    {
        $user = auth()->user();

        $userType =
            optional($user->userType)->user_type;

        $isSuperAdmin =
            $userType === UserType::SUPER_ADMIN;

        $isSalesUser =
            in_array(
                $userType,
                UserType::SALES_ROLES,
                true
            );

        if (!$isSuperAdmin && !$isSalesUser) {
            abort(403);
        }

        $query = LeadTransfer::query()
            ->with([
                'lead.client',
                'lead.representative',
                'fromUser',
                'toUser',
                'requestedBy',
                'respondedBy',
            ])
            ->orderByDesc('created_at');

        /*
         * Super Admin sees everything.
         */
        if (!$isSuperAdmin) {

            /*
             * Sales users see:
             *
             * 1. Requests for leads they currently own.
             * 2. Requests they personally created.
             * 3. Leads offered to them for approval.
             */
            $query->where(function ($q) use ($user) {
                $q->where(
                    'from_user_id',
                    $user->id
                )
                ->orWhere(
                    'requested_by',
                    $user->id
                )
                ->orWhere(
                    'to_user_id',
                    $user->id
                );
            });
        }

       $transfers = $query->get();

        return view(
            'admin.pages.leads.transfer-leads',
            compact(
                'transfers',
                'isSuperAdmin'
            )
        );
    }

    public function pendingCount(
        LeadTransferService $service
    ) {
        $user = auth()->user();

        if (!$user) {
            abort(403);
        }

        $userType =
            optional($user->userType)->user_type;

        $isSuperAdmin =
            $userType === UserType::SUPER_ADMIN;

        $isSalesUser =
            in_array(
                $userType,
                UserType::SALES_ROLES,
                true
            );

        if (!$isSuperAdmin && !$isSalesUser) {
            abort(403);
        }

        return response()->json([
            'count' =>
                $service->pendingActionCountFor($user),
        ]);
    }

    /**
     * Request ONE lead for logged-in salesperson.
     */
    public function store(
        Request $request,
        Lead $lead,
        LeadTransferService $service
    ) {
        $validated = $request->validate([
            'reason' =>
                'nullable|string|max:1000',
        ]);

        try {

            $service->requestForSelf(
                $lead,
                auth()->user(),
                $validated['reason']
                    ?? 'Lead access requested.'
            );

            return back()->with(
                'success',
                'Lead transfer request sent successfully. The current lead owner must approve it before the lead is assigned to you.'
            );

        } catch (ValidationException $e) {

            return back()
                ->withErrors($e->errors())
                ->withInput();

        } catch (\Throwable $e) {

            \Log::error(
                'Lead transfer request failed',
                [
                    'lead_id' =>
                        $lead->id,

                    'user_id' =>
                        auth()->id(),

                    'error' =>
                        $e->getMessage(),
                ]
            );

            return back()->with(
                'error',
                'The lead request could not be created. Please try again.'
            );
        }
    }

    /**
     * Request MULTIPLE leads at once.
     */
    public function bulkStore(
        Request $request,
        LeadTransferService $service
    ) {
        $validated = $request->validate([
            'lead_ids' =>
                'required|array|min:1|max:100',

            'lead_ids.*' =>
                'required|uuid|exists:leads,id',

            'reason' =>
                'nullable|string|max:1000',
        ]);

        $leadIds =
            array_values(
                array_unique(
                    $validated['lead_ids']
                )
            );

        $created = 0;
        $skipped = 0;

        foreach ($leadIds as $leadId) {

            try {

                $lead = Lead::findOrFail(
                    $leadId
                );

                $service->requestForSelf(
                    $lead,
                    auth()->user(),
                    $validated['reason']
                        ?? 'Bulk lead access request.'
                );

                $created++;

            } catch (ValidationException $e) {

                /*
                 * Another user may already have requested the lead,
                 * ownership may have changed, etc.
                 *
                 * Don't break the entire bulk request.
                 */
                $skipped++;

            } catch (\Throwable $e) {

                \Log::error(
                    'Bulk lead transfer request failed',
                    [
                        'lead_id' =>
                            $leadId,

                        'user_id' =>
                            auth()->id(),

                        'error' =>
                            $e->getMessage(),
                    ]
                );

                $skipped++;
            }
        }

        if ($created === 0) {
            return back()->with(
                'error',
                'No lead transfer requests were created. The selected leads may already belong to you or already have pending requests.'
            );
        }

        $message =
            $created .
            ' lead transfer request(s) created successfully.';

        if ($skipped > 0) {
            $message .=
                ' ' .
                $skipped .
                ' lead(s) were skipped.';
        }

        return back()->with(
            'success',
            $message
        );
    }

    public function bulkOffer(
        Request $request,
        LeadTransferService $service
    ) {
        $validated = $request->validate([
            'lead_ids' =>
                'required|array|min:1|max:100',

            'lead_ids.*' =>
                'required|uuid|exists:leads,id',

            'representative_user_id' =>
                'required|uuid|exists:users,id',

            'reason' =>
                'nullable|string|max:1000',
        ]);

        $recipient = User::query()
            ->with('userType')
            ->findOrFail(
                $validated['representative_user_id']
            );

        $leadIds =
            array_values(
                array_unique(
                    $validated['lead_ids']
                )
            );

        $created = 0;
        $skipped = 0;
        $lastSkipReason = null;

        foreach ($leadIds as $leadId) {

            try {

                $lead = Lead::findOrFail(
                    $leadId
                );

                $service->requestFromOwner(
                    $lead,
                    $recipient,
                    auth()->user(),
                    $validated['reason']
                        ?? 'Lead transfer requested from report.'
                );

                $created++;

            } catch (ValidationException $e) {

                $skipped++;

                $lastSkipReason =
                    collect(
                        $e->errors()
                    )
                    ->flatten()
                    ->first();

            } catch (\Throwable $e) {

                \Log::error(
                    'Bulk owner lead transfer request failed',
                    [
                        'lead_id' =>
                            $leadId,

                        'recipient_user_id' =>
                            $recipient->id,

                        'user_id' =>
                            auth()->id(),

                        'error' =>
                            $e->getMessage(),
                    ]
                );

                $skipped++;
            }
        }

        if ($created === 0) {
            return back()->with(
                'error',
                $lastSkipReason
                ?: 'No lead transfer requests were created. Please select your own leads without pending transfer requests.'
            );
        }

        $message =
            $created .
            ' lead transfer request(s) sent to ' .
            $recipient->name .
            '.';

        if ($skipped > 0) {
            $message .=
                ' ' .
                $skipped .
                ' lead(s) were skipped.';
        }

        return back()->with(
            'success',
            $message
        );
    }

    public function accept(
        LeadTransfer $transfer,
        LeadTransferService $service
    ) {
        try {

            $service->accept(
                $transfer,
                auth()->user()
            );

            return back()->with(
                'success',
                'Lead transfer approved successfully.'
            );

        } catch (ValidationException $e) {

            return back()->withErrors(
                $e->errors()
            );

        } catch (\Throwable $e) {

            \Log::error(
                'Lead transfer approval failed',
                [
                    'transfer_id' =>
                        $transfer->id,

                    'user_id' =>
                        auth()->id(),

                    'error' =>
                        $e->getMessage(),
                ]
            );

            return back()->with(
                'error',
                'The lead transfer could not be approved. Please try again.'
            );
        }
    }

    public function reject(
        Request $request,
        LeadTransfer $transfer,
        LeadTransferService $service
    ) {
        $validated = $request->validate([
            'response_note' =>
                'nullable|string|max:1000',
        ]);

        try {

            $service->reject(
                $transfer,
                auth()->user(),
                $validated['response_note']
                    ?? null
            );

            return back()->with(
                'success',
                'Lead transfer request rejected.'
            );

        } catch (ValidationException $e) {

            return back()->withErrors(
                $e->errors()
            );

        } catch (\Throwable $e) {

            \Log::error(
                'Lead transfer rejection failed',
                [
                    'transfer_id' =>
                        $transfer->id,

                    'user_id' =>
                        auth()->id(),

                    'error' =>
                        $e->getMessage(),
                ]
            );

            return back()->with(
                'error',
                'The lead transfer could not be rejected. Please try again.'
            );
        }
    }

    /**
 * Super Admin direct assignment/reassignment.
 *
 * Supports one or multiple leads.
 */
public function directAssign(
    Request $request,
    LeadTransferService $transferService,
    LeadAllocationService $allocationService
) {

    $actor = auth()->user();

    /*
     * Backend authorization.
     *
     * Do not rely only on hiding the Blade button.
     */
    $actorRole = optional($actor?->userType)->user_type;

    $isAdminOrOperations = $actor && (
        in_array($actorRole, UserType::ADMIN_ROLES, true)
        || in_array($actorRole, UserType::OPERATIONS_ROLES, true)
    );

    $isSalesManager = $actor && in_array($actorRole, [
        UserType::SENIOR_SALES_MANAGER,
        UserType::SALES_MANAGER,
    ], true);

    abort_unless(
        $isAdminOrOperations || $isSalesManager,
        403
    );

    $validated = $request->validate([
        'lead_ids' =>
            'required|array|min:1|max:100',

        'lead_ids.*' =>
            'required|uuid|exists:leads,id',

        'representative_user_id' =>
            'required|uuid|exists:users,id',
    ]);

    /*
     * Destination representative:
     * active Sales role only.
     */
    $representative =
        User::query()
            ->with('userType')
            ->where(
                'id',
                $validated[
                    'representative_user_id'
                ]
            )
            ->first();

    if (
        !$representative
        ||
        (int) $representative->status !== 1
        ||
        !in_array(
            optional(
                $representative->userType
            )->user_type,
            UserType::SALES_ROLES,
            true
        )
    ) {

        return back()->with(
            'error',
            'Please select an active Sales Manager or Sales Executive.'
        );
    }

    $allowedRepresentativeIds = null;

    if ($isSalesManager) {
        $allowedRepresentativeIds = getRepresentativeIds($actor);

        if ($allowedRepresentativeIds instanceof \Illuminate\Support\Collection) {
            $allowedRepresentativeIds = $allowedRepresentativeIds->toArray();
        }

        $allowedRepresentativeIds = array_map(
            'strval',
            $allowedRepresentativeIds ?: []
        );

        if (!in_array((string) $representative->id, $allowedRepresentativeIds, true)) {
            return back()->with(
                'error',
                'You can only assign leads to sales users inside your own team.'
            );
        }
    }

    $leadIds =
        array_values(
            array_unique(
                $validated['lead_ids']
            )
        );

    $transferred = 0;
    $skipped = 0;
    $handoffWarnings = 0;
    $lastSkipReason = null;

    foreach ($leadIds as $leadId) {

        try {

            $lead =
                Lead::findOrFail(
                    $leadId
                );

            if ($allowedRepresentativeIds !== null) {
                if (
                    empty($lead->representative_user_id)
                    || !in_array((string) $lead->representative_user_id, $allowedRepresentativeIds, true)
                ) {
                    $skipped++;
                    $lastSkipReason = 'One or more selected leads are outside your team scope.';
                    continue;
                }
            }

            $result =
                $transferService->directAssign(
                    $lead,
                    $representative,
                    $actor
                );

            if (
                !($result['changed'] ?? false)
            ) {

                $skipped++;

                $lastSkipReason =
                    'One or more selected leads were already assigned to the selected representative.';

                continue;
            }

            $transferred++;

            /*
             * A queued lead needs the same source
             * handoff which normal allocation performs.
             */
            if (
                $result['was_queued']
                ?? false
            ) {

                try {

                    $allocationService
                        ->finalizeManualQueuedAssignment(
                            $lead->fresh(),
                            $representative,
                            $result[
                                'queue_reason'
                            ] ?? null
                        );

                } catch (\Throwable $e) {

                    $handoffWarnings++;

                    \Log::error(
                        'Queued lead manual assignment handoff failed',
                        [
                            'lead_id' =>
                                $leadId,

                            'representative_user_id' =>
                                $representative->id,

                            'super_admin_id' =>
                                $actor->id,

                            'error' =>
                                $e->getMessage(),
                        ]
                    );
                }
            }

            $transferService
                ->recordDirectAssignmentFollowup(
                    $lead->fresh(),
                    $representative,
                    $actor,
                    $result[
                        'old_representative_id'
                    ] ?? null
                );

        } catch (
            ValidationException $e
        ) {

            $skipped++;

            $lastSkipReason =
                collect(
                    $e->errors()
                )
                ->flatten()
                ->first();

        } catch (\Throwable $e) {

            $skipped++;

            \Log::error(
                'Super Admin direct lead transfer failed',
                [
                    'lead_id' =>
                        $leadId,

                    'representative_user_id' =>
                        $representative->id,

                    'super_admin_id' =>
                        $actor->id,

                    'error' =>
                        $e->getMessage(),
                ]
            );
        }
    }

    if ($transferred === 0) {

        return back()->with(
            'error',
            $lastSkipReason
            ?: 'No selected leads were transferred.'
        );
    }

    $message =
        $transferred .
        ' lead(s) transferred successfully to ' .
        $representative->name .
        '.';

    if ($skipped > 0) {

        $message .=
            ' ' .
            $skipped .
            ' lead(s) were skipped.';
    }

    if ($handoffWarnings > 0) {

        $message .=
            ' ' .
            $handoffWarnings .
            ' queued lead handoff notification(s) need review.';
    }

    return back()->with(
        'success',
        $message
    );
}


}
