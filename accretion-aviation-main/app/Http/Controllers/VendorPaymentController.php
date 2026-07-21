<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Vendor;
use App\Models\Voucher;
use App\Models\VendorPayment;
use App\Models\LeadVendorPayment;
use App\Models\LeadVendorPaymentDetail;
use App\Models\Client;
use App\Models\Service;
use App\Models\ExtraService;
use App\Exports\VendorPaymentsExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class VendorPaymentController extends Controller
{
    /**
     * Display a listing of the vendor payments.
     */
    public function index(Request $request)
    {
        try {
            // Build base query for LeadVendorPayment with eager relationships
            $query = LeadVendorPayment::with([
                'lead.client',
                'vendor',
                'voucher',
                'paymentDetails.service',
                'paymentDetails.extraService',
                'vendorPayments'
            ]);

            // $query->whereDoesntHave('lead.latestFollowup', function ($q) {
            //     $q->where('status', 2);
            // });

            // Apply filters from request if provided
            $clientId = $request->get('client_id');
            $vendorId = $request->get('vendor_id');
            $serviceId = $request->get('service_id');
            $status = $request->get('status');
            $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

            if (!empty($clientId)) {
                $query->whereHas('lead.client', function ($q) use ($clientId) {
                    $q->where('id', $clientId);
                });
            }

            if (!empty($vendorId)) {
                $query->where('vendor_id', $vendorId);
            }

            if (!empty($serviceId)) {
                $query->whereHas('paymentDetails', function ($q) use ($serviceId) {
                    $q->where('service_id', $serviceId);
                });
            }

            // By default (when no explicit status filter is applied) show leads
            // that have at least one vendor entry which is NOT fully paid. This
            // ensures that for a lead with mixed statuses (e.g. one Unpaid and
            // one Fully Paid vendor) we still show the lead and ALL its vendors
            // (including the fully paid vendor). Only leads where ALL vendor
            // entries are fully paid will be excluded from the initial list.

            // if (empty($status)) {
            //     $query->whereIn('lead_id', function ($sub) {
            //         $sub->select('lead_id')
            //             ->from('lead_vendor_payments')
            //             ->whereNull('payment_status')
            //             ->orWhere('payment_status', '!=', 'paid');
            //     });
            // }

            // if (!empty($status)) {
            //     $normalized = strtolower(trim($status));

            //     // Use aggregate sums of vendor payments to determine "paid/partial/unpaid"
            //     // rather than relying solely on the `payment_status` text which has
            //     // inconsistent historical values.
            //     if (in_array($normalized, ['paid', 'full paid', 'full_paid', 'full'])) {
            //         // fully paid when total paid >= total_vendor_service_amount
            //         $query->whereRaw("(
            //             SELECT COALESCE(SUM(paid_amount), 0) FROM vendor_payments
            //             WHERE vendor_payments.lead_vendor_payment_id = lead_vendor_payments.id
            //         ) >= COALESCE(lead_vendor_payments.total_vendor_service_amount, 0)");
            //     } elseif (in_array($normalized, ['partial', 'partial paid', 'partial_paid'])) {
            //         // partial when some amount paid but less than total
            //         $query->whereRaw("(
            //             SELECT COALESCE(SUM(paid_amount), 0) FROM vendor_payments
            //             WHERE vendor_payments.lead_vendor_payment_id = lead_vendor_payments.id
            //         ) > 0")
            //         ->whereRaw("(
            //             SELECT COALESCE(SUM(paid_amount), 0) FROM vendor_payments
            //             WHERE vendor_payments.lead_vendor_payment_id = lead_vendor_payments.id
            //         ) < COALESCE(lead_vendor_payments.total_vendor_service_amount, 0)");
            //     } elseif (in_array($normalized, ['unpaid'])) {
            //         // unpaid when nothing has been paid yet
            //         $query->whereRaw("(
            //             SELECT COALESCE(SUM(paid_amount), 0) FROM vendor_payments
            //             WHERE vendor_payments.lead_vendor_payment_id = lead_vendor_payments.id
            //         ) = 0");
            //     } else {
            //         // allow direct match for any other custom status value
            //         $query->where('payment_status', $status);
            //     }
            // }

            // --- CORE LOGIC START ---
        
        // Define SQL snippets for readability
        // 1. Paid Amount Subquery
        $rawPaid = "(SELECT COALESCE(SUM(paid_amount), 0) 
                        FROM vendor_payments 
                        WHERE vendor_payments.lead_vendor_payment_id = lead_vendor_payments.id)";
        // 2. Total Cost
        $rawCost = "COALESCE(lead_vendor_payments.total_vendor_service_amount, 0)";
        // 3. Balance
        $rawBalance = "($rawCost - $rawPaid)";

        // CASE 1: Initial View (No Filter)
        // Logic: Show the WHOLE Lead group if at least ONE vendor in that group has a pending balance.
        // This ensures the "Fully Paid" vendor appears alongside the "Unpaid" vendor for the same lead.
        if (!request()->has('status')) {
            $query->whereIn('lead_id', function ($sub) {
                $sub->select('lead_id')
                    ->from('lead_vendor_payments')
                    // Check strictly for Balance > 0
                    ->whereRaw("(total_vendor_service_amount
                         - (SELECT COALESCE(SUM(paid_amount), 0) 
                         FROM vendor_payments 
                         WHERE vendor_payments.lead_vendor_payment_id = lead_vendor_payments.id)) > 0");
            });
        }
        
        // CASE 2: Specific Filters (Strict Filtering)
        // When a user explicitly asks for "Unpaid", we only show the unpaid rows.
        elseif (!empty($status)) {
            $normalized = strtolower(trim($status));

            if (in_array($normalized, ['paid', 'full paid', 'full_paid', 'full'])) {
                $query->whereRaw("$rawBalance <= 0");
            } 
            elseif (in_array($normalized, ['partial', 'partial paid', 'partial_paid'])) {
                $query->whereRaw("$rawPaid > 0")
                      ->whereRaw("$rawBalance > 0");
            } 
            elseif (in_array($normalized, ['unpaid'])) {
                $query->whereRaw("$rawPaid = 0")
                      ->whereRaw("$rawCost > 0");
            } 
            else {
                $query->where('payment_status', $status);
            }
        }
        // --- CORE LOGIC END ---

            $vendorPaymentsPaginator = (clone $query)
                ->setEagerLoads([])
                ->select('lead_id')
                ->selectRaw('MAX(created_at) as latest_created_at')
                ->groupBy('lead_id')
                ->orderByDesc('latest_created_at')
                ->paginate($perPage)
                ->appends($request->query());

            $pageLeadIds = collect($vendorPaymentsPaginator->items())
                ->pluck('lead_id')
                ->filter()
                ->values();

            // Finally get results for only the leads on the current page.
            // Sort by the latest paid_date from vendor payments (actual payment date)
            $vendorPayments = collect();
            if ($pageLeadIds->isNotEmpty()) {
                $vendorPayments = (clone $query)->whereIn('lead_id', $pageLeadIds->all())->get()->sortByDesc(function ($item) {
                    // Get the most recent paid_date from vendor payments
                    $latestPayment = $item->vendorPayments->sortByDesc('paid_date')->first();
                    return $latestPayment ? $latestPayment->paid_date : optional($item->voucher)->created_at;
                })->values();
            }

            // Group by lead to organize vendors under each client
            $groupedByLead = $vendorPayments->groupBy('lead_id');
            
            $vendorPaymentsData = collect();
            
            $pageLeadIds->each(function ($leadId) use ($groupedByLead, &$vendorPaymentsData) {
                $vendorPaymentsGroup = $groupedByLead->get($leadId);
                if (!$vendorPaymentsGroup || $vendorPaymentsGroup->isEmpty()) {
                    return;
                }

                $lead = $vendorPaymentsGroup->first()->lead;
                $clientName = $lead->client->name ?? 'N/A';
                
                // Collect all vendors for this client
                $vendorsData = [];
                $vendorPaymentsGroup->each(function ($vendorPayment) use (&$vendorsData) {
                    // Get vendor specific information
                    $vendorName = $vendorPayment->vendor->name ?? 'N/A';
                    
                    // Get services specific to this vendor
                    $vendorServiceInfo = $this->getVendorSpecificServiceInfo($vendorPayment);
                    
                    // Calculate vendor specific amounts
                    $vendorServiceCost = $vendorPayment->total_vendor_service_amount ?? 0;
                    $paidAmount = $vendorPayment->vendorPayments->sum('paid_amount') ?? 0;
                    $balanceAmount = $vendorServiceCost - $paidAmount;

                    // Visual Status Calculation
                 $statusLabel = 'Unpaid';
                $statusClass = 'bg-danger/10 text-danger';
                   // Use a small epsilon for float comparison safety, or strict check logic
                if ($balanceAmount <= 0 && $vendorServiceCost > 0) {
                    $statusLabel = 'Full Paid';
                    $statusClass = 'bg-success/10 text-success';
                } elseif ($paidAmount > 0 && $balanceAmount > 0) {
                    $statusLabel = 'Partial Paid';
                    $statusClass = 'bg-warning/10 text-warning';
                } elseif ($vendorServiceCost == 0) {
                     // Handle the visual label for "0 Cost" items if they appear
                    $statusLabel = 'Free/No Cost'; 
                    $statusClass = 'bg-gray-100 text-gray-500';
                }

                // Get Latest Paid Date
                $vendorPaidDates = $vendorPayment->vendorPayments->pluck('paid_date')->filter();
                $latestPaidDate = null;
                if ($vendorPaidDates->isNotEmpty()) {
                    $latest = $vendorPaidDates->sort()->last();
                    try {
                        $latestPaidDate = \Carbon\Carbon::parse($latest)->format('d M Y');
                    } catch (\Exception $e) {
                        $latestPaidDate = $latest;
                    }
                }
                    
                    // // Determine latest paid date for this vendor
                    // $vendorPaidDates = $vendorPayment->vendorPayments->pluck('paid_date')->filter();
                    // $latestPaidDate = null;
                    // if ($vendorPaidDates->isNotEmpty()) {
                    //     $latest = $vendorPaidDates->sort()->last();
                    //     try {
                    //         $latestPaidDate = \Carbon\Carbon::parse($latest)->format('d M Y');
                    //     } catch (\Exception $e) {
                    //         $latestPaidDate = $latest;
                    //     }
                    // }
                    
                    // Determine status for this vendor
                 
                    $vendorsData[] = [
                        'id' => $vendorPayment->id,
                        'vendor_name' => $vendorName,
                        'service_info' => [
                            'service_display' => $vendorServiceInfo['service_display'] ?? '',
                            'extra_service_display' => $vendorServiceInfo['extra_service_display'] ?? '',
                        ],
                        'vendor_service_cost' => $vendorServiceCost,
                        'balance_amount' => $balanceAmount,
                        'paid_amount' => $paidAmount,
                        'paid_date' => $latestPaidDate ?? 'N/A',
                        'status' => ['status' => $statusLabel, 'class' => $statusClass],
                    ];
                });
                
                // Add client entry with all vendors
                $vendorPaymentsData->push([
                    'lead_id' => $leadId,
                    'client_name' => $clientName,
                    'vendors' => $vendorsData,
                    'view_id' => $vendorPaymentsGroup->first()->id, // for modal
                    // include voucher id so the view can link to the invoice for this voucher
                    'voucher_id' => $vendorPaymentsGroup->first()->voucher_id ?? null,
                ]);
            });

            // Load services list to populate the Service dropdown in the UI (match payment-review UI)
            $services = Service::where('status', 1)->get();
            
            // Load clients and vendors for dropdowns
            $clients = Client::where('status', 1)->orderBy('name')->get();
            $vendors = Vendor::where('status', 1)->orderBy('name')->get();

            return view('admin.account.vendors.vendor-payments', compact('vendorPaymentsData', 'vendorPayments', 'vendorPaymentsPaginator', 'services', 'clients', 'vendors'));
        } catch (\Exception $e) {
            Log::error('Error in vendor payments index: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error loading vendor payments');
        }
    }

    /**
     * Show vendor payment details
     */
    public function show($id)
    {
        try {
            $vendorPayment = LeadVendorPayment::with([
                'lead.client.country',
                'lead.client.city',
                'vendor',
                'voucher',
                'paymentDetails'
            ])->findOrFail($id);

            // Get all vendors for this voucher (order preserved by voucher created_at if needed)
            $allVendorPayments = LeadVendorPayment::with(['vendor', 'paymentDetails', 'vendorPayments'])
                ->where('voucher_id', $vendorPayment->voucher_id)
                ->get();

            // Get service information
            $serviceInfo = $this->getServiceInfo($vendorPayment->lead);

            // Get payment history for this specific vendor payment
            $paymentHistory = VendorPayment::where('lead_vendor_payment_id', $id)
                ->orderBy('created_at', 'desc')
                ->get();

            // Build per-vendor payment histories and paid totals for all vendors in this voucher
            $perVendorHistories = [];
            foreach ($allVendorPayments as $vp) {
                $hist = VendorPayment::where('lead_vendor_payment_id', $vp->id)
                    ->orderBy('created_at', 'desc')
                    ->get();
                $perVendorHistories[$vp->id] = [
                    'history' => $hist,
                    'paid_total' => $hist->sum('paid_amount'),
                ];
            }

            // Also load client payment history from PaymentAuditTrail (via LeadFollowup)
            $clientPaymentHistory = [];
            try {
                $clientPaymentHistory = \App\Models\PaymentAuditTrail::with(['leadFollowup.followedBy'])
                    ->whereHas('leadFollowup', function ($query) use ($vendorPayment) {
                        $query->where('lead_id', $vendorPayment->lead_id);
                    })
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(function ($auditTrail) {
                        $followupRecord = $auditTrail->leadFollowup;
                        return [
                            'id' => $followupRecord ? $followupRecord->id : $auditTrail->id,
                            'amount' => $auditTrail->paid_amount,
                            'paid_date' => $auditTrail->paid_date,
                            'payment_method' => $auditTrail->payment_method,
                            'narration' => $auditTrail->narration,
                            'payment_status' => $auditTrail->payment_status,
                            'created_at' => $auditTrail->created_at,
                            'created_by_name' => $followupRecord && $followupRecord->followedBy ? $followupRecord->followedBy->name : null,
                        ];
                    });
            } catch (\Exception $e) {
                Log::warning('Could not load client payment history: ' . $e->getMessage());
            }

            return response()->json([
                'vendor_payment' => $vendorPayment,
                'all_vendor_payments' => $allVendorPayments,
                'service_info' => $serviceInfo,
                'payment_history' => $paymentHistory,
                'per_vendor_histories' => $perVendorHistories,
                'client' => $vendorPayment->lead->client,
                'travel_info' => $this->getTravelInfo($vendorPayment->lead),
                'client_payment_history' => $clientPaymentHistory,
            ]);
        } catch (\Exception $e) {
            Log::error('Error showing vendor payment: ' . $e->getMessage());
            return response()->json(['error' => 'Error loading vendor payment details'], 500);
        }
    }

    /**
     * Store a new vendor payment
     */
    // public function storePayment(Request $request)
    // {
    //     $request->validate([
    //         'lead_vendor_payment_id' => 'required|exists:lead_vendor_payments,id',
    //         'payment_method' => 'required|string',
    //         'paid_amount' => 'required|numeric|min:0',
    //         // disallow future dates for paid_date
    //         'paid_date' => 'required|date|before_or_equal:today',
    //         'narration' => 'nullable|string',
    //         'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
    //         'total_vendor_service_amount' => 'nullable|numeric|min:0',
    //     ]);

    //     try {
    //         DB::beginTransaction();

    //         // Handle file upload
    //         $receiptPath = null;
    //         if ($request->hasFile('receipt')) {
    //             $receiptPath = $request->file('receipt')->store('vendor-payment-receipts', 'public');
    //         }

    //         // Create vendor payment
    //         $vendorPayment = VendorPayment::create([
    //             'id' => Str::uuid(),
    //             'lead_vendor_payment_id' => $request->lead_vendor_payment_id,
    //             'payment_method' => $request->payment_method,
    //             'paid_amount' => $request->paid_amount,
    //             'paid_date' => $request->paid_date,
    //             'narration' => $request->narration,
    //             'receipt' => $receiptPath,
    //             'status' => 1, // Active
    //         ]);

    //         // Update the lead vendor payment status only (avoid writing non-existent columns)
    //         $leadVendorPayment = LeadVendorPayment::find($request->lead_vendor_payment_id);
    //             // If total_vendor_service_amount provided, persist it to lead vendor payment
    //             if ($request->filled('total_vendor_service_amount') && $leadVendorPayment) {
    //                 $leadVendorPayment->update([
    //                     'total_vendor_service_amount' => $request->total_vendor_service_amount,
    //                 ]);
    //             }
    //         $totalPaid = VendorPayment::where('lead_vendor_payment_id', $request->lead_vendor_payment_id)
    //             ->sum('paid_amount');

    //         $status = $totalPaid >= ($leadVendorPayment->total_vendor_service_amount ?? 0) ? 'paid' : 'partial';
    //         $leadVendorPayment->update([
    //             'payment_status' => $status,
    //         ]);

    //         DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Vendor payment saved successfully',
    //             'payment' => $vendorPayment,
    //             'receipt_url' => $receiptPath ? Storage::url($receiptPath) : null,
    //         ]);
    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         Log::error('Error storing vendor payment: ' . $e->getMessage());
    //         return response()->json(['error' => 'Error saving payment'], 500);
    //     }
    // }
