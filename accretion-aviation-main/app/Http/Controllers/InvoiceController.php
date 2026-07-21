<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Voucher;
use App\Models\Lead;
use App\Models\Client;
use App\Models\LeadPayment;
use App\Models\LeadPaymentDetail;
use App\Models\LeadVendorPayment;
use App\Models\Service;
use App\Models\ExtraService;
use App\Models\PaymentAuditTrail;
use App\Models\LeadFollowup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    /**
     * Display a listing of invoices.
     */
    public function index(Request $request)
    {
        try {
            // Get query parameters for filtering
            $clientId = $request->input('client_id');
            $serviceName = $request->input('service_name');
            $status = $request->input('status');
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');
            $serviceDate = $request->input('service_date');
            $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

            // Build base voucher query
            $query = Voucher::with([
                'invoice',
                'lead.client.country',
                'lead.client.city',
                'lead.rideSegments',
                'lead.leadFollowups' => function($q) {
                    $q->whereNotNull('received_amount')
                      ->where('received_amount', '>', 0)
                      ->orderBy('created_at', 'desc');
                },
                'vendorPayments.vendor',
                'vendorPayments.paymentDetails.service',
                'vendorPayments.paymentDetails.extraService',
                'paymentDetails.service',
                'paymentDetails.extraService',
                    'service',
                    'leadPassengers'
                ]);

            // Only include vouchers that have associated payments
            $query->whereHas('lead.leadFollowups', function ($q) {
                $q->whereNotNull('received_amount')
                  ->where('received_amount', '>', 0);
            });

            // Date filters
            // Service Date should filter vouchers by the ride's from_date (service date), not the voucher created_at
            if ($serviceDate) {
                try {
                    $query->whereHas('lead.rideSegments', function ($q) use ($serviceDate) {
                        $q->whereDate('from_date', Carbon::parse($serviceDate)->toDateString());
                    });
                } catch (\Exception $e) {
                    // ignore invalid date
                }
            } else {
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
            }

            // Client filter
            if ($clientId) {
                $query->whereHas('lead.client', function ($q) use ($clientId) {
                    $q->where('id', $clientId);
                });
            }

            // Service filter
            if ($serviceName) {
                $query->whereHas('lead', function ($q) use ($serviceName) {
                    $q->where('service_ids', 'like', '%' . $serviceName . '%')
                      ->orWhere('service_ids', 'like', '%"' . $serviceName . '"%');
                });
            }

            // Invoice status handling:
            // - If explicit status filter provided, honor it:
            //     '2' or 'completed' => show only finalized (invoice.status = 2)
            //     '1' or 'active'    => show only active (invoice.status = 1) or vouchers without invoice
            // - If no status filter provided, exclude finalized invoices by default
            if ($status) {
                if ($status === 'completed' || $status == 2 || $status === '2') {
                    $query->whereHas('invoice', function ($q) {
                        $q->where('status', 2);
                    });
                } elseif ($status === 'active' || $status == 1 || $status === '1') {
                    $query->where(function($q) {
                        $q->whereDoesntHave('invoice')
                          ->orWhereHas('invoice', function($qq) { $qq->where('status', 1); });
                    });
                } else {
                    // leave other (payment) status filtering to existing logic if any
                }
            } else {
                // Default: don't show finalized invoices (status = 2)
                $query->where(function($q) {
                    $q->whereDoesntHave('invoice')
                      ->orWhereHas('invoice', function($qq) { $qq->where('status', '!=', 2); });
                });
            }

            $vouchers = $query->orderBy('created_at', 'desc')
                ->paginate($perPage)
                ->appends($request->query());

            $invoicesData = collect();
            foreach ($vouchers as $voucher) {
                $lead = $voucher->lead;
                $client = $lead->client ?? null;
                if (!$client) continue;

                // Get latest payment (only followups that have received_amount > 0)
                $latestPayment = $lead->leadFollowups()
                    ->whereNotNull('received_amount')
                    ->where('received_amount', '>', 0)
                    ->orderBy('created_at', 'desc')
                    ->first();
                if (!$latestPayment) continue;

                // Ensure the latest followup (by created_at) is marked complete before showing invoice
                // In this codebase status '5' corresponds to confirm/complete (see LeadFollowup model)
                $latestFollowup = $lead->leadFollowups()->orderBy('created_at', 'desc')->first();
                if (!$latestFollowup) continue;
                $latestStatus = $latestFollowup->status;
                // Accept numeric 5 or string '5' or literal 'complete' if present
                if (!($latestStatus === 5 || $latestStatus === '5' || (is_string($latestStatus) && strtolower($latestStatus) === 'complete'))) {
                    // skip this voucher since the most recent followup isn't complete
                    continue;
                }

                $totalAmount = (float) $latestPayment->total_amount;
                $totalReceived = PaymentAuditTrail::whereHas('leadFollowup', function($q) use ($lead) {
                    $q->where('lead_id', $lead->id);
                })->where('payment_status', 1)->sum('paid_amount');

                $paymentStatus = 'Unpaid';
                if ($totalReceived >= $totalAmount && $totalAmount > 0) {
                    $paymentStatus = 'Full Paid';
                } elseif ($totalReceived > 0) {
                    $paymentStatus = 'Partial Paid';
                }

                $serviceInfo = $this->getServiceInformation($lead);
                $vendorInfo = $this->getVendorInformation($voucher);
                $profitLoss = $totalReceived - $vendorInfo['totalVendorCost'];
                $rideInfo = $this->getRideInformation($lead);
                $allRides = $this->getAllRideSegments($lead);

                $invoiceData = [
                    'id' => $voucher->id,
                    'client' => [
                        'name' => $client->name,
                        'email' => $client->email,
                        'phone' => $client->contact_number,
                        'whatsapp' => $client->alternate_number ?? $client->alternate_number,
                        'country' => $client->country->country ?? 'N/A',
                        'city' => $client->city->city ?? 'N/A',
                        'address' => $client->address ?? 'N/A'
                    ],
                    'service' => $serviceInfo,
                    'ride' => $rideInfo,
                    'all_rides' => $allRides,
                    'vendor' => $vendorInfo,
                    'payment' => [
                        'total_amount' => $totalAmount,
                        'received_amount' => $totalReceived,
                        'balance' => $totalAmount - $totalReceived,
                        'status' => $paymentStatus,
                        'latest_payment_date' => $latestPayment->created_at ?? null
                    ],
                    'profit_loss' => $profitLoss,
                    'status' => $paymentStatus,
                    'created_at' => $voucher->created_at
                ];

                // Attach existing invoice metadata so view can disable quick-generate button
                $existingInvoiceModel = Invoice::where('voucher_id', $voucher->id)->first();
                $invoiceData['existing_invoice'] = $existingInvoiceModel ? true : false;
                $invoiceData['existing_invoice_id'] = $existingInvoiceModel->invoice_id ?? null;

                $invoicesData->push($invoiceData);
            }

            $clients = Client::where('status', 1)->select('id', 'name')->get();
            $services = Service::where('status', 1)->select('id', 'service')->get();
            return view('admin.account.invoices.invoices', compact('invoicesData', 'vouchers', 'clients', 'services'));
        } catch (\Exception $e) {
            Log::error('Error in InvoiceController@index: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return back()->with('error', 'An error occurred while loading invoices.');
        }
    }

    /**
     * Show specific invoice details.
     */
    public function show($id)
    {
        try {
            $voucher = Voucher::with([
                'lead.client.country',
                'lead.client.city',
                'lead.rideSegments',
                'lead.leadFollowups' => function($q) {
                    $q->whereNotNull('received_amount')
                      ->where('received_amount', '>', 0)
                      ->orderBy('created_at', 'desc');
                },
                'vendorPayments.vendor',
                'vendorPayments.paymentDetails.service',
                'vendorPayments.paymentDetails.extraService',
                'vendorPayments.vendorPayments',
                'paymentDetails.service',
                'paymentDetails.extraService'
            ])->findOrFail($id);

            $lead = $voucher->lead;
            $client = $lead->client;

            // Get payment history
            $paymentHistory = $this->getPaymentHistory($lead->id);
            
            // Get comprehensive invoice data
            $invoiceData = $this->getComprehensiveInvoiceData($voucher);

            // Check if invoice already exists
            $existingInvoice = Invoice::where('voucher_id', $voucher->id)->first();

            // Return JSON for AJAX requests
            if (request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'voucher' => $voucher,
                        'invoiceData' => $invoiceData,
                        'paymentHistory' => $paymentHistory,
                        'existingInvoice' => $existingInvoice,
                        'lead' => $lead,
                        'client' => $client
                    ]
                ]);
            }

            // For non-AJAX requests, redirect to the invoices index and pass the voucher id
            // so the front-end can open the same invoices view and load details via AJAX.
            return redirect()->route('admin.account.invoices', ['open_invoice' => $voucher->id]);
            
        } catch (\Exception $e) {
            Log::error('Error in InvoiceController@show: ' . $e->getMessage());
            
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found or error occurred.'
                ]);
            }
            
            return back()->with('error', 'Invoice not found or error occurred.');
        }
    }

    /**
     * Generate and store invoice.
     */
    public function generateInvoice(Request $request, $voucherId)
    {
        try {
            DB::beginTransaction();

            $voucher = Voucher::findOrFail($voucherId);
            
            // Check if invoice already exists -> return existing invoice instead of treating as error
            $existingInvoice = Invoice::where('voucher_id', $voucher->id)->first();
            if ($existingInvoice) {
                return response()->json([
                    'success' => true,
                    'message' => 'Invoice already exists for this voucher.',
                    'invoice_id' => $existingInvoice->invoice_id,
                    'invoice' => $existingInvoice
                ]);
            }

            // Generate invoice ID
            $invoiceId = $this->generateInvoiceId();

            // Get invoice data. company_name may be omitted when quick-generating from the list.
            $invoiceData = $request->validate([
                'company_name' => 'nullable|string|max:255',
                'gst_number' => 'nullable|string|max:50',
                'billing_address' => 'nullable|string'
            ]);

            // Default company name to client name when not provided
            $clientName = $voucher->lead && $voucher->lead->client ? ($voucher->lead->client->name ?? null) : null;
            $companyName = $invoiceData['company_name'] ?? null;
            if (empty($companyName) && !empty($clientName)) {
                $companyName = $clientName;
            }

            // Create invoice using direct assignment to ensure required fields (like voucher_id)
            // are saved even if the model's $fillable does not include them.
            $invoice = new Invoice();
            $invoice->id = Str::uuid();
            $invoice->invoice_id = $invoiceId;
            $invoice->voucher_id = $voucher->id;
            $invoice->company_name = $companyName;
            $invoice->gst_number = $invoiceData['gst_number'] ?? null;
            $invoice->billing_address = $invoiceData['billing_address'] ?? null;
            $invoice->status = 1;
            $invoice->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Invoice generated successfully.',
                'invoice_id' => $invoice->invoice_id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in InvoiceController@generateInvoice: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate invoice.'
            ]);
        }
    }

    /**
     * Update invoice GST information.
     */
    public function updateGstInfo(Request $request, $voucherId)
    {
        try {
            $voucher = Voucher::findOrFail($voucherId);
            // Validate input and return structured JSON errors for AJAX callers
            $rules = [
                'company_name' => 'required|string|max:255',
                'gst_number' => 'nullable|string|max:50',
                'billing_address' => 'nullable|string'
            ];

            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validatedData = $validator->validated();

            // If invoice for this voucher doesn't exist yet, create one so GST info can be saved
            $invoice = Invoice::where('voucher_id', $voucher->id)->first();
            if (!$invoice) {
                $invoice = Invoice::create([
                    'id' => Str::uuid(),
                    'invoice_id' => $this->generateInvoiceId(),
                    'voucher_id' => $voucher->id,
                    'company_name' => $validatedData['company_name'],
                    'gst_number' => $validatedData['gst_number'] ?? null,
                    'billing_address' => $validatedData['billing_address'] ?? null,
                    'status' => 1
                ]);
            } else {
                $invoice->update($validatedData);
            }

            return response()->json([
                'success' => true,
                'message' => 'GST information saved successfully.'
            ]);

        } catch (\Exception $e) {
            Log::error('Error in InvoiceController@updateGstInfo: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update GST information. ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Finalize (mark completed) an invoice.
     */
    public function finalizeInvoice(Request $request, $voucherId)
    {
        try {
            $voucher = Voucher::findOrFail($voucherId);

            $invoice = Invoice::where('voucher_id', $voucher->id)->first();
            if (!$invoice) {
                return response()->json([ 'success' => false, 'message' => 'Invoice not found for this voucher' ], 404);
            }

            // mark as finalized/completed (use status = 2 for completed)
            $invoice->status = 2;
            $invoice->save();

            return response()->json([ 'success' => true, 'message' => 'Invoice marked as finalized.' ]);
        } catch (\Exception $e) {
            Log::error('Error in InvoiceController@finalizeInvoice: ' . $e->getMessage());
            return response()->json([ 'success' => false, 'message' => 'Failed to finalize invoice.' ], 500);
        }
    }

    /**
     * Get service information for a lead.
     */
    private function getServiceInformation($lead)
    {
        $serviceIds = $lead->service_ids_array;
        $services = [];
        $extraServices = [];
        $totalServiceCost = 0;

        if (!empty($serviceIds)) {
            $services = Service::whereIn('id', $serviceIds)->where('status', 1)->get();
        }


        // Get payment details for services and extra services from LeadPaymentDetail (client-side)
        $voucherIds = $lead->vouchers()->pluck('id')->toArray();
        if (!empty($voucherIds)) {
            $paymentDetails = LeadPaymentDetail::whereHas('leadPayment', function($q) use ($voucherIds) {
                $q->whereIn('voucher_id', $voucherIds);
            })->with(['service', 'extraService'])->get();
        } else {
            $paymentDetails = collect();
        }

        // Build per-item data keyed by type and id so we can map to masters and followups
        $detailedItems = [];
        $serviceIdsSeen = [];
        $extraIdsSeen = [];

        foreach ($paymentDetails as $detail) {
            $totalServiceCost += $detail->amount;

            $sid = $detail->service_id ?? null;
            $type = !empty($detail->is_extra_service) ? 'extra' : 'service';

            // collect id lists for later master lookups
            if ($type === 'extra' && $sid) $extraIdsSeen[] = $sid;
            if ($type === 'service' && $sid) $serviceIdsSeen[] = $sid;

            $key = $type . '|' . ($sid ?? 'unknown');
            if (!isset($detailedItems[$key])) {
                $name = 'Unknown';
                if ($type === 'extra' && $detail->extraService) $name = $detail->extraService->extra_service;
                if ($type === 'service' && $detail->service) $name = $detail->service->service;

                $detailedItems[$key] = [
                    'id' => $sid,
                    'type' => $type,
                    'name' => trim($name),
                    'selling_price' => 0.0,
                    'cost_price' => 0.0,
                    'vendor_amount' => 0.0,
                ];
            }
            $detailedItems[$key]['selling_price'] += (float) $detail->amount;

            // collect names for summary lists
            if ($type === 'extra') {
                $extraServices[] = $detailedItems[$key]['name'];
            } else {
                $services[] = $detailedItems[$key]['name'];
            }
        }

            // Also collect services/extra services from LeadVendorPayment details (vendor-side)
        try {
            $leadVendorPayments = \App\Models\LeadVendorPayment::where('lead_id', $lead->id)
                ->with(['paymentDetails.service', 'paymentDetails.extraService'])
                ->get();

            // Collect vendor-side costs as fallback if followup does not provide cost
            foreach ($leadVendorPayments as $vendorPayment) {
                foreach ($vendorPayment->paymentDetails as $detail) {
                    $sid = $detail->service_id ?? null;
                    $type = !empty($detail->is_extra_service) ? 'extra' : 'service';
                    $key = $type . '|' . ($sid ?? 'unknown');

                    // vendor_service_amount is what we pay to vendor
                    // service_amount is what we charge the client (cost price)
                    $vendorAmount = $detail->vendor_service_amount ?? 0;
                    $costPrice = $detail->service_amount ?? 0;
                    if (!isset($detailedItems[$key])) {
                        $name = $detail->service_name ?? ($detail->service->service ?? ($detail->extraService->extra_service ?? 'Unknown'));
                        $detailedItems[$key] = [
                            'id' => $sid,
                            'type' => $type,
                            'name' => trim($name),
                            'selling_price' => 0.0,
                            'cost_price' => 0.0,
                            'vendor_amount' => 0.0,
                        ];
                    }
                    $detailedItems[$key]['cost_price'] += (float) $costPrice;
                    $detailedItems[$key]['vendor_amount'] = (isset($detailedItems[$key]['vendor_amount']) ? $detailedItems[$key]['vendor_amount'] : 0) + (float) $vendorAmount;
                }
            }            // At this point we have selling_price aggregated from LeadPaymentDetail and cost_price aggregated from vendor details
            // Next: attempt to get cost_price from the latest followup (preferred source per request)
            // Prefer the latest followup that contains `service_details` (even if received_amount not present)
            $lastFollowupWithServiceDetails = $lead->leadFollowups()
                ->whereNotNull('service_details')
                ->orderBy('created_at', 'desc')
                ->first();

            $followupCosts = [];
            if ($lastFollowupWithServiceDetails && $lastFollowupWithServiceDetails->service_details) {
                $decoded = is_string($lastFollowupWithServiceDetails->service_details) ? json_decode($lastFollowupWithServiceDetails->service_details, true) : $lastFollowupWithServiceDetails->service_details;
                if (is_array($decoded)) {
                    foreach ($decoded as $d) {
                        $t = ($d['type'] ?? 'service');
                        // normalize type names to 'service'|'extra'
                        $normType = $t === 'extra_service' ? 'extra' : 'service';
                        $id = $d['id'] ?? null;
                        // Prefer final_amount if present; otherwise compute final = original_amount - discount_amount when available
                        if (isset($d['final_amount'])) {
                            $orig = $d['final_amount'];
                        } elseif (isset($d['original_amount'])) {
                            $orig = (float)$d['original_amount'] - (float)($d['discount_amount'] ?? 0);
                        } else {
                            $orig = $d['amount'] ?? null;
                        }
                        if ($id !== null) {
                            $followupCosts[$normType . '|' . $id] = (float) $orig;
                        }
                    }
                }
            }

            // Ensure any followup-only IDs are represented in $detailedItems even if no payment/vendor details exist
            foreach ($followupCosts as $fk => $fv) {
                list($t, $id) = explode('|', $fk) + [null, null];
                if (!$id) continue;
                $key = ($t === 'extra' ? 'extra' : 'service') . '|' . $id;
                if (!isset($detailedItems[$key])) {
                    // try lookup in masters
                    $name = 'Unknown';
                    if (isset($serviceMasters[$id])) {
                        $name = $serviceMasters[$id]->service ?? $serviceMasters[$id]->service_name ?? $name;
                    } elseif (isset($extraMasters[$id])) {
                        $name = $extraMasters[$id]->extra_service ?? $name;
                    } elseif (isset($leadMasters[$id])) {
                        $name = $leadMasters[$id]->service ?? $leadMasters[$id]->service_name ?? $name;
                    }

                    $detailedItems[$key] = [
                        'id' => $id,
                        'type' => ($t === 'extra' ? 'extra' : 'service'),
                        'name' => $name,
                        'selling_price' => 0.0,
                        'cost_price' => 0.0,
                        'vendor_amount' => 0.0,
                    ];
                }
                // Apply followup cost immediately so it appears even if no vendor/payment rows
                $detailedItems[$key]['cost_price'] = (float) $fv;
            }

            // Now apply followupCosts (preferred) and then master selling prices
            // Load master prices for any seen IDs. Also include IDs found in followupCosts and lead service_ids.
            $serviceIdsToLoad = array_values(array_unique($serviceIdsSeen));
            $extraIdsToLoad = array_values(array_unique($extraIdsSeen));

            // include IDs from followupCosts keys
            foreach ($followupCosts as $fk => $fv) {
                $parts = explode('|', $fk);
                if (count($parts) === 2) {
                    $t = $parts[0];
                    $id = $parts[1];
                    if ($t === 'service' && $id && !in_array($id, $serviceIdsToLoad)) $serviceIdsToLoad[] = $id;
                    if ($t === 'extra' && $id && !in_array($id, $extraIdsToLoad)) $extraIdsToLoad[] = $id;
                }
            }

            // include IDs from lead->service_ids_array as a fallback
            try {
                $leadServiceIds = $lead->service_ids_array ?? [];
                if (!empty($leadServiceIds) && is_array($leadServiceIds)) {
                    foreach ($leadServiceIds as $lsid) {
                        if ($lsid && !in_array($lsid, $serviceIdsToLoad)) $serviceIdsToLoad[] = $lsid;
                    }
                }
                // extra service ids from latest followup or lead followup arrays
                if (!empty($lastFollowupWithAmount)) {
                    $lfExtraIds = is_string($lastFollowupWithAmount->extra_service_ids) ? json_decode($lastFollowupWithAmount->extra_service_ids, true) : ($lastFollowupWithAmount->extra_service_ids ?? []);
                    if (is_array($lfExtraIds)) {
                        foreach ($lfExtraIds as $eId) {
                            if ($eId && !in_array($eId, $extraIdsToLoad)) $extraIdsToLoad[] = $eId;
                        }
                    }
                }
            } catch (\Throwable $_) {
                // ignore
            }
            $serviceMasters = [];
            $extraMasters = [];
            if (!empty($serviceIdsToLoad)) {
                $serviceMasters = Service::whereIn('id', $serviceIdsToLoad)->get()->keyBy('id');
            }
            if (!empty($extraIdsToLoad)) {
                $extraMasters = ExtraService::whereIn('id', $extraIdsToLoad)->get()->keyBy('id');
            }

            // also map services loaded at the top (lead->service_ids_array) as masters fallback
            $leadMasters = [];
            try {
                if (!empty($services) && is_iterable($services)) {
                    foreach ($services as $s) {
                        if (isset($s->id)) $leadMasters[$s->id] = $s;
                    }
                }
            } catch (\Throwable $_) {}

            foreach ($detailedItems as $k => &$it) {
                $keyId = $it['id'] ?? null;
                $type = $it['type'] ?? 'service';
                // selling price from master
                $masterSell = 0;
                if ($type === 'service' && $keyId) {
                    if (isset($serviceMasters[$keyId])) {
                        $masterSell = (float) ($serviceMasters[$keyId]->service_amount ?? 0);
                    } elseif (isset($leadMasters[$keyId])) {
                        $masterSell = (float) ($leadMasters[$keyId]->service_amount ?? 0);
                    }
                }
                if ($type === 'extra' && $keyId) {
                    if (isset($extraMasters[$keyId])) {
                        $masterSell = (float) ($extraMasters[$keyId]->extra_service_amount ?? 0);
                    }
                }
                // override selling price with master if available
                if ($masterSell > 0) {
                    $it['selling_price'] = $masterSell;
                }

                // cost price from followup if available, else keep existing (vendor-based) or zero
                $followKey = ($type === 'extra' ? 'extra' : 'service') . '|' . ($keyId ?? 'unknown');
                if (isset($followupCosts[$followKey])) {
                    $it['cost_price'] = $followupCosts[$followKey];
                }

                // Fall back to master record names if we could not resolve a name earlier
                if (empty($it['name']) || strtolower($it['name']) === 'unknown') {
                    // Try master extra service
                    if ($type === 'extra' && $keyId && isset($extraMasters[$keyId])) {
                        $it['name'] = trim($extraMasters[$keyId]->extra_service ?? $extraMasters[$keyId]->service ?? $it['name']);
                    }

                    // Try master service
                    if (($type === 'service' || empty($it['name']) || strtolower($it['name']) === 'unknown') && $keyId && isset($serviceMasters[$keyId])) {
                        $it['name'] = trim($serviceMasters[$keyId]->service ?? $serviceMasters[$keyId]->service_name ?? $it['name']);
                    }

                    // Last-ditch: use lead masters loaded from `lead->service_ids_array`
                    if (($type === 'service' || empty($it['name']) || strtolower($it['name']) === 'unknown') && $keyId && isset($leadMasters[$keyId])) {
                        $it['name'] = trim($leadMasters[$keyId]->service ?? $leadMasters[$keyId]->service_name ?? $it['name']);
                    }
                }
            }
            unset($it);
        } catch (\Exception $e) {
            // ignore vendor detail read errors
        }

        // Rebuild `services` & `extraServices` lists using resolved item names
        $services = [];
        $extraServices = [];
        foreach ($detailedItems as $d) {
            if (($d['type'] ?? 'service') === 'extra') {
                $extraServices[] = $d['name'] ?? null;
            } else {
                $services[] = $d['name'] ?? null;
            }
        }

        // Normalize names: services may be mixed collections/strings depending on source
        $serviceNames = [];
        foreach ($services as $s) {
            if (is_object($s) && isset($s->service)) {
                $serviceNames[] = trim($s->service);
            } elseif (is_string($s)) {
                $serviceNames[] = trim($s);
            }
        }

        $extraServiceNames = [];
        foreach ($extraServices as $es) {
            if (is_object($es) && isset($es->extra_service)) {
                $extraServiceNames[] = trim($es->extra_service);
            } elseif (is_string($es)) {
                $extraServiceNames[] = trim($es);
            }
        }

        // Deduplicate while preserving order
        $serviceNames = array_values(array_unique(array_filter($serviceNames)));
        $extraServiceNames = array_values(array_unique(array_filter($extraServiceNames)));

        // Normalize detailed items to sequential array
        $detailedItemsList = array_values($detailedItems);

        return [
            'services' => $services,
            'extra_services' => $extraServices,
            'total_cost' => $totalServiceCost,
            'service_names' => !empty($serviceNames) ? implode(', ', $serviceNames) : null,
            'extra_service_names' => !empty($extraServiceNames) ? implode(', ', $extraServiceNames) : null,
            'detailed_items' => $detailedItemsList
        ];
    }

    /**
     * Get vendor information for a voucher.
     */
    private function getVendorInformation($voucher)
    {
        $vendorPayments = $voucher->vendorPayments;
        $totalVendorCost = 0;
        $totalPaid = 0;
        $vendors = [];


        foreach ($vendorPayments as $vendorPayment) {
            // Prefer different possible column names depending on model/source
            $vendorCost = $vendorPayment->total_vendor_service_amount ?? $vendorPayment->total_service_amount ?? $vendorPayment->total_service_amount ?? 0;
            // VendorPayment records store paid_amount on child records
            $paidAmount = ($vendorPayment->vendorPayments->sum('paid_amount')) ?? 0;

            $totalVendorCost += $vendorCost;
            $totalPaid += $paidAmount;

            if ($vendorPayment->vendor) {
                $vendors[] = [
                    'id' => $vendorPayment->id,
                    'name' => $vendorPayment->vendor->name,
                    'total_amount' => $vendorCost,
                    'paid_amount' => $paidAmount,
                    'balance' => $vendorCost - $paidAmount,
                    'payment_status' => $vendorPayment->payment_status ?? 'unpaid',
                    // expose original field if present
                    'raw_total_service_amount' => $vendorPayment->total_service_amount ?? null,
                    'raw_total_vendor_service_amount' => $vendorPayment->total_vendor_service_amount ?? null
                ];
            }
        }

        return [
            'vendors' => $vendors,
            'totalVendorCost' => $totalVendorCost,
            'totalPaid' => $totalPaid,
            'totalBalance' => $totalVendorCost - $totalPaid,
            // legacy key to make it explicit in invoice templates
            'total_service_amount' => $totalVendorCost
        ];
    }

    /**
     * Get ride information for a lead.
     */
    private function getRideInformation($lead)
    {
        $rideSegments = $lead->rideSegments;
        
        if ($rideSegments->isEmpty()) {
            return [
                'from_date' => null,
                'to_date' => null,
                'from_place' => null,
                'to_place' => null
            ];
        }

        $firstSegment = $rideSegments->first();
        $lastSegment = $rideSegments->last();

        return [
            'from_date' => $firstSegment->from_date ?? null,
            'to_date' => $lastSegment->to_date ?? $firstSegment->to_date ?? null,
            'from_place' => $firstSegment->from_place ?? null,
            'to_place' => $lastSegment->to_place ?? $firstSegment->to_place ?? null
        ];
    }

    /**
     * Get all ride segments for multiple trips display.
     */
    private function getAllRideSegments($lead)
    {
        $rideSegments = $lead->rideSegments()->orderBy('from_date', 'asc')->get();
        
        return $rideSegments->map(function ($ride) {
            return [
                'id' => $ride->id,
                'from_date' => $ride->from_date,
                'to_date' => $ride->to_date,
                'from_place' => $ride->from_place,
                'to_place' => $ride->to_place,
                'total_time' => $ride->total_time,
                'is_tba' => $ride->is_tba
            ];
        })->toArray();
    }

    /**
     * Get payment history for a lead.
     */
    private function getPaymentHistory($leadId)
    {
                // 'leadFollowup' is available, but the LeadFollowup model does not define a
                // 'user' relationship in this codebase. Avoid eager-loading the nested
                // relation to prevent runtime errors. If you later add a 'user'
                // relationship on LeadFollowup, change this to eager-load it.
                return PaymentAuditTrail::whereHas('leadFollowup', function($q) use ($leadId) {
                        $q->where('lead_id', $leadId);
                })->with('leadFollowup')
                    ->orderBy('created_at', 'desc')
                    ->get();
    }

    /**
     * Get comprehensive invoice data.
     */
    private function getComprehensiveInvoiceData($voucher)
    {
        $lead = $voucher->lead;
        $client = $lead->client;
        
        // Get latest payment
        $latestPayment = $lead->leadFollowups()
            ->whereNotNull('received_amount')
            ->where('received_amount', '>', 0)
            ->orderBy('created_at', 'desc')
            ->first();

        $totalAmount = $latestPayment ? (float) $latestPayment->total_amount : 0;
        // Sum all approved payments for this lead (across followups)
        $totalReceived = PaymentAuditTrail::whereHas('leadFollowup', function($q) use ($lead) {
            $q->where('lead_id', $lead->id);
        })->where('payment_status', 1)->sum('paid_amount');

        // build service and vendor data once so we can reuse values and keep paths consistent
        $service = $this->getServiceInformation($lead);
        $vendor = $this->getVendorInformation($voucher);

        // ensure compatibility: copy aggregated vendor total into service.total_service_amount
        if (!isset($service['total_service_amount'])) {
            $service['total_service_amount'] = $vendor['total_service_amount'] ?? $vendor['totalVendorCost'] ?? 0;
        }

        return [
            'voucher' => $voucher,
            'client' => $client,
            'service' => $service,
            'vendor' => $vendor,
            'ride' => $this->getRideInformation($lead),
            'all_rides' => $this->getAllRideSegments($lead),
            'payment' => [
                'total_amount' => $totalAmount,
                'received_amount' => $totalReceived,
                'balance' => $totalAmount - $totalReceived,
                'latest_payment' => $latestPayment
            ],
            'profit_loss' => $totalReceived - ($vendor['totalVendorCost'] ?? 0)
        ];
    }

    /**
     * Generate unique invoice ID.
     */
    private function generateInvoiceId()
    {
        $year = Carbon::now()->year;
        $month = Carbon::now()->format('m');

        // Use year+month prefix so sequence resets each month: INV-YYYY-MM-####
        $prefix = "INV-{$year}-{$month}-";

        $lastInvoice = Invoice::where('invoice_id', 'like', "{$prefix}%")
            ->orderBy('invoice_id', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = intval(substr($lastInvoice->invoice_id, -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate PDF for invoice.
     */
    public function generatePDF($id)
    {
        try {

            Log::info("Generating PDF for voucher ID: " . $id);
            
            $voucher = Voucher::findOrFail($id);
            Log::info("Found voucher: " . $voucher->id);
            
            $invoiceData = $this->getComprehensiveInvoiceData($voucher);
            $existingInvoice = Invoice::where('voucher_id', $voucher->id)->first();

            // Provide absolute filesystem path for logo so DomPDF can embed the image
            $logo = public_path('assets/admin/images/logo.png');
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.account.invoices.invoices-preview', compact(
                'voucher', 
                'invoiceData', 
                'existingInvoice',
                'logo'
            ));

            Log::info("PDF generated successfully for voucher: " . $voucher->id);
            return $pdf->setPaper('a4', 'portrait')->stream('invoice-' . $voucher->id . '.pdf');
            
        } catch (\Exception $e) {
            Log::error('Error in InvoiceController@generatePDF: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Error generating PDF: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Download PDF for invoice.
     */
    public function downloadPDF($id)
    {
        try {
            Log::info("Downloading PDF for voucher ID: " . $id);
            
            $voucher = Voucher::findOrFail($id);
            Log::info("Found voucher for download: " . $voucher->id);
            
            $invoiceData = $this->getComprehensiveInvoiceData($voucher);
            $existingInvoice = Invoice::where('voucher_id', $voucher->id)->first();

            // Provide absolute filesystem path for logo so DomPDF can embed the image
            $logo = public_path('assets/admin/images/logo.png');
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.account.invoices.invoices-preview', compact(
                'voucher', 
                'invoiceData', 
                'existingInvoice',
                'logo'
            ));

            Log::info("PDF download generated successfully for voucher: " . $voucher->id);
            return $pdf->setPaper('a4', 'portrait')->download('invoice-' . $voucher->id . '.pdf');
            
        } catch (\Exception $e) {
            Log::error('Error in InvoiceController@downloadPDF: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Error downloading PDF: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Preview invoice as HTML.
     */
    public function previewHTML($id)
    {
        try {
            Log::info("Previewing HTML for voucher ID: " . $id);
            
            $voucher = Voucher::findOrFail($id);
            $invoiceData = $this->getComprehensiveInvoiceData($voucher);
            $existingInvoice = Invoice::where('voucher_id', $voucher->id)->first();

            // Use HTTP-accessible asset URL for logo when rendering HTML preview in browser
            $logo = asset('assets/admin/images/logo.png');
            Log::info("HTML preview generated successfully for voucher: " . $voucher->id);
            return view('admin.account.invoices.invoices-preview', compact(
                'voucher', 
                'invoiceData', 
                'existingInvoice',
                'logo'
            ));
            
        } catch (\Exception $e) {
            Log::error('Error in InvoiceController@previewHTML: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Error generating preview: ' . $e->getMessage()], 500);
        }
    }
}
