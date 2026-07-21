<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LeadRefund;
use App\Models\LeadRide;
use App\Models\LeadFollowup;
use App\Models\PaymentAuditTrail;
use App\Models\Service;
use App\Models\ExtraService;
use App\Models\Voucher;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use App\Models\UserType;

class RefundController extends Controller
{
    /**
     * Display refund notes listing
     */
    public function index(Request $request)
    {
        if (!auth()->user()) {
            return redirect()->route('login');
        }
        try {
            // Get query parameters for filtering
            $serviceDate = $request->input('service_date');
            $refundDate = $request->input('refund_date');
            $followupIdFilter = $request->input('followup_id');
            $nameFilter = $request->input('name');
            $emailFilter = $request->input('email');
            $phoneFilter = $request->input('phone');
            $staffFilter = $request->input('staff');
            $serviceIdFilter = $request->input('service_id');
            $productFilter = $request->input('product');
            // For direct links from exceptional dashboard

            // Show leads in Refund Notes ONLY when email/WA was sent (status >= 1):
            // status=0 = just created (never sent) — NOT shown
            // status=1 = notifications sent, pending processing — shown in PENDING tab
            // status=2 = marked as done — shown in COMPLETE tab
            // Save Changes alone does NOT make a lead appear here (it saves with status=0)
            // Only popup OK click (which calls sendRefundEmail → status=1) makes it appear
            $query = LeadFollowup::with([
                'enquiry.client',
                'enquiry.representative',
                'enquiry.rideSegments'
            ])
                ->whereHas('refunds', function ($refundQ) {
                    $refundQ->where('status', '>=', 1);
                })
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($serviceDate) {
                $query->whereHas('enquiry.rideSegments', function ($q) use ($serviceDate) {
                    $q->whereDate('from_date', $serviceDate);
                });
            }

            if ($refundDate) {
                $query->whereHas('refunds', function ($q) use ($refundDate) {
                    $q->whereDate('refund_date', $refundDate);
                });
            }

            if ($nameFilter) {
                $query->whereHas('enquiry.client', function ($q) use ($nameFilter) {
                    $q->where('name', 'like', '%' . $nameFilter . '%');
                });
            }

            if ($emailFilter) {
                $query->whereHas('enquiry.client', function ($q) use ($emailFilter) {
                    $q->where('email', 'like', '%' . $emailFilter . '%');
                });
            }

            if ($phoneFilter) {
                $query->whereHas('enquiry.client', function ($q) use ($phoneFilter) {
                    $q->where('contact_number', 'like', '%' . $phoneFilter . '%')
                        ->orWhere('alternate_number', 'like', '%' . $phoneFilter . '%');
                });
            }

            if ($staffFilter) {
                $query->whereHas('enquiry.representative', function ($q) use ($staffFilter) {
                    $q->where('name', 'like', '%' . $staffFilter . '%');
                });
            }

            if ($serviceIdFilter) {
                $query->where(function ($q) use ($serviceIdFilter) {
                    $q->whereRaw("JSON_CONTAINS(service_ids, '[\"" . $serviceIdFilter . "\"]')")
                        ->orWhereRaw("JSON_CONTAINS(service_ids, '\"" . $serviceIdFilter . "\"')");
                });
            }

            if ($productFilter) {
                // Search in service or extra service names
                $query->where(function ($q) use ($productFilter) {
                    // Search in service_ids (these are services)
                    $serviceIds = Service::where('service', 'like', '%' . $productFilter . '%')
                        ->pluck('id')
                        ->toArray();
                    
                    if (!empty($serviceIds)) {
                        foreach ($serviceIds as $id) {
                            $q->orWhereRaw("JSON_CONTAINS(service_ids, '\"" . $id . "\"')");
                        }
                    }
                    
                    // Search in extra service names
                    $extraServiceIds = ExtraService::where('service_name', 'like', '%' . $productFilter . '%')
                        ->pluck('id')
                        ->toArray();
                    
                    if (!empty($extraServiceIds)) {
                        foreach ($extraServiceIds as $id) {
                            $q->orWhereRaw("JSON_CONTAINS(extra_service_ids, '\"" . $id . "\"')");
                        }
                    }
                });
            }

            // Filter by specific followup_id if provided (from exceptional dashboard link)
            if ($followupIdFilter) {
                $query->where('id', $followupIdFilter);
            }

            $rides = $query->get();

            // Deduplicate by lead_id: keep only the latest followup per lead
            // This prevents multiple cancelled followups for the same lead from appearing as duplicates
            $seenLeadIds = [];
            $rides = $rides->filter(function ($ride) use (&$seenLeadIds) {
                // Only include if refund is notified (status>=1: email/WA sent via popup OK)
                $hasRefund = LeadRefund::where('lead_followup_id', $ride->id)->where('status', '>=', 1)->exists();
                if ($hasRefund) {
                    return true;
                }
                // For cancelled followups without refunds, only show one per lead
                if (isset($seenLeadIds[$ride->lead_id])) {
                    return false; // Already seen this lead_id, skip duplicate
                }
                $seenLeadIds[$ride->lead_id] = true;
                return true;
            });

            // Transform data for refund notes
            $refundsData = [];
            foreach ($rides as $ride) {
                $lead = $ride->enquiry;
                $client = $lead->client ?? null;
                // $ride is a LeadFollowup with status = 2 (cancelled)

                // Get service names
                $serviceNames = [];
                if ($ride->service_ids) {
                    $serviceIds = is_array($ride->service_ids)
                        ? $ride->service_ids
                        : json_decode($ride->service_ids, true);
                    if ($serviceIds) {
                        $serviceNames = Service::whereIn('id', $serviceIds)->pluck('service')->toArray();
                    }
                }

                // Calculate amounts
                // Prefer approved payment amounts from PaymentAuditTrail when available.
                // First check for approved audit entries for this followup, then fall back to sum of approved audits for the whole lead.
                $approvedForThisFollowup = 0;
                $approvedForLead = 0;
                try {
                    if ($ride && $ride->id) {
                        $approvedForThisFollowup = (float) PaymentAuditTrail::where('lead_followup_id', $ride->id)
                            ->where('payment_status', 1)
                            ->sum('paid_amount');
                    }
                    if ($lead) {
                        $followupIds = $lead->leadFollowups()->pluck('id')->toArray();
                        if (!empty($followupIds)) {
                            $approvedForLead = (float) PaymentAuditTrail::whereIn('lead_followup_id', $followupIds)
                                ->where('payment_status', 1)
                                ->sum('paid_amount');
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Error computing approved payment sums for refund listing: ' . $e->getMessage(), ['lead_id' => $ride->lead_id ?? null]);
                }

                // Determine total amount to display: prefer existing refund.original_amount, then approved for this followup, then approved sum for lead, then ride total_amount
                $totalAmount = (float) ($ride->total_amount ?? 0);
                $receivedAmount = 0;

                // Get total received amount from payment history
                $paymentHistory = $lead->leadFollowups()
                    ->whereNotNull('received_amount')
                    ->where('received_amount', '>', 0)
                    ->get();

                $receivedAmount = $paymentHistory->sum(function ($followup) {
                    return (float) $followup->received_amount;
                });

                // Check if refund already exists
                $existingRefund = LeadRefund::where('lead_followup_id', $ride->id)->first();
                $refundStatus = $existingRefund ? $this->getRefundStatusText($existingRefund->status) : 'pending';

                $refundAmount = $receivedAmount; // Default refund amount
                $refundDate = null;

                if ($existingRefund) {
                    $refundStatus = $this->getRefundStatusText($existingRefund->status);
                    $refundAmount = $existingRefund->refund_amount;
                    $refundDate = $existingRefund->refund_date;
                    // If an original_amount was stored on the refund previously, prefer that for display
                    if (!empty($existingRefund->original_amount)) {
                        $totalAmount = (float) $existingRefund->original_amount;
                    }
                } else {
                    // No existing refund - prefer approved amounts if present
                    if (!empty($approvedForThisFollowup) && $approvedForThisFollowup > 0) {
                        $totalAmount = (float) $approvedForThisFollowup;
                    } elseif (!empty($approvedForLead) && $approvedForLead > 0) {
                        $totalAmount = (float) $approvedForLead;
                    }
                }


                // Get service date from first ride segment if available
                $serviceDateValue = null;
                if ($lead && $lead->rideSegments && $lead->rideSegments->count() > 0) {
                    $serviceDateValue = $lead->rideSegments->first()->from_date;
                } else {
                    $serviceDateValue = $ride->from_date;
                }
                // try to find voucher and invoice for this lead
                $invoiceIdValue = null;
                try {
                    $voucherForLead = \App\Models\Voucher::where('lead_id', $ride->lead_id)->first();
                    if ($voucherForLead) {
                        $inv = \App\Models\Invoice::where('voucher_id', $voucherForLead->id)->first();
                        if ($inv) $invoiceIdValue = $inv->invoice_id;
                    }
                } catch (\Exception $e) {
                    // ignore lookup errors and leave invoice id null
                    Log::warning('Error fetching related invoice for refund listing: ' . $e->getMessage(), ['lead_id' => $ride->lead_id]);
                }

                $staffName = 'N/A';
                if ($lead && $lead->representative) {
                    $staffName = $lead->representative->name ?? 'N/A';
                }

                $refundsData[] = [
                    'id' => $ride->id,
                    'lead_id' => $ride->lead_id,
                    'invoice_id' => $invoiceIdValue,
                    'client_name' => $client ? $client->name : 'N/A',
                    'client_email' => $client ? $client->email : 'N/A',
                    'contact_number' => $client ? $client->contact_number : 'N/A',
                    'staff_name' => $staffName,
                    'service_date' => $serviceDateValue,
                    'service_names' => implode(', ', $serviceNames),
                    'original_amount' => $totalAmount,
                    'refund_amount' => $refundAmount,
                    'refund_date' => $refundDate ? $refundDate->format('d M Y') : null,
                    'refund_status' => $refundStatus,
                    'has_refund' => $existingRefund ? true : false,
                    'refund_id' => $existingRefund ? $existingRefund->id : null,
                    'followup_id' => $ride->id,
                    'remarks' => $existingRefund->remarks ?? null
                ];
            }
            //dd($refundsData);
            // Apply refund status filter: request param 'refund_status' can be 'pending' or 'complete'
            // By default show pending refunds so Accounts / Operations can process them first
            $requestedStatus = $request->input('refund_status', 'pending');

            if ($requestedStatus === 'complete') {
                // Only include items that have an existing refund with status == 2 (completed)
                $refundsData = array_filter($refundsData, function ($item) {
                    return isset($item['refund_status']) && strtolower($item['refund_status']) === 'completed';
                });
            } else {
                // 'pending' or any other value: exclude completed refund entries
                $refundsData = array_filter($refundsData, function ($item) {
                    return !(isset($item['refund_status']) && strtolower($item['refund_status']) === 'completed');
                });
            }

            // Reindex array to keep the view indexes consistent
            $refundsData = array_values($refundsData);

            // Get services for filter dropdown
            $services = Service::select('id', 'service')->get();
            return view('admin.pages.refund-notes.index', compact('refundsData', 'services'))
                ->with('selectedStatus', $requestedStatus);
        } catch (\Exception $e) {
            Log::error("Error in refund index: " . $e->getMessage());
            return view('admin.pages.refund-notes.index', ['refundsData' => [], 'services' => []]);
        }
    }

    /**
     * Show refund details for preview/edit
     */
    public function show($followupId)
    {
        if (!auth()->user()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        try {
            $followup = LeadFollowup::with([
                'enquiry.client.country',
                'enquiry.client.city',
                'enquiry.leadFollowups' => function ($query) {
                    $query->where('status', 2)->orderByDesc('created_at');
                },
                'enquiry.rideSegments'
            ])->find($followupId);

            if (!$followup) {
                return response()->json(['error' => 'Followup not found'], 404);
            }

            $lead = $followup->enquiry;
            $client = $lead->client ?? null;

            // Check if this followup has a refund note or is cancelled
            $existingRefund = LeadRefund::where('lead_followup_id', $followupId)->first();
            $isCancelled = ($followup->status == 2);
            
            // If not cancelled and no refund exists, this is an invalid request
            if (!$isCancelled && !$existingRefund) {
                return response()->json(['error' => 'Followup is not cancelled and has no refund note'], 400);
            }

            // Get all rides for this lead
            $allRides = $lead->rideSegments ?? collect(); // Changed from 'rides' to 'rideSegments'
            
            // Use the current followup (which may or may not be cancelled)
            $latestFollowup = $followup;

            // Get service and extra service names
            $serviceNames = [];
            $extraServiceNames = [];

            if ($latestFollowup->service_ids) {
                $serviceIds = is_array($latestFollowup->service_ids)
                    ? $latestFollowup->service_ids
                    : json_decode($latestFollowup->service_ids, true);
                if ($serviceIds) {
                    $serviceNames = Service::whereIn('id', $serviceIds)->pluck('service')->toArray();
                }
            }

            if ($latestFollowup->extra_service_ids) {
                $extraServiceIds = is_array($latestFollowup->extra_service_ids)
                    ? $latestFollowup->extra_service_ids
                    : json_decode($latestFollowup->extra_service_ids, true);
                if ($extraServiceIds) {
                    $extraServiceNames = ExtraService::whereIn('id', $extraServiceIds)->pluck('extra_service')->toArray();
                }
            }

            // Calculate amounts
            $totalAmount = (float) ($latestFollowup->total_amount ?? 0);

            // $existingRefund already fetched above - no need to fetch again
            // Prefer approved payment audit amounts when available
            try {
                $approvedForThisFollowup = (float) PaymentAuditTrail::where('lead_followup_id', $latestFollowup->id)
                    ->where('payment_status', 1)
                    ->sum('paid_amount');

                $followupIds = $lead->leadFollowups()->pluck('id')->toArray();
                $approvedForLead = 0;
                if (!empty($followupIds)) {
                    $approvedForLead = (float) PaymentAuditTrail::whereIn('lead_followup_id', $followupIds)
                        ->where('payment_status', 1)
                        ->sum('paid_amount');
                }
                // If no existing refund.original_amount present, prefer approved sums
                if ((!$existingRefund || empty($existingRefund->original_amount))) {
                    if (!empty($approvedForThisFollowup) && $approvedForThisFollowup > 0) {
                        $totalAmount = $approvedForThisFollowup;
                    } elseif (!empty($approvedForLead) && $approvedForLead > 0) {
                        $totalAmount = $approvedForLead;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Error computing approved payment sums for refund show: ' . $e->getMessage(), ['followup_id' => $latestFollowup->id]);
            }

            $paymentHistory = $lead->leadFollowups()
                ->whereNotNull('received_amount')
                ->where('received_amount', '>', 0)
                ->with(['followedBy', 'paymentAuditTrail'])
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($followup) {
                    $paymentMethod = 'Cash';
                    if ($followup->paymentAuditTrail && $followup->paymentAuditTrail->isNotEmpty()) {
                        $latestPaymentAudit = $followup->paymentAuditTrail->first();
                        $paymentMethod = $latestPaymentAudit->payment_method ?? 'Cash';
                    }
                    return [
                        'id' => $followup->id,
                        'amount' => $followup->received_amount,
                        'total_amount' => $followup->total_amount,
                        'status' => $followup->status,
                        'followup_note' => $followup->followup_note,
                        'file' => $followup->file,
                        'created_at' => $followup->created_at,
                        'created_by_name' => $followup->followedBy ? $followup->followedBy->name : null,
                        'created_by_email' => $followup->followedBy ? $followup->followedBy->email : null,
                        'payment_method' => $paymentMethod,
                    ];
                });

            $receivedAmount = $paymentHistory->sum(function ($payment) {
                return (float) $payment['amount'];
            });

            

            // Prefer original_amount saved on an existing refund for display if present
            if ($existingRefund && !empty($existingRefund->original_amount)) {
                $totalAmount = (float) $existingRefund->original_amount;
            }

            $response = [
                'ride' => [
                    'id' => $followup->id,
                    'lead_id' => $followup->lead_id,
                    'from_date' => $followup->from_date ? $followup->from_date->format('d-m-Y H:i') : null,
                    'to_date' => $followup->to_date ? $followup->to_date->format('d-m-Y H:i') : null,
                    'from_place' => $followup->from_place,
                    'to_place' => $followup->to_place,
                ],
                'all_rides' => $allRides->map(function ($ride) {
                    return [
                        'id' => $ride->id,
                        'from_date' => $ride->from_date ? $ride->from_date->format('d-m-Y H:i') : null,
                        'to_date' => $ride->to_date ? $ride->to_date->format('d-m-Y H:i') : null,
                        'from_place' => $ride->from_place,
                        'to_place' => $ride->to_place,
                    ];
                }),
                'client' => [
                    'name' => $client ? $client->name : 'N/A',
                    'email' => $client ? $client->email : 'N/A',
                    'contact_number' => $client ? $client->contact_number : 'N/A',
                    'whatsapp_number' => $client ? $client->alternate_number : 'N/A',
                    'country' => $client && $client->country ? $client->country->name : 'N/A',
                    'city' => $client && $client->city ? $client->city->name : 'N/A',
                    'address' => $client ? $client->address : 'N/A',
                ],
                'service_names' => implode(', ', $serviceNames),
                'extra_service_names' => implode(', ', $extraServiceNames),
                'original_amount' => $totalAmount,
                'received_amount' => $receivedAmount,
                'refund' => $existingRefund ? [
                    'id' => $existingRefund->id,
                    'refund_amount' => $existingRefund->refund_amount,
                    'refund_type' => $existingRefund->refund_type,
                    'refund_date' => $existingRefund->refund_date ? $existingRefund->refund_date->format('Y-m-d') : null,
                    'refund_reason' => $existingRefund->refund_reason,
                    'refund_proof' => $existingRefund->refund_proof,
                    'remarks' => $existingRefund->remarks,
                    'status' => $existingRefund->status ?? 0
                ] : null,
                'followup_id' => $latestFollowup->id,
                'payment_history' => $paymentHistory
            ];

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error("Error in refund show: " . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Store or update refund information
     */
    public function store(Request $request)
    {
        if (!auth()->user()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        try {
            // Determine user role and build role-specific validation rules
            $currentRole = optional(auth()->user()->userType)->user_type;
            $isAdmin = in_array($currentRole, UserType::ADMIN_ROLES ?? []);
            $isAccounts = in_array($currentRole, UserType::ACCOUNTS_ROLES ?? []);
            $isOperations = in_array($currentRole, UserType::OPERATIONS_ROLES ?? []);

            // Only accounts, operations or admins can submit refunds
            if (!($isAccounts || $isOperations || $isAdmin)) {
                return response()->json(['success' => false, 'message' => 'Forbidden: insufficient permissions'], 403);
            }

            // Base rules
            $rules = [
                'followup_id' => 'required|string'
            ];

            $messages = [
                'followup_id.required' => 'Required refund information is missing. Please reopen the refund and try again.'
            ];

            // Operations (or admin) are responsible for original amount
            if ($isOperations || $isAdmin) {
                $rules['original_amount'] = 'required|numeric|min:0';

                $messages['original_amount.required'] = 'Original amount is required.';
                $messages['original_amount.numeric'] = 'Original amount must be a valid number.';
                $messages['original_amount.min'] = 'Original amount must be zero or greater.';
            }

            // Accounts, Operations (or admin) can edit refund amount and reason
            if ($isAccounts || $isOperations || $isAdmin) {
                // Ensure refund_amount is not greater than original_amount
                $rules['refund_amount'] = 'required|numeric|min:0|lte:original_amount';
                $rules['refund_reason'] = 'nullable|string';

                $messages['refund_amount.required'] = 'Please enter the refund amount.';
                $messages['refund_amount.numeric'] = 'Refund amount must be a valid number.';
                $messages['refund_amount.min'] = 'Refund amount must be zero or greater.';
                $messages['refund_amount.lte'] = 'Refund amount cannot be greater than the original amount.';
            }

            // Accounts (or admin) are responsible for refund type, date and proof
            if ($isAccounts || $isAdmin) {
                $rules['refund_type'] = 'required|string';
                $rules['refund_date'] = 'required|date';
                // proof is required from accounts when they submit; allow file optional on operations submissions
                //$rules['refund_proof'] = 'required|file|mimes:pdf,jpg,jpeg,png|max:2048';
                // Check if proof already exists on this refund
$existingRefundForProofCheck = LeadRefund::where('lead_followup_id', $request->followup_id)->first();
$alreadyHasProof = $existingRefundForProofCheck && !empty($existingRefundForProofCheck->refund_proof);

if ($alreadyHasProof) {
    // Proof already saved — optional on re-save, existing proof is kept
    $rules['refund_proof'] = 'sometimes|file|mimes:pdf,jpg,jpeg,png|max:2048';
} else {
    // No proof yet — require upload
    $rules['refund_proof'] = 'required|file|mimes:pdf,jpg,jpeg,png|max:2048';
}

                $messages['refund_type.required'] = 'Please select a refund method (Refund Type).';
                $messages['refund_date.required'] = 'Please provide the refund date.';
                $messages['refund_date.date'] = 'Refund date must be a valid date (YYYY-MM-DD).';
                $messages['refund_proof.file'] = 'Refund proof must be a file (PDF or image).';
                $messages['refund_proof.mimes'] = 'Refund proof must be a PDF or image (JPG, JPEG, PNG).';
                $messages['refund_proof.max'] = 'Refund proof must be smaller than 2 MB.';
                $messages['refund_proof.required'] = 'Please upload a refund proof document (PDF or image).';
            }

            $validator = Validator::make($request->all(), $rules, $messages);
            if ($validator->fails()) {
                Log::error('Refund validation failed', [
                    'errors' => $validator->errors(),
                    'request' => $request->all(),
                    'role' => $currentRole
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors()
                ], 422);
            }

            Log::info('Refund store request received', [
                'followup_id' => $request->followup_id,
                'original_amount' => $request->original_amount,
                'refund_amount' => $request->refund_amount,
                'refund_type' => $request->refund_type,
                'has_file' => $request->hasFile('refund_proof')
            ]);

            $followup = LeadFollowup::find($request->followup_id);
            if (!$followup) {
                Log::error('Followup not found for refund', ['followup_id' => $request->followup_id]);
                return response()->json(['success' => false, 'message' => 'Followup not found'], 404);
            }

            // Handle file upload
            $refundProofPath = null;
            if ($request->hasFile('refund_proof')) {
                try {
                    $file = $request->file('refund_proof');

                    // Sanitize the filename
                    $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $extension = $file->getClientOriginalExtension();

                    // Remove special characters and replace spaces with underscores
                    $cleanName = preg_replace('/[^A-Za-z0-9\-]/', '_', $originalName);
                    $cleanName = substr($cleanName, 0, 100); // Limit length

                    $fileName = 'refund_' . time() . '_' . $cleanName . '.' . $extension;

                    $refundProofPath = $file->storeAs('refunds', $fileName, 'public');

                    Log::info('Refund proof uploaded successfully', [
                        'path' => $refundProofPath,
                        'original_name' => $file->getClientOriginalName(),
                        'stored_name' => $fileName
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error uploading refund proof', [
                        'error' => $e->getMessage(),
                        'followup_id' => $request->followup_id,
                        'trace' => $e->getTraceAsString()
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Error uploading refund proof: ' . $e->getMessage()
                    ], 500);
                }
            }

            // Check if refund already exists
            $existingRefund = LeadRefund::where('lead_followup_id', $request->followup_id)->first();

            // Build update/create payload only with allowed fields for the current role
            $payload = [];

            if ($isOperations || $isAdmin) {
                $payload['original_amount'] = $request->original_amount;
            }

            if ($isAccounts || $isOperations || $isAdmin) {
                $payload['refund_amount'] = $request->refund_amount;
                $payload['refund_reason'] = $request->refund_reason;
            }

            if ($isAccounts || $isAdmin) {
                $payload['refund_type'] = $request->refund_type;
                $payload['refund_date'] = $request->refund_date;
                // use uploaded proof if present, else leave existing
                if ($refundProofPath) {
                    $payload['refund_proof'] = $refundProofPath;
                }
                // allow optional remarks on store
                if ($request->has('remarks')) {
                    $payload['remarks'] = $request->remarks;
                }
            }

            // Always set status to processed (1) on any update/create performed by either team
            if (!empty($payload)) {
                $payload['status'] = 1;
            }

            if ($existingRefund) {
                Log::info('Updating existing refund', ['refund_id' => $existingRefund->id, 'role' => $currentRole]);
                if (!empty($payload)) {
                    $existingRefund->update($payload + ['updated_at' => now()]);
                }

                Log::info('Refund updated successfully', ['refund_id' => $existingRefund->id]);
                return response()->json([
                    'success' => true,
                    'message' => 'Refund updated successfully',
                    'refund_id' => $existingRefund->id
                ]);
            } else {
                Log::info('Creating new refund', ['role' => $currentRole]);
                // Fill default values for fields not provided to avoid DB errors
                $createData = [
                    'id' => Str::uuid(),
                    'lead_followup_id' => $request->followup_id,
                    'original_amount' => $payload['original_amount'] ?? 0,
                    'refund_amount' => $payload['refund_amount'] ?? 0,
                    'refund_type' => $payload['refund_type'] ?? null,
                    'refund_date' => $payload['refund_date'] ?? null,
                    'refund_reason' => $payload['refund_reason'] ?? null,
                    'refund_proof' => $payload['refund_proof'] ?? null,
                    'status' => $payload['status'] ?? 1
                ];

                $refund = LeadRefund::create($createData);

                Log::info('Refund created successfully', ['refund_id' => $refund->id]);
                return response()->json([
                    'success' => true,
                    'message' => 'Refund created successfully',
                    'refund_id' => $refund->id
                ]);
            }
        } catch (ValidationException $e) {
            Log::error('Refund validation failed', [
                'errors' => $e->errors(),
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error processing refund', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error processing refund: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download refund note
     */
    public function download($refundId)
    {
        if (!auth()->user()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        try {
            $refund = LeadRefund::findOrFail($refundId);

            if (!$refund->refund_proof) {
                return response()->json(['error' => 'No proof file available'], 404);
            }

            $filePath = storage_path('app/public/' . ltrim($refund->refund_proof, '/'));

            if (!file_exists($filePath)) {
                return response()->json(['error' => 'File not found'], 404);
            }

            // Clean up filename for download
            $originalName = basename($refund->refund_proof);
            $cleanName = 'refund_proof_' . $refundId . '.' . pathinfo($filePath, PATHINFO_EXTENSION);

            return response()->download($filePath, $cleanName, [
                'Content-Type' => mime_content_type($filePath),
                'Content-Disposition' => 'attachment; filename="' . $cleanName . '"'
            ]);
        } catch (\Exception $e) {
            Log::error("Download error: " . $e->getMessage());
            return response()->json(['error' => 'Download failed'], 500);
        }
    }

    public function markAsDone($refundId)
    {
        if (!auth()->user()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        try {
            $refund = LeadRefund::findOrFail($refundId);

            // Update status to "completed" (status 2)
            $refund->update([
                'status' => 2,
                'updated_at' => now()
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Refund marked as done successfully',
                'refund' => [
                    'id' => $refund->id,
                    'status' => $refund->status,
                    'status_text' => $this->getRefundStatusText($refund->status)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error("Error marking refund as done: " . $e->getMessage(), [
                'refund_id' => $refundId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error marking refund as done: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete refund proof and add remarks (only allowed for Accounts or Admin)
     */
    public function deleteProof(Request $request, $refundId)
    {
        if (!auth()->user()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $refund = LeadRefund::findOrFail($refundId);

            // Check role
            $currentRole = optional(auth()->user()->userType)->user_type;
            $isAdmin = in_array($currentRole, UserType::ADMIN_ROLES ?? []);
            $isAccounts = in_array($currentRole, UserType::ACCOUNTS_ROLES ?? []);

            if (!($isAccounts || $isAdmin)) {
                return response()->json(['success' => false, 'message' => 'Forbidden: insufficient permissions'], 403);
            }

            $validator = Validator::make($request->all(), [
                'remarks' => 'required|string'
            ], [
                'remarks.required' => 'Remarks are required to remove proof after submission.'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
            }

            // Delete the physical file if exists
            if ($refund->refund_proof) {
                try {
                    $path = ltrim($refund->refund_proof, '/');
                    if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
                    }
                } catch (\Exception $e) {
                    Log::warning('Could not delete refund proof file: ' . $e->getMessage(), ['refund_id' => $refundId]);
                }
            }

            $refund->update([
                'refund_proof' => null,
                'remarks' => $request->remarks,
                'updated_at' => now(),
            ]);

            Log::info('Refund proof removed', ['refund_id' => $refundId, 'user_id' => auth()->id()]);

            return response()->json(['success' => true, 'message' => 'Refund proof removed successfully']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Refund not found'], 404);
        } catch (\Exception $e) {
            Log::error('Error removing refund proof: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error removing proof: ' . $e->getMessage()], 500);
        }
    }
    /**
     * Generate refund invoice
     */
    public function generateRefundInvoice($refundId)
    {
        try {
            $refund = LeadRefund::with([
                'leadFollowup.enquiry.client.country',
                'leadFollowup.enquiry.client.city',
                'leadFollowup.enquiry.rideSegments'
            ])->findOrFail($refundId);

            // Get the original voucher and invoice
            $lead = $refund->leadFollowup->enquiry;
            $voucher = Voucher::where('lead_id', $lead->id)->first();
            $originalInvoice = null;
            
            if ($voucher) {
                $originalInvoice = Invoice::where('voucher_id', $voucher->id)->first();
            }

            // Generate refund invoice ID if not already generated
            if (!$refund->refund_invoice_id) {
                $refundInvoiceId = $this->generateRefundInvoiceId();
                $refund->update(['refund_invoice_id' => $refundInvoiceId]);
            }

            // Get comprehensive refund data
            $refundData = $this->getRefundInvoiceData($refund, $voucher, $originalInvoice);

            $is_pdf = true;
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.account.refunds.refund-invoice-preview', compact(
                'refund',
                'refundData',
                'originalInvoice',
                'voucher',
                'is_pdf'
            ));

            Log::info("Refund invoice PDF generated successfully for refund: " . $refund->id);
            return $pdf->setPaper('a4', 'portrait')->stream('refund-invoice-' . $refund->refund_invoice_id . '.pdf');
            
        } catch (\Exception $e) {
            Log::error('Error in RefundController@generateRefundInvoice: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Error generating refund invoice PDF: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Download refund invoice PDF
     */
    public function downloadRefundInvoice($refundId)
    {
        try {
            $refund = LeadRefund::with([
                'leadFollowup.enquiry.client.country',
                'leadFollowup.enquiry.client.city',
                'leadFollowup.enquiry.rideSegments'
            ])->findOrFail($refundId);

            // Get the original voucher and invoice
            $lead = $refund->leadFollowup->enquiry;
            $voucher = Voucher::where('lead_id', $lead->id)->first();
            $originalInvoice = null;
            
            if ($voucher) {
                $originalInvoice = Invoice::where('voucher_id', $voucher->id)->first();
            }

            // Generate refund invoice ID if not already generated
            if (!$refund->refund_invoice_id) {
                $refundInvoiceId = $this->generateRefundInvoiceId();
                $refund->update(['refund_invoice_id' => $refundInvoiceId]);
            }

            // Get comprehensive refund data
            $refundData = $this->getRefundInvoiceData($refund, $voucher, $originalInvoice);

            $is_pdf = true;
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.account.refunds.refund-invoice-preview', compact(
                'refund',
                'refundData',
                'originalInvoice',
                'voucher',
                'is_pdf'
            ));

            Log::info("Refund invoice PDF download generated successfully for refund: " . $refund->id);
            return $pdf->setPaper('a4', 'portrait')->download('refund-invoice-' . $refund->refund_invoice_id . '.pdf');
            
        } catch (\Exception $e) {
            Log::error('Error in RefundController@downloadRefundInvoice: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Error downloading refund invoice PDF: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get comprehensive refund invoice data
     */
    private function getRefundInvoiceData($refund, $voucher, $originalInvoice)
    {
        $leadFollowup = $refund->leadFollowup;
        $lead = $leadFollowup->enquiry;
        $client = $lead->client;

        // Get service names
        $serviceNames = [];
        if ($leadFollowup->service_ids) {
            $serviceIds = is_array($leadFollowup->service_ids)
                ? $leadFollowup->service_ids
                : json_decode($leadFollowup->service_ids, true);
            if ($serviceIds) {
                $serviceNames = Service::whereIn('id', $serviceIds)->pluck('service')->toArray();
            }
        }

        // Get extra service names
        $extraServiceNames = [];
        if ($leadFollowup->extra_service_ids) {
            $extraServiceIds = is_array($leadFollowup->extra_service_ids)
                ? $leadFollowup->extra_service_ids
                : json_decode($leadFollowup->extra_service_ids, true);
            if ($extraServiceIds) {
                $extraServiceNames = ExtraService::whereIn('id', $extraServiceIds)->pluck('extra_service')->toArray();
            }
        }

        // Get ride information
        $rideInfo = $this->getRefundRideInformation($lead);
        $allRides = $this->getRefundAllRideSegments($lead);

        return [
            'refund' => $refund,
            'client' => $client,
            'service_names' => implode(', ', $serviceNames),
            'extra_service_names' => implode(', ', $extraServiceNames),
            'ride' => $rideInfo,
            'all_rides' => $allRides,
            'original_invoice' => $originalInvoice,
            'voucher' => $voucher,
            'refund_date' => $refund->refund_date ? $refund->refund_date->format('d-m-Y') : Carbon::now()->format('d-m-Y'),
            'refund_invoice_id' => $refund->refund_invoice_id
        ];
    }

    /**
     * Generate unique refund invoice ID
     */
    private function generateRefundInvoiceId()
    {
        $year = Carbon::now()->year;
        $lastRefund = LeadRefund::where('refund_invoice_id', 'like', "REF-{$year}-%")
            ->orderBy('refund_invoice_id', 'desc')
            ->first();

        if ($lastRefund) {
            $lastNumber = intval(substr($lastRefund->refund_invoice_id, -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return "REF-{$year}-" . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get ride information for refund
     */
    private function getRefundRideInformation($lead)
    {
        $latestRide = $lead->rideSegments()->orderBy('from_date', 'desc')->first();
        
        if (!$latestRide) {
            return [
                'from_date' => 'N/A',
                'to_date' => 'N/A',
                'from_place' => 'N/A',
                'to_place' => 'N/A',
                'duration' => 'N/A'
            ];
        }

        $duration = 'N/A';
        if ($latestRide->from_date && $latestRide->to_date) {
            $fromDate = Carbon::parse($latestRide->from_date);
            $toDate = Carbon::parse($latestRide->to_date);
            $duration = $fromDate->diffInDays($toDate) + 1 . ' Day(s)';
        }

        return [
            'from_date' => $latestRide->from_date ? Carbon::parse($latestRide->from_date)->format('d-m-Y H:i') : 'N/A',
            'to_date' => $latestRide->to_date ? Carbon::parse($latestRide->to_date)->format('d-m-Y H:i') : 'N/A',
            'from_place' => $latestRide->from_place ?? 'N/A',
            'to_place' => $latestRide->to_place ?? 'N/A',
            'duration' => $duration
        ];
    }

    /**
     * Get all ride segments for refund
     */
    private function getRefundAllRideSegments($lead)
    {
        $rides = $lead->rideSegments()->orderBy('from_date', 'asc')->get();
        
        return $rides->map(function ($ride) {
            return [
                'from_date' => $ride->from_date ? Carbon::parse($ride->from_date)->format('d-m-Y H:i') : 'N/A',
                'to_date' => $ride->to_date ? Carbon::parse($ride->to_date)->format('d-m-Y H:i') : 'N/A',
                'from_place' => $ride->from_place ?? 'N/A',
                'to_place' => $ride->to_place ?? 'N/A',
            ];
        })->toArray();
    }

    /**
     * Preview refund invoice as HTML
     */
    public function previewRefundInvoice($refundId)
    {
        try {
            Log::info("Previewing refund invoice HTML for refund ID: " . $refundId);
            
            $refund = LeadRefund::with([
                'leadFollowup.enquiry.client.country',
                'leadFollowup.enquiry.client.city',
                'leadFollowup.enquiry.rideSegments'
            ])->findOrFail($refundId);

            // Get the original voucher and invoice
            $lead = $refund->leadFollowup->enquiry;
            $voucher = Voucher::where('lead_id', $lead->id)->first();
            $originalInvoice = null;
            
            if ($voucher) {
                $originalInvoice = Invoice::where('voucher_id', $voucher->id)->first();
            }

            // Generate refund invoice ID if not already generated
            if (!$refund->refund_invoice_id) {
                $refundInvoiceId = $this->generateRefundInvoiceId();
                $refund->update(['refund_invoice_id' => $refundInvoiceId]);
            }

            // Get comprehensive refund data
            $refundData = $this->getRefundInvoiceData($refund, $voucher, $originalInvoice);

            Log::info("Refund invoice HTML preview generated successfully for refund: " . $refund->id);
            $is_pdf = false;
            return view('admin.account.refunds.refund-invoice-preview', compact(
                'refund',
                'refundData',
                'originalInvoice',
                'voucher',
                'is_pdf'
            ));
            
        } catch (\Exception $e) {
            Log::error('Error in RefundController@previewRefundInvoice: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Error generating refund invoice preview: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get refund status text
     */
    private function getRefundStatusText($status)
    {
        switch ((int) $status) {
            case 0:
                return 'pending';
            case 1:
                return 'processed';
            case 2:
                return 'completed';
            case 3:
                return 'cancelled';
            default:
                return 'pending';
        }
    }
}