// ═══════════════════════════════════════════════════════════════════════════
// COMMENTED OUT — Old storePayment using Interakt WhatsApp
// ═══════════════════════════════════════════════════════════════════════════
// public function storePayment(Request $request)
// {
//     $request->validate([
//         'lead_vendor_payment_id'      => 'required|exists:lead_vendor_payments,id',
//         'payment_method'              => 'required|string',
//         'paid_amount'                 => 'required|numeric|min:0',
//         'paid_date'                   => 'required|date|before_or_equal:today',
//         'narration'                   => 'nullable|string',
//         'receipt'                     => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
//         'total_vendor_service_amount' => 'nullable|numeric|min:0',
//     ]);
//
//     try {
//         DB::beginTransaction();
//
//         // ── File upload ───────────────────────────────────────────────────────
//         $receiptPath = null;
//         if ($request->hasFile('receipt')) {
//             $receiptPath = $request->file('receipt')->store('vendor-payment-receipts', 'public');
//         }
//
//         // ── Create vendor payment ─────────────────────────────────────────────
//         $vendorPayment = VendorPayment::create([
//             'id'                     => Str::uuid(),
//             'lead_vendor_payment_id' => $request->lead_vendor_payment_id,
//             'payment_method'         => $request->payment_method,
//             'paid_amount'            => $request->paid_amount,
//             'paid_date'              => $request->paid_date,
//             'narration'              => $request->narration,
//             'receipt'                => $receiptPath,
//             'status'                 => 1,
//         ]);
//
//         // ── Update lead vendor payment status ─────────────────────────────────
//         $leadVendorPayment = LeadVendorPayment::find($request->lead_vendor_payment_id);
//
//         if ($request->filled('total_vendor_service_amount') && $leadVendorPayment) {
//             $leadVendorPayment->update([
//                 'total_vendor_service_amount' => $request->total_vendor_service_amount,
//             ]);
//         }
//
//         $totalPaid = VendorPayment::where('lead_vendor_payment_id', $request->lead_vendor_payment_id)
//             ->sum('paid_amount');
//
//         $status = $totalPaid >= ($leadVendorPayment->total_vendor_service_amount ?? 0) ? 'paid' : 'partial';
//         $leadVendorPayment->update(['payment_status' => $status]);
//
//         DB::commit();
//
//         // ════════════════════════════════════════════════════════════════════
//         // NOTIFICATIONS — Blade Email + WhatsApp to Vendor only
//         // ════════════════════════════════════════════════════════════════════
//         try {
//             $leadVendorPayment->load(['vendor', 'lead.client', 'paymentDetails.service']);
//
//             $vendor  = $leadVendorPayment->vendor;
//             $lead    = $leadVendorPayment->lead;
//             $client  = $lead->client;
//
//             // Service name
//             $firstDetail = $leadVendorPayment->paymentDetails->first();
//             $serviceObj  = $firstDetail ? ($firstDetail->service ?? null) : null;
//             $serviceName = $serviceObj->service ?? $serviceObj->service_name ?? 'N/A';
//
//             // Service date from latest ride
//             $pickup_date = 'N/A';
//             $ride = \App\Models\LeadRide::where('lead_id', $lead->id)->latest()->first();
//             if ($ride && !empty($ride->from_date)) {
//                 try {
//                     $pickup_date = \Carbon\Carbon::parse($ride->from_date)->format('jS F, Y');
//                 } catch (\Throwable $e) {}
//             }
//
//             // Payment date
//             $paymentDate = 'N/A';
//             try {
//                 if (!empty($request->paid_date)) {
//                     $paymentDate = \Carbon\Carbon::parse($request->paid_date)->format('jS F, Y');
//                 }
//             } catch (\Throwable $e) {}
//
//             // Amounts
//             $totalPaidNow  = VendorPayment::where('lead_vendor_payment_id', $leadVendorPayment->id)->sum('paid_amount');
//             $totalCost     = floatval($leadVendorPayment->total_vendor_service_amount ?? 0);
//             $paidAmount    = floatval($request->paid_amount);
//             $pendingAmount = max(0, $totalCost - $totalPaidNow);
//             $balanceAmount = $pendingAmount;
//
//             $vendorName  = $vendor->name  ?? 'Vendor';
//             $clientName  = $client->name  ?? 'Customer';
//             $vendorPhone = $vendor->whatsapp_number ?? $vendor->alternate_number ?? $vendor->contact_number ?? $vendor->phone ?? null;
//             $vendorEmail = $vendor->email ?? null;
//
//             // Receipt file paths
//             $waFileUrl        = $receiptPath
//                 ? rtrim(config('app.url'), '/') . '/storage/' . $receiptPath
//                 : null;
//             $receiptLocalPath = $receiptPath
//                 ? storage_path('app/public/' . $receiptPath)
//                 : null;
//
//             $smc = new SendMessageController();
//
//             // ── WhatsApp — refund_vendor_notify_v2 (9 variables) ─────────────
//             $sanitize = fn($arr) => array_map(fn($v) => is_null($v) ? '' : (string) $v, $arr);
//
//             $waTemplate = 'refund_vendor_notify_v2';
//             $waData     = $sanitize([
//                 $vendorName,
//                 $clientName,
//                 $serviceName,
//                 $pickup_date,
//                 number_format($totalPaidNow, 2),
//                 number_format($pendingAmount, 2),
//                 number_format($balanceAmount, 2),
//                 $paymentDate,
//                 $request->payment_method ?? 'N/A',
//             ]);
//
//             if (!empty($vendorPhone) && $waFileUrl) {
//                 $waResult = $smc->sendWhatsAppMessage(2, $waTemplate, $waData, $vendorPhone, $waFileUrl);
//                 Log::info('storePayment: vendor WhatsApp sent', ['number' => $vendorPhone, 'result' => $waResult]);
//             }
//
//             // ── Blade Email — vendor ──────────────────────────────────────────
//             $vendorEmailData = [
//                 'vendor_name'    => $vendorName,
//                 'client_name'    => $clientName,
//                 'service'        => $serviceName,
//                 'service_date'   => $pickup_date,
//                 'paid_amount'    => $totalPaidNow,
//                 'pending_amount' => $pendingAmount,
//                 'balance_amount' => $balanceAmount,
//                 'refund_date'    => $paymentDate,
//                 'refund_type'    => $request->payment_method ?? 'N/A',
//                 'refund_reason'  => '',
//             ];
//
//             if (!empty($vendorEmail) && filter_var($vendorEmail, FILTER_VALIDATE_EMAIL)) {
//                 \Illuminate\Support\Facades\Mail::to($vendorEmail)->send(new \App\Mail\RefundMail(
//                     'emails.refund-vendor',
//                     'Payment Notification – ' . $clientName . ' – ' . $serviceName,
//                     $vendorEmailData,
//                     $receiptLocalPath
//                 ));
//             }
//
//             // ── Notification Masters — WhatsApp + Email ───────────────────────
//             $notificationMasters = \App\Models\NotificationMaster::where('status', 1)->get();
//             foreach ($notificationMasters as $nm) {
//                 $nmEmail = $nm->email_id ?? null;
//                 if (!empty($nmEmail) && filter_var($nmEmail, FILTER_VALIDATE_EMAIL)) {
//                     \Illuminate\Support\Facades\Mail::to($nmEmail)->send(new \App\Mail\RefundMail(
//                         'emails.refund-vendor',
//                         'Vendor Payment Notification – ' . $clientName . ' – ' . $serviceName,
//                         $vendorEmailData,
//                         $receiptLocalPath
//                     ));
//                 }
//                 $nmPhone = !empty($nm->contact_country_code)
//                     ? $nm->contact_country_code . '-' . $nm->mobile_number
//                     : $nm->mobile_number;
//                 if (!empty($nm->mobile_number) && $waFileUrl) {
//                     $smc->sendWhatsAppMessage(2, $waTemplate, $waData, $nmPhone, $waFileUrl);
//                 }
//             }
//
//         } catch (\Exception $e) {
//             Log::warning('storePayment: vendor notification failed (payment still saved)', [
//                 'error' => $e->getMessage(),
//             ]);
//         }
//
//         return response()->json([
//             'success'     => true,
//             'message'     => 'Vendor payment saved successfully',
//             'payment'     => $vendorPayment,
//             'receipt_url' => $receiptPath ? Storage::url($receiptPath) : null,
//         ]);
//
//     } catch (\Exception $e) {
//         DB::rollback();
//         Log::error('Error storing vendor payment: ' . $e->getMessage());
//         return response()->json(['error' => 'Error saving payment'], 500);
//     }
// }

