<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\User;
use App\Models\Client;
use App\Models\Vendor;
use function App\Helpers\getRepresentativeIds;
use App\Models\Product;
use App\Models\Service;
use App\Models\Voucher;
use setasign\Fpdi\Fpdi;
use App\Models\LeadRide;
use App\Models\UserType;
use App\Mail\VoucherMail;
use App\Models\LeadFollowup;
use App\Models\LeadPayment;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\ExtraService;
use Illuminate\Http\Request;
use App\Models\LeadPassenger;
use App\Models\ServiceAddress;
use App\Models\LeadPaymentDetail;
use App\Models\LeadVendorPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use App\Models\LeadVendorPaymentDetail;
use App\Models\PaymentAuditTrail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\SendMessageController;

class VoucherController extends Controller
{
    /**
     * SendMessageController instance (injected).
     *
     * @var \App\Http\Controllers\SendMessageController
     */
    protected $sendMessageController;
    public function __construct(SendMessageController $sendMessageController)
    {
        $this->sendMessageController = $sendMessageController;
    }

    /**
     * Display a listing of leads with approved payments for voucher generation
     */
    public function index(Request $request)
    {
        $currentUser = auth()->user();
        $representatives = getRepresentativeIds($currentUser);

        // Get "Call Not Connected" service id
        $dnpService = Service::where('service', 'Call Not Connected')->first();
        $dnpServiceId = $dnpService ? $dnpService->id : null;

        // Base query - only leads with approved payments and no voucher created yet
        $query = Lead::with(['client', 'representative', 'rideSegments', 'leadFollowups.followedBy'])
            ->whereHas('leadFollowups', function ($q) {
                $q->whereHas('paymentAuditTrail', function ($pq) {
                    $pq->where('payment_status', 1)
                        ->where('paid_amount', '>', 0);
                });
            })
            ->whereDoesntHave('vouchers')
            ->where(function ($q) {
                $q->where(function ($subQ) {
                    $subQ->whereNotNull('service_ids')
                        ->whereRaw("service_ids::text != '[]'");
                })
                    ->orWhere(function ($subQ) {
                        $subQ->where(function ($nullServiceQ) {
                            $nullServiceQ->whereNull('service_ids')
                                ->orWhereRaw("service_ids::text = '[]'")
                                ->orWhereRaw("service_ids::text = 'null'");
                        })
                            ->whereDoesntHave('rideSegments');
                    });
            });

        if ($representatives) {
            $query->whereIn('representative_user_id', $representatives);
        }

        if ($dnpServiceId && !$request->filled('service_ids')) {
            $query->whereRaw("
                replace(trim(both '\"' from service_ids::text), '\\', '') NOT LIKE ?
            ", ['%' . $dnpServiceId . '%']);
        }

        // Date filters
        if ($request->filled('from_date')) {
            $fromDate = Carbon::parse($request->from_date)->startOfDay();
            $query->whereHas('rideSegments', fn($q) => $q->where('from_date', '>=', $fromDate));
        }
        if ($request->filled('to_date')) {
            $toDate = Carbon::parse($request->to_date)->endOfDay();
            $query->whereHas('rideSegments', fn($q) => $q->where('to_date', '<=', $toDate));
        }

        // Client filters
        foreach (['name', 'email', 'phone'] as $field) {
            if ($request->filled($field)) {
                $query->whereHas('client', fn($q) => $q->where($field === 'phone' ? 'contact_number' : $field, 'like', '%' . $request->$field . '%'));
            }
        }

        if ($request->filled('representative_user_id')) {
            $query->where('representative_user_id', $request->representative_user_id);
        }

        // Status filter (latest follow-up)
        if ($request->filled('status')) {
            $query->whereHas('leadFollowups', function ($q) use ($request) {
                $q->where('status', $request->status)
                    ->whereIn('id', function ($subQuery) {
                        $subQuery->select('id')
                            ->from('lead_followups as lf1')
                            ->whereColumn('lf1.lead_id', 'lead_followups.lead_id')
                            ->orderBy('lf1.created_at', 'desc')
                            ->limit(1);
                    });
            });
        }

        $leads = $query->orderBy('created_at', 'desc')->get();

        // Service filtering (PHP-based)
        if ($request->filled('service_ids')) {
            $serviceIds = is_array($request->service_ids) ? $request->service_ids : [$request->service_ids];

            $leads = $leads->filter(function ($lead) use ($serviceIds) {
                if (empty($lead->service_ids)) return false;

                $json = trim($lead->service_ids, '"');
                $json = str_replace('\\"', '"', $json);
                $ids = json_decode($json, true);

                $match = is_array($ids) && count(array_intersect($ids, $serviceIds)) > 0;
                return $match;
            });
        }

        // Product filtering (PHP-based)
        if ($request->filled('product_ids')) {
            $productIds = is_array($request->product_ids) ? $request->product_ids : [$request->product_ids];

            $leads = $leads->filter(function ($lead) use ($productIds) {
                if (empty($lead->product_ids)) return false;

                $json = trim($lead->product_ids, '"');
                $json = str_replace('\\"', '"', $json);
                $ids = json_decode($json, true);

                $match = is_array($ids) && count(array_intersect($ids, $productIds)) > 0;
                return $match;
            });
        }

        // Fetch supporting data
        $services = Service::where('status', 1)
            ->whereRaw("NOT (LOWER(service) LIKE ? OR LOWER(service) LIKE ?)", ['%call not connected%', '%no requirement%'])
            ->get();
        $products = Product::where('status', 1)
            ->whereRaw("NOT (LOWER(product) LIKE ? OR LOWER(product) LIKE ?)", ['%call not connected%', '%no requirement%'])
            ->get();

        $statusOptions = [
            0 => 'Initiated',
            1 => 'Active',
            2 => 'Cancelled',
            3 => 'Full Payment Received',
            4 => 'Partial Payment Received',
            5 => 'confirm',
            6 => 'Pending',
            7 => 'Reschedule',
            8 => 'Approve',
            9 => 'Reject'
        ];

        $staff = $this->getUsersInHierarchy() ?: collect([auth()->user()]);

        $leadsWithApprovedPayments = PaymentAuditTrail::where('payment_status', 1)
            ->where('paid_amount', '>', 0)
            ->whereHas('leadFollowup', fn($q) => $q->whereIn('status', [3, 4]))
            ->get()
            ->pluck('leadFollowup')
            ->filter()
            ->map(fn($lf) => $lf->lead_id)
            ->unique()
            ->values()
            ->toArray();

        return view('admin.pages.vouchers.index-voucher-generation', compact(
            'leads',
            'services',
            'products',
            'statusOptions',
            'staff',
            'leadsWithApprovedPayments'
        ));
    }

    /**
     * Get users in hierarchy for filtering
     */
    private function getUsersInHierarchy()
    {
        $currentUser = auth()->user();

        if (!$currentUser || !$currentUser->userType) {
            return collect([]);
        }

        $userType = $currentUser->userType->user_type;

        // Super admin sees all
        if ($userType === UserType::SUPER_ADMIN) {
            return User::where('status', 1)->get();
        }

        // Get user IDs from hierarchy
        $userIds = getRepresentativeIds($currentUser);

        if (empty($userIds)) {
            return collect([$currentUser]);
        }

        return User::whereIn('id', $userIds)->where('status', 1)->get();
    }

    public function showVoucherForm($lead_id)
    {
        try {
            $lead = Lead::with([
                'client.country',
                'client.city.state',
                'client.city.country',
                'rideSegments.serviceAddress.city.state',
                'rideSegments.serviceAddress.city.country',
                'latestFollowup'
            ])->findOrFail($lead_id);

            // Check if voucher already exists for this lead
            $existingVoucher = Voucher::where('lead_id', $lead_id)->first();

            if ($existingVoucher) {
                // If voucher exists, fetch information from LeadVendorPayment and LeadVendorPaymentDetail
                return $this->showExistingVoucherForm($lead, $existingVoucher);
            }

            // Get latest followup to fetch selected services and extra services
            $latestFollowup = $lead->leadFollowups()
                ->orderBy('created_at', 'desc')
                ->first();

            // Debug output
            Log::info('Followup data:', [
                'followup_id' => $latestFollowup?->id,
                'service_ids' => $latestFollowup?->service_ids,
                'extra_service_ids' => $latestFollowup?->extra_service_ids,
                'created_at' => $latestFollowup?->created_at
            ]);

            // Get selected services from latest followup
            $selectedServiceIds = $latestFollowup ?
                $this->cleanServiceIds($latestFollowup->service_ids) : [];

            $selectedExtraServiceIds = $latestFollowup ?
                $this->cleanServiceIds($latestFollowup->extra_service_ids) : [];

            // If no services in followup, use services from the lead itself as fallback
            if (empty($selectedServiceIds) && !empty($lead->service_ids)) {
                $leadServiceIds = is_string($lead->service_ids) ? json_decode($lead->service_ids, true) : $lead->service_ids;
                if (!empty($leadServiceIds)) {
                    $selectedServiceIds = $leadServiceIds;
                    Log::info('Using services from lead as fallback:', [
                        'lead_service_ids' => $leadServiceIds
                    ]);
                }
            }

            // Debug: Log the processed IDs
            Log::info('Processed IDs:', [
                'service_ids' => $selectedServiceIds,
                'extra_service_ids' => $selectedExtraServiceIds,
                'using_lead_fallback' => !empty($leadServiceIds ?? []) ? true : false
            ]);

            $selectedServices = Service::whereIn('id', $selectedServiceIds)->get();
            $selectedExtraServices = ExtraService::whereIn('id', $selectedExtraServiceIds)->get();

            // If we have service_details saved on the latest followup, use those to determine
            // the final (discounted) amounts for services and extra services. This ensures
            // the voucher shows the net amount after per-item discounts.
            $serviceDetails = [];
            if ($latestFollowup && $latestFollowup->service_details) {
                try {
                    $serviceDetails = is_string($latestFollowup->service_details) ? json_decode($latestFollowup->service_details, true) : $latestFollowup->service_details;
                } catch (\Exception $e) {
                    Log::warning('Failed to decode service_details for voucher generation: ' . $e->getMessage());
                }
            }

            // Apply discounted amounts to selected services
            foreach ($selectedServices as $service) {
                $detail = null;
                if (!empty($serviceDetails)) {
                    // firstWhere does not accept a closure as the key parameter in this context
                    // (it expects a key or key/value). Use first() with a closure instead.
                    $detail = collect($serviceDetails)->first(function ($d) use ($service) {
                        return ($d['type'] ?? null) === 'service' && ($d['id'] ?? null) == $service->id;
                    });
                }

                if ($detail) {
                    $original = floatval($detail['original_amount'] ?? $service->service_amount ?? 0);
                    $discount = floatval($detail['discount_amount'] ?? 0);
                    $service->amount = max(0, $original - $discount);
                } else {
                    // Fallback to the service's configured amount
                    $service->amount = floatval($service->service_amount ?? 0);
                }
            }

            // Apply discounted amounts to selected extra services
            foreach ($selectedExtraServices as $extraService) {
                $detail = null;
                if (!empty($serviceDetails)) {
                    // See note above: use first() with a closure to find matching extra_service detail
                    $detail = collect($serviceDetails)->first(function ($d) use ($extraService) {
                        return ($d['type'] ?? null) === 'extra_service' && ($d['id'] ?? null) == $extraService->id;
                    });
                }

                if ($detail) {
                    $original = floatval($detail['original_amount'] ?? $extraService->extra_service_amount ?? 0);
                    $discount = floatval($detail['discount_amount'] ?? 0);
                    $extraService->amount = max(0, $original - $discount);
                } else {
                    $extraService->amount = floatval($extraService->extra_service_amount ?? 0);
                }
            }

            // If no services found, add some fallback for testing
            if ($selectedServices->isEmpty()) {
                Log::info('No services found in followup, table will show empty with add buttons');
            }
            if ($selectedExtraServices->isEmpty()) {
                Log::info('No extra services found in followup, table will show empty with add buttons');
            }
            // Load vendors for each service and extra service (ensure we assign collections, not relation objects)
            foreach ($selectedServices as $service) {
                try {
                    // Service::vendors() returns a Collection (not a relation). Call it directly.
                    $vendors = $service->vendors();
                    $service->vendors = $vendors instanceof \Illuminate\Support\Collection ? $vendors : collect($vendors);
                } catch (\Throwable $e) {
                    Log::warning('Unable to load vendors collection for service: ' . $service->id . ' - ' . $e->getMessage());
                    $service->vendors = collect();
                }
            }
            foreach ($selectedExtraServices as $extraService) {
                try {
                    $vendors = $extraService->vendors();
                    $extraService->vendors = $vendors instanceof \Illuminate\Support\Collection ? $vendors : collect($vendors);
                } catch (\Throwable $e) {
                    Log::warning('Unable to load vendors collection for extra service: ' . $extraService->id . ' - ' . $e->getMessage());
                    $extraService->vendors = collect();
                }
            }

            // Get all services and extra services for dropdown
            $allServices = Service::where('status', 1)->get();
            $allExtraServices = ExtraService::where('status', 1)->get();

            // Load vendors for dropdown services (assign collections)
            foreach ($allServices as $service) {
                try {
                    $vendors = $service->vendors();
                    $service->vendors = $vendors instanceof \Illuminate\Support\Collection ? $vendors : collect($vendors);
                } catch (\Throwable $e) {
                    Log::warning('Unable to load vendors for service in dropdown: ' . $service->id . ' - ' . $e->getMessage());
                    $service->vendors = collect();
                }
            }
            foreach ($allExtraServices as $extraService) {
                try {
                    $vendors = $extraService->vendors();
                    $extraService->vendors = $vendors instanceof \Illuminate\Support\Collection ? $vendors : collect($vendors);
                } catch (\Throwable $e) {
                    Log::warning('Unable to load vendors for extra service in dropdown: ' . $extraService->id . ' - ' . $e->getMessage());
                    $extraService->vendors = collect();
                }
            }

            // Debug: Log vendor counts for final verification
            Log::info('Voucher form ready:', [
                'selected_services_count' => $selectedServices->count(),
                'selected_extra_services_count' => $selectedExtraServices->count(),
                'lead_id' => $lead->id,
                'followup_id' => $latestFollowup->id
            ]);

            // Get service addresses - show all initially, filtering will be done via JavaScript
            $serviceAddresses = \App\Models\ServiceAddress::where('status', 1)
                ->with(['service', 'product', 'city.state', 'city.country'])
                ->get();

            // Get operation team members: only users whose user type is one of OPERATIONS_ROLES
            $operationRoleNames = UserType::OPERATIONS_ROLES;
            $operationTypeIds = UserType::whereIn('user_type', $operationRoleNames)->pluck('id')->toArray();

            // If the logged-in user is super admin, show all operation users; otherwise scope to user's hierarchy
            $operationQuery = User::where('status', 1)
                ->select('id', 'name', 'email', 'contact_number')
                ->whereIn('user_type_id', $operationTypeIds);

            $currentUser = Auth::user();
            if ($currentUser && ! $currentUser->isSuperAdmin()) {
                // get allowed user ids from current user's hierarchy
                $allowed = $currentUser->getUsersInHierarchy()->pluck('id')->toArray();
                if (!empty($allowed)) {
                    $operationQuery->whereIn('id', $allowed);
                } else {
                    // if no allowed users, limit to current user only (fallback)
                    $operationQuery->where('id', $currentUser->id);
                }
            }

            $operationTeam = $operationQuery->get();

            // Check if handler sections should be shown
            $showHandlerSections = false;
            $isAirAmbulance = false;
            foreach ($selectedServices as $service) {
                $products = $service->getProducts();
                foreach ($products as $product) {
                    if ($product->is_private || $product->is_airambulance) {
                        $showHandlerSections = true;
                        if ($product->is_airambulance) {
                            $isAirAmbulance = true;
                        }
                        break 2;
                    }
                }
            }

            // Get pre-voucher passengers (from registration form) if they exist
            $preVoucherPassengers = $lead->preVoucherPassengers()
                ->where('name', '!=', '')
                ->get();

            return view('admin.pages.vouchers.generate-voucher', compact(
                'lead',
                'selectedServices',
                'selectedExtraServices',
                'allServices',
                'allExtraServices',
                'serviceAddresses',
                'operationTeam',
                'showHandlerSections',
                'isAirAmbulance',
                'preVoucherPassengers'
            ))->with('registration_link', null);
        } catch (\Exception $e) {
            Log::error('Error showing voucher form: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return redirect()->back()->with('error', 'Error loading voucher form: ' . $e->getMessage());
        }
    }

    /**
     * Show existing voucher form with data from LeadVendorPayment
     */
    // private function showExistingVoucherForm($lead, $existingVoucher)
    // {
    //     try {
    //         // Fetch voucher with all related data including lead rides
    //         $voucher = Voucher::with([
    //             'operationTeamUser',
    //             'passengers',
    //             'vendorPayments.vendor',
    //             'vendorPayments.paymentDetails.service',
    //             //'vendorPayments.paymentDetails.extraService',
    //             'lead.rideSegments.serviceAddress.service',
    //             'lead.rideSegments.serviceAddress.city'
    //         ])->find($existingVoucher->id);

    //         // Get services and extra services from LeadVendorPaymentDetail
    //         $selectedServices = collect();
    //         $selectedExtraServices = collect();

    //         foreach ($voucher->vendorPayments as $vendorPayment) {
    //             foreach ($vendorPayment->paymentDetails as $detail) {
    //                 if ($detail->is_extra_service) {
    //                     $extraService = ExtraService::find($detail->service_id);
    //                     if ($extraService) {
    //                         $extraService->vendor_id = $vendorPayment->vendor_id;
    //                         $extraService->amount = $detail->service_amount;
    //                         $extraService->vendor_amount = $detail->vendor_service_amount;
    //                         $selectedExtraServices->push($extraService);
    //                     }
    //                 } else {
    //                     $service = Service::find($detail->service_id);
    //                     if ($service) {
    //                         $service->vendor_id = $vendorPayment->vendor_id;
    //                         $service->amount = $detail->service_amount;
    //                         $service->vendor_amount = $detail->vendor_service_amount;
    //                         $selectedServices->push($service);
    //                     }
    //                 }
    //             }
    //         }

    //         // If the lead has a latest followup with service_details, prefer those
    //         // per-item discounted amounts for displaying the voucher amounts.
    //         try {
    //             $leadLatestFollowup = $lead->leadFollowups()->orderBy('created_at', 'desc')->first();
    //             $leadServiceDetails = [];
    //             if ($leadLatestFollowup && $leadLatestFollowup->service_details) {
    //                 $leadServiceDetails = is_string($leadLatestFollowup->service_details) ? json_decode($leadLatestFollowup->service_details, true) : $leadLatestFollowup->service_details;
    //             }

    //             if (!empty($leadServiceDetails)) {
    //                 foreach ($selectedServices as $service) {
    //                     // Use first() with closure to avoid passing a Closure into data_get via firstWhere
    //                     $detail = collect($leadServiceDetails)->first(function ($d) use ($service) {
    //                         return ($d['type'] ?? null) === 'service' && ($d['id'] ?? null) == $service->id;
    //                     });
    //                     if ($detail) {
    //                         $orig = floatval($detail['original_amount'] ?? $service->service_amount ?? 0);
    //                         $disc = floatval($detail['discount_amount'] ?? 0);
    //                         $service->amount = max(0, $orig - $disc);
    //                     }
    //                 }

    //                 foreach ($selectedExtraServices as $extraService) {
    //                     $detail = collect($leadServiceDetails)->first(function ($d) use ($extraService) {
    //                         return ($d['type'] ?? null) === 'extra_service' && ($d['id'] ?? null) == $extraService->id;
    //                     });
    //                     if ($detail) {
    //                         $orig = floatval($detail['original_amount'] ?? $extraService->extra_service_amount ?? 0);
    //                         $disc = floatval($detail['discount_amount'] ?? 0);
    //                         $extraService->amount = max(0, $orig - $disc);
    //                     }
    //                 }
    //             }
    //         } catch (\Exception $e) {
    //             Log::warning('Unable to apply lead followup service_details to existing voucher amounts: ' . $e->getMessage());
    //         }

    //         // Load vendors for each service and extra service (ensure collections)
    //         foreach ($selectedServices as $service) {
    //             try {
    //                 $vendors = $service->vendors();
    //                 $service->vendors = $vendors instanceof \Illuminate\Support\Collection ? $vendors : collect($vendors);
    //             } catch (\Throwable $e) {
    //                 Log::warning('Unable to load vendors collection for service (existing voucher path): ' . $service->id . ' - ' . $e->getMessage());
    //                 $service->vendors = collect();
    //             }
    //         }
    //         foreach ($selectedExtraServices as $extraService) {
    //             try {
    //                 $vendors = $extraService->vendors();
    //                 $extraService->vendors = $vendors instanceof \Illuminate\Support\Collection ? $vendors : collect($vendors);
    //             } catch (\Throwable $e) {
    //                 Log::warning('Unable to load vendors collection for extra service (existing voucher path): ' . $extraService->id . ' - ' . $e->getMessage());
    //                 $extraService->vendors = collect();
    //             }
    //         }

    //         // Get all services and extra services for dropdown
    //         $allServices = Service::where('status', 1)->get();
    //         $allExtraServices = ExtraService::where('status', 1)->get();

    //         // Load vendors for dropdown services (ensure collections)
    //         foreach ($allServices as $service) {
    //             try {
    //                 $vendors = $service->vendors();
    //                 $service->vendors = $vendors instanceof \Illuminate\Support\Collection ? $vendors : collect($vendors);
    //             } catch (\Throwable $e) {
    //                 Log::warning('Unable to load vendors for service in dropdown (existing voucher path): ' . $service->id . ' - ' . $e->getMessage());
    //                 $service->vendors = collect();
    //             }
    //         }
    //         foreach ($allExtraServices as $extraService) {
    //             try {
    //                 $vendors = $extraService->vendors();
    //                 $extraService->vendors = $vendors instanceof \Illuminate\Support\Collection ? $vendors : collect($vendors);
    //             } catch (\Throwable $e) {
    //                 Log::warning('Unable to load vendors for extra service in dropdown (existing voucher path): ' . $extraService->id . ' - ' . $e->getMessage());
    //                 $extraService->vendors = collect();
    //             }
    //         }

    //         // Get service addresses - show all initially, filtering will be done via JavaScript
    //         $serviceAddresses = \App\Models\ServiceAddress::where('status', 1)
    //             ->with(['service', 'product', 'city.state', 'city.country'])
    //             ->get();

    //         // Get operation team members: only users whose user type is one of OPERATIONS_ROLES
    //         $operationRoleNames = UserType::OPERATIONS_ROLES;
    //         $operationTypeIds = UserType::whereIn('user_type', $operationRoleNames)->pluck('id')->toArray();

    //         $operationQuery = User::where('status', 1)
    //             ->select('id', 'name', 'email', 'contact_number')
    //             ->whereIn('user_type_id', $operationTypeIds);

    //         $currentUser = Auth::user();
    //         if ($currentUser && ! $currentUser->isSuperAdmin()) {
    //             $allowed = $currentUser->getUsersInHierarchy()->pluck('id')->toArray();
    //             if (!empty($allowed)) {
    //                 $operationQuery->whereIn('id', $allowed);
    //             } else {
    //                 $operationQuery->where('id', $currentUser->id);
    //             }
    //         }

    //         $operationTeam = $operationQuery->get();

    //         // Check if handler sections should be shown
    //         $showHandlerSections = false;
    //         $isAirAmbulance = false;
    //         foreach ($selectedServices as $service) {
    //             $products = $service->getProducts();
    //             foreach ($products as $product) {
    //                 if ($product->is_private || $product->is_airambulance) {
    //                     $showHandlerSections = true;
    //                     if ($product->is_airambulance) {
    //                         $isAirAmbulance = true;
    //                     }
    //                     break 2;
    //                 }
    //             }
    //         }

    //         return view('admin.pages.vouchers.generate-voucher', compact(
    //             'lead',
    //             'selectedServices',
    //             'selectedExtraServices',
    //             'allServices',
    //             'allExtraServices',
    //             'serviceAddresses',
    //             'operationTeam',
    //             'showHandlerSections',
    //             'isAirAmbulance',
    //             'voucher'
    //         ))->with('registration_link', $voucher->registrationLink());
    //     } catch (\Exception $e) {
    //         Log::error('Error showing existing voucher form: ' . $e->getMessage());
    //         throw $e;
    //     }
    // }

    /**
     * FIXED VERSION: Show existing voucher form with data from LeadVendorPayment
     * This version properly aggregates services from BOTH:
     * 1. Existing voucher (LeadVendorPaymentDetail)
     * 2. All approved follow-ups (including services added after voucher creation)
     */
    private function showExistingVoucherForm($lead, $existingVoucher)
    {
        try {
            // Fetch voucher with all related data including lead rides
            $voucher = Voucher::with([
                'operationTeamUser',
                'passengers',
                'vendorPayments.vendor',
                'vendorPayments.paymentDetails.service',
                'lead.rideSegments.serviceAddress.service',
                'lead.rideSegments.serviceAddress.city'
            ])->find($existingVoucher->id);

            // STEP 1: Get services already saved in the voucher (from LeadVendorPaymentDetail)
            // Store as a keyed array so we can preserve vendor assignments and amounts
            $voucherServiceIds = collect();
            $voucherExtraServiceIds = collect();
            $voucherServiceData = []; // Store vendor/amount info from voucher

            foreach ($voucher->vendorPayments as $vendorPayment) {
                foreach ($vendorPayment->paymentDetails as $detail) {
                    if ($detail->is_extra_service) {
                        $voucherExtraServiceIds->push($detail->service_id);
                        $voucherServiceData['extra_' . $detail->service_id] = [
                            'vendor_id' => $vendorPayment->vendor_id,
                            'amount' => $detail->service_amount,
                            'vendor_amount' => $detail->vendor_service_amount
                        ];
                    } else {
                        $voucherServiceIds->push($detail->service_id);
                        $voucherServiceData['service_' . $detail->service_id] = [
                            'vendor_id' => $vendorPayment->vendor_id,
                            'amount' => $detail->service_amount,
                            'vendor_amount' => $detail->vendor_service_amount
                        ];
                    }
                }
            }

            // Start with the voucher's LeadVendorPaymentDetail as the base.
            $allServiceIds = $voucherServiceIds
                ->unique()
                ->filter()
                ->values()
                ->all();

            $allExtraServiceIds = $voucherExtraServiceIds
                ->unique()
                ->filter()
                ->values()
                ->all();

            // STEP 2B: If there's a newer follow-up (created after the voucher) with
            // service info, treat it as the source of truth for WHICH services to display.
            // This handles both additions (new service after voucher) and deletions
            // (user removed a service and re-generated the voucher, which creates/updates
            // a follow-up with only the remaining services).
            // We only look at the LATEST such follow-up — older ones may still reference
            // services that were intentionally deleted.
            try {
                $latestFollowupAfterVoucher = $lead->leadFollowups()
                    ->where('created_at', '>', $voucher->created_at)
                    ->where(function ($q) {
                        $q->whereNotNull('extra_service_ids')
                            ->orWhereNotNull('service_ids');
                    })
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($latestFollowupAfterVoucher) {
                    // Use the latest follow-up's service selections as the definitive list.
                    // Voucher data ($voucherServiceData) is still used in STEP 4 for
                    // vendor assignments and amounts of services that were in the voucher.
                    $fuServiceIds = $this->cleanServiceIds($latestFollowupAfterVoucher->service_ids);
                    $fuExtraServiceIds = $this->cleanServiceIds($latestFollowupAfterVoucher->extra_service_ids);

                    if (!empty($fuServiceIds)) {
                        $allServiceIds = array_values(array_unique($fuServiceIds));
                    }

                    if (!empty($fuExtraServiceIds)) {
                        $allExtraServiceIds = array_values(array_unique($fuExtraServiceIds));
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Error merging follow-up services into existing voucher: ' . $e->getMessage());
            }

            Log::info('Services for existing voucher (voucher + newer follow-ups merged)', [
                'voucher_id' => $voucher->id,
                'lead_id' => $lead->id,
                'voucher_services' => $allServiceIds,
                'voucher_extra_services' => $allExtraServiceIds
            ]);

            // STEP 4: Load actual service objects and apply amounts
            $selectedServices = collect();
            $selectedExtraServices = collect();

            // Load regular services
            foreach ($allServiceIds as $serviceId) {
                $service = Service::find($serviceId);
                if ($service) {
                    // Use voucher data if available (service was in original voucher)
                    $key = 'service_' . $serviceId;
                    if (isset($voucherServiceData[$key])) {
                        $service->vendor_id = $voucherServiceData[$key]['vendor_id'];
                        $service->amount = $voucherServiceData[$key]['amount'];
                        $service->vendor_amount = $voucherServiceData[$key]['vendor_amount'];
                    } else {
                        // New service added after voucher creation - use defaults
                        $service->amount = $service->service_amount;
                        // vendor_id will be null - user needs to assign vendor in UI
                    }
                    $selectedServices->push($service);
                }
            }

            // Load extra services
            foreach ($allExtraServiceIds as $extraServiceId) {
                $extraService = ExtraService::find($extraServiceId);
                if ($extraService) {
                    // Use voucher data if available (service was in original voucher)
                    $key = 'extra_' . $extraServiceId;
                    if (isset($voucherServiceData[$key])) {
                        $extraService->vendor_id = $voucherServiceData[$key]['vendor_id'];
                        $extraService->amount = $voucherServiceData[$key]['amount'];
                        $extraService->vendor_amount = $voucherServiceData[$key]['vendor_amount'];
                    } else {
                        // New extra service added after voucher creation - use defaults
                        $extraService->amount = $extraService->extra_service_amount;
                        // vendor_id will be null - user needs to assign vendor in UI
                    }
                    $selectedExtraServices->push($extraService);
                }
            }

            // STEP 5: Apply discounts from ALL followups' service_details (not just latest),
            // since services may come from different follow-ups. Later follow-ups take
            // priority for the same service/extra service.
            try {
                $allFollowups = $lead->leadFollowups()->orderBy('created_at', 'asc')->get();
                $mergedServiceDetails = [];

                foreach ($allFollowups as $fu) {
                    if ($fu->service_details) {
                        $fuDetails = is_string($fu->service_details)
                            ? json_decode($fu->service_details, true)
                            : $fu->service_details;
                        if (is_array($fuDetails)) {
                            foreach ($fuDetails as $d) {
                                $key = ($d['type'] ?? '') . '_' . ($d['id'] ?? '');
                                // Later follow-ups overwrite earlier ones for the same service
                                $mergedServiceDetails[$key] = $d;
                            }
                        }
                    }
                }

                $leadServiceDetails = array_values($mergedServiceDetails);

                if (!empty($leadServiceDetails)) {
                    // Apply discounts to regular services
                    foreach ($selectedServices as $service) {
                        $detail = collect($leadServiceDetails)->first(function ($d) use ($service) {
                            return ($d['type'] ?? null) === 'service' && ($d['id'] ?? null) == $service->id;
                        });
                        if ($detail) {
                            $orig = floatval($detail['original_amount'] ?? $service->service_amount ?? 0);
                            $disc = floatval($detail['discount_amount'] ?? 0);
                            $service->amount = max(0, $orig - $disc);
                        }
                    }

                    // Apply discounts to extra services
                    foreach ($selectedExtraServices as $extraService) {
                        $detail = collect($leadServiceDetails)->first(function ($d) use ($extraService) {
                            return ($d['type'] ?? null) === 'extra_service' && ($d['id'] ?? null) == $extraService->id;
                        });
                        if ($detail) {
                            $orig = floatval($detail['original_amount'] ?? $extraService->extra_service_amount ?? 0);
                            $disc = floatval($detail['discount_amount'] ?? 0);
                            $extraService->amount = max(0, $orig - $disc);
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Unable to apply lead followup service_details to existing voucher amounts: ' . $e->getMessage());
            }

            // Load vendors for each service and extra service (ensure collections)
            foreach ($selectedServices as $service) {
                try {
                    $vendors = $service->vendors();
                    $service->vendors = $vendors instanceof \Illuminate\Support\Collection ? $vendors : collect($vendors);
                } catch (\Throwable $e) {
                    Log::warning('Unable to load vendors collection for service (existing voucher path): ' . $service->id . ' - ' . $e->getMessage());
                    $service->vendors = collect();
                }
            }
            foreach ($selectedExtraServices as $extraService) {
                try {
                    $vendors = $extraService->vendors();
                    $extraService->vendors = $vendors instanceof \Illuminate\Support\Collection ? $vendors : collect($vendors);
                } catch (\Throwable $e) {
                    Log::warning('Unable to load vendors collection for extra service (existing voucher path): ' . $extraService->id . ' - ' . $e->getMessage());
                    $extraService->vendors = collect();
                }
            }

            // Get all services and extra services for dropdown
            $allServices = Service::where('status', 1)->get();
            $allExtraServices = ExtraService::where('status', 1)->get();

            // Load vendors for dropdown services (ensure collections)
            foreach ($allServices as $service) {
                try {
                    $vendors = $service->vendors();
                    $service->vendors = $vendors instanceof \Illuminate\Support\Collection ? $vendors : collect($vendors);
                } catch (\Throwable $e) {
                    Log::warning('Unable to load vendors for service in dropdown (existing voucher path): ' . $service->id . ' - ' . $e->getMessage());
                    $service->vendors = collect();
                }
            }
            foreach ($allExtraServices as $extraService) {
                try {
                    $vendors = $extraService->vendors();
                    $extraService->vendors = $vendors instanceof \Illuminate\Support\Collection ? $vendors : collect($vendors);
                } catch (\Throwable $e) {
                    Log::warning('Unable to load vendors for extra service in dropdown (existing voucher path): ' . $extraService->id . ' - ' . $e->getMessage());
                    $extraService->vendors = collect();
                }
            }

            // Get service addresses - show all initially, filtering will be done via JavaScript
            $serviceAddresses = \App\Models\ServiceAddress::where('status', 1)
                ->with(['service', 'product', 'city.state', 'city.country'])
                ->get();

            // Get operation team members: only users whose user type is one of OPERATIONS_ROLES
            $operationRoleNames = UserType::OPERATIONS_ROLES;
            $operationTypeIds = UserType::whereIn('user_type', $operationRoleNames)->pluck('id')->toArray();

            $operationQuery = User::where('status', 1)
                ->select('id', 'name', 'email', 'contact_number')
                ->whereIn('user_type_id', $operationTypeIds);

            $currentUser = Auth::user();
            if ($currentUser && ! $currentUser->isSuperAdmin()) {
                $allowed = $currentUser->getUsersInHierarchy()->pluck('id')->toArray();
                if (!empty($allowed)) {
                    $operationQuery->whereIn('id', $allowed);
                } else {
                    $operationQuery->where('id', $currentUser->id);
                }
            }

            $operationTeam = $operationQuery->get();

            // Check if handler sections should be shown
            $showHandlerSections = false;
            $isAirAmbulance = false;
            foreach ($selectedServices as $service) {
                $products = $service->getProducts();
                foreach ($products as $product) {
                    if ($product->is_private || $product->is_airambulance) {
                        $showHandlerSections = true;
                        if ($product->is_airambulance) {
                            $isAirAmbulance = true;
                        }
                        break 2;
                    }
                }
            }

            return view('admin.pages.vouchers.generate-voucher', compact(
                'lead',
                'selectedServices',
                'selectedExtraServices',
                'allServices',
                'allExtraServices',
                'serviceAddresses',
                'operationTeam',
                'showHandlerSections',
                'isAirAmbulance',
                'voucher'
            ))->with('registration_link', $voucher->registrationLink());
        } catch (\Exception $e) {
            Log::error('Error showing existing voucher form: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Store voucher data
     */
    public function storeVoucher(Request $request)
    {
        try {
            // Filter out empty/null passengers from the request BEFORE validation
            if ($request->has('passengers') && is_array($request->passengers)) {
                $cleanedPassengers = [];
                foreach ($request->passengers as $passenger) {
                    // Only keep passengers that have name filled (age is optional)
                    if (!empty($passenger['name'])) {
                        $cleanedPassengers[] = $passenger;
                    }
                }
                // Replace the passengers array with cleaned version
                $request->merge(['passengers' => $cleanedPassengers]);

                Log::info('Filtered passengers', [
                    'original_count' => count($request->passengers ?? []),
                    'cleaned_count' => count($cleanedPassengers)
                ]);
            }

            // Track and permanently delete any passengers the user removed in the UI (deleted_passenger_ids[])
            // We also keep the list so later transfer logic doesn't re-attach deleted pre-voucher passengers.
            $deletedPassengerIds = [];
            if ($request->has('deleted_passenger_ids')) {
                $deletedIds = array_filter((array) $request->input('deleted_passenger_ids'));
                $deletedPassengerIds = $deletedIds;
                if (!empty($deletedIds)) {
                    LeadPassenger::whereIn('id', $deletedIds)->delete();
                    Log::info('Deleted passengers via deleted_passenger_ids', ['deleted_ids' => $deletedIds]);
                }
            }
            // Define regex patterns as PHP variables to avoid escaping issues in inline rule strings
            $lettersRegex = '/^[\p{L}\s]+$/u'; // letters and spaces (unicode)
            // Do not use a fragile regex for narration (free-form user text). We'll validate narration as a string with a max length instead.

            // Custom validation with dynamic messages
            $rules = [
                'lead_id' => 'required|string|exists:leads,id',
                // Client name: only letters and spaces allowed (unicode letters supported)
                'client_name' => ['required', 'string', 'max:255'],
                'client_email' => 'required|email|max:255',
                'client_phone' => 'required|string|max:20',
                'client_whatsapp' => 'nullable|string|max:20',
                'operation_team_user_id' => 'required|exists:users,id',
                'services' => 'required|array|min:1',
                'services.*.service_id' => 'required|string|exists:services,id',
                'services.*.vendor_id' => 'required|exists:vendors,id',
                'services.*.amount' => 'required|numeric|min:0',
                'extra_services' => 'nullable|array',
                'extra_services.*.extra_service_id' => 'required_with:extra_services|string|exists:extra_services,id',
                'extra_services.*.vendor_id' => 'required_with:extra_services|exists:vendors,id',
                'extra_services.*.amount' => 'required_with:extra_services|numeric|min:0',
                'passengers' => 'required|array|min:1',
                'passengers.*.name' => 'required|string|max:255',
                'passengers.*.age' => 'nullable|integer|min:0|max:150',
                'passengers.*.contact_number' => 'nullable|string|max:20',
                'passengers.*.traveller_type' => 'nullable|string|in:adult,child,infant',
                'passengers.*.weight' => 'nullable|numeric|min:0|max:500',
                // Passenger document uploads: only images and pdf up to 5MB
                'passengers.*.front_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
                'passengers.*.back_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
                'extra_upload' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:2048',
                'rides' => 'nullable|array',
                'rides.*.is_tba' => 'nullable|boolean',
                'rides.*.service_date' => 'nullable|date',
                'rides.*.from_date' => 'nullable|date',
                'rides.*.to_date' => 'nullable|date',
                'rides.*.time_from' => 'nullable|date_format:H:i',
                'rides.*.time_to' => 'nullable|date_format:H:i',
                // Only letters and spaces allowed for city/contact person fields
                'rides.*.from_place' => ['nullable', 'string', 'max:255', 'regex:' . $lettersRegex],
                'rides.*.to_place' => ['nullable', 'string', 'max:255', 'regex:' . $lettersRegex],
                'rides.*.service_address_id' => 'nullable|exists:service_addresses,id',
                'rides.*.contact_person' => ['nullable', 'string', 'max:255', 'regex:' . $lettersRegex],
                'rides.*.contact_number' => 'nullable|string|max:20',
                // Handler and Additional person fields: names only letters/spaces, contacts numbers only
                'handler_person_name' => ['nullable', 'string', 'max:255', 'regex:' . $lettersRegex],
                'handler_person_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]+$/'],
                'additional_person_name' => ['nullable', 'string', 'max:255', 'regex:' . $lettersRegex],
                'additional_person_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]+$/'],
                'rides.*.total_time' => 'nullable|numeric',
                // Additional information: free-form text up to 2000 chars. Avoid regex to prevent PCRE errors on certain inputs.
                'naration' => ['nullable', 'string'],
            ];

            // Dynamic validation messages with row numbers
            $messages = [
                // Client Information Messages
                'client_name.required' => 'Client name is required.',
                'client_name.string' => 'Client name must be valid text.',
                'client_name.max' => 'Client name cannot exceed 255 characters.',
                //'client_name.regex' => 'Client name should contain only letters and spaces.',
                'client_email.required' => 'Client email is required.',
                'client_email.email' => 'Please enter a valid email address.',
                'client_email.max' => 'Client email cannot exceed 255 characters.',
                'client_phone.required' => 'Client phone number is required.',
                'client_phone.string' => 'Client phone must be valid text.',
                'client_phone.max' => 'Client phone cannot exceed 20 characters.',
                'client_whatsapp.string' => 'WhatsApp number must be valid text.',
                'client_whatsapp.max' => 'WhatsApp number cannot exceed 20 characters.',
                // Ride field messages for invalid characters
                'rides.*.from_place.regex' => 'Departure city should contain only letters and spaces.',
                'rides.*.to_place.regex' => 'Arrival city should contain only letters and spaces.',
                'rides.*.contact_person.regex' => 'Contact person should contain only letters and spaces.',
                'handler_person_name.regex' => 'Handler name should contain only letters and spaces.',
                'handler_person_name.max' => 'Handler name cannot exceed 255 characters.',
                'handler_person_phone.regex' => 'Handler contact should contain numbers only.',
                'handler_person_phone.max' => 'Handler contact cannot exceed 20 characters.',
                'additional_person_name.regex' => 'Additional person name should contain only letters and spaces.',
                'additional_person_name.max' => 'Additional person name cannot exceed 255 characters.',
                'additional_person_phone.regex' => 'Additional person contact should contain numbers only.',
                'additional_person_phone.max' => 'Additional person contact cannot exceed 20 characters.',
                // narration now validated by string length only

                // Operation Team Messages
                'operation_team_user_id.required' => 'Please select an operation team member.',
                'operation_team_user_id.exists' => 'Selected operation team member is invalid.',

                // Service & Vendor Messages
                'services.required' => 'At least one service must be added.',
                'services.min' => 'At least one service must be added.',

                // Passenger Messages
                'passengers.required' => 'At least one passenger must be added.',
                'passengers.min' => 'At least one passenger must be added.',

                // File Upload Messages
                'extra_upload.file' => 'Extra upload must be a file.',
                'extra_upload.mimes' => 'Extra upload must be a PDF, DOC, DOCX, JPG, JPEG, or PNG file.',
                'extra_upload.max' => 'Extra upload file size cannot exceed 2MB.',
                // Passenger document messages (generic)
                'passengers.*.front_document.file' => 'Passenger front document must be a file.',
                'passengers.*.front_document.mimes' => 'Passenger front document must be JPG, JPEG, PNG, or PDF.',
                'passengers.*.front_document.max' => 'Passenger front document cannot exceed 5MB.',
                'passengers.*.back_document.file' => 'Passenger back document must be a file.',
                'passengers.*.back_document.mimes' => 'Passenger back document must be JPG, JPEG, PNG, or PDF.',
                'passengers.*.back_document.max' => 'Passenger back document cannot exceed 5MB.',
            ];

            // Add dynamic messages for services array
            if ($request->has('services')) {
                foreach ($request->services as $index => $service) {
                    $rowNumber = $index + 1;
                    $messages["services.{$index}.service_id.required"] = "Please select a service for row {$rowNumber}.";
                    $messages["services.{$index}.service_id.exists"] = "Selected service in row {$rowNumber} is invalid.";
                    $messages["services.{$index}.vendor_id.required"] = "Please select a vendor for row {$rowNumber}.";
                    $messages["services.{$index}.vendor_id.exists"] = "Selected vendor in row {$rowNumber} is invalid.";
                    $messages["services.{$index}.amount.required"] = "Service amount is required for row {$rowNumber}.";
                    $messages["services.{$index}.amount.numeric"] = "Service amount in row {$rowNumber} must be a number.";
                    $messages["services.{$index}.amount.min"] = "Service amount in row {$rowNumber} must be at least 0.";
                }
            }

            // Add dynamic messages for extra services array
            if ($request->has('extra_services')) {
                foreach ($request->extra_services as $index => $extraService) {
                    $rowNumber = $index + 1;
                    $messages["extra_services.{$index}.extra_service_id.required_with"] = "Please select an extra service for row {$rowNumber}.";
                    $messages["extra_services.{$index}.extra_service_id.exists"] = "Selected extra service in row {$rowNumber} is invalid.";
                    $messages["extra_services.{$index}.vendor_id.required_with"] = "Please select a vendor for extra service row {$rowNumber}.";
                    $messages["extra_services.{$index}.vendor_id.exists"] = "Selected vendor in extra service row {$rowNumber} is invalid.";
                    $messages["extra_services.{$index}.amount.required_with"] = "Extra service amount is required for row {$rowNumber}.";
                    $messages["extra_services.{$index}.amount.numeric"] = "Extra service amount in row {$rowNumber} must be a number.";
                    $messages["extra_services.{$index}.amount.min"] = "Extra service amount in row {$rowNumber} must be at least 0.";
                }
            }

            // Add dynamic messages for passengers array
            // Only relax passenger validation for the 'generate' action if no voucher exists yet for this lead.
            $action = $request->input('action');
            $existingVoucherForLead = null;
            if ($request->filled('lead_id')) {
                $existingVoucherForLead = Voucher::where('lead_id', $request->input('lead_id'))->first();
            }

            // if ($action === 'generate' && !$existingVoucherForLead) {
            //     // First-time generate: allow empty passenger fields so voucher can be created.
            //     $rules['passengers'] = 'nullable|array';
            //     $rules['passengers.*.name'] = 'nullable|string|max:255';
            //     $rules['passengers.*.age'] = 'nullable|integer|min:0|max:150';
            //     $rules['passengers.*.contact_number'] = 'nullable|string|max:20';
            //     $rules['passengers.*.traveller_type'] = 'nullable|string|in:adult,child,infant';
            //     $rules['passengers.*.weight'] = 'nullable|numeric|min:0|max:500';
            // } 
            if ($action === 'generate') {
                // First-time generate: allow empty passenger fields so voucher can be created.
                $rules['passengers'] = 'nullable|array';
                $rules['passengers.*.name'] = 'nullable|string|max:255';
                $rules['passengers.*.age'] = 'nullable|integer|min:0|max:150';
                $rules['passengers.*.contact_number'] = 'nullable|string|max:20';
                $rules['passengers.*.traveller_type'] = 'nullable|string|in:adult,child,infant';
                $rules['passengers.*.weight'] = 'nullable|numeric|min:0|max:500';
            } else {
                // Subsequent generates or other actions: enforce passenger required rules and messages
                $rules['passengers'] = 'required|array|min:1';
                $rules['passengers.*.name'] = 'required|string|max:255';
                $rules['passengers.*.age'] = 'nullable|integer|min:0|max:150';
                $rules['passengers.*.contact_number'] = 'nullable|string|max:20';
                $rules['passengers.*.traveller_type'] = 'nullable|string|in:adult,child,infant';
                $rules['passengers.*.weight'] = 'nullable|numeric|min:0|max:500';

                if ($request->has('passengers')) {
                    foreach ($request->passengers as $index => $passenger) {
                        $passengerNumber = $index + 1;
                        $messages["passengers.{$index}.name.required"] = "Passenger {$passengerNumber} name is required.";
                        $messages["passengers.{$index}.name.string"] = "Passenger {$passengerNumber} name must be valid text.";
                        $messages["passengers.{$index}.name.max"] = "Passenger {$passengerNumber} name cannot exceed 255 characters.";
                        $messages["passengers.{$index}.age.integer"] = "Passenger {$passengerNumber} age must be a valid number.";
                        $messages["passengers.{$index}.age.min"] = "Passenger {$passengerNumber} age must be at least 0.";
                        $messages["passengers.{$index}.age.max"] = "Passenger {$passengerNumber} age cannot exceed 150.";
                        $messages["passengers.{$index}.weight.numeric"] = "Passenger {$passengerNumber} weight must be a valid number.";
                        $messages["passengers.{$index}.weight.min"] = "Passenger {$passengerNumber} weight must be at least 0.";
                        $messages["passengers.{$index}.weight.max"] = "Passenger {$passengerNumber} weight cannot exceed 500 kg.";
                    }
                }
            }

            $validator = Validator::make($request->all(), $rules, $messages);

            if ($validator->fails()) {
                // Log validation errors for debugging
                // dd($validator->errors()->toArray());
                Log::error('Voucher validation failed:', [
                    'errors' => $validator->errors()->toArray(),
                    'request_data' => $request->except(['_token', 'passengers.*.front_document', 'passengers.*.back_document', 'extra_upload'])
                ]);

                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput()
                    ->with('error', 'Please check and fix the validation errors highlighted below.');
            }

            // Additional server-side date sanity checks: ensure to_date is same or after from_date for each ride
            if ($request->has('rides')) {
                $dateErrors = new \Illuminate\Support\MessageBag();
                foreach ($request->rides as $idx => $rideData) {
                    // skip if TBA
                    $isTba = isset($rideData['is_tba']) && ($rideData['is_tba'] == '1' || $rideData['is_tba'] === true);
                    if ($isTba) continue;

                    // If both from_date and to_date are provided, ensure to_date >= from_date
                    if (!empty($rideData['from_date']) && !empty($rideData['to_date'])) {
                        try {
                            $from = Carbon::parse($rideData['from_date'])->startOfDay();
                            $to = Carbon::parse($rideData['to_date'])->startOfDay();
                            if ($to->lt($from)) {
                                $dateErrors->add("rides.{$idx}.to_date", "To Date must be the same as or after From Date for trip " . ($idx + 1));
                            }
                        } catch (\Exception $e) {
                            // ignore parse errors here; base validator handles date format
                        }
                    }
                }

                if ($dateErrors->any()) {
                    // Merge with existing validator errors if any
                    return redirect()->back()->withErrors($dateErrors)->withInput()->with('error', 'Please fix the ride date errors.');
                }
            }

            DB::beginTransaction();


            // Update client information if it has changed
            $lead = Lead::with('client')->findOrFail($request->lead_id);
            $client = $lead->client;

            $clientUpdated = false;
            if ($client->name !== $request->client_name) {
                $client->name = $request->client_name;
                $clientUpdated = true;
            }
            if ($client->email !== $request->client_email) {
                $client->email = $request->client_email;
                $clientUpdated = true;
            }
            if ($client->contact_number !== $request->client_phone) {
                $client->contact_number = $request->client_phone;
                $clientUpdated = true;
            }
            if ($request->has('client_whatsapp') && $client->alternate_number !== $request->client_whatsapp) {
                $client->alternate_number = $request->client_whatsapp;
                $clientUpdated = true;
            }

            if ($clientUpdated) {
                $client->save();
                Log::info('Client information updated for lead: ' . $request->lead_id);
            }

            // Update travel information if provided
            $this->updateTravelInformation($request, $lead);

            // Check if voucher already exists for this lead
            $existingVoucher = Voucher::where('lead_id', $request->lead_id)->first();

            if ($existingVoucher) {
                // Update existing voucher
                $voucherId = $existingVoucher->id;

                // Handle extra upload first
                $extraUploadPath = $existingVoucher->extra_upload; // Keep existing path by default
                if ($request->hasFile('extra_upload')) {
                    // Delete old file if exists
                    if ($existingVoucher->extra_upload) {
                        Storage::disk('public')->delete($existingVoucher->extra_upload);
                    }

                    $file = $request->file('extra_upload');
                    $filename = time() . '_' . $file->getClientOriginalName();
                    $extraUploadPath = $file->storeAs('vouchers/uploads', $filename, 'public');

                    // DEBUG: write stored path
                    try {
                        $debugPath = storage_path('logs/voucher-debug.txt');
                        $line = 'Existing voucher extra_upload saved: ' . ($extraUploadPath ?? 'null') . ' | exists_on_disk=' . (Storage::disk('public')->exists($extraUploadPath) ? 'yes' : 'no') . ' | time=' . date('c') . "\n";
                        @file_put_contents($debugPath, $line, FILE_APPEND | LOCK_EX);
                    } catch (\Throwable $e) {
                        // dd($e);
                        @file_put_contents('php://stderr', 'storeVoucher existing file write failed: ' . $e->getMessage() . "\n");
                    }
                }

                // Now update the voucher with all data including the upload path
                $existingVoucher->update([
                    'operation_team_user_id' => $request->operation_team_user_id,
                    'naration' => $request->naration,
                    'extra_upload' => $extraUploadPath,
                    'updated_at' => now(),
                    'registration_token' => $existingVoucher->registration_token ?? (string) Str::random(40)
                ]);

                // Collect existing LeadVendorPayment entries and their VendorPayment history so we can reassign
                $existingPayments = LeadVendorPayment::where('voucher_id', $voucherId)->get();
                $oldVendorPaymentsMap = [];
                $oldPaymentIds = [];
                foreach ($existingPayments as $payment) {
                    $key = is_null($payment->vendor_id) ? 'null_vendor' : (string) $payment->vendor_id;
                    $oldVendorPaymentsMap[$key] = \App\Models\VendorPayment::where('lead_vendor_payment_id', $payment->id)->get();
                    $oldPaymentIds[] = $payment->id;
                }

                // Delete only passengers whose IDs were explicitly marked for deletion via deleted_passenger_ids
                // Do NOT delete all passengers; we'll update existing ones and create new ones below
                if (!empty($deletedPassengerIds)) {
                    LeadPassenger::whereIn('id', $deletedPassengerIds)
                        ->where('voucher_id', $voucherId)
                        ->delete();
                }
            } else {
                // Create new voucher
                $voucherId = (string) Str::uuid();
                $voucher = Voucher::create([
                    'id' => $voucherId,
                    'lead_id' => $request->lead_id,
                    'operation_team_user_id' => $request->operation_team_user_id,
                    'naration' => $request->naration,
                    'status' => 1,
                    'created_by' => auth()->id(),
                    'registration_token' => (string) Str::random(40)
                ]);

                // Handle extra upload for new voucher
                if ($request->hasFile('extra_upload')) {
                    $file = $request->file('extra_upload');
                    $filename = time() . '_' . $file->getClientOriginalName();
                    $path = $file->storeAs('vouchers/uploads', $filename, 'public');
                    $voucher->update(['extra_upload' => $path]);

                    // DEBUG: write stored path for new voucher
                    try {
                        $debugPath = storage_path('logs/voucher-debug.txt');
                        $line = 'New voucher extra_upload saved: ' . ($path ?? 'null') . ' | exists_on_disk=' . (Storage::disk('public')->exists($path) ? 'yes' : 'no') . ' | time=' . date('c') . "\n";
                        @file_put_contents($debugPath, $line, FILE_APPEND | LOCK_EX);
                    } catch (\Throwable $e) {
                        @file_put_contents('php://stderr', 'storeVoucher new file write failed: ' . $e->getMessage() . "\n");
                    }
                }
            }

            // Process all passengers from form: update existing ones (with IDs) and create new ones (without IDs)
            $lead = Lead::findOrFail($request->lead_id);

            if ($request->has('passengers') && is_array($request->passengers)) {
                foreach ($request->passengers as $index => $passengerData) {
                    // Skip passengers with empty name (age is optional)
                    if (empty($passengerData['name'])) {
                        continue;
                    }

                    // Skip passengers whose IDs were explicitly deleted via deleted_passenger_ids
                    if (!empty($passengerData['id']) && in_array($passengerData['id'], $deletedPassengerIds)) {
                        Log::info('Skipping deleted passenger during form processing', ['passenger_id' => $passengerData['id']]);
                        continue;
                    }

                    // Determine if this is an existing passenger (has ID) or new (no ID)
                    if (!empty($passengerData['id'])) {
                        // Update existing passenger by ID
                        $passenger = LeadPassenger::find($passengerData['id']);
                        if ($passenger) {
                            $passenger->update([
                                'voucher_id' => $voucherId,
                                'name' => $passengerData['name'],
                                'age' => $passengerData['age'],
                                'contact_number' => $passengerData['contact_number'] ?? $passenger->contact_number,
                                'weight' => $passengerData['weight'] ?? $passenger->weight,
                            ]);

                            Log::info('Updated existing passenger', ['passenger_id' => $passenger->id]);
                        }
                    } else {
                        // Create new passenger (no ID in form)
                        $passenger = LeadPassenger::create([
                            'id' => (string) Str::uuid(),
                            'voucher_id' => $voucherId,
                            'lead_id' => $request->lead_id,
                            'name' => $passengerData['name'],
                            'age' => $passengerData['age'],
                            'contact_number' => $passengerData['contact_number'] ?? null,
                            'weight' => $passengerData['weight'] ?? null,
                            'is_handler' => false,
                            'is_additional_person' => false,
                        ]);

                        Log::info('Created new passenger', ['passenger_id' => $passenger->id]);
                    }

                    // Handle document uploads for both existing and new passengers
                    if ($request->hasFile("passengers.{$index}.front_document")) {
                        $file = $request->file("passengers.{$index}.front_document");
                        $filename = time() . '_front_' . $file->getClientOriginalName();
                        $path = $file->storeAs('vouchers/documents', $filename, 'public');
                        $passenger->update(['front_document' => $path]);
                    }

                    if ($request->hasFile("passengers.{$index}.back_document")) {
                        $file = $request->file("passengers.{$index}.back_document");
                        $filename = time() . '_back_' . $file->getClientOriginalName();
                        $path = $file->storeAs('vouchers/documents', $filename, 'public');
                        $passenger->update(['back_document' => $path]);
                    }
                }
            }

            // Store handler information if provided
            if ($request->has('handler_person_name') && !empty($request->handler_person_name)) {
                LeadPassenger::create([
                    'id' => (string) Str::uuid(),
                    'voucher_id' => $voucherId,
                    'name' => $request->handler_person_name,
                    'age' => 0, // Default age for handler
                    'contact_number' => $request->handler_person_phone ?? null,
                    //  'traveller_type' => 'adult',
                    'weight' => null,
                    'is_handler' => true,
                    'is_additional_person' => false,
                ]);
            }

            // Store additional person information if provided
            if ($request->has('additional_person_name') && !empty($request->additional_person_name)) {
                LeadPassenger::create([
                    'id' => (string) Str::uuid(),
                    'voucher_id' => $voucherId,
                    'name' => $request->additional_person_name,
                    'age' => 0, // Default age for additional person
                    'contact_number' => $request->additional_person_phone ?? null,
                    //'traveller_type' => 'adult',
                    'weight' => null,
                    'is_handler' => false,
                    'is_additional_person' => true,
                ]);
            }

            // Store service and vendor payment details
            // Group services and extra services by vendor so each vendor has a single LeadVendorPayment with summed totals
            $items = [];

            if ($request->has('services')) {
                foreach ($request->services as $serviceData) {
                    $items[] = [
                        'vendor_id' => $serviceData['vendor_id'] ?? null,
                        'service_id' => $serviceData['service_id'],
                        'service_amount' => $serviceData['amount'],
                        'vendor_service_amount' => $serviceData['vendor_amount'] ?? $serviceData['amount'],
                        'is_extra_service' => false,
                    ];
                }
            }

            if ($request->has('extra_services')) {
                foreach ($request->extra_services as $extraServiceData) {
                    $items[] = [
                        'vendor_id' => $extraServiceData['vendor_id'] ?? null,
                        // keep the same field stored previously for extra services
                        'service_id' => $extraServiceData['extra_service_id'],
                        'service_amount' => $extraServiceData['amount'],
                        'vendor_service_amount' => $extraServiceData['vendor_amount'] ?? $extraServiceData['amount'],
                        'is_extra_service' => true,
                    ];
                }
            }

            // Group by vendor_id (null vendors grouped together)
            $grouped = [];
            foreach ($items as $it) {
                $key = is_null($it['vendor_id']) ? 'null_vendor' : (string) $it['vendor_id'];
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'vendor_id' => $it['vendor_id'],
                        'items' => [],
                        'total_service_amount' => 0,
                        'total_vendor_service_amount' => 0,
                    ];
                }

                $grouped[$key]['items'][] = $it;
                $grouped[$key]['total_service_amount'] += floatval($it['service_amount']);
                $grouped[$key]['total_vendor_service_amount'] += floatval($it['vendor_service_amount']);
            }

            // Create one LeadVendorPayment per vendor and attach all corresponding details
            $createdVendorPaymentIds = [];
            foreach ($grouped as $group) {
                $vendorPayment = LeadVendorPayment::create([
                    'id' => (string) Str::uuid(),
                    'lead_id' => $request->lead_id,
                    'voucher_id' => $voucherId,
                    'vendor_id' => $group['vendor_id'] ?? null,
                    'total_service_amount' => $group['total_service_amount'],
                    'total_vendor_service_amount' => $group['total_vendor_service_amount'],
                    'payment_status' => 1 // Assuming 1 means pending
                ]);

                // Save created id by vendor key so we can reassign old vendor payments if present
                $createdKey = is_null($vendorPayment->vendor_id) ? 'null_vendor' : (string) $vendorPayment->vendor_id;
                $createdVendorPaymentIds[$createdKey] = $vendorPayment->id;

                foreach ($group['items'] as $detail) {
                    LeadVendorPaymentDetail::create([
                        'id' => (string) Str::uuid(),
                        'lead_vendor_payment_id' => $vendorPayment->id,
                        'service_id' => $detail['service_id'],
                        'service_amount' => $detail['service_amount'],
                        'vendor_service_amount' => $detail['vendor_service_amount'],
                        'is_extra_service' => $detail['is_extra_service'],
                        'status' => 1 // Assuming 1 means pending
                    ]);
                }
            }

            // Reassign any historical VendorPayment records from old LeadVendorPayment ids to the newly created ones
            if (!empty($oldVendorPaymentsMap)) {
                foreach ($oldVendorPaymentsMap as $vendorKey => $vendorPayments) {
                    if (isset($createdVendorPaymentIds[$vendorKey]) && $vendorPayments->isNotEmpty()) {
                        $newLeadVendorPaymentId = $createdVendorPaymentIds[$vendorKey];
                        foreach ($vendorPayments as $hist) {
                            try {
                                $hist->lead_vendor_payment_id = $newLeadVendorPaymentId;
                                $hist->save();
                            } catch (\Exception $e) {
                                Log::warning('Could not reassign vendor payment history: ' . $e->getMessage());
                            }
                        }
                    }
                }

                // After reassigning historical VendorPayment rows, delete old details and old LeadVendorPayment rows
                if (!empty($oldPaymentIds)) {
                    foreach ($oldPaymentIds as $oldId) {
                        LeadVendorPaymentDetail::where('lead_vendor_payment_id', $oldId)->delete();
                        LeadVendorPayment::where('id', $oldId)->delete();
                    }
                }
            }

            // Check for new services added to voucher and create follow-up entry if needed
            $this->createFollowUpForAddedServices($request, $lead, $existingVoucher);

            DB::commit();

            $successMessage = $existingVoucher ? 'Voucher updated successfully!' : 'Voucher generated successfully!';

            // Send registration link via WhatsApp for newly created vouchers
            // if (!$existingVoucher) {
            //     try {
            //         $voucher = Voucher::with('lead.client')->find($voucherId);
            //         if ($voucher) {
            //             $link = $voucher->registrationLink();
            //             if ($link) {
            //                 $clientName = $voucher->lead->client->name ?? 'Customer';
            //                 $whatsAppNumber = $voucher->lead->client->contact_number ?? null;

            //                 if (!empty($whatsAppNumber)) {
            //                     $whatsAppTemplate = 'Registration Link';
            //                     $whatsAppData = [$clientName, $link]; // {{1}} = name, {{2}} = link

            //                     $message = $this->sendMessageController->sendWhatsAppMessage(3, $whatsAppTemplate, $whatsAppData, $whatsAppNumber, $link);

            //                     if (isset($message['result']) && $message['result'] == false) {
            //                         Log::warning('WhatsApp registration link sending failed during voucher creation', [
            //                             'voucher' => $voucher->id, 
            //                             'message' => $message['message'] ?? 'Unknown error'
            //                         ]);
            //                     } else {
            //                         Log::info('Registration link sent via WhatsApp during voucher creation', ['voucher' => $voucher->id]);
            //                     }
            //                 }
            //             }
            //         }
            //     } catch (\Exception $e) {
            //         Log::error('Error sending registration link during voucher creation: ' . $e->getMessage());
            //         // Don't fail the whole process for WhatsApp error
            //     }
            // }

            // Handle different actions
            if ($request->action === 'generate_and_send') {
                return $this->generateAndSendVoucherPdf($voucherId, $request);
            } else if ($request->action === 'generate') {
                // Redirect back to form with success message
                return redirect()->route('admin.vouchers.form', $request->lead_id)->with('success', $successMessage);
            } else {
                return redirect()->route('admin.vouchers.pdf', $voucherId)->with('success', $successMessage);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error storing voucher: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return redirect()->back()
                ->with('error', 'Error processing voucher: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Generate voucher PDF
     */
    public function generateVoucherPdf(Request $request, $voucher_id)
    {
        try {
            $voucher = Voucher::with([
                'lead.client.country',
                'lead.client.city',
                'lead.rideSegments.serviceAddress.service',
                'lead.rideSegments.serviceAddress.city',
                'passengers',
                'operationTeamUser',
                'vendorPayments.vendor',
                'vendorPayments.paymentDetails.service',
                'lead.latestFollowup'
            ])->findOrFail($voucher_id);

            // Compute pending amount from PaymentAuditTrail (approved payments)
            // Ensure we fetch the actual latest followup (most recently created)
            $latestFollowup = $voucher->lead->leadFollowups()->orderBy('created_at', 'desc')->first();
            // Prefer followup total_amount; fallback to vendorPayments total_service_amount
            $totalAmount = optional($latestFollowup)->total_amount ?? $voucher->vendorPayments->sum('total_service_amount');
            $followupIds = $voucher->lead->leadFollowups->pluck('id')->toArray();
            $approvedPaid = 0;
            if (!empty($followupIds)) {
                $approvedPaid = PaymentAuditTrail::whereIn('lead_followup_id', $followupIds)
                    ->where('payment_status', 1)
                    ->sum('paid_amount');
            }
            $pendingAmount = max(0, $totalAmount - $approvedPaid);
            // Generate PDF using DomPDF with regular voucher template
            $pdf = PDF::loadView('admin.pages.pdf.regular-voucher-pdf', compact('voucher', 'pendingAmount'));
            $pdf->setPaper('A4', 'portrait');

            // Resolve extra upload to a local filesystem path (same logic as generateAndSendVoucherPdf)
            $extraPdfPath = null;
            if ($voucher->extra_upload) {
                $extra = $voucher->extra_upload;
                if (filter_var($extra, FILTER_VALIDATE_URL)) {
                    $parsedPath = parse_url($extra, PHP_URL_PATH) ?: '';
                    $storagePrefix = '/storage/';
                    if (strpos($parsedPath, $storagePrefix) === 0) {
                        $relative = substr($parsedPath, strlen($storagePrefix));
                        $candidate = storage_path('app/public/' . ltrim($relative, '/'));
                    } else {
                        $relative = ltrim($parsedPath, '/');
                        $candidate = public_path($relative);
                        if (!file_exists($candidate)) {
                            $candidate = storage_path('app/public/' . $relative);
                        }
                    }
                } else {
                    $candidate = storage_path('app/public/' . ltrim($extra, '/'));
                }

                if (isset($candidate) && file_exists($candidate)) {
                    $extraPdfPath = $candidate;
                } else {
                    $extraPdfPath = null;
                    Log::warning('Extra PDF path could not be resolved to a local file: ' . $voucher->extra_upload);
                }
            }

            // Decide response disposition: preview (inline) when ?preview=1 is present
            $isPreview = $request->query('preview') ? true : false;

            // If extra is a PDF file, merge it; otherwise return DomPDF download/stream
            if ($extraPdfPath && file_exists($extraPdfPath)) {
                // Save base voucher temporarily
                $tempPath = storage_path('app/public/vouchers/temp-' . $voucher->id . '.pdf');
                if (!file_exists(dirname($tempPath))) {
                    mkdir(dirname($tempPath), 0777, true);
                }
                $pdf->save($tempPath);

                // Merge using FPDI
                $fpdi = new Fpdi();

                // Add voucher pages
                $pageCount = $fpdi->setSourceFile($tempPath);
                for ($i = 1; $i <= $pageCount; $i++) {
                    $templateId = $fpdi->importPage($i);
                    $size = $fpdi->getTemplateSize($templateId);
                    $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $fpdi->useTemplate($templateId);
                }

                // Add extra pages
                $pageCount = $fpdi->setSourceFile($extraPdfPath);
                for ($i = 1; $i <= $pageCount; $i++) {
                    $templateId = $fpdi->importPage($i);
                    $size = $fpdi->getTemplateSize($templateId);
                    $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $fpdi->useTemplate($templateId);
                }

                // Output merged PDF as download or inline preview
                $merged = $fpdi->Output('S');
                @unlink($tempPath);

                $disposition = $isPreview ? 'inline' : 'attachment';

                return response($merged, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => $disposition . '; filename="voucher-' . $voucher->id . '.pdf"'
                ]);
            }

            // Fallback: return DomPDF download when there's no extra PDF or it couldn't be resolved
            if ($isPreview) {
                return $pdf->stream('voucher-' . $voucher->id . '.pdf');
            }

            return $pdf->download('voucher-' . $voucher->id . '.pdf');
        } catch (\Exception $e) {
            Log::error('Error generating voucher PDF: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error generating PDF: ' . $e->getMessage());
        }
    }

    /**
     * Generate and send voucher PDF
     */
    public function generateAndSendVoucherPdf($voucher_id, $request)
    {
        try {
            $voucher = Voucher::with([
                'lead.client.country',
                'lead.client.city',
                'lead.rideSegments.serviceAddress.service',
                'lead.rideSegments.serviceAddress.city',
                'passengers',
                'operationTeamUser',
                'vendorPayments.vendor',
                'vendorPayments.paymentDetails.service',
                'lead.latestFollowup'
            ])->findOrFail($voucher_id);

            // File name + storage path on the public disk (storage/app/public/vouchers)
            $storePath = 'vouchers/voucher-' . $voucher->id . '.pdf';
            $fullStoragePath = storage_path('app/public/' . $storePath);

            // Ensure the storage vouchers directory exists
            if (!file_exists(dirname($fullStoragePath))) {
                mkdir(dirname($fullStoragePath), 0777, true);
            }

            // Compute pending amount from PaymentAuditTrail (approved payments)
            // Ensure we fetch the actual latest followup (most recently created)
            $latestFollowup = $voucher->lead->leadFollowups()->orderBy('created_at', 'desc')->first();
            // Prefer followup total_amount; fallback to vendorPayments total_service_amount
            $totalAmount = optional($latestFollowup)->total_amount ?? $voucher->vendorPayments->sum('total_service_amount');
            $followupIds = $voucher->lead->leadFollowups->pluck('id')->toArray();
            $approvedPaid = 0;
            if (!empty($followupIds)) {
                $approvedPaid = PaymentAuditTrail::whereIn('lead_followup_id', $followupIds)
                    ->where('payment_status', 1)
                    ->sum('paid_amount');
            }
            $pendingAmount = max(0, $totalAmount - $approvedPaid);

            // Generate PDF using DomPDF with regular voucher template
            $pdf = PDF::loadView('admin.pages.pdf.regular-voucher-pdf', compact('voucher', 'pendingAmount'));

            // Set paper size and orientation
            $pdf->setPaper('A4', 'portrait');

            // Resolve extra upload to a local filesystem path.
            // The stored value might be a relative storage path (e.g. "vouchers/..."),
            // or a full URL (e.g. http://localhost/storage/vouchers/...).
            $extraPdfPath = null;
            if ($voucher->extra_upload) {
                $extra = $voucher->extra_upload;

                // If it's a full URL, try to extract the path component and map to storage/public
                if (filter_var($extra, FILTER_VALIDATE_URL)) {
                    $parsedPath = parse_url($extra, PHP_URL_PATH) ?: '';
                    // If URL contains "/storage/" prefix (default Laravel public storage), strip it
                    $storagePrefix = '/storage/';
                    if (strpos($parsedPath, $storagePrefix) === 0) {
                        $relative = substr($parsedPath, strlen($storagePrefix));
                        $candidate = storage_path('app/public/' . ltrim($relative, '/'));
                    } else {
                        // Otherwise try mapping the URL path directly to public (fallback)
                        $relative = ltrim($parsedPath, '/');
                        $candidate = public_path($relative);
                        if (!file_exists($candidate)) {
                            $candidate = storage_path('app/public/' . $relative);
                        }
                    }
                } else {
                    // Not a URL: assume it's a relative storage path
                    $candidate = storage_path('app/public/' . ltrim($extra, '/'));
                }

                if (isset($candidate) && file_exists($candidate)) {
                    $extraPdfPath = $candidate;
                } else {
                    // If the resolved candidate doesn't exist, null it so merging is skipped
                    $extraPdfPath = null;
                    Log::warning('Extra PDF path could not be resolved to a local file: ' . $voucher->extra_upload);
                }
            }

            // Create merged (or base) voucher PDF file and return its full storage path
            $fullStoragePath = $this->saveVoucherPdfWithOptionalExtra($voucher, $pdf, $storePath);

            // Return public URL built from the public disk. This will produce
            // a URL like /storage/vouchers/voucher-....pdf which maps to
            // storage/app/public/vouchers via the storage:link symlink.
            $filePath = asset('storage/' . $storePath);

            // Data
            $data = [
                'voucher_id'     => $voucher->id,
                'client_name'    => $voucher->lead->client->name, // Client Name
                'service'        => $voucher->vendorPayments->first()->paymentDetails->first()->service->service ?? 'N/A', // Service
                // Pickup date/time: avoid formatting time when the ride is marked TBA
                'pickup_date'    => optional($voucher->lead->rideSegments->first())->from_date ? optional($voucher->lead->rideSegments->first()->from_date)->format('jS F, Y') : 'N/A', // Pickup Date
                'pickup_time'    => (function () use ($voucher) {
                    $ride = $voucher->lead->rideSegments->first();
                    if (!$ride || empty($ride->from_date)) return 'N/A';
                    // If ride is explicitly marked TBA, return 'TBA' (do not show 12:00)
                    if (!empty($ride->is_tba)) return 'TBA';
                    try {
                        return \Carbon\Carbon::parse($ride->from_date)->format('h:i A');
                    } catch (\Throwable $ex) {
                        return is_string($ride->from_date) ? date('h:i A', strtotime($ride->from_date)) : 'N/A';
                    }
                })(), // Pickup Time
                'product'        => $voucher->vendorPayments->first()->paymentDetails->first()->service->products->first()->product ?? 'N/A', // Product
                'location'       =>  $voucher->lead->rideSegments->first()->serviceAddress->address ?? 'N/A', // Amount
                'contact_person' => $voucher->lead->rideSegments->first()->serviceAddress->contact_person_name ?? 'N/A', // Contact Person Name
                'contact_number' => $voucher->lead->rideSegments->first()->serviceAddress->contact_number ?? 'N/A', // Contact Person Number
                'map_link'       => $voucher->lead->rideSegments->first()->serviceAddress->map_link ?? 'N/A', // Google map link
                'naration'       => $voucher->naration ?? 'N/A', // Naration
            ];
            $whatsAppdata = [
                $data['client_name'],
                $data['service'],
                $data['pickup_date'],
                $data['pickup_time'],
                $data['product'],
                $data['location'],
                $data['contact_person'],
                $data['contact_number'],
                $data['map_link'],
                $data['naration'],
            ];

            // Prefer the client's WhatsApp/alternate number if available, otherwise use contact number
            $whatsAppNumber = !empty($voucher->lead->client->alternate_number)
                ? $voucher->lead->client->alternate_number
                : $voucher->lead->client->contact_number;
            // $whatsAppNumber = '+91-9870029314'; // Uncomment this line for local testing

            $type = 2; // message with file
            $whatsAppTemplate = 'customer_whatsapp_msg_0e';
            // Ensure $filePath is the public URL to the generated voucher file
            $filePath = asset('storage/' . $storePath);
            // Example fallback:
            // $filePath = 'https://webaccertionaviation.pleximus.co.in/storage/vouchers/documents/1755523270_front_image.png';

            // Send email (validate recipient)
            $emailTemplate = 'emails.voucher';
            // Use a descriptive booking confirmation subject instead of exposing the voucher id
            $subject = 'Booking Confirmation for ' . ($data['client_name'] ?? 'Client');
            $recipientEmail = $voucher->lead->client->email ?? null;
            if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                Log::warning('Invalid or missing recipient email for voucher send', ['voucher' => $voucher->id, 'email' => $recipientEmail]);
                return redirect()->back()->with('error', 'Client email is not available or invalid. Cannot send voucher email.');
            }
            Mail::to($recipientEmail)->send(new VoucherMail($emailTemplate, $subject, $data, $filePath));

            // Send WhatsApp
            $message = $this->sendMessageController->sendWhatsAppMessage($type, $whatsAppTemplate, $whatsAppdata, $whatsAppNumber, $filePath);

            if ($message['result'] == false) {
                return redirect()->back()->with('error', 'Error sending voucher PDF: ' . $message['message']);
            }

            // NOTE: do not delete the file immediately. The WhatsApp/SMS provider
            // may fetch the file asynchronously after we return. Removing it here
            // caused 404s when the provider attempted to retrieve the file.
            // Consider scheduling a cleanup job to remove old vouchers later.
            return redirect()->back()->with('success', 'Voucher generated and sent successfully!', $message['message']);
        } catch (\Exception $e) {
            Log::error('Error generating and sending voucher PDF: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error generating PDF: ' . $e->getMessage());
        }
    }

    /**
     * Send voucher PDF via email
     */
    public function sendVoucherPdf($voucher_id)
    {
        try {
            $voucher = Voucher::with([
                'lead.client.country',
                'lead.client.city',
                'lead.rideSegments.serviceAddress',
                'passengers',
                'vendorPayments.paymentDetails.service'
            ])->findOrFail($voucher_id);

            // File name + storage path on public disk
            $storePath = 'vouchers/voucher-' . $voucher->id . '.pdf';
            $fullStoragePath = storage_path('app/public/' . $storePath);

            if (!file_exists(dirname($fullStoragePath))) {
                mkdir(dirname($fullStoragePath), 0777, true);
            }

            // Compute pending amount from PaymentAuditTrail (approved payments)
            // Ensure we fetch the actual latest followup (most recently created)
            $latestFollowup = $voucher->lead->leadFollowups()->orderBy('created_at', 'desc')->first();
            $totalAmount = optional($latestFollowup)->total_amount ?? $voucher->vendorPayments->sum('total_service_amount');
            $followupIds = $voucher->lead->leadFollowups->pluck('id')->toArray();
            $approvedPaid = 0;
            if (!empty($followupIds)) {
                $approvedPaid = PaymentAuditTrail::whereIn('lead_followup_id', $followupIds)
                    ->where('payment_status', 1)
                    ->sum('paid_amount');
            }
            $pendingAmount = max(0, $totalAmount - $approvedPaid);

            // Generate PDF and save (merge if extra exists)
            $pdf = PDF::loadView('admin.pages.pdf.regular-voucher-pdf', compact('voucher', 'pendingAmount'));
            $pdf->setPaper('A4', 'portrait');
            $fullStoragePath = $this->saveVoucherPdfWithOptionalExtra($voucher, $pdf, 'vouchers/voucher-' . $voucher->id . '.pdf');

            // Prepare email data (reuse same structure used elsewhere)
            $ride = $voucher->lead->rideSegments->first();
            $pickup_date = 'N/A';
            $pickup_time = 'N/A';
            if ($ride && !empty($ride->from_date)) {
                try {
                    $cd = \Carbon\Carbon::parse($ride->from_date);
                    $pickup_date = $cd->format('jS F, Y');
                    if (!empty($ride->is_tba)) {
                        $pickup_time = 'TBA';
                    } else {
                        $pickup_time = $cd->format('h:i A');
                    }
                } catch (\Throwable $ex) {
                    $pickup_date = is_string($ride->from_date) ? date('jS F, Y', strtotime($ride->from_date)) : 'N/A';
                    $pickup_time = is_string($ride->from_date) ? date('h:i A', strtotime($ride->from_date)) : 'N/A';
                }
            }

            $data = [
                'voucher_id'     => $voucher->id,
                'client_name'    => $voucher->lead->client->name,
                'service'        => $voucher->vendorPayments->first()->paymentDetails->first()->service->service ?? 'N/A',
                'pickup_date'    => $pickup_date,
                'pickup_time'    => $pickup_time,
                'product'        => $voucher->vendorPayments->first()->paymentDetails->first()->service->products->first()->product ?? 'N/A',
                'location'       => $voucher->lead->rideSegments->first()->serviceAddress->address ?? 'N/A',
                'contact_person' => $voucher->lead->rideSegments->first()->serviceAddress->contact_person_name ?? 'N/A',
                'contact_number' => $voucher->lead->rideSegments->first()->serviceAddress->contact_number ?? 'N/A',
                'map_link'       => $voucher->lead->rideSegments->first()->serviceAddress->map_link ?? 'N/A',
                'naration'       => $voucher->naration ?? 'N/A',
            ];

            // Attach local storage path
            $fileUrl = $fullStoragePath; // attach local path

            $emailTemplate = 'emails.voucher';
            // Use a descriptive booking confirmation subject instead of exposing the voucher id
            $subject = 'Booking Confirmation for ' . ($data['client_name'] ?? 'Client');

            $recipientEmail = $voucher->lead->client->email ?? null;
            if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                Log::warning('Invalid or missing recipient email for voucher PDF send', ['voucher' => $voucher->id, 'email' => $recipientEmail]);
                // Do not unlink immediately; external providers may still fetch the file.
                // Optionally schedule cleanup of old voucher files.
                return response()->json(['success' => false, 'message' => 'Client email is not available or invalid. Cannot send voucher email.'], 400);
            }
            Mail::to($recipientEmail)->send(new VoucherMail($emailTemplate, $subject, $data, $fileUrl));

            // Check for transport-level failures (SMTP). This returns an array of failed recipients.
            try {
                $failures = Mail::failures();
            } catch (\Throwable $ex) {
                $failures = [];
            }

            if (!empty($failures)) {
                Log::warning('Voucher email reported failures', ['voucher' => $voucher->id, 'failures' => $failures]);
                // Delete generated file
                // Do not unlink immediately; external providers may still fetch the file.
                return response()->json([
                    'success' => false,
                    'message' => 'Mail transporter reported failures',
                    'failures' => $failures
                ], 500);
            }

            Log::info('Voucher email sent', ['voucher' => $voucher->id, 'to' => $voucher->lead->client->email]);

            // Keep the generated file for a short retention period. Cleanup
            // should be handled by a scheduled job or manual process.

            return response()->json([
                'success' => true,
                'message' => 'Voucher emailed successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending voucher: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error sending voucher: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate voucher PDF and send via WhatsApp (AJAX)
     */
    public function sendVoucherWhatsApp($voucher_id)
    {
        try {

            $voucher = Voucher::with(['lead.client', 'lead.rideSegments.serviceAddress', 'vendorPayments.paymentDetails.service'])->findOrFail($voucher_id);

            // Compute pending amount from PaymentAuditTrail (approved payments)
            // Ensure we fetch the actual latest followup (most recently created)
            $latestFollowup = $voucher->lead->leadFollowups()->orderBy('created_at', 'desc')->first();
            $totalAmount = optional($latestFollowup)->total_amount ?? $voucher->vendorPayments->sum('total_service_amount');
            $followupIds = $voucher->lead->leadFollowups->pluck('id')->toArray();
            $approvedPaid = 0;
            if (!empty($followupIds)) {
                $approvedPaid = PaymentAuditTrail::whereIn('lead_followup_id', $followupIds)
                    ->where('payment_status', 1)
                    ->sum('paid_amount');
            }

            $pendingAmount = max(0, $totalAmount - $approvedPaid);
            // Generate PDF to public storage temporarily
            // Save PDF to public disk so remote providers can fetch via /storage/...
            $storePath = 'vouchers/voucher-' . $voucher->id . '.pdf';
            $fullStoragePath = storage_path('app/public/' . $storePath);
            if (!file_exists(dirname($fullStoragePath))) {
                mkdir(dirname($fullStoragePath), 0777, true);
            }

            $pdf = PDF::loadView('admin.pages.pdf.regular-voucher-pdf', compact('voucher', 'pendingAmount'));
            $pdf->setPaper('A4', 'portrait');
            $fullStoragePath = $this->saveVoucherPdfWithOptionalExtra($voucher, $pdf, $storePath);

            // Build data for WhatsApp - provide the 10 template variables expected by the template
            $firstVendorPayment = $voucher->vendorPayments->first();
            $firstDetail = $firstVendorPayment ? $firstVendorPayment->paymentDetails->first() : null;
            $serviceObj = $firstDetail ? ($firstDetail->service ?? null) : null;

            $ride = $voucher->lead->rideSegments->first();
            // pickup date/time safe formatting
            $pickup_date = 'N/A';
            $pickup_time = 'N/A';
            if ($ride && !empty($ride->from_date)) {
                try {
                    $cd = \Carbon\Carbon::parse($ride->from_date);
                    $pickup_date = $cd->format('jS F, Y');
                    // If the ride is marked as TBA, don't show/format the time (avoid showing 12:00)
                    if (!empty($ride->is_tba)) {
                        $pickup_time = 'TBA';
                    } else {
                        $pickup_time = $cd->format('h:i A');
                    }
                } catch (\Throwable $ex) {
                    // fallback to raw
                    $pickup_date = is_string($ride->from_date) ? date('jS F, Y', strtotime($ride->from_date)) : 'N/A';
                    $pickup_time = is_string($ride->from_date) ? date('h:i A', strtotime($ride->from_date)) : 'N/A';
                }
            }

            $serviceName = $serviceObj->service ?? ($serviceObj->service_name ?? 'N/A');
            $productName = 'N/A';
            if ($serviceObj && isset($serviceObj->products) && $serviceObj->products->first()) {
                $productName = $serviceObj->products->first()->product ?? 'N/A';
            }

            $location = $ride && $ride->serviceAddress ? ($ride->serviceAddress->address ?? 'N/A') : 'N/A';
            $contact_person = $ride && $ride->serviceAddress ? ($ride->serviceAddress->contact_person_name ?? 'N/A') : 'N/A';
            $contact_number = $ride && $ride->serviceAddress ? ($ride->serviceAddress->contact_number ?? 'N/A') : 'N/A';
            $map_link = $ride && $ride->serviceAddress ? ($ride->serviceAddress->map_link ?? 'N/A') : 'N/A';

            $whatsAppdata = [
                $voucher->lead->client->name ?? 'Customer',           // client_name
                $serviceName,                                         // service
                $pickup_date,                                         // pickup_date
                $pickup_time,                                         // pickup_time
                $productName,                                         // product
                $location,                                            // location
                $contact_person,                                      // contact_person
                $contact_number,                                      // contact_number
                $map_link,                                            // map_link
                $voucher->naration ?? 'N/A',                          // naration
            ];

            // Ensure template variables are strings and exact count required by template
            $whatsAppdata = array_map(function ($v) {
                if (is_null($v)) return '';
                if (is_array($v) || is_object($v)) {
                    return json_encode($v);
                }
                return (string) $v;
            }, $whatsAppdata);

            // Validate WhatsApp number
            $whatsAppNumber = !empty($voucher->lead->client->alternate_number) ? $voucher->lead->client->alternate_number : $voucher->lead->client->contact_number;
            if (empty($whatsAppNumber)) {
                Log::warning('Missing WhatsApp number for voucher', ['voucher' => $voucher->id]);
                return response()->json(['success' => false, 'message' => 'Client WhatsApp number is not available'], 400);
            }

            $filePath = asset('storage/' . $storePath);
            $filename = 'voucher-' . $voucher->id . '.pdf';

            // Log payload for debugging
            Log::info('Sending Voucher WhatsApp via WhatsCRM Vouchers Account', [
                'voucher' => $voucher->id,
                'template' => 'customer_whatsapp_msg_ke',
                'bodyValues' => $whatsAppdata,
                'number' => $whatsAppNumber,
                'file' => $filePath
            ]);

            // NEW: Use dedicated WhatsCRM Vouchers method with separate account
            $message = $this->sendMessageController->sendWhatsCrmVoucherMessage(
                $whatsAppNumber,      // Phone number (WhatsCRM handles +91 format)
                $whatsAppdata,        // Template body variables (10 parameters)
                $filePath,            // PDF file URL
                $filename,            // Filename (e.g., voucher-123.pdf)
                'customer_whatsapp_msg_ke'  // Template name
            );

            // Log response for debugging
            Log::info('WhatsCRM Vouchers API response', ['voucher' => $voucher->id, 'response' => $message]);

            // Keep the temporary file available briefly; don't unlink immediately.
            // Consider a scheduled cleanup to remove old voucher files.

            if (!($message['success'] ?? false)) {
                return response()->json(['success' => false, 'message' => $message['message'] ?? 'WhatsApp send failed'], 500);
            }

            return response()->json(['success' => true, 'message' => 'WhatsApp sent successfully via WhatsCRM Vouchers Account']);
        } catch (\Exception $e) {
            Log::error('Error sending voucher via WhatsApp: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete the extra_upload attachment for a voucher
     */
    public function deleteAttachment(Request $request, $voucher_id)
    {
        try {
            $voucher = Voucher::findOrFail($voucher_id);

            if (empty($voucher->extra_upload)) {
                return redirect()->back()->with('error', 'No attachment found for this voucher.');
            }

            // Delete from public disk if exists
            try {
                if (Storage::disk('public')->exists($voucher->extra_upload)) {
                    Storage::disk('public')->delete($voucher->extra_upload);
                }
            } catch (\Throwable $e) {
                // Log and continue to null the DB reference
                Log::warning('Failed deleting voucher extra_upload file from disk: ' . $e->getMessage());
            }

            $voucher->extra_upload = null;
            //  $voucher->created_by = auth()->id() ?? $voucher->created_by;
            $voucher->save();

            return redirect()->back()->with('success', 'Attachment deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Error deleting voucher attachment: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error deleting attachment: ' . $e->getMessage());
        }
    }

    /**
     * Resend registration link to client email and WhatsApp
     */
    public function resendRegistrationLink($voucher_id)
    {
        try {
            $voucher = Voucher::with('lead.client')->findOrFail($voucher_id);
            $link = $voucher->registrationLink();
            if (!$link) {
                return response()->json(['success' => false, 'message' => 'Registration link not available'], 400);
            }

            $emailTemplate = 'emails.registration_link';
            // Use a clearer subject for registration emails and avoid including the voucher id
            $subject = 'Passenger Registration Link for ' . ($voucher->lead->client->name ?? 'Client');
            $data = ['registration_link' => $link, 'client_name' => $voucher->lead->client->name];

            $recipientEmail = $voucher->lead->client->email ?? null;
            if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                Log::warning('Invalid or missing recipient email for resend registration link', ['voucher' => $voucher->id, 'email' => $recipientEmail]);
                return response()->json(['success' => false, 'message' => 'Client email is not available or invalid. Cannot resend registration link.'], 400);
            }
            Mail::to($recipientEmail)->send(new VoucherMail($emailTemplate, $subject, $data, null));

            // Send WhatsApp with Registration Link template via WhatsCRM
            $whatsAppNumber = !empty($voucher->lead->client->alternate_number) ? $voucher->lead->client->alternate_number : $voucher->lead->client->contact_number;
            if (!empty($whatsAppNumber)) {
                $whatsAppData = [$voucher->lead->client->name, $link]; // {{1}} = name, {{2}} = link
                try {
                    $message = $this->sendMessageController->sendWhatsCrmRegistrationLinkMessage($whatsAppNumber, $whatsAppData);
                    if (!($message['success'] ?? false)) {
                        Log::warning('WhatsApp registration link sending failed', ['voucher' => $voucher->id, 'error' => $message['error'] ?? 'Unknown error']);
                    }
                } catch (\Exception $e) {
                    Log::error('Error sending WhatsApp registration link', ['voucher' => $voucher->id, 'error' => $e->getMessage()]);
                }
            }

            return response()->json(['success' => true, 'message' => 'Registration link resent via email and WhatsApp']);
        } catch (\Exception $e) {
            Log::error('Error resending registration link: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Send registration link via WhatsApp only
     */
    public function sendRegistrationLinkWhatsApp($voucher_id)
    {
        try {
            $voucher = Voucher::with('lead.client')->findOrFail($voucher_id);
            $link = $voucher->registrationLink();
            if (!$link) {
                return response()->json(['success' => false, 'message' => 'Registration link not available'], 400);
            }

            $clientName = $voucher->lead->client->name ?? 'Customer';
            $whatsAppNumber = !empty($voucher->lead->client->alternate_number) ? $voucher->lead->client->alternate_number : $voucher->lead->client->contact_number;

            if (empty($whatsAppNumber)) {
                return response()->json(['success' => false, 'message' => 'Client WhatsApp number is not available'], 400);
            }

            // Send WhatsApp with Registration Link template via WhatsCRM
            $whatsAppData = [$clientName, $link]; // {{1}} = name, {{2}} = link

            try {
                $message = $this->sendMessageController->sendWhatsCrmRegistrationLinkMessage($whatsAppNumber, $whatsAppData);
                if (!($message['success'] ?? false)) {
                    Log::warning('WhatsApp registration link sending failed', ['voucher' => $voucher->id, 'error' => $message['error'] ?? 'Unknown error']);
                    return response()->json(['success' => false, 'message' => 'Failed to send WhatsApp message: ' . ($message['error'] ?? 'Unknown error')], 400);
                }
            } catch (\Exception $e) {
                Log::error('Error sending WhatsApp registration link', ['voucher' => $voucher->id, 'error' => $e->getMessage()]);
                return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
            }

            return response()->json(['success' => true, 'message' => 'Registration link sent via WhatsApp successfully']);
        } catch (\Exception $e) {
            Log::error('Error sending registration link via WhatsApp: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update travel information if provided in request
     */
    private function updateTravelInformation(Request $request, $lead)
    {
        try {
            $updated = false;

            // Handle ride segments (travel information)
            if ($request->has('rides')) {
                foreach ($request->rides as $index => $rideData) {
                    // Get existing ride segment or create new one
                    $rideSegment = $lead->rideSegments->get($index);
                    if (!$rideSegment) {
                        $rideSegment = new LeadRide();
                        $rideSegment->id = (string) Str::uuid();
                        $rideSegment->lead_id = $lead->id;
                    }

                    // Handle TBA (To Be Announced) logic
                    $isTba = isset($rideData['is_tba']) && $rideData['is_tba'] == '1';

                    if ($isTba) {
                        // If TBA is checked, keep the date part but set time to 00:00:00
                        // (frontend clears time inputs, backend stores zeroed time)
                        if (isset($rideData['service_date'])) {
                            $date = $rideData['service_date'];
                            $rideSegment->from_date = $date . ' 00:00:00';
                            $rideSegment->to_date = $date . ' 00:00:00';
                        } else {
                            // Multiple day service - preserve date portion if available
                            if (isset($rideData['from_date'])) {
                                $rideSegment->from_date = $rideData['from_date'] . ' 00:00:00';
                            } elseif ($rideSegment->from_date) {
                                try {
                                    $d = Carbon::parse($rideSegment->from_date)->format('Y-m-d');
                                    $rideSegment->from_date = $d . ' 00:00:00';
                                } catch (\Exception $e) {
                                    $rideSegment->from_date = null;
                                }
                            } else {
                                $rideSegment->from_date = null;
                            }

                            if (isset($rideData['to_date'])) {
                                $rideSegment->to_date = $rideData['to_date'] . ' 00:00:00';
                            } elseif ($rideSegment->to_date) {
                                try {
                                    $d2 = Carbon::parse($rideSegment->to_date)->format('Y-m-d');
                                    $rideSegment->to_date = $d2 . ' 00:00:00';
                                } catch (\Exception $e) {
                                    $rideSegment->to_date = null;
                                }
                            } else {
                                $rideSegment->to_date = null;
                            }
                        }
                        $rideSegment->is_tba = true;
                    } else {
                        // Handle date and time combination
                        $fromDate = null;
                        $toDate = null;

                        if (isset($rideData['service_date'])) {
                            // Single service date (same day service)
                            $serviceDate = $rideData['service_date'];
                            $fromTime = $rideData['time_from'] ?? '00:00';
                            $toTime = $rideData['time_to'] ?? '00:00';

                            $fromDate = $serviceDate . ' ' . $fromTime . ':00';
                            $toDate = $serviceDate . ' ' . $toTime . ':00';
                        } else {
                            // Multiple day service
                            if (isset($rideData['from_date']) && isset($rideData['time_from'])) {
                                $fromDate = $rideData['from_date'] . ' ' . $rideData['time_from'] . ':00';
                            }
                            if (isset($rideData['to_date']) && isset($rideData['time_to'])) {
                                $toDate = $rideData['to_date'] . ' ' . $rideData['time_to'] . ':00';
                            }
                        }

                        $rideSegment->from_date = $fromDate;
                        $rideSegment->to_date = $toDate;
                        $rideSegment->is_tba = false;
                    }

                    // Update other ride details
                    if (isset($rideData['from_place'])) {
                        $rideSegment->from_place = $rideData['from_place'];
                    }
                    if (isset($rideData['to_place'])) {
                        $rideSegment->to_place = $rideData['to_place'];
                    }
                    if (isset($rideData['service_address_id'])) {
                        $rideSegment->service_address_id = $rideData['service_address_id'];
                    }
                    if (isset($rideData['total_time'])) {
                        $rideSegment->total_time = $rideData['total_time'];
                    }

                    $rideSegment->save();

                    // Update service address contact information if provided
                    if (isset($rideData['service_address_id']) && $rideData['service_address_id']) {
                        $serviceAddress = ServiceAddress::find($rideData['service_address_id']);
                        if ($serviceAddress) {
                            $contactUpdated = false;

                            // Update contact person if provided and different
                            if (
                                isset($rideData['contact_person']) &&
                                $serviceAddress->contact_person_name !== $rideData['contact_person']
                            ) {
                                $serviceAddress->contact_person_name = $rideData['contact_person'];
                                $contactUpdated = true;
                            }

                            // Update contact number if provided and different
                            if (
                                isset($rideData['contact_number']) &&
                                $serviceAddress->contact_number !== $rideData['contact_number']
                            ) {
                                $serviceAddress->contact_number = $rideData['contact_number'];
                                $contactUpdated = true;
                            }

                            if (
                                isset($rideData['map_link']) &&
                                $serviceAddress->map_link !== $rideData['map_link']
                            ) {
                                $serviceAddress->map_link = $rideData['map_link'];
                                $contactUpdated = true;
                            }

                            if ($contactUpdated) {
                                $serviceAddress->save();
                                Log::info('Service address contact updated for address: ' . $serviceAddress->id);
                            }
                        }
                    }

                    $updated = true;
                }
            }

            if ($updated) {
                Log::info('Travel information updated for lead: ' . $lead->id);
            }

            return $updated;
        } catch (\Exception $e) {
            Log::error('Error updating travel information: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    protected function cleanServiceIds($ids)
    {
        if (empty($ids)) {
            return [];
        }

        if (is_array($ids)) {
            return $ids;
        }

        // Remove escaped quotes if present
        $cleaned = str_replace('\"', '"', $ids);

        // Handle cases where the string might be double-encoded
        if (str_starts_with($cleaned, '"[')) {
            $cleaned = trim($cleaned, '"');
        }

        $decoded = json_decode($cleaned, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function saveVoucherPdfWithOptionalExtra($voucher, $pdf, $storePath)
    {
        // Ensure target directory exists
        $fullStoragePath = storage_path('app/public/' . $storePath);
        if (!file_exists(dirname($fullStoragePath))) {
            mkdir(dirname($fullStoragePath), 0777, true);
        }

        // Resolve extra_upload (if present) to local file
        $extraPdfPath = null;
        if (!empty($voucher->extra_upload)) {
            $extra = $voucher->extra_upload;
            if (filter_var($extra, FILTER_VALIDATE_URL)) {
                $parsedPath = parse_url($extra, PHP_URL_PATH) ?: '';
                $storagePrefix = '/storage/';
                if (strpos($parsedPath, $storagePrefix) === 0) {
                    $relative = substr($parsedPath, strlen($storagePrefix));
                    $candidate = storage_path('app/public/' . ltrim($relative, '/'));
                } else {
                    $relative = ltrim($parsedPath, '/');
                    $candidate = public_path($relative);
                    if (!file_exists($candidate)) {
                        $candidate = storage_path('app/public/' . $relative);
                    }
                }
            } else {
                $candidate = storage_path('app/public/' . ltrim($extra, '/'));
            }

            if (isset($candidate) && file_exists($candidate)) {
                $extraPdfPath = $candidate;
            } else {
                Log::warning('Extra PDF could not be resolved for voucher ' . $voucher->id . ': ' . $voucher->extra_upload);
                $extraPdfPath = null;
            }
        }

        if ($extraPdfPath && file_exists($extraPdfPath)) {
            // Save base voucher temporarily then merge
            $tempPath = storage_path('app/public/vouchers/temp-' . $voucher->id . '.pdf');
            if (!file_exists(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0777, true);
            }
            $pdf->save($tempPath);

            try {
                $fpdi = new Fpdi();
                // Add voucher pages
                $pageCount = $fpdi->setSourceFile($tempPath);
                for ($i = 1; $i <= $pageCount; $i++) {
                    $templateId = $fpdi->importPage($i);
                    $size = $fpdi->getTemplateSize($templateId);
                    $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $fpdi->useTemplate($templateId);
                }

                // Add extra pages
                $pageCount = $fpdi->setSourceFile($extraPdfPath);
                for ($i = 1; $i <= $pageCount; $i++) {
                    $templateId = $fpdi->importPage($i);
                    $size = $fpdi->getTemplateSize($templateId);
                    $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $fpdi->useTemplate($templateId);
                }

                // Save merged PDF to public storage
                file_put_contents($fullStoragePath, $fpdi->Output('S'));
            } catch (\Exception $e) {
                // If merge fails for any reason, fallback to saving base PDF only and log
                Log::error('FPDI merge failed for voucher ' . $voucher->id . ': ' . $e->getMessage());
                file_put_contents($fullStoragePath, $pdf->output());
            }

            @unlink($tempPath);
        } else {
            // No extra PDF, save base voucher only
            file_put_contents($fullStoragePath, $pdf->output());
        }

        return $fullStoragePath;
    }

    // Test Message
    public function sendTestMessage(Request $request)
    {
        $data = [
            'Bilal Shaikh',
            'TEST Service',
            '12:00 PM',
            'Mumbai',
            'TEST'
        ];

        $message = $this->sendMessageController->sendWhatsAppMessages(1, 'whatsapp_reminder', $data, '+91-9168272192');

        return response()->json(['message' => $message]);
    }

    /**
     * Update or create a follow-up entry when new services or extra services are added to voucher
     * Strategy: Update latest followup if it's Active/Initiated and not locked by audit; otherwise create new
     */
    private function createFollowUpForAddedServices(Request $request, $lead, $existingVoucher = null)
    {
        try {
            // Get the latest follow-up to compare services
            $latestFollowup = $lead->leadFollowups()
                ->orderBy('created_at', 'desc')
                ->first();

            // Get current services and extra services from the request
            $currentServiceIds = [];
            $currentExtraServiceIds = [];

            if ($request->has('services')) {
                foreach ($request->services as $serviceData) {
                    if (!empty($serviceData['service_id'])) {
                        $currentServiceIds[] = $serviceData['service_id'];
                    }
                }
            }

            if ($request->has('extra_services')) {
                foreach ($request->extra_services as $extraServiceData) {
                    if (!empty($extraServiceData['extra_service_id'])) {
                        $currentExtraServiceIds[] = $extraServiceData['extra_service_id'];
                    }
                }
            }

            // Get services from latest follow-up
            $followupServiceIds = [];
            $followupExtraServiceIds = [];

            if ($latestFollowup) {
                $followupServiceIds = $this->cleanServiceIds($latestFollowup->service_ids ?? []);
                $followupExtraServiceIds = $this->cleanServiceIds($latestFollowup->extra_service_ids ?? []);
            }

            // Find newly added services and extra services
            $newServiceIds = array_diff($currentServiceIds, $followupServiceIds);
            $newExtraServiceIds = array_diff($currentExtraServiceIds, $followupExtraServiceIds);

            // Find removed services (were in followup but removed from voucher)
            $removedServiceIds = array_diff($followupServiceIds, $currentServiceIds);
            $removedExtraServiceIds = array_diff($followupExtraServiceIds, $currentExtraServiceIds);

            // Proceed if there are new OR removed services/extra services
            $hasChanges = !empty($newServiceIds) || !empty($newExtraServiceIds) || !empty($removedServiceIds) || !empty($removedExtraServiceIds);
            if ($hasChanges) {
                $action = $existingVoucher ? 'updated' : 'created';
                $notesAddition = "Services/Extra Services changed in voucher (voucher {$action}):\n";

                // Add new services to notes
                if (!empty($newServiceIds)) {
                    $services = Service::whereIn('id', $newServiceIds)
                        ->get()
                        ->map(function ($s) {
                            return $s->service ?? ($s->service_name ?? null);
                        })
                        ->filter()
                        ->values()
                        ->toArray();
                    if (!empty($services)) {
                        $notesAddition .= "• New Services: " . implode(', ', $services) . "\n";
                    }
                }

                // Add new extra services to notes
                if (!empty($newExtraServiceIds)) {
                    $extraServices = ExtraService::whereIn('id', $newExtraServiceIds)->pluck('extra_service')->toArray();
                    if (!empty($extraServices)) {
                        $notesAddition .= "• New Extra Services: " . implode(', ', $extraServices) . "\n";
                    }
                }

                // Add removed services to notes
                if (!empty($removedServiceIds)) {
                    $removedServices = Service::whereIn('id', $removedServiceIds)
                        ->get()
                        ->map(function ($s) {
                            return $s->service ?? ($s->service_name ?? null);
                        })
                        ->filter()
                        ->values()
                        ->toArray();
                    if (!empty($removedServices)) {
                        $notesAddition .= "• Removed Services: " . implode(', ', $removedServices) . "\n";
                    }
                }

                // Add removed extra services to notes
                if (!empty($removedExtraServiceIds)) {
                    $removedExtraServices = ExtraService::whereIn('id', $removedExtraServiceIds)->pluck('extra_service')->toArray();
                    if (!empty($removedExtraServices)) {
                        $notesAddition .= "• Removed Extra Services: " . implode(', ', $removedExtraServices) . "\n";
                    }
                }

                $notesAddition .= "(Changed during voucher {$action} on " . now()->format('Y-m-d H:i') . ")";

                // Determine if we should UPDATE latest followup or CREATE new one
                $shouldUpdateLatest = false;

                if ($latestFollowup) {
                    // Check if latest followup is in an "updateable" state
                    $updateableStatuses = [0, 1]; // 0=Initiated, 1=Active
                    $isUpdateableStatus = in_array($latestFollowup->status, $updateableStatuses);

                    // Check if followup is locked by payment audit (has approved/rejected payments)
                    $hasAuditLock = false;
                    try {
                        $hasAuditLock = \App\Models\PaymentAuditTrail::where('lead_followup_id', $latestFollowup->id)
                            ->whereIn('payment_status', [1, 2]) // 1=Approved, 2=Rejected
                            ->exists();
                    } catch (\Exception $e) {
                        Log::warning('Could not check audit lock status: ' . $e->getMessage());
                    }

                    // Optional: Check if followup is recent (within last 48 hours)
                    $isRecent = $latestFollowup->created_at->gt(now()->subHours(48));

                    // Update if: updateable status + no audit lock + recent
                    $shouldUpdateLatest = $isUpdateableStatus && !$hasAuditLock && $isRecent;
                }

                if ($shouldUpdateLatest) {
                    // **UPDATE STRATEGY**: Append to existing followup and recalculate amounts
                    $updatedNotes = trim($latestFollowup->followup_note) . "\n\n---\n" . $notesAddition;

                    // Use current service IDs from voucher form (replaces old followup IDs to reflect removals)
                    $mergedServiceIds = array_values(array_unique($currentServiceIds));
                    $mergedExtraServiceIds = array_values(array_unique($currentExtraServiceIds));

                    // **RECALCULATE AMOUNTS** - Fetch all service and extra service prices
                    $serviceAmountTotal = 0;
                    $serviceDetailsArray = [];

                    // Get existing service_details to preserve discounts
                    $existingServiceDetails = [];
                    if ($latestFollowup->service_details) {
                        $decoded = is_string($latestFollowup->service_details)
                            ? json_decode($latestFollowup->service_details, true)
                            : $latestFollowup->service_details;
                        if (is_array($decoded)) {
                            foreach ($decoded as $detail) {
                                $key = ($detail['type'] ?? '') . '_' . ($detail['id'] ?? '');
                                $existingServiceDetails[$key] = $detail;
                            }
                        }
                    }

                    // Process all merged services
                    if (!empty($mergedServiceIds)) {
                        $services = Service::whereIn('id', $mergedServiceIds)->get();
                        foreach ($services as $service) {
                            $serviceKey = 'service_' . $service->id;
                            $originalAmount = (float) ($service->service_amount ?? 0);

                            // Preserve existing discount if service was already there, otherwise 0
                            $discountAmount = isset($existingServiceDetails[$serviceKey])
                                ? (float) ($existingServiceDetails[$serviceKey]['discount_amount'] ?? 0)
                                : 0;

                            $serviceDetailsArray[] = [
                                'id' => $service->id,
                                'type' => 'service',
                                'name' => $service->service ?? $service->service_name ?? 'Service',
                                'original_amount' => $originalAmount,
                                'discount_amount' => $discountAmount
                            ];

                            $serviceAmountTotal += $originalAmount;
                        }
                    }

                    // Process all merged extra services
                    if (!empty($mergedExtraServiceIds)) {
                        $extraServices = ExtraService::whereIn('id', $mergedExtraServiceIds)->get();
                        foreach ($extraServices as $extraService) {
                            $extraKey = 'extra_service_' . $extraService->id;
                            $originalAmount = (float) ($extraService->extra_service_amount ?? 0);

                            // Preserve existing discount if extra service was already there, otherwise 0
                            $discountAmount = isset($existingServiceDetails[$extraKey])
                                ? (float) ($existingServiceDetails[$extraKey]['discount_amount'] ?? 0)
                                : 0;

                            $serviceDetailsArray[] = [
                                'id' => $extraService->id,
                                'type' => 'extra_service',
                                'name' => $extraService->extra_service ?? 'Extra Service',
                                'original_amount' => $originalAmount,
                                'discount_amount' => $discountAmount
                            ];

                            $serviceAmountTotal += $originalAmount;
                        }
                    }

                    // Calculate total discount and final total
                    $totalDiscount = array_sum(array_column($serviceDetailsArray, 'discount_amount'));
                    $totalAmount = $serviceAmountTotal - $totalDiscount;

                    // Update followup with recalculated amounts
                    $latestFollowup->update([
                        'followup_note' => $updatedNotes,
                        'service_ids' => !empty($mergedServiceIds) ? json_encode($mergedServiceIds) : null,
                        'extra_service_ids' => !empty($mergedExtraServiceIds) ? json_encode($mergedExtraServiceIds) : null,
                        'service_amount' => $serviceAmountTotal,
                        'discount_amount' => $totalDiscount,
                        'total_amount' => $totalAmount,
                        'service_details' => json_encode($serviceDetailsArray),
                        'updated_at' => now(),
                    ]);

                    Log::info('Updated latest follow-up with service changes and recalculated amounts', [
                        'lead_id' => $lead->id,
                        'followup_id' => $latestFollowup->id,
                        'new_services' => $newServiceIds,
                        'new_extra_services' => $newExtraServiceIds,
                        'removed_services' => $removedServiceIds,
                        'removed_extra_services' => $removedExtraServiceIds,
                        'final_services' => $mergedServiceIds,
                        'final_extra_services' => $mergedExtraServiceIds,
                        'service_amount' => $serviceAmountTotal,
                        'discount_amount' => $totalDiscount,
                        'total_amount' => $totalAmount,
                        'voucher_action' => $action
                    ]);
                } else {
                    // **CREATE STRATEGY**: Create new followup entry with recalculated amounts
                    $latestFollowupForLead = \App\Models\LeadFollowup::where('lead_id', $lead->id)
                        ->orderBy('created_at', 'desc')
                        ->first();

                    // **RECALCULATE AMOUNTS** for new followup
                    $serviceAmountTotal = 0;
                    $serviceDetailsArray = [];

                    // Get existing service_details to preserve discounts for services that were in previous followup
                    $existingServiceDetails = [];
                    if ($latestFollowupForLead && $latestFollowupForLead->service_details) {
                        $decoded = is_string($latestFollowupForLead->service_details)
                            ? json_decode($latestFollowupForLead->service_details, true)
                            : $latestFollowupForLead->service_details;
                        if (is_array($decoded)) {
                            foreach ($decoded as $detail) {
                                $key = ($detail['type'] ?? '') . '_' . ($detail['id'] ?? '');
                                $existingServiceDetails[$key] = $detail;
                            }
                        }
                    }

                    // Process all current services
                    if (!empty($currentServiceIds)) {
                        $services = Service::whereIn('id', $currentServiceIds)->get();
                        foreach ($services as $service) {
                            $serviceKey = 'service_' . $service->id;
                            $originalAmount = (float) ($service->service_amount ?? 0);

                            // Preserve existing discount if service was in previous followup, otherwise 0
                            $discountAmount = isset($existingServiceDetails[$serviceKey])
                                ? (float) ($existingServiceDetails[$serviceKey]['discount_amount'] ?? 0)
                                : 0;

                            $serviceDetailsArray[] = [
                                'id' => $service->id,
                                'type' => 'service',
                                'name' => $service->service ?? $service->service_name ?? 'Service',
                                'original_amount' => $originalAmount,
                                'discount_amount' => $discountAmount
                            ];

                            $serviceAmountTotal += $originalAmount;
                        }
                    }

                    // Process all current extra services
                    if (!empty($currentExtraServiceIds)) {
                        $extraServices = ExtraService::whereIn('id', $currentExtraServiceIds)->get();
                        foreach ($extraServices as $extraService) {
                            $extraKey = 'extra_service_' . $extraService->id;
                            $originalAmount = (float) ($extraService->extra_service_amount ?? 0);

                            // Preserve existing discount if extra service was in previous followup, otherwise 0
                            $discountAmount = isset($existingServiceDetails[$extraKey])
                                ? (float) ($existingServiceDetails[$extraKey]['discount_amount'] ?? 0)
                                : 0;

                            $serviceDetailsArray[] = [
                                'id' => $extraService->id,
                                'type' => 'extra_service',
                                'name' => $extraService->extra_service ?? 'Extra Service',
                                'original_amount' => $originalAmount,
                                'discount_amount' => $discountAmount
                            ];

                            $serviceAmountTotal += $originalAmount;
                        }
                    }

                    // Calculate total discount and final total
                    $totalDiscount = array_sum(array_column($serviceDetailsArray, 'discount_amount'));
                    $totalAmount = $serviceAmountTotal - $totalDiscount;

                    // Preserve the latest business status when creating a voucher-change followup.
                    // This prevents paid/approved/completed leads from being reset back to Active.
                    $preservedStatus = 1; // fallback: Active
                    if ($latestFollowupForLead && isset($latestFollowupForLead->status)) {
                        $candidateStatus = (int) $latestFollowupForLead->status;
                        if ($candidateStatus >= 0 && $candidateStatus <= 9) {
                            $preservedStatus = $candidateStatus;
                        }
                    }

                    $followupData = [
                        'id' => Str::uuid(),
                        'lead_id' => $lead->id,
                        'followup_note' => $notesAddition,
                        'status' => $preservedStatus,
                        'followed_by' => auth()->id(),
                        'next_followup_date' => now()->addDays(7)->format('Y-m-d H:i:s'),
                        'service_ids' => !empty($currentServiceIds) ? json_encode($currentServiceIds) : null,
                        'extra_service_ids' => !empty($currentExtraServiceIds) ? json_encode($currentExtraServiceIds) : null,
                        // Calculated amounts based on actual service prices
                        'service_amount' => $serviceAmountTotal,
                        'discount_amount' => $totalDiscount,
                        'total_amount' => $totalAmount,
                        'service_details' => json_encode($serviceDetailsArray),
                    ];

                    $newFollowup = \App\Models\LeadFollowup::create($followupData);

                    Log::info('Created new follow-up with recalculated amounts', [
                        'lead_id' => $lead->id,
                        'followup_id' => $newFollowup->id,
                        'new_services' => $newServiceIds,
                        'new_extra_services' => $newExtraServiceIds,
                        'service_amount' => $serviceAmountTotal,
                        'discount_amount' => $totalDiscount,
                        'total_amount' => $totalAmount,
                        'voucher_action' => $action,
                        'reason' => 'Latest followup not updateable (status=' . ($latestFollowup->status ?? 'none') . ')'
                    ]);
                }
            } else {
                Log::info('No new services to add to follow-up', [
                    'lead_id' => $lead->id,
                    'current_services' => $currentServiceIds,
                    'followup_services' => $followupServiceIds,
                    'current_extra_services' => $currentExtraServiceIds,
                    'followup_extra_services' => $followupExtraServiceIds
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error updating/creating follow-up for added services: ' . $e->getMessage(), [
                'lead_id' => $lead->id ?? null,
                'stack_trace' => $e->getTraceAsString()
            ]);
            // Don't fail the whole voucher process for follow-up error
        }
    }

    /**
     * Get service addresses filtered by selected services
     */
    public function     getServiceAddresses(Request $request)
    {
        try {
            $serviceIds = $request->input('service_ids', []);

            if (empty($serviceIds)) {
                return response()->json([
                    'success' => true,
                    'addresses' => []
                ]);
            }

            $serviceAddresses = ServiceAddress::where('status', 1)
                ->with(['service', 'product', 'city.state', 'city.country'])
                ->whereIn('service_id', $serviceIds)
                ->get();

            return response()->json([
                'success' => true,
                'addresses' => $serviceAddresses
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching service addresses: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch service addresses'
            ], 500);
        }
    }
}
