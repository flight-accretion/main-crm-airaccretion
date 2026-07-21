<?php

namespace App\Http\Controllers;

use App\Models\LeadFollowup;
use App\Models\Service;
use App\Models\ExtraService;
use App\Models\PaymentAuditTrail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Exports\PaymentReportExport;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class PaymentReviewController extends Controller
{
    public function index(Request $request)
    {
        // Ensure user is authenticated
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        try {
            // Get query parameters for filtering
            $serviceDate = $request->input('service_date');
            $serviceName = $request->input('service_name');
            $paymentStatus = $request->input('payment_status'); // Approval status: pending, approved, rejected
            if ($request->has('status')) {
                $rawStatus = $request->query('status', null);
                $status = ($rawStatus === null || $rawStatus === '') ? 'all' : intval($rawStatus);
            } else {
                $status = null;
            }
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');
            $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

            // Base query for payment-related followups
            $query = LeadFollowup::with([
                'enquiry.client.country',
                'enquiry.client.city',
                'enquiry.rideSegments'
            ])
                ->whereNotNull('received_amount')
                ->where('received_amount', '>', 0)
                ->whereIn('status', [3, 4]);

            if ($fromDate && $toDate) {
                $query->whereBetween('created_at', [
                    Carbon::parse($fromDate)->startOfDay(),
                    Carbon::parse($toDate)->endOfDay()
                ]);
            } elseif ($fromDate) {
                $query->where('created_at', '>=', Carbon::parse($fromDate)->startOfDay());
            } elseif ($toDate) {
                $query->where('created_at', '<=', Carbon::parse($toDate)->endOfDay());
            }
            // Apply service date filter
            if ($serviceDate) {
                $query->whereHas('enquiry.rideSegments', function ($q) use ($serviceDate) {
                    $q->whereDate('from_date', $serviceDate);
                });
            }

            // Apply service name filter
            if ($serviceName) {
                $query->where(function ($q) use ($serviceName) {
                    // Handle both array and JSON string formats
                    $q->where('service_ids', 'like', '%' . $serviceName . '%')
                        ->orWhere('service_ids', 'like', '%"' . $serviceName . '"%');
                });
            }

            // Apply status filter - use normalized $status ('all' means no DB filter)
            if ($status !== null && $status !== 'all') {
                $query->where('status', $status);
            }

            // Apply payment status filter (approval status from audit trail)
            if ($paymentStatus && $paymentStatus !== '') {
                if ($paymentStatus === 'approved') {
                    $query->whereHas('paymentAuditTrail', function ($q) {
                        $q->where('payment_status', 1);
                    });
                } elseif ($paymentStatus === 'rejected') {
                    $query->whereHas('paymentAuditTrail', function ($q) {
                        $q->where('payment_status', 2);
                    });
                } elseif ($paymentStatus === 'pending') {
                    $query->whereDoesntHave('paymentAuditTrail', function ($q) {
                        $q->whereIn('payment_status', [1, 2]);
                    });
                }
            }

            $paymentsPaginator = (clone $query)
                ->setEagerLoads([])
                ->select('lead_id')
                ->selectRaw('MAX(created_at) as latest_created_at')
                ->groupBy('lead_id')
                ->orderByDesc('latest_created_at')
                ->paginate($perPage)
                ->appends($request->query());

            $pageLeadIds = collect($paymentsPaginator->items())
                ->pluck('lead_id')
                ->filter()
                ->values();

            $allPayments = collect();
            if ($pageLeadIds->isNotEmpty()) {
                $allPayments = (clone $query)
                    ->whereIn('lead_id', $pageLeadIds->all())
                    ->orderBy('created_at', 'desc')
                    ->get();
            }

            // Group by lead_id and process
            $mappedPayments = $allPayments
                ->groupBy('lead_id')
                ->map(function ($group) {
                    // Get the latest followup entry
                    $latest = $group->sortByDesc('created_at')->first();
                    $client = $latest->enquiry->client ?? null;
                    $ride = $latest->enquiry->rideSegments->first() ?? null;
                    $totalAmount = (float) $latest->total_amount;

                    // Calculate received amount only from approved payments (audit trail status = 1)
                    $approvedPayments = PaymentAuditTrail::whereIn('lead_followup_id', $group->pluck('id'))
                        ->where('payment_status', 1) // Only approved payments
                        ->get();

                    $totalReceivedApproved = $approvedPayments->sum('paid_amount');

                    // Also calculate total received as the sum of received_amount fields on followup records
                    // This represents the receipts submitted by users (may be unreviewed)
                    $totalReceivedFromFollowups = (float) $group->sum(function ($g) {
                        return (float) ($g->received_amount ?? 0);
                    });

                    // Determine whether ALL payment followups in this group have been
                    // reviewed (i.e. have an audit entry with payment_status 1 or 2).
                    // We only want to remove the lead from listing when every payment
                    // followup for that lead has been approved/rejected. If some remain
                    // unreviewed, the lead should stay visible.
                    $followupIds = $group->pluck('id')->toArray();
                    $reviewedFollowupCount = PaymentAuditTrail::whereIn('lead_followup_id', $followupIds)
                        ->whereIn('payment_status', [1, 2])
                        ->distinct()
                        ->count('lead_followup_id');

                    $allFollowupsReviewed = count($followupIds) > 0 && $reviewedFollowupCount >= count($followupIds);

                    // Determine payment status based on followup receipts (what's been received regardless of audit)
                    $paymentStatus = 'Unpaid';
                    if ($totalReceivedFromFollowups >= $totalAmount && $totalAmount > 0) {
                        $paymentStatus = 'Full Paid';
                    } elseif ($totalReceivedFromFollowups > 0) {
                        $paymentStatus = 'Partial Paid';
                    }

                    // Get latest audit trail
                    $latestAudit = PaymentAuditTrail::where('lead_followup_id', $latest->id)
                        ->orderBy('created_at', 'desc')
                        ->first();

                    // Determine audit status: prefer latest audit. Do not infer approval from
                    // unreviewed followup receipts. If the approved payments already cover
                    // the total, mark as approved.
                    $auditStatus = $latestAudit ? $latestAudit->payment_status : null;
                    if ($totalAmount > 0 && $totalReceivedApproved >= $totalAmount) {
                        $auditStatus = 1; // approved
                    }

                    return (object) [
                        'followup_id' => $latest->id,
                        'lead_id' => $latest->lead_id,
                        'first_name' => $client->name ?? '',
                        'phone_number' => $client->contact_number ?? '',
                        'email' => $client->email ?? '',
                        'whatsapp_number' => $client->alternate_number ?? '',
                        'address' => $client->address ?? '',
                        'country_name' => $client->country->name ?? 'N/A',
                        'city_name' => $client->city->name ?? 'N/A',
                        'from_date' => $ride->from_date ?? null,
                        'to_date' => $ride->to_date ?? null,
                        'from_place' => $ride->from_place ?? '',
                        'to_place' => $ride->to_place ?? '',
                        'total_amount' => $totalAmount,
                        'received_amount' => $totalReceivedFromFollowups, // Sum of submitted receipts (may be unreviewed)
                        'received_amount_approved' => $totalReceivedApproved,
                        'balance' => $totalAmount - $totalReceivedFromFollowups,
                        'status' => $latest->status ?? 'pending',
                        'created_at' => $latest->created_at,
                        'payment_status' => $paymentStatus,
                        'service_ids' => $latest->service_ids,
                        'audit_status' => $auditStatus,
                        'all_followups_reviewed' => $allFollowupsReviewed
                    ];
                });

            // Prepare final payments collection - apply status-specific rules
            $payments = $mappedPayments
                ->filter(function ($item) use ($status) {
                    // If the caller explicitly selected "All", include everything
                    if ($status === 'all') {
                        return true;
                    }
                    // If the caller asked specifically for Full Paid or Partial Paid entries (status == 3 or 4), keep them
                    if ($status === 3 || $status === 4) {
                        return true;
                    }
                    // Otherwise hide leads whose all followups have already been reviewed
                    if (isset($item->all_followups_reviewed) && $item->all_followups_reviewed) {
                        return false;
                    }

                    return true;
                })
                ->values(); // Reset keys for view

            // Get services for filter dropdown (same as refund notes)
            $services = Service::select('id', 'service')->get();

            return view('admin.account.payment-review.payment-review', compact('payments', 'paymentsPaginator', 'services', 'serviceDate', 'serviceName', 'status', 'paymentStatus', 'fromDate', 'toDate'));
        } catch (\Exception $e) {
            Log::error("Error in payment review index: " . $e->getMessage());
            return view('admin.account.payment-review.payment-review', [
                'payments' => collect(),
                'services' => collect(),
                'serviceDate' => null,
                'serviceName' => null,
                'status' => null,
                'paymentStatus' => null
            ]);
        }
    }

    public function show($id)
    {
        // Ensure user is authenticated
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Get detailed payment information for the modal
        $followup = LeadFollowup::with([
            'enquiry.client.country',
            'enquiry.client.city',
            'enquiry.rideSegments'
        ])->findOrFail($id);

        // Get payment history for this lead from PaymentAuditTrail (to avoid duplicates)
        $paymentHistory = PaymentAuditTrail::with(['leadFollowup.followedBy'])
            ->whereHas('leadFollowup', function ($query) use ($followup) {
                $query->where('lead_id', $followup->lead_id);
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($auditTrail) {
                $followupRecord = $auditTrail->leadFollowup;

                // Determine status text based on audit trail payment_status
                $statusText = '';
                switch ($auditTrail->payment_status) {
                    case 1:
                        $statusText = 'Payment Approved';
                        break;
                    case 2:
                        $statusText = 'Payment Rejected';
                        break;
                    case 3:
                        $statusText = 'Full Payment Received';
                        break;
                    case 4:
                        $statusText = 'Partial Payment Received';
                        break;
                    default:
                        $statusText = 'Payment Pending';
                }

                return [
                    'id' => $followupRecord ? $followupRecord->id : $auditTrail->id,
                    'amount' => $auditTrail->paid_amount,
                    'total_amount' => $followupRecord ? $followupRecord->total_amount : 0,
                    'status' => $auditTrail->payment_status,
                    'status_text' => $statusText,
                    'created_at' => $auditTrail->created_at,
                    'followup_note' => $followupRecord ? $followupRecord->followup_note : $auditTrail->narration,
                    'file' => $followupRecord ? $followupRecord->file : null,
                    'created_by_name' => $followupRecord && $followupRecord->followedBy ? $followupRecord->followedBy->name : null,
                    'created_by_email' => $followupRecord && $followupRecord->followedBy ? $followupRecord->followedBy->email : null,
                    'created_by_id' => $auditTrail->created_by,
                    'payment_method' => $followupRecord ? $followupRecord->payment_method : $auditTrail->payment_method,
                    'paid_date' => $followupRecord ? $followupRecord->paid_date : $auditTrail->paid_date,
                    'audit_trail' => [
                        'payment_method' => $auditTrail->payment_method,
                        'paid_date' => $auditTrail->paid_date,
                        'narration' => $auditTrail->narration,
                        'payment_status' => $auditTrail->payment_status
                    ]
                ];
            });

        // Get services
        $services = [];
        if ($followup->service_ids) {
            $serviceIds = is_array($followup->service_ids) ? $followup->service_ids : json_decode($followup->service_ids, true);
            $services = Service::whereIn('id', $serviceIds)->get();
        }

        // Get extra services
        $extraServices = [];
        if ($followup->extra_service_ids) {
            $extraServiceIds = is_array($followup->extra_service_ids) ? $followup->extra_service_ids : json_decode($followup->extra_service_ids, true);
            $extraServices = ExtraService::whereIn('id', $extraServiceIds)->get();
        }

        $latestAudit = PaymentAuditTrail::where('lead_followup_id', $followup->id)
            ->orderBy('created_at', 'desc')
            ->first();

        return response()->json([
            'followup' => $followup,
            'client' => $followup->enquiry->client,
            'rides' => $followup->enquiry->rideSegments, // Send all ride segments
            'services' => $services,
            'extraServices' => $extraServices,
            'paymentHistory' => $paymentHistory,
            'latestAudit' => $latestAudit
        ]);
    }

    public function approve(Request $request, $id)
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validator = $request->validate([
            'payment_method' => 'required|string',
            'received_date' => 'required|date',
            'narration' => 'nullable|string'
        ]);

        if (!$validator) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed'
            ], 422);
        }

        $originalFollowup = LeadFollowup::findOrFail($id);

        DB::beginTransaction();
        try {
            // Parse received_date to Y-m-d format (start of day)
            $parsedPaidDate = null;
            try {
                $parsedPaidDate = \Carbon\Carbon::createFromFormat('Y-m-d', $request->received_date)->startOfDay()->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid date format. Please select a valid date.'
                ], 422);
            }

            // Get latest followup for this lead
            $latestFollowup = LeadFollowup::where('lead_id', $originalFollowup->lead_id)
                ->orderBy('created_at', 'desc')
                ->first();

            // Determine payment status based on amount comparison
            $isFullPayment = $originalFollowup->received_amount >= $originalFollowup->total_amount;

            // Create NEW followup for approval with correct status codes
            $newFollowup = LeadFollowup::create([
                'id' => Str::uuid(),
                'parent_followup_id' => $originalFollowup->id,
                'lead_id' => $latestFollowup->lead_id,
                'followed_by' => auth()->id(),
                'followup_note' => 'Payment Approved',
                'status' => 8, // 8 = Approve (as per your status codes)
                'received_amount' => $originalFollowup->received_amount,
                'total_amount' => $latestFollowup->total_amount,
                'service_ids' => $latestFollowup->service_ids,
                'extra_service_ids' => $latestFollowup->extra_service_ids,
                'file' => $latestFollowup->file,
                // Preserve service/discount details so approval doesn't lose per-service discounts
                'service_amount' => $latestFollowup->service_amount ?? null,
                'discount_amount' => $latestFollowup->discount_amount ?? null,
                'service_details' => $latestFollowup->service_details ?? null,
                'next_followup_date' => $latestFollowup->next_followup_date,
            ]);

            PaymentAuditTrail::where('lead_followup_id', $originalFollowup->id)
                ->update([
                    'payment_status' => 1, // 1 = Approve
                    'payment_method' => $request->payment_method,
                    'paid_date' => $parsedPaidDate,
                    'narration' => $request->narration,
                    'updated_at' => now()
                ]);

            Log::info('Payment approved successfully', [
                'followup_id' => $newFollowup->id,
                'paid_amount' => $originalFollowup->received_amount,
                'paid_date' => $request->received_date,
                'payment_method' => $request->payment_method,
                'narration' => $request->narration
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment approved successfully!',
                'new_followup_id' => $newFollowup->id
            ]);
        } catch (\Exception $e) {
            Log::error('Payment approval failed: ' . $e->getMessage());
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Error approving payment: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reject(Request $request, $id)
    {
        // Ensure user is authenticated
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'narration' => 'nullable|string'
        ]);

        $originalFollowup = LeadFollowup::findOrFail($id);

        DB::beginTransaction();
        try {
            // Get latest followup for this lead
            $latestFollowup = LeadFollowup::where('lead_id', $originalFollowup->lead_id)
                ->orderBy('created_at', 'desc')
                ->first();

            // Create NEW followup for rejection with correct status codes
            $newFollowup = LeadFollowup::create([
                'id' => Str::uuid(),
                'parent_followup_id' => $originalFollowup->id,
                'lead_id' => $latestFollowup->lead_id,
                'followed_by' => auth()->id(),
                'followup_note' => 'Payment Rejected',
                'status' => 9, // 9 = Reject (as per your status codes)
                'received_amount' => $originalFollowup->received_amount,
                'total_amount' => $latestFollowup->total_amount,
                'service_ids' => $latestFollowup->service_ids,
                'extra_service_ids' => $latestFollowup->extra_service_ids,
                'file' => $latestFollowup->file,
                // Preserve breakdown and discounts when creating rejection followup
                'service_amount' => $latestFollowup->service_amount ?? null,
                'discount_amount' => $latestFollowup->discount_amount ?? null,
                'service_details' => $latestFollowup->service_details ?? null,
                'next_followup_date' => $latestFollowup->next_followup_date,
            ]);

            // Create audit trail with correct status codes
            PaymentAuditTrail::create([
                'id' => Str::uuid(),
                'lead_followup_id' => $newFollowup->id,
                'paid_amount' => $originalFollowup->received_amount,
                'paid_date' => now(),
                'payment_method' => $request->payment_method ?? 'Manual Rejection',
                'narration' => $request->narration ?? 'Payment rejected',
                'payment_status' => 2, // 2 = Reject (audit trail status)
                'created_by' => auth()->id()
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment rejected successfully!',
                'new_followup_id' => $newFollowup->id
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting payment: ' . $e->getMessage()
            ], 500);
        }
    }

    public function approveHistory(Request $request, $id)
    {

        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'payment_method' => 'required|string',
            'received_date' => 'required|date',
            'narration' => 'nullable|string'
        ]);

        // Only Super Admin can select a past date
        $isSuperAdmin = (auth()->user()->userType->user_type ?? '') === \App\Models\UserType::SUPER_ADMIN;
        if (!$isSuperAdmin && $request->received_date < date('Y-m-d')) {
            return response()->json([
                'success' => false,
                'message' => 'Only Super Admin can select a past date.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Parse received_date to Y-m-d format (start of day)
            $parsedPaidDate = null;
            try {
                $parsedPaidDate = \Carbon\Carbon::createFromFormat('Y-m-d', $request->received_date)->startOfDay()->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid date format. Please select a valid date.'
                ], 422);
            }

            // Find the original payment followup entry
            $originalFollowup = LeadFollowup::findOrFail($id);

            // Get latest followup for this lead to copy all data
            $latestFollowup = LeadFollowup::where('lead_id', $originalFollowup->lead_id)
                ->orderBy('created_at', 'desc')
                ->first();

            // Create NEW followup entry for approval (don't update existing)
            $newFollowup = LeadFollowup::create([
                'id' => Str::uuid(),
                'parent_followup_id' => $originalFollowup->id,
                'lead_id' => $originalFollowup->lead_id,
                'followed_by' => auth()->id(),
                'followup_note' => 'Payment Approved (from history)',
                'status' => 8, // 8 = Approve
                'received_amount' => $originalFollowup->received_amount,
                'total_amount' => $latestFollowup->total_amount,
                'service_ids' => $latestFollowup->service_ids,
                'extra_service_ids' => $latestFollowup->extra_service_ids,
                'file' => $originalFollowup->file, // Keep original receipt file
                // Preserve service/discount details
                'service_amount' => $latestFollowup->service_amount ?? null,
                'discount_amount' => $latestFollowup->discount_amount ?? null,
                'service_details' => $latestFollowup->service_details ?? null,
                'next_followup_date' => $latestFollowup->next_followup_date,
            ]);

            PaymentAuditTrail::where('lead_followup_id', $originalFollowup->id)
                ->update([
                    'payment_status' => 1, // 1 = Approve
                    'payment_method' => $request->payment_method,
                    'paid_date' => $parsedPaidDate,
                    'narration' => $request->narration,
                    'updated_at' => now()
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment approved successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error approving payment history: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error approving payment: ' . $e->getMessage()
            ], 500);
        }
    }

    public function rejectHistory(Request $request, $id)
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'narration' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            // Find the original payment followup entry
            $originalFollowup = LeadFollowup::findOrFail($id);

            // Get latest followup for this lead to copy all data
            $latestFollowup = LeadFollowup::where('lead_id', $originalFollowup->lead_id)
                ->orderBy('created_at', 'desc')
                ->first();

            // Create NEW followup entry for rejection (don't update existing)
            $newFollowup = LeadFollowup::create([
                'id' => Str::uuid(),
                'parent_followup_id' => $originalFollowup->id,
                'lead_id' => $originalFollowup->lead_id,
                'followed_by' => auth()->id(),
                'followup_note' => 'Payment Rejected (from history)',
                'status' => 9, // 9 = Reject
                'received_amount' => $originalFollowup->received_amount,
                'total_amount' => $latestFollowup->total_amount,
                'service_ids' => $latestFollowup->service_ids,
                'extra_service_ids' => $latestFollowup->extra_service_ids,
                'file' => $originalFollowup->file, // Keep original receipt file
                // Preserve service breakdown and discounts
                'service_amount' => $latestFollowup->service_amount ?? null,
                'discount_amount' => $latestFollowup->discount_amount ?? null,
                'service_details' => $latestFollowup->service_details ?? null,
                'next_followup_date' => $latestFollowup->next_followup_date,
            ]);

            // Create new audit trail entry for the new followup
            PaymentAuditTrail::where('lead_followup_id', $originalFollowup->id)
                ->update([
                    'payment_status' => 2, // 2 = Reject
                    'paid_amount' => $originalFollowup->received_amount,
                    'paid_date' => now(),
                    'payment_method' => $request->payment_method ?? 'Manual Rejection',
                    'narration' => $request->narration ?? 'Payment rejected',
                    'updated_at' => now()
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment rejected successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error rejecting payment history: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting payment: ' . $e->getMessage()
            ], 500);
        }
    }

    public function approveAll(Request $request, $id)
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'payment_method' => 'required|string',
            'received_date' => 'required|date',
            'narration' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            $parsedPaidDate = null;
            try {
                $parsedPaidDate = \Carbon\Carbon::createFromFormat('Y-m-d', $request->received_date)->startOfDay()->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid date format. Please select a valid date.'
                ], 422);
            }

            $originalFollowup = LeadFollowup::findOrFail($id);

            // Get all payment-related followups for this lead that have partial/full payment status
            $paymentFollowups = LeadFollowup::where('lead_id', $originalFollowup->lead_id)
                ->whereNotNull('received_amount')
                ->where('received_amount', '>', 0)
                ->whereIn('status', [3, 4]) // Only Full Paid (3) and Partial Paid (4)
                ->get();

            // Filter to only include followups that don't have approved/rejected audit trails
            $followupsToProcess = $paymentFollowups->filter(function ($followup) {
                $auditTrail = PaymentAuditTrail::where('lead_followup_id', $followup->id)->first();
                // Only process if no audit trail exists OR audit trail is not approved (1) or rejected (2)
                return !$auditTrail || !in_array($auditTrail->payment_status, [1, 2]);
            });

            $processedCount = 0;
            foreach ($followupsToProcess as $followup) {
                // Update follow-up status to approve (8 = Approve)
                $followup->status = 8;
                $followup->followup_note = 'Payment Approved (Approve All)';
                $followup->save();

                // Find and update existing audit trail entry or create new one
                $auditTrail = PaymentAuditTrail::where('lead_followup_id', $followup->id)->first();

                if ($auditTrail) {
                    $auditTrail->payment_status = 1; // 1 = Approve
                    $auditTrail->payment_method = $request->payment_method;
                    $auditTrail->paid_date = $parsedPaidDate;
                    $auditTrail->narration = $request->narration ?? ($auditTrail->narration . ' [Approved via Approve All]');
                    $auditTrail->save();
                    Log::info('Audit trail updated for followup ID: ' . $followup->id);
                } else {
                    PaymentAuditTrail::create([
                        'id' => Str::uuid(),
                        'lead_followup_id' => $followup->id,
                        'paid_amount' => $followup->received_amount,
                        'paid_date' => $parsedPaidDate,
                        'payment_method' => $request->payment_method,
                        'narration' => $request->narration ?? 'Payment approved via Approve All',
                        'payment_status' => 1, // 1 = Approve
                        'created_by' => auth()->id()
                    ]);
                }
                $processedCount++;
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully approved {$processedCount} payments."
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error approving all payments: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error approving payments: ' . $e->getMessage()
            ], 500);
        }
    }

    public function rejectAll(Request $request, $id)
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'narration' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            $originalFollowup = LeadFollowup::findOrFail($id);

            // Get all payment-related followups for this lead that have partial/full payment status
            $paymentFollowups = LeadFollowup::where('lead_id', $originalFollowup->lead_id)
                ->whereNotNull('received_amount')
                ->where('received_amount', '>', 0)
                ->whereIn('status', [3, 4]) // Only Full Paid (3) and Partial Paid (4)
                ->get();

            // Filter to only include followups that don't have approved/rejected audit trails
            $followupsToProcess = $paymentFollowups->filter(function ($followup) {
                $auditTrail = PaymentAuditTrail::where('lead_followup_id', $followup->id)->first();
                // Only process if no audit trail exists OR audit trail is not approved (1) or rejected (2)
                return !$auditTrail || !in_array($auditTrail->payment_status, [1, 2]);
            });

            $processedCount = 0;
            foreach ($followupsToProcess as $followup) {
                // Update follow-up status to reject (9 = Reject)
                $followup->status = 9;
                $followup->followup_note = 'Payment Rejected (Reject All)';
                $followup->save();

                // Find and update existing audit trail entry or create new one
                $auditTrail = PaymentAuditTrail::where('lead_followup_id', $followup->id)->first();

                if ($auditTrail) {
                    $auditTrail->payment_status = 2; // 2 = Reject
                    $auditTrail->narration = ($auditTrail->narration ?? '') . ' [Rejected via Reject All]';
                    $auditTrail->save();
                    Log::info('Audit trail updated for followup ID: ' . $followup->id);
                } else {
                    PaymentAuditTrail::create([
                        'id' => Str::uuid(),
                        'lead_followup_id' => $followup->id,
                        'paid_amount' => $followup->received_amount,
                        'paid_date' => now(),
                        'payment_method' => 'Manual Rejection',
                        'narration' => $request->narration ?? 'Payment rejected via Reject All',
                        'payment_status' => 2, // 2 = Reject
                        'created_by' => auth()->id()
                    ]);
                }
                $processedCount++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully rejected {$processedCount} payments."
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error rejecting all payments: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting payments: ' . $e->getMessage()
            ], 500);
        }
    }

    public function export(Request $request)
    {
        try {

            $filters = [
                'service_date' => $request->input('service_date'),
                'service_name' => $request->input('service_name'),
                'status' => $request->input('status'),
                'payment_status' => $request->input('payment_status'),
                'from_date' => $request->input('from_date'),
                'to_date' => $request->input('to_date'),
            ];

            $fileName = 'payment_reports_' . date('Y-m-d_H-i-s') . '.xlsx';

            return Excel::download(new PaymentReportExport($filters), $fileName);
        } catch (\Exception $e) {
            Log::error('Error exporting payment report: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error exporting payment report: ' . $e->getMessage());
        }
    }
}