// ═══════════════════════════════════════════════════════════════════════════
// NEW storePayment — uses MSG91 WhatsApp (vendor_payment template)
// Email notifications remain unchanged.
// ═══════════════════════════════════════════════════════════════════════════
    public function storePayment(Request $request)
    {
        $request->validate([
            'lead_vendor_payment_id'      => 'required|exists:lead_vendor_payments,id',
            'payment_method'              => 'required|string',
            'paid_amount'                 => 'required|numeric|min:0',
            'paid_date'                   => 'required|date|before_or_equal:today',
            'narration'                   => 'nullable|string',
            'receipt'                     => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'total_vendor_service_amount' => 'nullable|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // ── File upload ───────────────────────────────────────────────────────
            $receiptPath = null;
            if ($request->hasFile('receipt')) {
                $receiptPath = $request->file('receipt')->store('vendor-payment-receipts', 'public');
            }

            // ── Create vendor payment ─────────────────────────────────────────────
            $vendorPayment = VendorPayment::create([
                'id'                     => Str::uuid(),
                'lead_vendor_payment_id' => $request->lead_vendor_payment_id,
                'payment_method'         => $request->payment_method,
                'paid_amount'            => $request->paid_amount,
                'paid_date'              => $request->paid_date,
                'narration'              => $request->narration,
                'receipt'                => $receiptPath,
                'status'                 => 1,
            ]);

            // ── Update lead vendor payment status ─────────────────────────────────
            $leadVendorPayment = LeadVendorPayment::find($request->lead_vendor_payment_id);

            if ($request->filled('total_vendor_service_amount') && $leadVendorPayment) {
                $leadVendorPayment->update([
                    'total_vendor_service_amount' => $request->total_vendor_service_amount,
                ]);
            }

            $totalPaid = VendorPayment::where('lead_vendor_payment_id', $request->lead_vendor_payment_id)
                ->sum('paid_amount');

            $status = $totalPaid >= ($leadVendorPayment->total_vendor_service_amount ?? 0) ? 'paid' : 'partial';
            $leadVendorPayment->update(['payment_status' => $status]);

            DB::commit();

            // ════════════════════════════════════════════════════════════════════
            // NOTIFICATIONS — Blade Email + MSG91 WhatsApp to Vendor
            // ════════════════════════════════════════════════════════════════════
            try {
                // Extend execution time for notifications (WhatsApp + SMTP emails)
                set_time_limit(120);
                $leadVendorPayment->load(['vendor', 'lead.client', 'paymentDetails.service']);

                $vendor  = $leadVendorPayment->vendor;
                $lead    = $leadVendorPayment->lead;
                $client  = $lead->client;

                // Service name
                $firstDetail = $leadVendorPayment->paymentDetails->first();
                $serviceObj  = $firstDetail ? ($firstDetail->service ?? null) : null;
                $serviceName = $serviceObj->service ?? $serviceObj->service_name ?? 'N/A';

                // Service date from latest ride
                $pickup_date = 'N/A';
                $ride = \App\Models\LeadRide::where('lead_id', $lead->id)->latest()->first();
                if ($ride && !empty($ride->from_date)) {
                    try {
                        $pickup_date = \Carbon\Carbon::parse($ride->from_date)->format('jS F, Y');
                    } catch (\Throwable $e) {}
                }

                // Payment date
                $paymentDate = 'N/A';
                try {
                    if (!empty($request->paid_date)) {
                        $paymentDate = \Carbon\Carbon::parse($request->paid_date)->format('jS F, Y');
                    }
                } catch (\Throwable $e) {}

                // Amounts
                $totalPaidNow  = VendorPayment::where('lead_vendor_payment_id', $leadVendorPayment->id)->sum('paid_amount');
                $totalCost     = floatval($leadVendorPayment->total_vendor_service_amount ?? 0);
                $paidAmount    = floatval($request->paid_amount);
                $pendingAmount = max(0, $totalCost - $totalPaidNow);
                $balanceAmount = $pendingAmount;

                $vendorName  = $vendor->name  ?? 'Vendor';
                $clientName  = $client->name  ?? 'Customer';
                $vendorPhone = $vendor->whatsapp_number ?? $vendor->alternate_number ?? $vendor->contact_number ?? $vendor->phone ?? null;
                $vendorEmail = $vendor->email ?? null;

                // Receipt file paths
                $waFileUrl        = $receiptPath
                    ? rtrim(config('app.url'), '/') . '/storage/' . $receiptPath
                    : null;
                $receiptLocalPath = $receiptPath
                    ? storage_path('app/public/' . $receiptPath)
                    : null;

                // Receipt filename for WhatsApp document header
                $receiptFilename = $receiptPath ? basename($receiptPath) : 'receipt.pdf';

                // $smc = new SendMessageController();

                // // ── MSG91 WhatsApp — vendor_payment template (7 body variables + document header) ──
                // $waTemplate = 'vendor_payment';
                // $waBodyData = [
                //     $vendorName,                          // body_1: Vendor Name
                //     $clientName,                          // body_2: Customer Name
                //     $serviceName,                         // body_3: Service
                //     $pickup_date,                         // body_4: Service Date
                //     number_format($totalPaidNow, 2),      // body_5: Amount Paid
                //     $paymentDate,                         // body_6: Payment Date
                //     $request->payment_method ?? 'N/A',    // body_7: Payment Method
                // ];

                // if (!empty($vendorPhone) && $waFileUrl) {
                //     Log::info('storePayment: sending vendor MSG91 WhatsApp', [ 
                //         'template' => $waTemplate,
                //         'number'   => $vendorPhone,
                //         'file'     => $waFileUrl,
                //         'data'     => $waBodyData,
                //     ]);

                //     // Clean vendor phone — remove country code prefix separator if present
                //     $cleanVendorPhone = str_replace(['+', '-', ' '], '', $vendorPhone);

                //     $waResult = $smc->sendWhatsAppMsg91Message(
                //         $cleanVendorPhone,
                //         $waTemplate,
                //         $waBodyData,
                //         $waFileUrl,
                //         $receiptFilename
                //     );

                //     Log::info('storePayment: vendor MSG91 WhatsApp sent', [
                //         'number' => $vendorPhone,
                //         'result' => $waResult,
                //     ]);
                // } elseif (empty($vendorPhone)) {
                //     Log::warning('storePayment: vendor WhatsApp number missing', ['vendor' => $vendorName]);
                // } elseif (!$waFileUrl) {
                //     Log::warning('storePayment: no receipt URL — WhatsApp skipped');
                // }

                $smc = new SendMessageController();

                // ── WhatsCRM WhatsApp — auto picks vendor_payment or vendor_payment_img ──
                $waBodyData = [
                    $vendorName,                          // {{1}} Vendor Name
                    $clientName,                          // {{2}} Customer Name
                    $serviceName,                         // {{3}} Service
                    $pickup_date,                         // {{4}} Service Date
                    number_format($totalPaidNow, 2),      // {{5}} Amount Paid
                    $paymentDate,                         // {{6}} Payment Date
                    $request->payment_method ?? 'N/A',    // {{7}} Payment Method
                ];

                if (!empty($vendorPhone) && $waFileUrl) {
                    $cleanVendorPhone = preg_replace('/[^0-9]/', '', $vendorPhone);

                    $waResult = $smc->sendWhatsCrmMessage(
                        $cleanVendorPhone,
                        $waBodyData,
                        $waFileUrl,
                        $receiptFilename
                    );

                    // Log::info('storePayment: vendor WhatsCRM WhatsApp sent', [
                    //     'number' => $vendorPhone,
                    //     'result' => $waResult,
                    // ]);
                    Log::info('VENDOR PAYMENT ► Vendor WhatsApp', [
                        'To'     => $vendorPhone,
                        'Status' => ($waResult['success'] ?? false) ? '✓ Sent' : '✗ Failed',
                    ]);
                } elseif (empty($vendorPhone)) {
                    Log::warning('storePayment: vendor WhatsApp number missing', ['vendor' => $vendorName]);
                } elseif (!$waFileUrl) {
                    Log::warning('storePayment: no receipt URL — WhatsApp skipped');
                }

                // ── Blade Email — vendor (UNCHANGED) ────────────────────────────────
                $vendorEmailData = [
                    'vendor_name'    => $vendorName,
                    'client_name'    => $clientName,
                    'service'        => $serviceName,
                    'service_date'   => $pickup_date,
                    'paid_amount'    => $totalPaidNow,
                    'pending_amount' => $pendingAmount,
                    'balance_amount' => $balanceAmount,
                    'refund_date'    => $paymentDate,
                    'refund_type'    => $request->payment_method ?? 'N/A',
                    'refund_reason'  => '',
                ];

                if (!empty($vendorEmail) && filter_var($vendorEmail, FILTER_VALIDATE_EMAIL)) {
                    try {
                        \Illuminate\Support\Facades\Mail::to($vendorEmail)->send(new \App\Mail\RefundMail(
                            'emails.refund-vendor',
                            'Payment Notification – ' . $clientName . ' – ' . $serviceName,
                            $vendorEmailData,
                            $receiptLocalPath
                        ));
                        Log::info('storePayment: vendor blade email sent', ['to' => $vendorEmail]);
                    } catch (\Exception $e) {
                        Log::error('storePayment: vendor email failed (continuing to NM notifications)', [
                            'to'    => $vendorEmail,
                            'error' => $e->getMessage(),
                        ]);
                    }
                } else {
                    Log::warning('storePayment: vendor email missing/invalid', [
                        'vendor' => $vendorName,
                        'email'  => $vendorEmail,
                    ]);
                }

                // ── Notification Masters — MSG91 WhatsApp + Email (UNCHANGED email) ──
                $notificationMasters = \App\Models\NotificationMaster::where('status', 1)->get();
                Log::info('storePayment: notification masters', ['count' => $notificationMasters->count()]);

                foreach ($notificationMasters as $nm) {
                    // Blade Email to NM (UNCHANGED)
                    $nmEmail = $nm->email_id ?? null;
                    if (!empty($nmEmail) && filter_var($nmEmail, FILTER_VALIDATE_EMAIL)) {
                        try {
                            \Illuminate\Support\Facades\Mail::to($nmEmail)->send(new \App\Mail\RefundMail(
                                'emails.refund-vendor',
                                'Vendor Payment Notification – ' . $clientName . ' – ' . $serviceName,
                                $vendorEmailData,
                                $receiptLocalPath
                            ));
                            Log::info('storePayment: NM blade email sent', ['to' => $nmEmail, 'nm_id' => $nm->id]);
                        } catch (\Exception $e) {
                            Log::error('storePayment: NM email failed', [
                                'nm_id' => $nm->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    // MSG91 WhatsApp to NM
                    $nmPhone = !empty($nm->contact_country_code)
                        ? $nm->contact_country_code . $nm->mobile_number
                        : $nm->mobile_number;

                    if (empty($nm->mobile_number)) {
                        Log::warning('storePayment: NM phone missing', ['nm_id' => $nm->id]);
                    } elseif (!$waFileUrl) {
                        Log::warning('storePayment: NM WhatsApp skipped — no receipt URL', ['nm_id' => $nm->id]);
                    } else {
                        try {
                            // $cleanNmPhone = preg_replace('/[^0-9]/', '', $nmPhone);
                            // $nmWaResult = $smc->sendWhatsAppMsg91Message(
                            //     $cleanNmPhone,
                            //     $waTemplate,
                            //     $waBodyData,
                            //     $waFileUrl,
                            //     $receiptFilename
                            // );
                            // Log::info('storePayment: NM MSG91 WhatsApp sent', [
                            //     'nm_id'  => $nm->id,
                            //     'result' => $nmWaResult,
                            // ]);

                            $cleanNmPhone = preg_replace('/[^0-9]/', '', $nmPhone);
                            $nmWaResult = $smc->sendWhatsCrmMessage(
                                $cleanNmPhone,
                                $waBodyData,
                                $waFileUrl,
                                $receiptFilename
                            );
                            // Log::info('storePayment: NM WhatsCRM WhatsApp sent', [
                            //     'nm_id'  => $nm->id,
                            //     'result' => $nmWaResult,
                            // ]);

                            Log::info('VENDOR PAYMENT ► NM WhatsApp', [
                                'NM_ID'  => $nm->id,
                                'To'     => $nmPhone,
                                'Status' => ($nmWaResult['success'] ?? false) ? '✓ Sent' : '✗ Failed',
                            ]);
                        } catch (\Exception $e) {
                            Log::error('storePayment: NM WhatsApp exception', [
                                'nm_id' => $nm->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }

            } catch (\Exception $e) {
                Log::warning('storePayment: vendor notification failed (payment still saved)', [
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'success'     => true,
                'message'     => 'Vendor payment saved successfully',
                'payment'     => $vendorPayment,
                'receipt_url' => $receiptPath ? Storage::url($receiptPath) : null,
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error storing vendor payment: ' . $e->getMessage());
            return response()->json(['error' => 'Error saving payment'], 500);
        }
    }

    /**
     * Get service information for a lead
     */
    private function getServiceInfo($lead)
    {
        $services = [];
        $extraServices = [];

        if ($lead->service_ids) {
            $serviceIds = is_array($lead->service_ids) ? $lead->service_ids : json_decode($lead->service_ids, true);
            if ($serviceIds && is_array($serviceIds)) {
                $services = Service::whereIn('id', $serviceIds)->pluck('service')->toArray();
            }
        }

        // Get services and extra services from LeadVendorPaymentDetail for this lead
        $leadVendorPayments = LeadVendorPayment::where('lead_id', $lead->id)
            ->with(['paymentDetails.service', 'paymentDetails.extraService'])
            ->get();

        foreach ($leadVendorPayments as $vendorPayment) {
            foreach ($vendorPayment->paymentDetails as $detail) {
                // Always collect from relations if available
                if ($detail->relationLoaded('extraService') && $detail->extraService && !empty($detail->extraService->extra_service)) {
                    $extraServices[] = $detail->extraService->extra_service;
                }

                if ($detail->relationLoaded('service') && $detail->service && !empty($detail->service->service)) {
                    $services[] = $detail->service->service;
                }

                // Fallback: if neither relation present but a service_name column exists on detail
                if ((!$detail->relationLoaded('service') || empty($detail->service)) && (!$detail->relationLoaded('extraService') || empty($detail->extraService))) {
                    if (!empty($detail->service_name)) {
                        $services[] = $detail->service_name; // assume regular service
                    }
                }
            }
        }

        // Merge lead.service_ids names with details, dedupe
        $services = array_unique(array_filter($services));
        $extraServices = array_unique(array_filter($extraServices));

        // Calculate totals and profit/loss based on LeadVendorPayment records for this lead
        $totalServiceAmount = 0.0; // client-facing total (total_service_amount)
        $totalVendorAmount = 0.0;  // vendor-side total (total_vendor_service_amount)
        try {
            $totalServiceAmount = $leadVendorPayments->sum(function ($p) {
                return floatval($p->total_service_amount ?? 0);
            });
            $totalVendorAmount = $leadVendorPayments->sum(function ($p) {
                return floatval($p->total_vendor_service_amount ?? 0);
            });
        } catch (\Exception $e) {
            Log::warning('Error calculating totals in getServiceInfo: ' . $e->getMessage());
        }

        $profitLoss = $totalServiceAmount - $totalVendorAmount;

        return [
            'services' => $services,
            'extra_services' => $extraServices,
            'service_display' => implode(', ', $services),
            'extra_service_display' => implode(', ', $extraServices),
            'total_service_amount' => $totalServiceAmount,
            'total_vendor_amount' => $totalVendorAmount,
            'profit_loss' => $profitLoss,
            'profit_loss_label' => ($profitLoss >= 0) ? 'Profit' : 'Loss',
        ];
    }

    /**
     * Get vendor-specific service information
     */
    private function getVendorSpecificServiceInfo($vendorPayment)
    {
        $services = [];
        $extraServices = [];

        // Get services and extra services specific to this vendor payment
        foreach ($vendorPayment->paymentDetails as $detail) {
            if ($detail->is_extra_service) {
                // This is an extra service
                if ($detail->relationLoaded('extraService') && $detail->extraService) {
                    $extraServices[] = $detail->extraService->extra_service;
                } elseif (!empty($detail->service_name)) {
                    $extraServices[] = $detail->service_name;
                }
            } else {
                // This is a regular service
                if ($detail->relationLoaded('service') && $detail->service) {
                    $services[] = $detail->service->service;
                } elseif (!empty($detail->service_name)) {
                    $services[] = $detail->service_name;
                }
            }
        }

        // Remove duplicates and filter empty values
        $services = array_unique(array_filter($services));
        $extraServices = array_unique(array_filter($extraServices));

        return [
            'services' => $services,
            'extra_services' => $extraServices,
            'service_display' => implode(', ', $services),
            'extra_service_display' => implode(', ', $extraServices),
        ];
    }

    /**
     * Get travel information for a lead
     */
    private function getTravelInfo($lead)
    {
        // Try to get rides from the lead
        $rides = [];
        try {
            $rides = \App\Models\LeadRide::where('lead_id', $lead->id)->get();
        } catch (\Exception $e) {
            Log::warning('Could not load rides for lead: ' . $e->getMessage());
        }

        // Return rides as an array so the view can display multiple trip details.
        // Keep keys consistent: return array of rides with fields used in the view.
        $result = $rides->map(function ($r) {
            return [
                'from_date' => $r->from_date ?? null,
                'to_date' => $r->to_date ?? null,
                'from_place' => $r->from_place ?? 'N/A',
                'to_place' => $r->to_place ?? 'N/A',
            ];
        })->toArray();

        return $result;
    }

    /**
     * Get payment status for a vendor payment
     */
    private function getPaymentStatus($payment)
    {
        $totalAmount = $payment->total_vendor_service_amount ?? 0;
        $paidAmount = $payment->paid_amount ?? 0;

        if ($paidAmount >= $totalAmount && $totalAmount > 0) {
            return ['status' => 'Full Paid', 'class' => 'bg-success/10 text-success'];
        } elseif ($paidAmount > 0) {
            return ['status' => 'Partial Paid', 'class' => 'bg-warning/10 text-warning'];
        } else {
            return ['status' => 'Unpaid', 'class' => 'bg-danger/10 text-danger'];
        }
    }

    /**
     * Update vendor payment amounts
     */
    public function updatePaymentAmount(Request $request, $id)
    {
        $request->validate([
            'total_vendor_service_amount' => 'required|numeric|min:0',
        ]);

        try {
            $vendorPayment = LeadVendorPayment::findOrFail($id);
            $vendorPayment->update([
                'total_vendor_service_amount' => $request->total_vendor_service_amount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment amount updated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating payment amount: ' . $e->getMessage());
            return response()->json(['error' => 'Error updating payment amount'], 500);
        }
    }

    /**
     * Export vendor payments to Excel
     */
    public function export(Request $request)
    {
        try {
            $filters = [
                'client_id' => $request->get('client_id'),
                'vendor_id' => $request->get('vendor_id'),
                'service_id' => $request->get('service_id'),
                'status' => $request->get('status'),
            ];

            $fileName = 'vendor_payments_' . date('Y-m-d_H-i-s') . '.xlsx';
            
            return Excel::download(new VendorPaymentsExport($filters), $fileName);

        } catch (\Exception $e) {
            Log::error('Error exporting vendor payments: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error exporting vendor payments: ' . $e->getMessage());
        }
    }
}
