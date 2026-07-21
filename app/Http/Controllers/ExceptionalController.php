<?php

namespace App\Http\Controllers;

use App\Models\LeadFollowup;
use App\Models\PaymentAuditTrail;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ExceptionalController extends Controller
{
    /**
     * Show dashboard entries where received_amount (sum of followups) > total_amount
     */
    public function index(Request $request)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        try {
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');

            // Important: evaluate exceptional entries at lead level, not only payment-status followups.
            // A later voucher/service update can reduce total_amount while received payment remains higher.
            $query = LeadFollowup::with(['enquiry.client', 'enquiry.rideSegments']);

            if ($fromDate && $toDate) {
                $query->whereBetween('created_at', [Carbon::parse($fromDate)->startOfDay(), Carbon::parse($toDate)->endOfDay()]);
            } elseif ($fromDate) {
                $query->where('created_at', '>=', Carbon::parse($fromDate)->startOfDay());
            } elseif ($toDate) {
                $query->where('created_at', '<=', Carbon::parse($toDate)->endOfDay());
            }

            $allPayments = $query->orderBy('created_at', 'desc')->get();

            $payments = $allPayments
                ->groupBy('lead_id')
                ->map(function ($group) {
                    $sortedGroup = $group->sortByDesc('created_at')->values();
                    $latest = $sortedGroup->first();
                    $client = $latest->enquiry->client ?? null;
                    $ride = $latest->enquiry->rideSegments->first() ?? null;
                    // Use latest followup having a valid total amount.
                    // If latest status row has 0/null total, fallback to previous non-zero total.
                    $latestWithAmount = $sortedGroup->first(function ($f) {
                        return isset($f->total_amount) && (float) $f->total_amount > 0;
                    });
                    $effectiveTotalFollowup = $latestWithAmount ?: $latest;
                    $totalAmount = (float) ($effectiveTotalFollowup->total_amount ?? 0);

                    $totalReceivedFromFollowups = (float) $group->sum(function ($g) {
                        return (float) ($g->received_amount ?? 0);
                    });

                    // Get approved received amount too (for reference)
                    $approvedPayments = PaymentAuditTrail::whereIn('lead_followup_id', $group->pluck('id'))
                        ->where('payment_status', 1)
                        ->get();
                    $totalReceivedApproved = $approvedPayments->sum('paid_amount');

                    // Prefer approved audit total; fallback to received_amount sum for older data.
                    $effectiveReceived = $totalReceivedApproved > 0 ? (float) $totalReceivedApproved : $totalReceivedFromFollowups;

                    // Check if a refund note already exists for the effective total followup
                    $existingRefund = \App\Models\LeadRefund::where('lead_followup_id', $effectiveTotalFollowup->id)->first();

                    return (object) [
                        'followup_id' => $effectiveTotalFollowup->id,
                        'lead_id' => $latest->lead_id,
                        'first_name' => $client->name ?? '',
                        'phone_number' => $client->contact_number ?? '',
                        'email' => $client->email ?? '',
                        'from_date' => $ride->from_date ?? null,
                        'total_amount' => $totalAmount,
                        'received_amount' => $effectiveReceived,
                        'received_amount_approved' => $totalReceivedApproved,
                        'balance' => $totalAmount - $effectiveReceived,
                        'service_ids' => $effectiveTotalFollowup->service_ids,
                        'has_refund_note' => $existingRefund ? true : false,
                        'refund_id' => $existingRefund ? $existingRefund->id : null,
                        'added_to_sales' => (bool) $effectiveTotalFollowup->added_to_sales,
                        'created_at' => $latest->created_at,
                    ];
                })
                // Keep only cases where received > total (and total > 0)
                ->filter(function ($item) {
                    return isset($item->total_amount) && $item->total_amount > 0 && (float) $item->received_amount > (float) $item->total_amount;
                })
                ->values();

            $services = Service::select('id', 'service')->get();

            return view('admin.account.exceptional.exceptional', compact('payments', 'services', 'fromDate', 'toDate'));
        } catch (\Exception $e) {
            Log::error('Error loading exceptional dashboard: ' . $e->getMessage());
            return view('admin.account.exceptional.exceptional', [
                'payments' => collect(),
                'services' => collect(),
                'fromDate' => null,
                'toDate' => null
            ]);
        }
    }

    /**
     * Show edit total amount page for a followup (edits latest followup total for that lead)
     */
    // public function editTotal($followupId)
    // {
    //     $followup = LeadFollowup::with('enquiry.client')->findOrFail($followupId);

    //     // get latest followup for this lead (we will update that)
    //     $latestFollowup = LeadFollowup::where('lead_id', $followup->lead_id)->orderBy('created_at', 'desc')->first();

    //     return view('admin.account.exceptional.edit-total', ['followup' => $followup, 'latest' => $latestFollowup]);
    // }

    /**
     * Update total amount on latest followup for the lead
     */
    public function updateTotal(Request $request, $followupId)
    {
        $request->validate([
            'total_amount' => 'required|numeric|min:0'
        ]);

        // Update the specific followup passed (not an implicitly-determined "latest"),
        // so the UI that opened the edit for that followup will reflect the change.
        $followup = LeadFollowup::findOrFail($followupId);
        $newTotal = $request->input('total_amount');
        Log::info('ExceptionalController@updateTotal: updating specific followup total', [
            'followup_id' => $followupId,
            'old_total' => $followup->total_amount,
            'new_total' => $newTotal,
        ]);

        try {
            $followup->total_amount = $newTotal;
            $saved = $followup->save();
            Log::info('ExceptionalController@updateTotal: save result', ['followup_id' => $followupId, 'saved' => $saved]);
        } catch (\Throwable $e) {
            Log::error('ExceptionalController@updateTotal: save failed', ['error' => $e->getMessage(), 'followupId' => $followupId]);
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Failed to update total: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Failed to update total.');
        }

        // If this was an AJAX/JSON request, return JSON so client-side JS can handle it
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Total amount updated successfully.',
                'followup' => $followup
            ], 200);
        }

        return redirect()->route('admin.account.exceptional')->with('success', 'Total amount updated successfully.');
    }

    /**
     * Create a refund note for excess payment without canceling the followup
     */
    public function createRefundNote(Request $request)
    {
        if (!auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $request->validate([
                'followup_id' => 'required|exists:lead_followups,id',
                'refund_amount' => 'required|numeric|min:0.01',
                'refund_reason' => 'nullable|string',
            ]);

            $followupId = $request->input('followup_id');
            $refundAmount = $request->input('refund_amount');
            $refundReason = $request->input('refund_reason', 'Excess payment refund from exceptional dashboard');

            $followup = LeadFollowup::with('enquiry')->findOrFail($followupId);

            // Get the total amount from followup
            $originalAmount = (float) ($followup->total_amount ?? 0);

            // Check if a refund already exists for this followup
            $existingRefund = \App\Models\LeadRefund::where('lead_followup_id', $followupId)->first();

            if ($existingRefund) {
                Log::info('Refund note already exists for followup', ['followup_id' => $followupId, 'refund_id' => $existingRefund->id]);
                return response()->json([
                    'success' => true,
                    'message' => 'Refund note already exists',
                    'refund_id' => $existingRefund->id,
                    'followup_id' => $followupId
                ]);
            }

            // Create new refund note
            $refund = \App\Models\LeadRefund::create([
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'lead_followup_id' => $followupId,
                'original_amount' => $originalAmount,
                'refund_amount' => $refundAmount,
                'refund_reason' => $refundReason,
                'status' => 0, // Pending status
            ]);

            Log::info('Refund note created from exceptional dashboard', [
                'refund_id' => $refund->id,
                'followup_id' => $followupId,
                'refund_amount' => $refundAmount,
                'original_amount' => $originalAmount
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Refund note created successfully',
                'refund_id' => $refund->id,
                'followup_id' => $followupId
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed in createRefundNote', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating refund note from exceptional dashboard: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error creating refund note: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * "Add to Sales" action from the Exceptional Dashboard.
     *
     * Business rule:
     *   - Service amount (total_amount) = ₹35,000  → currently in achieved sales
     *   - Client paid (received_amount)  = ₹40,000  → ₹5,000 extra sitting in exceptional
     *   - Admin decides NOT to refund the extra ₹5,000
     *   - We set total_amount = received_amount (₹40,000) so that the full amount
     *     now flows into the DashboardController achieved calculation.
     *   - We flag added_to_sales = true so the button permanently switches to a
     *     "done" state across page reloads.
     */
    public function addToSales(Request $request, $followupId)
    {
        if (!auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $followup = LeadFollowup::findOrFail($followupId);

            // Guard: already processed
            if ($followup->added_to_sales) {
                return response()->json([
                    'success' => false,
                    'message' => 'This entry has already been added to sales.',
                ], 422);
            }

            $oldTotal = (float) ($followup->total_amount ?? 0);

            // ── Compute effectiveReceived the same way index() does ──────────────
            // The displayed "Received" on the dashboard is NOT simply followup->received_amount;
            // it is the sum of approved PaymentAuditTrail entries for ALL followups of this lead.
            // Reading just the raw column would give the wrong number (often = total_amount).
            $allFollowupIds = LeadFollowup::where('lead_id', $followup->lead_id)->pluck('id');

            $approvedTotal = PaymentAuditTrail::whereIn('lead_followup_id', $allFollowupIds)
                ->where('payment_status', 1)
                ->sum('paid_amount');

            $followupReceivedSum = LeadFollowup::whereIn('id', $allFollowupIds)->sum('received_amount');

            // Mirror index(): prefer approved audit total; fall back to followup sum for older data
            $effectiveReceived = $approvedTotal > 0 ? (float) $approvedTotal : (float) $followupReceivedSum;

            $extraAmount = $effectiveReceived - $oldTotal;   // e.g. ₹31k - ₹26.5k = ₹4.5k

            if ($extraAmount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No extra amount found to add to sales.',
                ], 422);
            }

            // Update total_amount to effectiveReceived so the dashboard picks up the full amount.
            $followup->total_amount   = $effectiveReceived;
            $followup->added_to_sales = true;
            $followup->save();

            Log::info('ExceptionalController@addToSales: extra amount accepted into sales', [
                'followup_id'       => $followupId,
                'old_total'         => $oldTotal,
                'new_total'         => $effectiveReceived,
                'approved_audit'    => $approvedTotal,
                'extra_added'       => $extraAmount,
                'added_by'          => auth()->id(),
            ]);

            return response()->json([
                'success'     => true,
                'message'     => 'Extra ₹' . number_format($extraAmount, 2) . ' has been added to sales.',
                'followup_id' => $followupId,
                'new_total'   => $effectiveReceived,
                'extra_added' => $extraAmount,
            ]);

        } catch (\Exception $e) {
            Log::error('ExceptionalController@addToSales: error', [
                'followup_id' => $followupId,
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error adding to sales: ' . $e->getMessage(),
            ], 500);
        }
    }
}
