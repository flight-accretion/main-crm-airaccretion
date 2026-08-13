<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadTransfer;
use App\Models\UserType;
use App\Services\LeadTransferService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LeadTransferController extends Controller
{
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
             */
            $query->where(function ($q) use ($user) {
                $q->where(
                    'from_user_id',
                    $user->id
                )
                ->orWhere(
                    'requested_by',
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
}