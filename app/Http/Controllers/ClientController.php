<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\City;
use App\Models\Lead;
use App\Models\User;
use Faker\Core\Uuid;
use App\Models\Client;
use App\Models\Country;
use App\Models\Product;
use App\Models\Service;
use App\Models\LeadRide;
use App\Models\UserType;
use Illuminate\Support\Str;
use App\Imports\LeadsImport;
use App\Models\ExtraService;
use App\Models\LeadFollowup;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use App\Models\PaymentAuditTrail;
use App\Models\LeadAuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\AirpointsIntegrationService;
use function App\Helpers\getRepresentativeIds;

class ClientController extends Controller
{
    /**
     * Helper method to get users in hierarchy based on user types and sales executive assignments
     */
    private function getUsersInHierarchy()
    {
        try {
            $currentUser = auth()->user();

            // If user doesn't have a user type, return all users as fallback
            if (!$currentUser || !$currentUser->userType) {
                return User::all();
            }

            // If the logged-in user is a Sales Manager, allow them to see other Sales Managers
            // as well as Sales Executives (so they can assign to managers and their team).
            // try {
            //     $roleName = $currentUser->userType->user_type;
            //     if (trim(strtolower($roleName)) === trim(strtolower(UserType::SALES_MANAGER))) {
            //         $typeIds = UserType::whereIn('user_type', [UserType::SALES_MANAGER, UserType::SALES_EXECUTIVE])->pluck('id')->toArray();
            //         return User::whereIn('user_type_id', $typeIds)->where('status', 1)->get();
            //     }
            //     // If the logged-in user is a Senior Sales Manager, allow them to see
            //     // Senior Sales Managers, Sales Managers, and Sales Executives.
            //     if (trim(strtolower($roleName)) === trim(strtolower(UserType::SENIOR_SALES_MANAGER))) {
            //         $typeIds = UserType::whereIn('user_type', [UserType::SENIOR_SALES_MANAGER, UserType::SALES_MANAGER, UserType::SALES_EXECUTIVE])->pluck('id')->toArray();
            //         return User::whereIn('user_type_id', $typeIds)->where('status', 1)->get();
            //     }
            // } catch (\Exception $e) {
            //     // don't block normal flow on errors here; fall back to default behaviour
            //     Log::warning('Error evaluating Sales Manager special case: ' . $e->getMessage());
            // }

            $userType = $currentUser->userType->user_type;

            // Admin and Super Admin can see all users
            if (in_array($userType, [UserType::SUPER_ADMIN, UserType::ADMIN])) {
                return User::where('status', 1)->get();
            }

            // Sales Managers can see themselves + assigned sales executives
            if (in_array($userType, [UserType::SENIOR_SALES_MANAGER, UserType::SALES_MANAGER])) {
                $assignedExecutiveIds = \App\Models\SalesExecutiveAssignment::getSalesExecutivesForManager($currentUser->id)->pluck('id');
                $userIds = $assignedExecutiveIds->push($currentUser->id); // Include manager's own ID

                return User::whereIn('id', $userIds)->where('status', 1)->get();
            }

            // Operations roles - allow them to see all active users except Super Admin and Admin
            if (in_array($userType, UserType::OPERATIONS_ROLES)) {
                // Get user_type ids for admin/super admin to exclude
                $excludeTypeIds = UserType::whereIn('user_type', [UserType::SUPER_ADMIN, UserType::ADMIN])->pluck('id')->toArray();

                return User::where('status', 1)
                    ->whereNotIn('user_type_id', $excludeTypeIds)
                    ->get();
            }

            // For other roles (Sales Executive, etc.) - only return themselves
            return collect([$currentUser]);
        } catch (\Exception $e) {
            // Log the error and return current user as fallback
            Log::error('Error getting users in hierarchy: ' . $e->getMessage());
            return collect([auth()->user()]);
        }
    }

    /**
     * Export DNP leads to Excel
     */
    public function exportDnpLeads(Request $request)
    {
        try {
            $filters = [
                'from_date' => $request->input('from_date'),
                'to_date' => $request->input('to_date'),
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
                'representative_user_id' => $request->input('representative_user_id'),
                // ids can be a comma separated string from datatable
                'ids' => $request->input('ids'),
            ];

            $fileName = 'dnp_leads_' . date('Y-m-d_H-i-s') . '.xlsx';

            return Excel::download(new \App\Exports\DnpLeadsExport($filters), $fileName);
        } catch (\Exception $e) {
            Log::error('Error exporting DNP leads: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error exporting DNP leads: ' . $e->getMessage());
        }
    }

    public function index(Request $request)
    {
        $currentUser = auth()->user();
        $representatives = getRepresentativeIds($currentUser);

        // Pagination settings
        $perPage = $request->input('per_page', 10);
        $perPage = min($perPage, 100); // Cap max per page

        // Cache key for metadata
        $cacheKey = 'leads_metadata_' . auth()->id();

        // Get "Call Not Connected" service id (cached)
        $dnpServiceId = \Illuminate\Support\Facades\Cache::remember('dnp_service_id', 3600, function () {
            $dnpService = Service::where('service', 'Call Not Connected')->first();
            return $dnpService ? $dnpService->id : null;
        });

        // Base query with selective eager loading
        $query = Lead::with([
            'client:id,name,email,contact_number,country_id,city_id,status',
            'representative:id,name,email',
            'rideSegments:id,lead_id,from_date,to_date,from_place,to_place'
        ])
            ->select('leads.id', 'leads.client_id', 'leads.representative_user_id', 'leads.service_ids', 'leads.product_ids', 'leads.created_at', 'leads.updated_at')
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
            $pattern = '%' . addcslashes($dnpServiceId, '%_') . '%';
            $quoted = DB::getPdo()->quote($pattern);
            $query->whereRaw("replace(trim(both '\"' from service_ids::text), E'\\\\', '') NOT LIKE $quoted");
        }

        // Service Date filters
        if ($request->filled('from_date')) {
            $fromDate = Carbon::parse($request->from_date)->startOfDay();
            $query->whereHas('rideSegments', fn($q) => $q->where('from_date', '>=', $fromDate));
        }
        if ($request->filled('to_date')) {
            $toDate = Carbon::parse($request->to_date)->endOfDay();
            $query->whereHas('rideSegments', fn($q) => $q->where('to_date', '<=', $toDate));
        }

        // Created Date filters
        if ($request->filled('from_created_date')) {
            $fromCreatedDate = Carbon::parse($request->from_created_date)->startOfDay();
            $query->where('leads.created_at', '>=', $fromCreatedDate);
        }
        if ($request->filled('to_created_date')) {
            $toCreatedDate = Carbon::parse($request->to_created_date)->endOfDay();
            $query->where('leads.created_at', '<=', $toCreatedDate);
        }

        // Global Search - searches across multiple fields
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                // Search in client table
                $q->whereHas('client', function ($clientQuery) use ($searchTerm) {
                    $clientQuery->where('name', 'like', '%' . $searchTerm . '%')
                        ->orWhere('email', 'like', '%' . $searchTerm . '%')
                        ->orWhere('contact_number', 'like', '%' . $searchTerm . '%');
                })
                    // Search in representative name
                    ->orWhereHas('representative', function ($repQuery) use ($searchTerm) {
                        $repQuery->where('name', 'like', '%' . $searchTerm . '%');
                    })
                    // Search in ride segments
                    ->orWhereHas('rideSegments', function ($rideQuery) use ($searchTerm) {
                        $rideQuery->where('from_place', 'like', '%' . $searchTerm . '%')
                            ->orWhere('to_place', 'like', '%' . $searchTerm . '%');
                    })
                    // Search in follow-up notes
                    ->orWhereHas('leadFollowups', function ($followupQuery) use ($searchTerm) {
                        $followupQuery->where('followup_note', 'like', '%' . $searchTerm . '%');
                    });
            });
        }

        // Client filters using JOIN for better performance
        foreach (['name', 'email', 'phone'] as $field) {
            if ($request->filled($field)) {
                $query->whereHas('client', fn($q) => $q->where($field === 'phone' ? 'contact_number' : $field, 'like', '%' . $request->$field . '%'));
            }
        }

        if ($request->filled('representative_user_id')) {
            $query->where('representative_user_id', $request->representative_user_id);
        }

        // Status filter (latest follow-up) - using subquery for efficiency
        // Note: Using created_at instead of MAX(id) because id is UUID type which doesn't support MAX() in PostgreSQL
        if ($request->filled('status')) {
            $query->whereIn('id', function ($subQuery) use ($request) {
                $subQuery->select('lf.lead_id')
                    ->from('lead_followups as lf')
                    ->where('lf.status', $request->status)
                    ->whereRaw('lf.created_at = (SELECT MAX(lf2.created_at) FROM lead_followups as lf2 WHERE lf2.lead_id = lf.lead_id)');
            });
        }

        // Service filtering - move to DB where possible
        if ($request->filled('service_ids')) {
            $serviceIds = is_array($request->service_ids) ? $request->service_ids : [$request->service_ids];
            // Build LIKE pattern for array search in JSON
            $query->where(function ($q) use ($serviceIds) {
                foreach ($serviceIds as $serviceId) {
                    // Normalize stored JSON (trim surrounding quotes and remove backslashes) then match the id
                    $pattern = '%"' . $serviceId . '"%';
                    $quoted = DB::getPdo()->quote($pattern);
                    $q->orWhereRaw("replace(trim(both '\"' from service_ids::text), E'\\\\', '') LIKE $quoted");
                }
            });
        }

        // Product filtering - move to DB where possible
        if ($request->filled('product_ids')) {
            $productIds = is_array($request->product_ids) ? $request->product_ids : [$request->product_ids];
            $query->where(function ($q) use ($productIds) {
                foreach ($productIds as $productId) {
                    // Normalize stored JSON (trim surrounding quotes and remove backslashes) then match the id
                    $pattern = '%"' . $productId . '"%';
                    $quoted = DB::getPdo()->quote($pattern);
                    $q->orWhereRaw("replace(trim(both '\"' from product_ids::text), E'\\\\', '') LIKE $quoted");
                }
            });
        }

        // Order and paginate
        $leads = $query->orderBy('leads.created_at', 'desc')->paginate($perPage);

        // Fetch supporting data (cached)
        $services = \Illuminate\Support\Facades\Cache::remember('active_services', 3600, function () {
            return Service::where('status', 1)
                ->whereRaw("NOT (LOWER(service) LIKE ? OR LOWER(service) LIKE ?)", ['%call not connected%', '%no requirement%'])
                ->select('id', 'service', 'status')
                ->get();
        });

        $products = \Illuminate\Support\Facades\Cache::remember('active_products', 3600, function () {
            return Product::where('status', 1)
                ->whereRaw("NOT (LOWER(product) LIKE ? OR LOWER(product) LIKE ?)", ['%call not connected%', '%no requirement%'])
                ->select('id', 'product', 'status')
                ->get();
        });

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

        $staff = $this->getUsersInHierarchy() ?: auth()->user();

        // Optimize approved payments query - use select and distinct
        $leadsWithApprovedPayments = \App\Models\PaymentAuditTrail::where('payment_status', 1)
            ->where('paid_amount', '>', 0)
            ->join('lead_followups', 'payment_audit_trail.lead_followup_id', '=', 'lead_followups.id')
            ->whereIn('lead_followups.status', [3, 4])
            ->select('lead_followups.lead_id')
            ->distinct()
            ->pluck('lead_followups.lead_id')
            ->toArray();

        // Load latest followup for each lead (single query)
        $leadIds = $leads->pluck('id')->toArray();
        $latestFollowups = LeadFollowup::whereIn('lead_id', $leadIds)
            ->select('id', 'lead_id', 'status', 'next_followup_date')
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('lead_id')
            ->keyBy('lead_id');

        return view('admin.pages.leads.index-lead', compact(
            'leads',
            'services',
            'products',
            'statusOptions',
            'staff',
            'leadsWithApprovedPayments',
            'latestFollowups'
        ));
    }

    public function create()
    {
        $products = Product::where('status', 1)->get();
        // Get staff based on logged-in user hierarchy
        $staff = $this->getUsersInHierarchy();
        $countries = Country::all();
        $cities = collect();
        $existingClients = Client::with('country')
            ->where('status', 1)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'contact_number',
                'alternate_number',
                'date_of_birth',
                'country_id',
                'city_id',
                'address'
            ]);

        if (old('country_id')) {
            $cities = City::where('country_id', old('country_id'))
                ->where('status', 1)
                ->get();
        }
        return view('admin.pages.leads.add-lead', compact('products', 'staff', 'countries', 'cities', 'existingClients'));
    }

    public function store(Request $request)
    {
        // Determine if we're using an existing client or creating a new one
        $usingExistingClient = $request->client_id && $request->client_id !== 'new';
        // Determine if product/service indicates "Call Not Connected" and make travel fields optional in that case
        $isCallNotConnected = false;
        try {
            // Check products and services for "Call Not Connected"
            // Prefer checking provided product_ids (array) first, then fallback to single product_id
            if ($request->filled('product_ids')) {
                $pids = is_array($request->product_ids) ? $request->product_ids : json_decode($request->product_ids, true);
                if (is_array($pids) && count($pids) > 0) {
                    $exists = Product::whereIn('id', $pids)
                        ->where(function ($q) {
                            $q->whereRaw('LOWER(product) LIKE ?', ['%call not connected%'])
                                ->orWhereRaw('LOWER(product) LIKE ?', ['%no requirement%']);
                        })->exists();
                    if ($exists) $isCallNotConnected = true;
                }
            }

            // no longer check single product_id; product selection should come via product_ids only

            // Also check selected services (service can be named "Call Not Connected")
            if (!$isCallNotConnected && $request->filled('service_ids')) {
                $serviceIds = is_array($request->service_ids) ? $request->service_ids : json_decode($request->service_ids, true);
                if (is_array($serviceIds) && count($serviceIds) > 0) {
                    $exists = Service::whereIn('id', $serviceIds)
                        ->where(function ($q) {
                            $q->whereRaw('LOWER(service) LIKE ?', ['%call not connected%'])
                                ->orWhereRaw('LOWER(service) LIKE ?', ['%no requirement%']);
                        })->exists();
                    if ($exists) $isCallNotConnected = true;
                }
            }
        } catch (\Exception $e) {
            // If any error occurs, default to normal validation (do nothing special)
            Log::warning('Error checking Call Not Connected flag: ' . $e->getMessage());
        }
        // Common validation rules for both cases
        $commonRules = [
            'number_of_passengers' => ($isCallNotConnected ? 'nullable|integer|min:1|max:100' : 'required|integer|min:1|max:100'),
            'occasion' => ['nullable', 'string', 'max:255'],

            // Trips Validation
            'trips' => ($isCallNotConnected ? 'nullable|array' : 'required|array|min:1'),
            'trips.*.from_date' => ($isCallNotConnected ? 'nullable|date_format:Y-m-d H:i' : 'required|date_format:Y-m-d H:i'),
            'trips.*.to_date' => ($isCallNotConnected ? 'nullable|date_format:Y-m-d H:i' : 'required|date_format:Y-m-d H:i|after_or_equal:trips.*.from_date'),
            'trips.*.from_place' => ($isCallNotConnected ? ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z\s\-\.]+$/'] : ['required', 'string', 'max:255', 'regex:/^[A-Za-z\s\-\.]+$/']),
            'trips.*.to_place' => ($isCallNotConnected ? ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z\s\-\.]+$/'] : ['required', 'string', 'max:255', 'regex:/^[A-Za-z\s\-\.]+$/']),

            // Address: require at least one letter and allow only letters, numbers and spaces
            'address' => ['nullable', 'string', 'max:500', 'regex:/^(?=.*[A-Za-z])[A-Za-z0-9\\s]+$/'],

            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',

            // Service Validation
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'exists:services,id',

            // Follow-up Validation
            // next follow-up must be present and not in the past (optional for cancelled leads)
            'next_follow_up' => ['nullable', 'date_format:Y-m-d H:i', function ($attr, $value, $fail) use ($request) {
                // Skip validation if lead is cancelled and no follow-up date provided
                if ($request->status == 2 && empty($value)) {
                    return;
                }

                try {
                    $dt = Carbon::createFromFormat('Y-m-d H:i', $value);
                    if ($dt->lt(Carbon::now())) {
                        $fail('Next follow-up date must be present or a future date.');
                    }
                } catch (\Exception $e) {
                    $fail('Invalid date format for next follow-up.');
                }
            }],
            'representative_user_id' => 'required|uuid|exists:users,id',
            // Call Notes: require at least one letter and allow only letters, numbers and spaces
            'requirement_description' => ['nullable', 'string', 'max:1000'],
            // Status validation
            'status' => 'required|integer|in:0,2', // 0=Initiated, 2=Cancelled
        ];

        // Client-specific validation rules
        $clientRules = [
            // Name: only alphabetic characters and spaces
            'name' => ['required', 'string', 'max:255'],

            // Email: optional but if present must be a valid email
            'email' => ['nullable', 'email:rfc,dns', 'max:255'],

            // Phone numbers: digits only (5-20)
            'contact_number' => ['required', 'string', 'regex:/^[0-9]{5,20}$/'],
            'alternate_number' => ['nullable', 'string', 'regex:/^[0-9]{5,20}$/'],

            // Country codes (e.g. +1, +91)
            'contact_country_code' => ['required', 'string', 'max:5', 'regex:/^\+\d{1,4}$/'],
            'whatsapp_country_code' => ['nullable', 'string', 'max:5', 'regex:/^\+\d{1,4}$/'],

            'country_id' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if (!Str::isUuid($value)) {
                        $fail('The ' . $attribute . ' must be a valid UUID.');
                    }
                },
                Rule::exists('countries', 'id')
            ],


            'city' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => [
                'nullable',
                'date',
                'before_or_equal:today',
                function ($attribute, $value, $fail) {
                    $birthDate = \Carbon\Carbon::parse($value);
                    $age = $birthDate->diffInYears(\Carbon\Carbon::now());
                    if ($birthDate->isFuture()) {
                        $fail('Date cannot be in future');
                    }
                }
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];

        // Merge rules based on whether we're using an existing client
        // For existing clients, we still need client validation rules because user can edit them
        $validationRules = $usingExistingClient
            ? array_merge(['client_id' => 'required|uuid|exists:clients,id'], $clientRules, $commonRules)
            : array_merge($clientRules, $commonRules);

        $validator = Validator::make($request->all(), $validationRules, [
            'trips.required' => 'At least one trip segment is required.',
            'trips.*.from_date.required' => 'The departure date is required for all trip segments.',
            'trips.*.from_date.date_format' => 'The departure date must be a valid date and time format.',
            'trips.*.to_date.required' => 'The arrival date is required for all trip segments.',
            'trips.*.to_date.date_format' => 'The arrival date must be a valid date and time format.',
            'trips.*.to_date.after_or_equal' => 'The arrival date must be after or equal to the departure date.',
            'trips.*.from_place.required' => 'The departure location is required for all trip segments.',
            'trips.*.from_place.regex' => 'From Place should contain only letters, spaces, hyphens or dots.',
            'trips.*.to_place.required' => 'The arrival location is required for all trip segments.',
            'trips.*.to_place.regex' => 'To Place should contain only letters, spaces, hyphens or dots.',
            'service_ids.required' => 'At least one service must be selected.',
            'product_ids.required' => 'At least one product must be selected.',
            //'product_ids.*.exists' => 'One or more selected products are invalid.',
            'representative_user_id.required' => 'A staff representative is required.',
            'email.unique' => 'This email is already associated with another client.',
            'contact_country_code.required' => 'Country code is required',
            'contact_country_code.max' => 'Country code cannot exceed 5 characters',
            'contact_country_code.regex' => 'Country code must be in format +XXX',
            'whatsapp_country_code.max' => 'WhatsApp country code cannot exceed 5 characters',
            'whatsapp_country_code.regex' => 'WhatsApp country code must be in format +XXX',
            'next_follow_up.required' => 'The next follow-up date is required.',
            'next_follow_up.date_format' => 'Next follow-up must be in format YYYY-MM-DD HH:MM',
            'status.required' => 'Status is required.',
            'status.in' => 'Please select a valid status (Initiated or Cancelled).',

            // New client-specific messages
            'name.required' => 'Full Name is required',
            //'name.regex' => 'Full Name should contain only letters and spaces.',
            'name.max' => 'Full Name cannot exceed 255 characters.',

            'email.email' => 'Please enter a valid email address (e.g., example@domain.com).',
            // 'email.unique' => 'This email is already associated with another client.',
            'email.max' => 'Email cannot exceed 255 characters.',

            'contact_number.required' => 'Phone number is required.',
            'contact_number.regex' => 'Phone number must contain only digits and be 5 to 20 characters long.',

            'alternate_number.regex' => 'Alternate phone number must contain only digits and be 5 to 20 characters long.',

            'contact_country_code.required' => 'Country code is required.',
            'contact_country_code.regex' => 'Country code must be in the format +XXX.',

            'address.regex' => 'Address must contain letters and may only include letters, numbers and spaces',
            'address.max' => 'Address cannot exceed 500 characters.',
            // 'requirement_description.regex' => 'Call Notes must contain at least one letter and may only include letters, numbers and spaces',
            'requirement_description.max' => 'Call Notes cannot exceed 1000 characters',
            // 'description.regex' => 'Description should be alphanumeric and can include basic punctuation. Special characters only are not allowed.',
            'date_of_birth.date' => 'Please enter a valid date',
            'date_of_birth.before_or_equal' => 'Date of birth cannot be in the future',

        ]);

        if ($validator->fails()) {
            // dd($validator->errors());
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            // Handle client creation/selection
            if ($usingExistingClient) {
                // Find existing client and update with edited information
                $client = Client::findOrFail($request->client_id);

                // Update client with the edited data
                $strContactNumber = $request->contact_country_code . '-' . $request->contact_number;
                $strAlternateNumber = $request->alternate_number
                    ? $request->whatsapp_country_code . '-' . $request->alternate_number
                    : null;

                $client->update([
                    'name' => $request->name,
                    'email' => $request->email,
                    'contact_number' => $strContactNumber,
                    'alternate_number' => $strAlternateNumber,
                    'date_of_birth' => $request->date_of_birth,
                    'city_id' => $request->city,
                    'country_id' => $request->country_id,
                    'address' => $request->address,
                    'description' => $request->description,
                ]);
            } else {
                // Create new client
                $strContactNumber = $request->contact_country_code . '-' . $request->contact_number;
                $strAlternateNumber = $request->alternate_number
                    ? $request->whatsapp_country_code . '-' . $request->alternate_number
                    : null;
                $client = Client::create([
                    'id' => Str::uuid(),
                    'name' => $request->name,
                    'email' => $request->email,
                    'contact_number' => $strContactNumber,
                    'alternate_number' => $strAlternateNumber,
                    'date_of_birth' => $request->date_of_birth,
                    'city_id' => $request->city,
                    'country_id' => $request->country_id,
                    'address' => $request->address,
                    'description' => $request->description,
                    'status' => 1,
                    'created_by' => auth()->id(),
                ]);
            }

            // Create enquiry
            // Use only product_ids (array) and never save product_id
            $leadProductIds = null;
            if ($request->filled('product_ids')) {
                $leadProductIds = is_array($request->product_ids) ? $request->product_ids : json_decode($request->product_ids, true);
                $leadProductIds = is_array($leadProductIds) ? array_values($leadProductIds) : null;
            }

            $enquiry = Lead::create([
                'id' => Str::uuid(),
                'client_id' => $client->id,
                'representative_user_id' => $request->representative_user_id,
                'product_ids' => !empty($leadProductIds) ? json_encode($leadProductIds) : null,
                'service_ids' => json_encode($request->service_ids),
                'number_of_passengers' => $request->number_of_passengers,
                'description' => $request->requirement_description,
                'occasion' => $request->occasion,
            ]);

            // Create trip segments if provided (may be omitted for Call Not Connected)
            if (!empty($request->trips) && is_array($request->trips)) {
                foreach ($request->trips as $trip) {
                    // skip empty trip rows
                    if (empty(array_filter($trip))) continue;

                    LeadRide::create([
                        'id' => Str::uuid(),
                        'lead_id' => $enquiry->id,
                        'from_date' => $trip['from_date'] ?? null,
                        'to_date' => $trip['to_date'] ?? null,
                        'from_place' => $trip['from_place'] ?? null,
                        'to_place' => $trip['to_place'] ?? null,
                    ]);
                }
            }

            // Determine follow-up note based on status — prefer requirement_description when present
            $followupNote = trim((string)($request->requirement_description ?? '')) !== ''
                ? $request->requirement_description
                : ($request->status == 2 ? 'Lead cancelled during creation' : 'Initial lead created');

            $leadFollowUp = LeadFollowUp::create([
                'id' => Str::uuid(),
                'lead_id' => $enquiry->id,
                'next_followup_date' => $request->next_follow_up,
                'followup_note' => $followupNote,
                'followed_by' => auth()->id(),
                'status' => $request->status,
            ]);
            DB::commit();

            $statusMessage = $request->status == 2 ? 'Lead cancelled and' : 'Lead created successfully.';
            $clientMessage = $usingExistingClient ? 'Client information has been updated.' : 'New client has been created.';

            return redirect()
                ->route('admin.clients.create')
                ->with('success', $statusMessage . ' ' . $clientMessage);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating lead: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return back()
                ->withInput()
                ->with('error', 'Error creating lead: ' . $e->getMessage());
        }
    }
    public function getCitiesByCountry($countryId)
    {
        try {
            if (is_null($countryId) || $countryId === '' || $countryId === 'null') {
                return response()->json([]);
            }
            $cities = City::where('country_id', $countryId)
                ->where('status', 1)
                ->select('id', 'name')
                ->get();

            return response()->json($cities);
        } catch (\Exception $e) {
            Log::error("Error fetching cities: " . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    public function edit(Client $client)
    {
        // Fetch only the latest lead and its ride segments (avoid loading all leads)
        $latestLead = $client->leads()->with('rideSegments')->latest('created_at')->first();

        // Load reference data with minimal columns where possible
        $services = Service::where('status', 1)->select('id', 'service')->get();
        $products = Product::where('status', 1)->select('id', 'product')->get();

        // Staff (may be scoped by hierarchy inside helper)
        $staff = $this->getUsersInHierarchy();

        $countries = Country::where('status', 1)->select('id', 'name')->get();

        // Get cities for the client's country (only if needed)
        $cities = collect();
        if ($client->country_id) {
            $cities = City::where('country_id', $client->country_id)
                ->where('status', 1)
                ->select('id', 'name')
                ->get();
        }

        // Latest follow-up for this lead (single query)
        $followups = null;
        if ($latestLead) {
            $followups = $latestLead->leadFollowups()->latest('next_followup_date')->first();
        }

        // Prepare trips for the edit view. Prefer old input if available (validation redirect)
        if (old('trips')) {
            $trips = old('trips');
        } elseif ($latestLead && $latestLead->rideSegments && $latestLead->rideSegments->isNotEmpty()) {
            $trips = $latestLead->rideSegments->map(function ($seg) {
                return [
                    'id' => $seg->id ?? '',
                    'from_date' => $seg->from_date ?? '',
                    'to_date' => $seg->to_date ?? '',
                    'from_place' => $seg->from_place ?? '',
                    'to_place' => $seg->to_place ?? '',
                ];
            })->toArray();
        } else {
            // No existing ride segments. Detect "Call Not Connected" from the lead's service_ids
            $isCallNotConnected = false;
            if ($latestLead && !empty($latestLead->service_ids)) {
                try {
                    $serviceIds = is_string($latestLead->service_ids) ? json_decode($latestLead->service_ids, true) : $latestLead->service_ids;
                    if (is_array($serviceIds) && count($serviceIds) > 0) {
                        // Check in-memory using the loaded $services collection to avoid an extra DB query
                        $serviceNames = $services->whereIn('id', $serviceIds)->pluck('service')->map(fn($s) => strtolower($s))->toArray();
                        $isCallNotConnected = in_array('call not connected', $serviceNames, true);
                    }
                } catch (\Exception $e) {
                    Log::warning('Error detecting Call Not Connected in edit: ' . $e->getMessage());
                }
            }

            $trips = [[
                'id' => '',
                'from_date' => '',
                'to_date' => '',
                'from_place' => '',
                'to_place' => '',
            ]];
        }

        return view('admin.pages.leads.edit-lead', compact(
            'client',
            'products',
            'services',
            'staff',
            'followups',
            'countries',
            'cities',
            'latestLead',
            'trips'
        ));
    }

    // public function update(Request $request, Client $client)
    // {
    //     // Detect Call Not Connected similarly to store()
    //     $isCallNotConnected = false;
    //     try {
    //         // product selection should come via product_ids only
    //         if ($request->filled('product_ids')) {
    //             $pids = is_array($request->product_ids) ? $request->product_ids : json_decode($request->product_ids, true);
    //             if (is_array($pids) && count($pids) > 0) {
    //                 $exists = Product::whereIn('id', $pids)
    //                     ->where(function($q) {
    //                         $q->whereRaw('LOWER(product) LIKE ?', ['%call not connected%'])
    //                           ->orWhereRaw('LOWER(product) LIKE ?', ['%no requirement%']);
    //                     })->exists();
    //                 if ($exists) $isCallNotConnected = true;
    //             }
    //         }

    //         if (!$isCallNotConnected && $request->filled('service_ids')) {
    //             $serviceIds = is_array($request->service_ids) ? $request->service_ids : json_decode($request->service_ids, true);
    //             if (is_array($serviceIds) && count($serviceIds) > 0) {
    //                 $exists = Service::whereIn('id', $serviceIds)
    //                     ->where(function($q) {
    //                         $q->whereRaw('LOWER(service) LIKE ?', ['%call not connected%'])
    //                           ->orWhereRaw('LOWER(service) LIKE ?', ['%no requirement%']);
    //                     })->exists();
    //                 if ($exists) $isCallNotConnected = true;
    //             }
    //         }
    //     } catch (\Exception $e) {
    //         Log::warning('Error checking Call Not Connected flag in update: ' . $e->getMessage());
    //     }

    //     $validator = Validator::make($request->all(), [
    //         'name' => 'required|string|max:255',
    //         'email' => [
    //             'nullable',
    //             'email',
    //             'max:255',
    //             //'email:rfc,dns'
    //            // Rule::unique('clients', 'email')->ignore($client->id),
    //         ],
    //         'contact_number' => 'required|numeric|digits_between:5,20',
    //         'alternate_number' => 'nullable|numeric|digits_between:5,20',
    //         'contact_country_code' => 'required|string|max:5|regex:/^\+\d{1,4}$/',
    //         'whatsapp_country_code' => 'nullable|string|max:5|regex:/^\+\d{1,4}$/',
    //         'country_id' => [
    //             'nullable',
    //             Rule::exists('countries', 'id')->where(function ($query) {
    //                 $query->where('status', 1);
    //             }),
    //         ],
    //         // Require at least one letter and allow only letters, numbers and spaces
    //         'address' => ['nullable', 'string', 'max:500'],
    //         'city' => 'nullable|string|max:100',
    //         'date_of_birth' => [
    //             'nullable',
    //             'date',
    //             'before_or_equal:today',
    //             function ($attribute, $value, $fail) {
    //                 $birthDate = \Carbon\Carbon::parse($value);
    //                 $age = $birthDate->diffInYears(\Carbon\Carbon::now());
    //                 if ($birthDate->isFuture()) {
    //                     $fail('Date cannot be in future');
    //                 }
    //             }
    //         ],

    //         // Travel Details Validation
    //         'number_of_passengers' => ($isCallNotConnected ? 'nullable|integer|min:1|max:100' : 'required|integer|min:1|max:100'),
    //         'occasion' => 'nullable|string|max:255',

    //         // Trips Validation
    //         'trips' => ($isCallNotConnected ? 'nullable|array' : 'required|array|min:1'),
    //         'trips.*.from_date' => ($isCallNotConnected ? 'nullable|date_format:Y-m-d H:i|after_or_equal:today' : 'required|date_format:Y-m-d H:i|after_or_equal:today'),
    //         'trips.*.to_date' => ($isCallNotConnected ? 'nullable|date_format:Y-m-d H:i|after_or_equal:trips.*.from_date' : 'required|date_format:Y-m-d H:i|after_or_equal:trips.*.from_date'),
    //         'trips.*.from_place' => ($isCallNotConnected ? 'nullable|string|max:255|regex:/^[A-Za-z ]+$/' : 'required|string|max:255|regex:/^[A-Za-z ]+$/'),
    //         'trips.*.to_place' => ($isCallNotConnected ? 'nullable|string|max:255|regex:/^[A-Za-z ]+$/' : 'required|string|max:255|regex:/^[A-Za-z ]+$/'),

    //         'product_ids' => 'required|array',
    //         'product_ids.*' => 'exists:products,id',

    //         // Service Validation
    //         'service_ids' => 'nullable|array',
    //         'service_ids.*' => 'exists:services,id',

    //         // Follow-up Validation
    //         'next_followup_date' => 'nullable|date_format:Y-m-d H:i',
    //         'representative_user_id' => 'required|uuid|exists:users,id',
    //         'requirement_description' => 'nullable|string',
    //         'date_of_birth.date' => 'Please enter a valid date',
    //         'date_of_birth.before_or_equal' => 'Date of birth cannot be in the future',

    //     ], [
    //         //'name.regex' => 'Full Name should contain only letters and spaces.',
    //        // 'occasion.regex' => 'Occasion should contain only letters and spaces.',
    //         'trips.required' => 'At least one trip segment is required.',
    //         'trips.*.from_date.required' => 'The departure date is required for all trip segments.',
    //         'trips.*.from_date.date_format' => 'The departure date must be a valid date and time format.',
    //         'trips.*.to_date.required' => 'The arrival date is required for all trip segments.',
    //         'trips.*.to_date.date_format' => 'The arrival date must be a valid date and time format.',
    //         'trips.*.to_date.after_or_equal' => 'The arrival date must be after or equal to the departure date.',
    //         'trips.*.from_place.required' => 'The departure location is required for all trip segments.',
    //         'trips.*.to_place.required' => 'The arrival location is required for all trip segments.',
    //         'trips.*.from_place.regex' => 'From Place should contain only letters and spaces.',
    //         'trips.*.to_place.regex' => 'To Place should contain only letters and spaces.',
    //         'service_ids.required' => 'At least one service must be selected.',
    //         'product_ids.required' => 'At least one product must be selected.',
    //        // 'product_ids.*.exists' => 'One or more selected products are invalid.',
    //         'representative_user_id.required' => 'A staff representative is required.',
    //         'contact_number.required' => 'Primary contact number is required',
    //         'contact_number.numeric' => 'Primary contact must contain only numbers',
    //         'contact_number.digits_between' => 'Primary contact must be 5-20 digits long',
    //         'alternate_number.numeric' => 'Alternate contact must contain only numbers',
    //         'alternate_number.digits_between' => 'Alternate contact must be 5-20 digits long',
    //         'contact_country_code.required' => 'Primary country code is required',
    //         'contact_country_code.max' => 'Primary country code cannot exceed 5 characters',
    //         'contact_country_code.regex' => 'Primary country code must be in format +XXX (e.g. +91)',
    //         'whatsapp_country_code.max' => 'WhatsApp country code cannot exceed 5 characters',
    //         'whatsapp_country_code.regex' => 'WhatsApp country code must be in format +XXX (e.g. +91)',
    //         'address.regex' => 'Address must contain letters and may only include letters, numbers and spaces',
    //     ]);
    //     if ($validator->fails()) {

    //         return back()->withErrors($validator)->withInput();
    //     }
    //     DB::beginTransaction();

    //     try {
    //         // Update client
    //         $strContactNumber = $request->contact_country_code . '-' . $request->contact_number;
    //         $strAlternateNumber = $request->alternate_number
    //             ? $request->whatsapp_country_code . '-' . $request->alternate_number : null;
    //         $client->update([
    //             'name' => $request->name,
    //             'email' => $request->email,
    //             'contact_number' => $strContactNumber,
    //             'alternate_number' => $strAlternateNumber,
    //             'date_of_birth' => $request->date_of_birth,
    //             'city_id' => $request->city ?: null,
    //             'country_id' => $request->country_id ?: null,
    //             'address' => $request->address,
    //             'description' => $request->description,
    //             'status' => $request->status ?? 1,
    //         ]);
    //         // Get or create the latest enquiry
    //         $enquiry = $client->leads()->latest()->first();

    //         if (!$enquiry) {
    //             $enquiry = new Lead([
    //                 'id' => Str::uuid(),
    //                 'client_id' => $client->id,
    //             ]);
    //         }

    //         // Update enquiry
    //         // Expect product_ids array only (no single product_id fallback)
    //         $leadProductIds = null;
    //         if ($request->filled('product_ids')) {
    //             $leadProductIds = is_array($request->product_ids) ? $request->product_ids : json_decode($request->product_ids, true);
    //             $leadProductIds = is_array($leadProductIds) ? array_values($leadProductIds) : null;
    //         }

    //         $enquiry->fill([
    //             'representative_user_id' => $request->representative_user_id,
    //             // Use product_ids JSON array only.
    //             'product_ids' => !empty($leadProductIds) ? json_encode($leadProductIds) : null,
    //             'service_ids' => json_encode($request->service_ids),
    //             'number_of_passengers' => $request->number_of_passengers,
    //             'description' => $request->requirement_description,
    //             'occasion' => $request->occasion,
    //         ])->save();

    //         if (trim((string)($request->requirement_description ?? '')) !== '') {
    //             try {
    //                 $firstFollowup = LeadFollowUp::where('lead_id', $enquiry->id)
    //                     ->orderBy('created_at', 'asc')
    //                     ->first();

    //                 if ($firstFollowup) {
    //                     $firstFollowup->update([
    //                         'followup_note' => $request->requirement_description,
    //                     ]);
    //                 } else {
    //                     Log::info('No existing LeadFollowUp found for lead ' . $enquiry->id . ' — skipping followup note sync as creation is disabled.');
    //                 }
    //             } catch (\Exception $e) {
    //                 Log::error('Error syncing call note to first followup: ' . $e->getMessage());
    //             }
    //         }

    //         // Sync trip segments
    //         $existingTripIds = $enquiry->rideSegments->pluck('id')->toArray();
    //         $updatedTripIds = [];

    //         // If trips are missing or empty and Call Not Connected, remove all existing trip segments
    //         if (empty($request->trips) || !is_array($request->trips)) {
    //             // Delete all existing segments if any
    //             if (!empty($existingTripIds)) {
    //                 LeadRide::whereIn('id', $existingTripIds)->delete();
    //             }
    //         } else {
    //             foreach ($request->trips as $tripData) {
    //                 // Skip entirely empty rows
    //                 if (empty(array_filter($tripData))) continue;

    //                 // If no ID is provided or it's empty, create a new segment
    //                 if (empty($tripData['id'])) {
    //                     $tripId = Str::uuid();
    //                     $tripSegment = new LeadRide([
    //                         'id' => $tripId,
    //                         'lead_id' => $enquiry->id,
    //                         'from_date' => $tripData['from_date'] ?? null,
    //                         'to_date' => $tripData['to_date'] ?? null,
    //                         'from_place' => $tripData['from_place'] ?? null,
    //                         'to_place' => $tripData['to_place'] ?? null,
    //                     ]);
    //                     $tripSegment->save();
    //                 } else {
    //                     // Update existing segment
    //                     $tripSegment = LeadRide::updateOrCreate(
    //                         ['id' => $tripData['id']],
    //                         [
    //                             'lead_id' => $enquiry->id,
    //                             'from_date' => $tripData['from_date'] ?? null,
    //                             'to_date' => $tripData['to_date'] ?? null,
    //                             'from_place' => $tripData['from_place'] ?? null,
    //                             'to_place' => $tripData['to_place'] ?? null,
    //                         ]
    //                     );
    //                 }

    //                 $updatedTripIds[] = $tripSegment->id;
    //             }

    //             // Delete removed trip segments
    //             $tripsToDelete = array_diff($existingTripIds, $updatedTripIds);
    //             if (!empty($tripsToDelete)) {
    //                 LeadRide::whereIn('id', $tripsToDelete)->delete();
    //             }
    //         }
    //         // Persist next follow-up date if provided in the form. Support both
    //         // `next_followup_date` and legacy `next_follow_up` input names.
    //         $nextFollowupInput = $request->input('next_followup_date', $request->input('next_follow_up', null));
    //         if (!empty($nextFollowupInput)) {
    //             // Normalize various possible input formats (e.g. 'Y-m-d\TH:i' from datetime-local
    //             // or 'Y-m-d H:i' from text inputs) into a DB-friendly format using Carbon.
    //             $normalized = null;
    //             try {
    //                 if (strpos($nextFollowupInput, 'T') !== false) {
    //                     $dt = Carbon::createFromFormat('Y-m-d\TH:i', $nextFollowupInput);
    //                 } else {
    //                     $dt = Carbon::createFromFormat('Y-m-d H:i', $nextFollowupInput);
    //                 }
    //                 $normalized = $dt->format('Y-m-d H:i');
    //             } catch (\Exception $e) {
    //                 try {
    //                     // Last resort: let Carbon attempt to parse and reformat
    //                     $dt = Carbon::parse($nextFollowupInput);
    //                     $normalized = $dt->format('Y-m-d H:i');
    //                 } catch (\Exception $e2) {
    //                     Log::warning('Unable to parse next_followup input: ' . $nextFollowupInput);
    //                     $normalized = $nextFollowupInput; // save raw as fallback
    //                 }
    //             }

    //             try {
    //                 $existingFollowup = LeadFollowUp::where('lead_id', $enquiry->id)->latest()->first();
    //                 if ($existingFollowup) {
    //                     $existingFollowup->update(['next_followup_date' => $normalized]);
    //                 } else {
    //                     Log::info('No existing LeadFollowUp found for lead ' . $enquiry->id . ' — skipping create as requested.');
    //                 }
    //             } catch (\Exception $e) {
    //                 Log::error('Error updating lead followup from edit: ' . $e->getMessage());
    //             }
    //         }

    //         // Persist status if provided in the form (update latest followup status)
    //         $statusInput = $request->input('status', null);
    //         if (!is_null($statusInput)) {
    //             try {
    //                 $existingFollowup = LeadFollowUp::where('lead_id', $enquiry->id)->latest()->first();
    //                 if ($existingFollowup) {
    //                     $existingFollowup->update(['status' => (int) $statusInput]);
    //                 } else {
    //                     Log::info('No existing LeadFollowUp found for lead ' . $enquiry->id . ' — skipping status update.');
    //                 }
    //             } catch (\Exception $e) {
    //                 Log::error('Error updating lead followup status from edit: ' . $e->getMessage());
    //             }
    //         }
    //         DB::commit();
    //         return redirect()->route('admin.clients.edit', $client)->with('success', 'Lead updated successfully.');
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return back()->withInput()->with('error', 'Error updating client: ' . $e->getMessage());
    //     }
    // }
    public function destroy(Client $client)
    {
        try {
            $client->update(['status' => 0]);

            return response()->json([
                'success' => true,
                'message' => 'Client Deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete client'
            ], 500);
        }
    }
    public function view(Client $client)
    {
        // Remove the UUID check since we're using route model binding
        $client->load(['leads.rideSegments', 'leads.representative']);

        // Get all leads with their services
        $leads = $client->leads->map(function ($enquiry) {
            $serviceIds = json_decode($enquiry->service_ids, true) ?? [];
            $enquiry->services = Service::whereIn('id', $serviceIds)->pluck('service')->toArray();
            return $enquiry;
        });
        $latestLead = $client->leads->sortByDesc('created_at')->first();
        $followups = [];
        if ($latestLead) {
            $followups = LeadFollowup::with('followedBy')
                ->where('lead_id', $latestLead->id)
                ->orderBy('created_at', 'desc')
                ->get();
        }
        $latestFollowup = null;
        if ($latestLead) {
            $latestFollowup = LeadFollowup::with('followedBy')
                ->where('lead_id', $latestLead->id)
                ->orderByDesc('created_at')
                ->first();
        }

        // Get services and extra services with pricing information
        $selectedServices = collect();
        $selectedExtraServices = collect();
        $totalServiceAmount = 0;
        $totalExtraServiceAmount = 0;
        $totalAmount = 0;
        $isStoredAmount = false; // Flag to indicate if we're using stored amount vs calculated

        if ($latestFollowup) {
            // Check if we have a stored total amount from the followup
            if ($latestFollowup->total_amount && $latestFollowup->total_amount > 0) {
                $totalAmount = $latestFollowup->total_amount;
                $isStoredAmount = true;
            }

            // Get services from the latest followup with names
            if (!empty($latestFollowup->service_ids)) {
                $serviceIds = is_string($latestFollowup->service_ids) ? json_decode($latestFollowup->service_ids, true) : $latestFollowup->service_ids;
                $selectedServices = Service::whereIn('id', $serviceIds)->get();

                // Calculate current service amounts for display (may differ from stored amount)
                $totalServiceAmount = $selectedServices->sum('service_amount');
            }

            // Get extra services from the latest followup with names
            if (!empty($latestFollowup->extra_service_ids)) {
                $extraServiceIds = is_string($latestFollowup->extra_service_ids) ? json_decode($latestFollowup->extra_service_ids, true) : $latestFollowup->extra_service_ids;
                $selectedExtraServices = ExtraService::whereIn('id', $extraServiceIds)->get();

                // Calculate current extra service amounts for display (may differ from stored amount)
                $totalExtraServiceAmount = $selectedExtraServices->sum('extra_service_amount');
            }

            // If we don't have a stored total amount, calculate it from current amounts
            if (!$isStoredAmount) {
                $totalAmount = $totalServiceAmount + $totalExtraServiceAmount;
            }
        }

        $cityName = null;
        if ($client->city_id) {
            $cityName = DB::table('cities')
                ->where('id', $client->city_id)
                ->value('name');
        }
        $services = Service::all();

        // Get staff based on logged-in user hierarchy
        $staff = $this->getUsersInHierarchy();

        $country = DB::table('countries')
            ->where('id', $client->country_id)
            ->value('name');
        return view('admin.pages.leads.view-lead', compact(
            'client',
            'leads',
            'latestLead',
            'latestFollowup',
            'cityName',
            'services',
            'country',
            'staff',
            'selectedServices',
            'selectedExtraServices',
            'totalServiceAmount',
            'totalExtraServiceAmount',
            'totalAmount',
            'isStoredAmount'
        ));
    }
    public function toggleStatus(Client $client)
    {
        try {
            $client->update(['status' => !$client->status]);
            return response()->json([
                'success' => true,
                'message' => 'Client status updated successfully.',
                'new_status' => $client->status
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update client status: ' . $e->getMessage()
            ], 500);
        }
    }
    // public function createFollowUp(Client $client)
    // {
    //     // Load the latest lead with its relationships
    //     $latestLead = $client->leads()->with('rideSegments')->latest('created_at')->first();

    //     // Get basic client info
    //     $clientInfo = [
    //         'name' => $client->name,
    //         'email' => $client->email,
    //         'phone' => $client->contact_number,
    //         'services' => 'N/A', // Default value
    //         'trip_from' => 'N/A',
    //         'trip_to' => 'N/A'
    //     ];

    //     // Get ALL available services
    //     $services = Service::get();

    //     // Get distinct extra services with their amounts
    //     $allExtraServices = ExtraService::select('id', 'extra_service', 'extra_service_amount')
    //         // ->distinct('extra_service_amount')
    //         ->get();

    //     // Get service IDs from the latest lead
    //     $leadServiceIds = [];
    //     if ($latestLead && !empty($latestLead->service_ids)) {
    //         try {
    //             $leadServiceIds = is_array($latestLead->service_ids) ?
    //                 $latestLead->service_ids :
    //                 json_decode($latestLead->service_ids, true) ?? [];
    //             $clientInfo['services'] = Service::whereIn('id', $leadServiceIds)
    //                 ->pluck('service')
    //                 ->implode(', ');
    //         } catch (\Exception $e) {
    //             Log::error("Error processing product IDs: " . $e->getMessage());
    //         }
    //     } else {
    //         Log::warning('No service IDs found in latest lead', [
    //             'lead_id' => $latestLead ? $latestLead->id : 'No lead found',
    //             'service_ids' => $latestLead ? $latestLead->service_ids : 'N/A'
    //         ]);
    //     }

    //     // Include product names from latest lead into client info
    //     if ($latestLead) {
    //         try {
    //             $productNames = $latestLead->product_names ?? [];
    //             $clientInfo['products'] = is_array($productNames) ? implode(', ', $productNames) : ($productNames ?: 'N/A');
    //         } catch (\Exception $e) {
    //             Log::error('Error retrieving product names for latest lead: ' . $e->getMessage());
    //             $clientInfo['products'] = 'N/A';
    //         }
    //     } else {
    //         $clientInfo['products'] = 'N/A';
    //     }

    //     // Get trip details if available
    //     if ($latestLead && $latestLead->rideSegments->count() > 0) {
    //         $firstSegment = $latestLead->rideSegments->first();
    //         $lastSegment = $latestLead->rideSegments->last();

    //         $clientInfo['trip_from'] = date('Y-m-d', strtotime($firstSegment->from_date)) . ' - ' . $firstSegment->from_place;
    //         $clientInfo['trip_to'] = date('Y-m-d', strtotime($lastSegment->to_date)) . ' - ' . $lastSegment->to_place;
    //     }

    //     // Eager-load paymentAuditTrail to allow view to check audit entries without extra queries
    //     // Use the latest lead's followups to avoid mixing followups across multiple leads for the same client
    //     if ($latestLead) {
    //         $followups = $latestLead->leadFollowups()->with('paymentAuditTrail')->latest()->get();
    //         $latestFollowup = $followups->first();
    //     } else {
    //         $followups = collect();
    //         $latestFollowup = null;
    //     }

    //     // Determine which services and extra services to pre-select
    //     $selectedServices = [];
    //     $selectedExtraServices = [];

    //     if ($latestFollowup) {
    //         // If there's a previous followup, use its selections
    //         if ($latestFollowup->service_ids && !empty(trim($latestFollowup->service_ids))) {
    //             $decodedServices = json_decode($latestFollowup->service_ids, true) ?? [];
    //             $selectedServices = array_values(array_intersect($decodedServices, $services->pluck('id')->toArray()));
    //         } else {
    //             // If followup exists but no services selected, fall back to lead services
    //             $selectedServices = $leadServiceIds;
    //         }

    //         if ($latestFollowup->extra_service_ids && !empty(trim($latestFollowup->extra_service_ids))) {
    //             $decodedExtraServices = json_decode($latestFollowup->extra_service_ids, true) ?? [];
    //             $selectedExtraServices = array_values(array_intersect($decodedExtraServices, $allExtraServices->pluck('id')->toArray()));
    //         }
    //         // else {
    //         // // If no extra services in followup, auto-select all for current services
    //         // $selectedExtraServices = $services->whereIn('id', $selectedServices)
    //         //     ->flatMap(function($service) {
    //         //         return $service->extraServices->pluck('id');
    //         //     })->toArray();

    //         // }
    //     } else {
    //         // If no previous followup, use services from the lead
    //         $selectedServices = $leadServiceIds;

    //         // // Auto-select all extra services for the lead's services
    //         // $selectedExtraServices = $allExtraServices->whereIn('id', $leadServiceIds)
    //         //     ->flatMap(function($service) {
    //         //         return $service->extraServices->pluck('id');
    //         //     })->toArray();
    //     }

    //     // Ensure selectedServices contains valid service IDs
    //     $selectedServices = array_values(array_filter($selectedServices));
    //     $selectedExtraServices = array_values(array_filter($selectedExtraServices));

    //     // Debug log the final selections
    //     Log::info('Final service selections for follow-up:', [
    //         'client_id' => $client->id,
    //         'latest_lead_id' => $latestLead ? $latestLead->id : null,
    //         'lead_service_ids' => $leadServiceIds,
    //         'has_latest_followup' => $latestFollowup ? true : false,
    //         'selected_services' => $selectedServices,
    //         'selected_extra_services' => $selectedExtraServices
    //     ]);

    //     // Create service-to-extra-service mapping for frontend JavaScript
    //     // $serviceExtraServiceMap = [];
    //     $servicePrices = [];
    //     $extraServicePrices = [];

    //     foreach ($services as $service) {
    //         // $serviceExtraServiceMap[$service->id] = $service->extraServices->pluck('id')->toArray();
    //         $servicePrices[$service->id] = $service->service_amount;

    //         // foreach ($service->extraServices as $extraService) {
    //         //     $extraServicePrices[$extraService->id] = $extraService->extra_service_amount;
    //         // }
    //     }

    //     // Also add all extra services to the prices array
    //     foreach ($allExtraServices as $extraService) {
    //         $extraServicePrices[$extraService->id] = $extraService->extra_service_amount;
    //     }

    //     $lastFollowupWithAmount = $latestLead
    //         ? $latestLead->leadFollowups()->whereNotNull('total_amount')->latest('created_at')->first()
    //         : null;

    //     $lastFollowupTotalAmount = $lastFollowupWithAmount ? $lastFollowupWithAmount->total_amount : null;
    //     return view('admin.pages.follow-ups.add-follow-up', [
    //         'clientInfo' => $clientInfo,
    //         'followups' => $followups,
    //         'client' => $client,
    //         'services' => $services,
    //         'allExtraServices' => $allExtraServices,
    //         'selectedServices' => $selectedServices,
    //         'selectedExtraServices' => $selectedExtraServices,
    //         // 'serviceExtraServiceMap' => $serviceExtraServiceMap,
    //         'servicePrices' => $servicePrices,
    //         'lastFollowupTotalAmount' => $lastFollowupTotalAmount,
    //         'extraServicePrices' => $extraServicePrices
    //     ]);
    // }
    // public function storeFollowUp(Request $request, Client $client)
    // {
    //     $messages = [
    //         // 'notes.regex' => 'Follow-up notes must contain at least one letter and may only include letters, numbers and spaces',
    //         'notes.max' => 'Follow-up notes cannot exceed 1000 characters',
    //         'image.required_if' => 'Upload Receipt is required when status is Full or Partial payment received',
    //         'image.mimes' => 'Only PDF, JPG, or PNG formats are allowed',
    //         'image.max' => 'File size must not exceed 2MB',
    //         'image.image' => 'Uploaded file must be a valid image or PDF',
    //         'services.required_if' => 'At least one service must be selected when status is Full or Partial payment received',
    //         'received_amount.required_if' => 'Received amount is required when status is Full or Partial payment received',
    //         'payment_method.required_if' => 'Payment method is required when status is Full or Partial payment received',
    //         'paid_date.required_if' => 'Paid date is required when status is Full or Partial payment received',
    //     ];

    //     $validator = Validator::make($request->all(), [
    //         // notes must contain at least one letter and may only include letters, numbers and spaces
    //         'notes' => ['nullable', 'string', 'max:1000'],
    //         'status' => 'required|integer|in:0,1,2,3,4,5,6,7', //0=Initiated, 1=Active, 2=Cancelled, 3=Full payment received, 4=Partial payment received, 5=Completed, 6=pending, 7=rescheduled
    //         'next_followup_date' => ['nullable', 'date_format:Y-m-d\TH:i', function ($attr, $value, $fail) {
    //             // try {
    //             //     $dt = Carbon::createFromFormat('Y-m-d\TH:i', $value);
    //             //     if ($dt->lt(Carbon::now())) {
    //             //         $fail('Next follow-up date must be present or a future date.');
    //             //     }
    //             // } catch (\Exception $e) {
    //             //     $fail('Invalid date format for next follow-up.');
    //             // }
    //         }],
    //         // allow jpg, jpeg, png and pdf; max 2048 KB (2MB)
    //         // Make upload required when status is 3 (Full payment) or 4 (Partial payment)
    //         'image' => 'required_if:status,3,4|mimes:jpeg,png,jpg,pdf|max:2048',
    //         // require services when status is Full(3) or Partial(4) payment received
    //         'services' => 'required_if:status,3,4|array',
    //         'services.*' => 'exists:services,id',
    //         'extra_services' => 'nullable|array',
    //         'extra_services.*' => 'exists:extra_services,id',
    //         'total_amount' => 'nullable|numeric|min:0',
    //         'received_amount' => [
    //             'required_if:status,3,4',
    //             'nullable',
    //             'numeric',
    //             'min:0',
    //             function ($attribute, $value, $fail) use ($request) {
    //                 $total = $request->input('total_amount');
    //                 if (!is_null($total) && !is_null($value) && $value > $total) {
    //                     $fail('Received amount cannot be greater than Total amount.');
    //                 }
    //             }
    //         ],
    //         'payment_method' => 'required_if:status,3,4|nullable|string',
    //         'paid_date' => 'required_if:status,3,4|nullable|date|before_or_equal:today',
    //      ], $messages);

    //     if ($validator->fails()) {
    //        // dd($validator->errors()->all());
    //         return back()->withErrors($validator)->withInput();
    //     }

    //     DB::beginTransaction();
    //     try {
    //         $imagePath = null;
    //         if ($request->hasFile('image')) {
    //             $imagePath = $request->file('image')->store('followups', 'public');
    //         }

    //         $followedById = auth()->id();
    //         if (!Str::isUuid($followedById)) {
    //             throw new \Exception('Invalid user ID format');
    //         }

    //         // Validate that client has a latest lead
    //         if (!$client->latestLead) {
    //             throw new \Exception('No lead found for this client');
    //         }

    //         // Ensure services and extra_services are arrays and filter out invalid values
    //         $services = $request->services ? array_filter($request->services) : [];
    //         $extraServices = $request->extra_services ? array_filter($request->extra_services) : [];

    //         if (!empty($services)) {
    //             $client->latestLead->update([
    //                 'service_ids' => json_encode(array_values($services)),
    //                 'updated_at' => now()
    //             ]);
    //         }

    //         // Calculate total amount if not provided
    //         $totalAmount = $request->total_amount;
    //         if (empty($totalAmount)) {
    //             $serviceAmount = Service::whereIn('id', $services)->sum('service_amount');
    //             $extraServiceAmount = ExtraService::whereIn('id', $extraServices)->sum('extra_service_amount');
    //             $totalAmount = $serviceAmount + $extraServiceAmount;
    //         }

    //         $followup = LeadFollowup::create([
    //             'id' => Str::uuid(),
    //             'lead_id' => $client->latestLead->id,
    //             'next_followup_date' => $request->next_followup_date,
    //             'followup_note' => $request->notes,
    //             'status' => $request->status,
    //             'followed_by' =>  $followedById,
    //             'file' => $imagePath,
    //             'service_ids' => !empty($services) ? json_encode(array_values($services)) : null,
    //             'extra_service_ids' => !empty($extraServices) ? json_encode(array_values($extraServices)) : null,
    //             'total_amount' => $totalAmount,
    //             'received_amount' => $request->received_amount,
    //             'payment_method' => $request->payment_method,
    //             'paid_date' => $request->paid_date,
    //             'created_at' => now(),
    //             'updated_at' => now(),
    //         ]);
    //         // Status 3 = Full Payment Received, 4 = Partial Payment Received
    //         if (in_array((int)$request->status, [3, 4]) && $request->filled('received_amount') && $request->received_amount > 0) {
    //             PaymentAuditTrail::create([
    //                 'id' => Str::uuid(),
    //                 'lead_followup_id' => $followup->id,
    //                 'paid_amount' => $request->received_amount,
    //                 'paid_date' => $request->paid_date ? \Carbon\Carbon::parse($request->paid_date)->format('Y-m-d H:i:s') : now(),
    //                 'payment_method' => $request->payment_method,
    //                 'narration' => 'Payment received in follow-up',
    //                 'payment_status' => $request->status, // 3 for full, 4 for partial
    //                 'created_by' => auth()->id(),
    //             ]);
    //         }

    //         // Update the enquiry's next follow-up date
    //         if ($request->status == 2) { // Completed
    //             $client->latestLead->update([
    //                 'next_follow_up' => $request->next_followup_date
    //             ]);
    //         }

    //         DB::commit();

    //         return redirect()->route('admin.clients.follow-up.create', $client->id)
    //             ->with('success', 'Follow-up added successfully!');
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return back()->withInput()->with('error', 'Error adding follow-up: ' . $e->getMessage());
    //     }
    // }

    /**
     * Generate passenger registration token for a client's lead
     */
    public function generatePassengerRegistrationLink(Client $client)
    {
        try {
            $lead = $client->latestLead;
            if (!$lead) {
                return response()->json(['success' => false, 'message' => 'No lead found for this client']);
            }

            // Ensure a passenger record and token exist
            $token = $lead->generatePassengerRegistrationToken();

            // Ensure a short slug exists on the passenger
            $passenger = $lead->passengers()->whereNull('voucher_id')->first();
            if ($passenger && empty($passenger->registration_slug)) {
                $passenger->generateRegistrationSlug();
            }

            $longLink = route('lead.register.form', ['lead' => $lead->id, 'token' => $token]);
            $shortLink = $passenger ? $passenger->getShortRegistrationLink() : null;

            return response()->json([
                'success' => true,
                'message' => 'Registration link generated successfully',
                'link' => $longLink,
                'short_link' => $shortLink,
            ]);
        } catch (\Exception $e) {
            Log::error('Error generating passenger registration link: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error generating registration link']);
        }
    }

    /**
     * Get existing passenger registration link for a client's lead
     */
    public function getPassengerRegistrationLink(Client $client)
    {
        try {
            $lead = $client->latestLead;
            if (!$lead) {
                return response()->json(['success' => false, 'message' => 'No lead found for this client']);
            }

            // Return both short and long link where available
            $passenger = $lead->passengers()->whereNull('voucher_id')->whereNotNull('registration_token')->first();
            if ($passenger && $passenger->isTokenValid()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Registration link retrieved successfully',
                    'link' => $passenger->getRegistrationLink(),
                    'short_link' => $passenger->getShortRegistrationLink(),
                ]);
            }

            return response()->json(['success' => false, 'message' => 'No active registration link found']);
        } catch (\Exception $e) {
            Log::error('Error retrieving passenger registration link: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error retrieving registration link']);
        }
    }
    public function indexClient(Request $request)
    {
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $clients = Client::with(['city', 'country'])->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->appends($request->query());
        $countries = Country::all();
        $cities = collect();
        if (old('country_id')) {
            $cities = City::where('country_id', old('country_id'))
                ->where('status', 1)
                ->get();
        }
        return view('admin.pages.clients.index-client', compact('clients', 'countries'));
    }

    public function createClient()
    {
        $countries = Country::all();
        $cities = collect();
        if (old('country_id')) {
            $cities = City::where('country_id', old('country_id'))
                ->where('status', 1)
                ->get();
        }
        return view('admin.pages.clients.add-client', compact('countries', 'cities'));
    }

    public function storeClient(Request $request)
    {
        // Trim whitespace from email input
        $request->merge([
            'email' => trim($request->input('email')),
            'name' => trim($request->input('name')),
            'address' => trim($request->input('address')),
            'city' => trim($request->input('city')),
        ]);

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                //'regex:/^[A-Za-z ]+$/',
            ],
            'email' => [
                'nullable',
                'email:rfc,dns',  // Enhanced email validation with DNS checking
                'max:254',  // RFC compliant email length limit
                // 'unique:clients,email',
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',  // Additional format validation
                function ($attribute, $value, $fail) {
                    // Check for valid domain extension
                    $domain = substr(strrchr($value, "@"), 1);
                    if (!filter_var("test@" . $domain, FILTER_VALIDATE_EMAIL)) {
                        $fail('Invalid domain extension');
                    }

                    // Block suspicious or invalid email formats
                    if (preg_match('/\s/', $value)) {
                        $fail('Email cannot contain spaces');
                    }

                    // Additional domain validation
                    $validExtensions = ['com', 'org', 'net', 'edu', 'gov', 'mil', 'int', 'co', 'in', 'uk', 'de', 'fr', 'it', 'es', 'au', 'ca', 'jp', 'br', 'ru', 'cn'];
                    $extension = strtolower(substr($domain, strrpos($domain, '.') + 1));
                    if (strlen($extension) < 2 || strlen($extension) > 6) {
                        $fail('Invalid domain extension');
                    }
                }
            ],
            'contact_number' => [
                'required',
                'string',
                'unique:clients,contact_number',
                'regex:/^[0-9]{5,15}$/',  // Only digits, 5-15 length
                function ($attribute, $value, $fail) use ($request) {
                    $cleanNumber = preg_replace('/[^0-9]/', '', $value);
                    $countryCode = $request->contact_country_code ?? '';
                    $full = $countryCode . '-' . $cleanNumber;
                    if (strlen($cleanNumber) < 5) {
                        $fail('Phone number too short');
                    }
                    if (strlen($full) > 20) {
                        $fail('Phone number (with country code) too long');
                    }
                    if (preg_match('/[^0-9]/', $value)) {
                        $fail('Invalid characters in phone number');
                    }
                }
            ],
            'alternate_number' => [
                'nullable',
                'string',
                'regex:/^[0-9]{5,15}$/',  // Only digits, 5-15 length
                function ($attribute, $value, $fail) use ($request) {
                    if (empty($value)) {
                        $fail('WhatsApp number is required');
                        return;
                    }
                    $cleanNumber = preg_replace('/[^0-9]/', '', $value);
                    $countryCode = $request->whatsapp_country_code ?? '';
                    $full = $countryCode . '-' . $cleanNumber;
                    if (strlen($cleanNumber) < 5) {
                        $fail('Phone number too short');
                    }
                    if (strlen($full) > 20) {
                        $fail('WhatsApp number (with country code) too long');
                    }
                    if (preg_match('/[^0-9]/', $value)) {
                        $fail('Invalid characters in phone number');
                    }
                }
            ],
            'contact_country_code' => 'required|string|max:5|regex:/^\+\d{1,4}$/',
            'whatsapp_country_code' => 'nullable|string|max:5|regex:/^\+\d{1,4}$/',

            'country_id' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if (!Str::isUuid($value)) {
                        $fail('The ' . $attribute . ' must be a valid UUID.');
                    }
                },
                Rule::exists('countries', 'id')
            ],
            'address' => [
                'nullable',
                'string',
                'max:500',  // Added character limit
                //'regex:/^[a-zA-Z0-9\s,.\-#\/()]+$/',  // Valid address characters
                function ($attribute, $value, $fail) {
                    if (empty(trim($value))) {
                        $fail('Address is required');
                    }
                    if (strlen(trim($value)) < 10) {
                        $fail('Address is too short - please provide a complete address');
                    }
                }
            ],
            'city' => [
                'nullable',  // Made mandatory
                'string',
                'max:100',

            ],
            'date_of_birth' => [
                'nullable',
                'date',
                'before_or_equal:today',
                function ($attribute, $value, $fail) {
                    $birthDate = \Carbon\Carbon::parse($value);
                    $age = $birthDate->diffInYears(\Carbon\Carbon::now());
                    if ($birthDate->isFuture()) {
                        $fail('Date cannot be in future');
                    }
                }
            ],
        ], [
            // Name validation messages
            'name.required' => 'Client Name is required',
            // 'name.regex' => 'Invalid name. Only letters and spaces are allowed.',
            'name.max' => 'Client Name cannot exceed 255 characters',

            // Email validation messages
            // 'email.required' => 'Email Address is required',
            'email.email' => 'Invalid email format',
            // 'email.unique' => 'This email is already registered',
            'email.max' => 'Email too long - maximum 254 characters allowed',
            'email.regex' => 'Invalid email format',

            // Contact number validation messages
            'contact_number.required' => 'Phone number is required',
            'contact_number.string' => 'Enter numeric value',
            'contact_number.regex' => 'Enter numeric value - invalid characters in phone number',

            // WhatsApp number validation messages
            'alternate_number.required' => 'WhatsApp number is required',
            'alternate_number.string' => 'Enter numeric value',
            'alternate_number.regex' => 'Enter numeric value - invalid characters in phone number',

            // Country code validation messages
            'contact_country_code.required' => 'Country code is required',
            'contact_country_code.max' => 'Country code cannot exceed 5 characters',
            'contact_country_code.regex' => 'Country code must be in format +XXX',
            'whatsapp_country_code.max' => 'WhatsApp country code cannot exceed 5 characters',
            'whatsapp_country_code.regex' => 'WhatsApp country code must be in format +XXX',

            // Country validation messages
            // 'country_id.required' => 'Country is required',
            'country_id.exists' => 'Selected country is invalid',

            // Address validation messages
            'address.required' => 'Address is required',
            'address.max' => 'Address cannot exceed 500 characters',
            // 'address.regex' => 'Address must contain letters and may only include letters, numbers and spaces',

            // City validation messages
            //'city.required' => 'City is required',
            'city.max' => 'City name cannot exceed 100 characters',
            'city.regex' => 'Invalid city name - only letters, spaces, hyphens, and dots allowed',

            // Date of birth validation messages
            'date_of_birth.date' => 'Please enter a valid date',
            'date_of_birth.before_or_equal' => 'Date of birth cannot be in the future',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator, 'add')->withInput();
        }

        DB::beginTransaction();

        try {
            // Clean and format phone numbers (remove any spaces or formatting)
            $cleanContactNumber = preg_replace('/[^0-9]/', '', $request->contact_number);
            $cleanAlternateNumber = preg_replace('/[^0-9]/', '', $request->alternate_number);

            // basically concatenates the country phone code and contact number
            $strContactNumber = $request->contact_country_code . '-' . $request->contact_number;
            $strAlternateNumber = $request->alternate_number
                ? $request->whatsapp_country_code . '-' . $request->alternate_number
                : null;
            if ($strAlternateNumber && strlen($strAlternateNumber) > 20) {
                $strAlternateNumber = substr($strAlternateNumber, 0, 20);
            }

            $client = Client::create([
                'id' => Str::uuid(),
                'name' => $request->name,
                'email' => $request->email,
                'contact_number' => $strContactNumber,
                'alternate_number' => $strAlternateNumber,
                'date_of_birth' => $request->date_of_birth,
                'city_id' => $request->city,
                'country_id' => $request->country_id,
                'address' => $request->address,
                'description' => $request->description,
                'status' => $request->status ?? 1,
                'created_by' => auth()->id(),
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Client added successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Error creating client: ' . $e->getMessage());
        }
    }



    public function updateClient(Request $request, Client $client)
    {
        // Trim whitespace from inputs
        $request->merge([
            'email' => trim($request->input('email')),
            'name' => trim($request->input('name')),
            'address' => trim($request->input('address')),
            'city' => trim($request->input('city')),
        ]);

        // Debug: log incoming email and contact fields to help trace update issues
        try {
            Log::info('updateClient request payload', [
                'client_id' => $client->id,
                'email' => $request->input('email'),
                'contact_number' => $request->input('contact_number'),
                'contact_country_code' => $request->input('contact_country_code'),
                'alternate_number' => $request->input('alternate_number'),
                'whatsapp_country_code' => $request->input('whatsapp_country_code'),
            ]);
        } catch (\Exception $e) {
            // swallow logging errors
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                //'regex:/^[A-Za-z ]+$/',
            ],
            'email' => [
                'nullable',
                'email:rfc,dns',  // Enhanced email validation with DNS checking
                'max:254',  // RFC compliant email length limit
                // Rule::unique('clients', 'email')->ignore($client->id),
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',  // Additional format validation
                function ($attribute, $value, $fail) {
                    // Check for valid domain extension
                    $domain = substr(strrchr($value, "@"), 1);
                    if (!filter_var("test@" . $domain, FILTER_VALIDATE_EMAIL)) {
                        $fail('Invalid domain extension');
                    }

                    // Block suspicious or invalid email formats
                    if (preg_match('/\s/', $value)) {
                        $fail('Email cannot contain spaces');
                    }

                    // Additional domain validation
                    $validExtensions = ['com', 'org', 'net', 'edu', 'gov', 'mil', 'int', 'co', 'in', 'uk', 'de', 'fr', 'it', 'es', 'au', 'ca', 'jp', 'br', 'ru', 'cn'];
                    $extension = strtolower(substr($domain, strrpos($domain, '.') + 1));
                    if (strlen($extension) < 2 || strlen($extension) > 6) {
                        $fail('Invalid domain extension');
                    }
                }
            ],
            'contact_number' => [
                'required',
                'string',
                'regex:/^[0-9]{5,20}$/',  // Only digits, 5-20 length
                function ($attribute, $value, $fail) {
                    // Remove any spaces or formatting
                    $cleanNumber = preg_replace('/[^0-9]/', '', $value);
                    if (strlen($cleanNumber) < 5) {
                        $fail('Phone number too short');
                    }
                    if (strlen($cleanNumber) > 20) {
                        $fail('Phone number too long');
                    }
                    if (preg_match('/[^0-9]/', $value)) {
                        $fail('Invalid characters in phone number');
                    }
                }
            ],
            'alternate_number' => [
                'nullable',  // Made mandatory as per requirements
                'string',
                'regex:/^[0-9]{5,20}$/',  // Only digits, 5-20 length
                function ($attribute, $value, $fail) {
                    if (empty($value)) {
                        $fail('WhatsApp number is required');
                        return;
                    }
                    // Remove any spaces or formatting
                    $cleanNumber = preg_replace('/[^0-9]/', '', $value);
                    if (strlen($cleanNumber) < 5) {
                        $fail('Phone number too short');
                    }
                    if (strlen($cleanNumber) > 20) {
                        $fail('Phone number too long');
                    }
                    if (preg_match('/[^0-9]/', $value)) {
                        $fail('Invalid characters in phone number');
                    }
                }
            ],
            'contact_country_code' => 'required|string|max:5|regex:/^\+\d{1,4}$/',
            'whatsapp_country_code' => 'nullable|string|max:5|regex:/^\+\d{1,4}$/',  // Made required
            'country_id' => [
                'nullable',
                Rule::exists('countries', 'id')->where(function ($query) {
                    $query->where('status', 1);
                }),
            ],
            'address' => [
                'nullable',  // Made mandatory
                'string',
                'max:500',  // Added character limit

                function ($attribute, $value, $fail) {
                    if (empty(trim($value))) {
                        $fail('Address is required');
                    }
                    if (strlen(trim($value)) < 10) {
                        $fail('Address is too short - please provide a complete address');
                    }
                }
            ],
            'city' => [
                'nullable',  // Made mandatory
                'string',
                'max:100',

            ],
            'date_of_birth' => [
                'nullable',
                'date',
                'before_or_equal:today',
                function ($attribute, $value, $fail) {
                    $birthDate = Carbon::parse($value);
                    $age = $birthDate->diffInYears(Carbon::now());

                    if ($age < 18) {
                        $fail('Client must be at least 18 years old');
                    }

                    if ($birthDate->isFuture()) {
                        $fail('Date cannot be in future');
                    }
                }
            ],
        ], [
            // Name validation messages
            'name.required' => 'Client Name is required',
            // 'name.regex' => 'Invalid name. Only letters and spaces are allowed.',
            'name.max' => 'Client Name cannot exceed 255 characters',

            // Email validation messages
            // 'email.required' => 'Email Address is required',
            'email.email' => 'Invalid email format',
            // 'email.unique' => 'This email is already registered',
            'email.max' => 'Email too long - maximum 254 characters allowed',
            'email.regex' => 'Invalid email format',

            // Contact number validation messages
            'contact_number.required' => 'Phone number is required',
            'contact_number.string' => 'Enter numeric value',
            'contact_number.regex' => 'Enter numeric value - invalid characters in phone number',

            // WhatsApp number validation messages
            'alternate_number.required' => 'WhatsApp number is required',
            'alternate_number.string' => 'Enter numeric value',
            'alternate_number.regex' => 'Enter numeric value - invalid characters in phone number',

            // Country code validation messages
            'contact_country_code.required' => 'Country code is required',
            'contact_country_code.max' => 'Country code cannot exceed 5 characters',
            'contact_country_code.regex' => 'Country code must be in format +XXX',
            'whatsapp_country_code.required' => 'WhatsApp country code is required',
            'whatsapp_country_code.max' => 'WhatsApp country code cannot exceed 5 characters',
            'whatsapp_country_code.regex' => 'WhatsApp country code must be in format +XXX',

            // Country validation messages
            'country_id.required' => 'Country is required',
            'country_id.exists' => 'Selected country is invalid',

            // Address validation messages
            'address.required' => 'Address is required',
            'address.max' => 'Address cannot exceed 500 characters',
            'address.regex' => 'Address must contain letters and may only include letters, numbers and spaces',

            // City validation messages
            'city.required' => 'City is required',
            'city.max' => 'City name cannot exceed 100 characters',
            'city.regex' => 'Invalid city name - only letters, spaces, hyphens, and dots allowed',

            // Date of birth validation messages
            'date_of_birth.required' => 'Date of Birth is required',
            'date_of_birth.date' => 'Please enter a valid date',
            'date_of_birth.before_or_equal' => 'Date of birth cannot be in the future',
        ]);

        if ($validator->fails()) {

            return back()->withErrors($validator, 'edit')->withInput()->with('id', $client->id);;
        }

        DB::beginTransaction();

        try {
            // Clean and format phone numbers (remove any spaces or formatting)
            $cleanContactNumber = preg_replace('/[^0-9]/', '', $request->contact_number);
            $cleanAlternateNumber = preg_replace('/[^0-9]/', '', $request->alternate_number);

            // Format numbers with hyphen between country code and number
            $formattedContactNumber = $request->contact_country_code . '-' . $cleanContactNumber;
            $formattedAlternateNumber = $cleanAlternateNumber
                ? $request->whatsapp_country_code . '-' . $cleanAlternateNumber
                : null;

            // Update client
            $client->update([
                'name' => $request->name,
                'email' => $request->email,
                'contact_number' => $formattedContactNumber,
                'alternate_number' => $formattedAlternateNumber,
                'date_of_birth' => $request->date_of_birth,
                'city_id' => $request->city ?: null,
                'country_id' => $request->country_id ?: null,
                'address' => $request->address,
                'description' => $request->description,
                'status' => $request->status ?? 1,
            ]);
            DB::commit();
            return redirect()->back()->with('success', 'Client updated successfully.');
        } catch (\Exception $e) {

            DB::rollBack();
            return back()->withInput()->with('error', 'Error updating client: ' . $e->getMessage());
        }
    }



    public function editClient(Client $client)
    {
        $countries = Country::where('status', 1)->get();

        // Get cities for the client's country
        $cities = [];
        if ($client->country_id) {
            $cities = City::where('country_id', $client->country_id)
                ->where('status', 1)
                ->get();
        }

        return view('admin.pages.clients.edit-client', [
            'client' => $client,
            'countries' => $countries,
            'cities' => $cities,
        ]);
    }

    public function getClientData(Client $client)
    {
        $countries = Country::where('status', 1)->get();
        $cities = [];


        if ($client->country_id) {
            $cities = City::where('country_id', $client->country_id)
                ->where('status', 1)
                ->get();
        }

        return response()->json([
            'success' => true,
            'client' => $client,
            'countries' => $countries,
            'cities' => $cities,
        ]);
    }


    public function viewClient($clientId)
    {
        if (!Str::isUuid($clientId)) {
            abort(404, 'Invalid client ID');
        }
        $client = Client::with(['leads' => function ($query) {
            $query->orderBy('created_at', 'desc');
        }, 'leads.representative', 'leads.leadFollowups', 'leads.rideSegments'])->findOrFail($clientId);

        if (!$client) {
            abort(404, 'Client not found');
        }
        $cityName = null;
        if ($client->city_id) {
            $cityName = DB::table('cities')
                ->where('id', $client->city_id)
                ->value('name');
        }
        // Get country name
        $country = DB::table('countries')
            ->where('id', $client->country_id)
            ->value('name');
        return view('admin.pages.clients.view-client', compact(
            'client',
            'country',
            'cityName'
        ));
    }
    public function storeLeadFollowUp(Request $request, Lead $lead)
    {
        $messages = [
            'notes.max' => 'Follow-up notes cannot exceed 1000 characters',
            'image.required_if' => 'Upload Receipt is required when status is Full or Partial payment received',
            'image.mimes' => 'Only PDF, JPG, or PNG formats are allowed',
            'image.max' => 'File size must not exceed 2MB',
            'image.image' => 'Uploaded file must be a valid image or PDF',
            'services.required_if' => 'At least one service must be selected when status is Full or Partial payment received',
            'received_amount.required_if' => 'Received amount is required when status is Full or Partial payment received',
            'payment_method.required_if' => 'Payment method is required when status is Full or Partial payment received',
            'paid_date.required_if' => 'Paid date is required when status is Full or Partial payment received',
            'redeem_points.required_if' => 'Points to redeem is required when Acepoint Point Redeem is selected',
        ];

        // Check if this is an AJAX request (for quick status updates)
        $isAjaxRequest = $request->ajax() || $request->wantsJson();

        // Check if Acepoint payment method is selected or if vendor payment was selected
        $isAcepointPayment = $request->payment_method === 'Acepoint Point Redeem';
        $isVendorPayment = $request->payment_method === 'Paid Directly to Vendor';

        if ($isAjaxRequest) {
            // Simple validation for AJAX requests
            $validator = Validator::make($request->all(), [
                'notes' => ['required', 'string', 'max:1000'],
                'status' => 'required|in:0,1,2,3,4,5',
                'next_followup_date' => 'nullable|date_format:Y-m-d H:i',
                'services' => 'nullable|array',
                'services.*' => 'exists:services,id',
                'extra_services' => 'nullable|array',
                'extra_services.*' => 'exists:extra_services,id',
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ], 422);
            }
        } else {
            // Full validation for form submissions
            $validationRules = [
                'notes' => ['nullable', 'string', 'max:1000'],
                'status' => 'required|integer|in:0,1,2,3,4,5,6,7',
                'next_followup_date' => ['nullable', 'date_format:Y-m-d\TH:i'],
                'services' => 'required_if:status,3,4|array',
                'services.*' => 'exists:services,id',
                'extra_services' => 'nullable|array',
                'extra_services.*' => 'exists:extra_services,id',
                'service_details' => 'nullable|json',
                'service_amount' => 'nullable|numeric|min:0',
                'discount_amount' => 'nullable',
                'total_amount' => 'nullable|numeric|min:0',
                'received_amount' => [
                    'required_if:status,3,4',
                    'nullable',
                    'numeric',
                    'min:0',
                    // function ($attribute, $value, $fail) use ($request) {
                    //     $total = $request->input('total_amount');
                    //     if (!is_null($total) && !is_null($value) && $value > $total) {
                    //         $fail('Received amount cannot be greater than Total amount.');
                    //     }
                    // }
                ],
                'payment_method' => 'required_if:status,3,4|nullable|string',
                'redeem_points' => [
                    'required_if:payment_method,Acepoint Point Redeem',
                    'nullable',
                    'integer',
                    'min:1'
                ],
            ];

            // Conditionally set image and paid_date validation based on payment method
            if ($isAcepointPayment) {
                // For Acepoint, image and paid_date are optional
                $validationRules['image'] = 'nullable|mimes:jpeg,png,jpg,pdf|max:2048';
                $validationRules['paid_date'] = 'nullable|date|before_or_equal:today';
            } elseif ($isVendorPayment) {
                // If Paid Directly to Vendor is selected, make image optional
                $validationRules['image'] = 'nullable|mimes:jpeg,png,jpg,pdf|max:2048';
                $validationRules['paid_date'] = 'required_if:status,3,4|nullable|date|before_or_equal:today';
                // Only allow operations OR accounts roles to use this payment method
                //$currentType = auth()->user()->userType->user_type ?? null;
                // $allowedVendorRoles = array_merge(\App\Models\UserType::OPERATIONS_ROLES, \App\Models\UserType::ACCOUNTS_ROLES);
                // if (!in_array($currentType, $allowedVendorRoles)) {
                //     return back()->withInput()->withErrors(['payment_method' => 'You do not have permission to select Paid Directly to Vendor']);
                // }
            } else {
                // For other payment methods, image and paid_date are required for payment statuses
                $validationRules['image'] = 'required_if:status,3,4|mimes:jpeg,png,jpg,pdf|max:2048';
                $validationRules['paid_date'] = 'required_if:status,3,4|nullable|date|before_or_equal:today';
            }

            $validator = Validator::make($request->all(), $validationRules, $messages);

            if ($validator->fails()) {
                // dd($validator->errors()->toArray());
                return back()->withErrors($validator)->withInput();
            }

            // Additional validation for Acepoint: Check if user has sufficient points
            if ($isAcepointPayment && $request->filled('redeem_points')) {
                $airpointsService = app(AirpointsIntegrationService::class);
                $client = $lead->client;

                if (!$client) {
                    return back()->withInput()->with('error', 'Client not found for this lead');
                }

                // Check user points in Airpoints
                $pointsCheck = $airpointsService->getUserPoints($client);

                if (!$pointsCheck['success']) {
                    $errMsg = $pointsCheck['message'] ?? 'User not found in Acepoint system. Please ensure the user is registered.';
                    return back()->withInput()->withErrors([
                        'redeem_points' => $errMsg
                    ]);
                }

                $availablePoints = $pointsCheck['points'] ?? 0;
                $requestedPoints = (int) $request->redeem_points;

                if ($requestedPoints > $availablePoints) {
                    return back()->withInput()->withErrors([
                        'redeem_points' => "Insufficient points. Available points: {$availablePoints}, Requested: {$requestedPoints}"
                    ]);
                }
            }
        }

        DB::beginTransaction();
        try {
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('followups', 'public');
            }

            $followedById = auth()->id();
            if (!Str::isUuid($followedById)) {
                throw new \Exception('Invalid user ID format');
            }

            // Ensure services and extra_services are arrays and filter out invalid values
            $services = $request->services ? array_filter($request->services) : [];
            $extraServices = $request->extra_services ? array_filter($request->extra_services) : [];

            if (!empty($services)) {
                $lead->update([
                    'service_ids' => json_encode(array_values($services)),
                    'updated_at' => now()
                ]);
            }

            // Calculate total amount with service-wise discounts
            $serviceAmount = 0;
            $extraServiceAmount = 0;
            $totalDiscount = 0;
            $serviceDetails = [];

            // Parse service_details from request (sent as JSON string from frontend)
            if ($request->has('service_details') && !empty($request->service_details)) {
                $serviceDetails = is_string($request->service_details)
                    ? json_decode($request->service_details, true)
                    : $request->service_details;

                // Calculate totals from service details
                foreach ($serviceDetails as $detail) {
                    $serviceAmount += floatval($detail['original_amount'] ?? 0);
                    $totalDiscount += floatval($detail['discount_amount'] ?? 0);
                }
            } else {
                // Fallback: calculate from services and extra services if service_details not provided
                if (!empty($services)) {
                    $serviceAmount = Service::whereIn('id', $services)->sum('service_amount');
                }
                if (!empty($extraServices)) {
                    $extraServiceAmount = ExtraService::whereIn('id', $extraServices)->sum('extra_service_amount');
                }
                $serviceAmount += $extraServiceAmount;
            }

            // Total service amount before discount
            $calculatedServiceAmount = $serviceAmount;

            // Get discount from request (sum of individual discounts)
            $discountAmount = $totalDiscount;

            // Calculate final total: service_amount - discount
            $totalAmount = $calculatedServiceAmount - $discountAmount;

            // If the form submitted a manual total_amount (user edited total), respect it
            if ($request->filled('total_amount')) {
                try {
                    $submitted = floatval($request->input('total_amount'));
                    // Use submitted manual total (but ensure non-negative)
                    $totalAmount = max(0, $submitted);
                } catch (\Throwable $e) {
                    // ignore and keep calculated total
                    Log::warning('Invalid manual total_amount submitted: ' . $e->getMessage());
                }
            }

            // Ensure total is not negative as a final safeguard
            $totalAmount = max(0, $totalAmount);

            $followup = LeadFollowup::create([
                'id' => Str::uuid(),
                'lead_id' => $lead->id,
                'next_followup_date' => $request->next_followup_date,
                'followup_note' => $request->notes,
                'status' => $request->status,
                'followed_by' => $followedById,
                'file' => $imagePath,
                'service_ids' => !empty($services) ? json_encode(array_values($services)) : null,
                'extra_service_ids' => !empty($extraServices) ? json_encode(array_values($extraServices)) : null,
                'service_amount' => $calculatedServiceAmount,
                'discount_amount' => $discountAmount,
                'service_details' => !empty($serviceDetails) ? json_encode($serviceDetails) : null,
                'total_amount' => $totalAmount,
                'received_amount' => $request->received_amount,
                'payment_method' => $request->payment_method,
                'paid_date' => $request->paid_date,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Status 3 = Full Payment Received, 4 = Partial Payment Received
            if (in_array((int)$request->status, [3, 4]) && $request->filled('received_amount') && $request->received_amount > 0) {
                PaymentAuditTrail::create([
                    'id' => Str::uuid(),
                    'lead_followup_id' => $followup->id,
                    'paid_amount' => $request->received_amount,
                    'paid_date' => $request->paid_date ? \Carbon\Carbon::parse($request->paid_date)->format('Y-m-d H:i:s') : now(),
                    'payment_method' => $request->payment_method,
                    'narration' => 'Payment received in follow-up',
                    'payment_status' => $request->status,
                    'created_by' => auth()->id(),
                ]);

                // If payment method is Acepoint, redeem points from Airpoints system
                if ($isAcepointPayment && $request->filled('redeem_points')) {
                    try {
                        $airpointsService = app(AirpointsIntegrationService::class);
                        $client = $lead->client;

                        // Get the product from selected services
                        $product = null;
                        if (!empty($services)) {
                            // Get the first service and extract its product
                            $service = \App\Models\Service::whereIn('id', $services)->first();
                            if ($service && !empty($service->product_ids)) {
                                $productIds = is_array($service->product_ids) ? $service->product_ids : json_decode($service->product_ids, true);
                                if (!empty($productIds)) {
                                    $product = \App\Models\Product::whereIn('id', $productIds)->first();
                                }
                            }
                        }

                        // If no product found from services, get from lead's products
                        if (!$product) {
                            $leadProducts = $lead->products;
                            if ($leadProducts && $leadProducts->count() > 0) {
                                $product = $leadProducts->first();
                            }
                        }

                        // Final fallback to any product
                        if (!$product) {
                            $product = \App\Models\Product::first();
                        }

                        if (!$product) {
                            throw new \Exception('No product available for point redemption');
                        }

                        // Get service date from lead's ride segments
                        $serviceDate = null;
                        if ($lead->rideSegments && $lead->rideSegments->count() > 0) {
                            $firstRide = $lead->rideSegments->sortBy('from_date')->first();
                            $serviceDate = $firstRide->from_date ?
                                \Carbon\Carbon::parse($firstRide->from_date)->format('Y-m-d') :
                                null;

                            Log::info('Using service date from lead ride segment', [
                                'lead_id' => $lead->id,
                                'ride_id' => $firstRide->id,
                                'from_date' => $firstRide->from_date,
                                'service_date' => $serviceDate
                            ]);
                        }

                        // Fallback to paid_date or today if no ride segments
                        if (!$serviceDate) {
                            $serviceDate = $request->paid_date ? $request->paid_date : now()->format('Y-m-d');
                            Log::info('Using fallback service date', [
                                'lead_id' => $lead->id,
                                'paid_date' => $request->paid_date,
                                'service_date' => $serviceDate,
                                'source' => $request->paid_date ? 'paid_date' : 'today'
                            ]);
                        }

                        $pointsToRedeem = (int) $request->redeem_points;
                        $amountValue = (float) $request->received_amount;

                        $redeemResult = $airpointsService->redeemPoints(
                            $client,
                            $product,
                            $pointsToRedeem,
                            $amountValue,
                            $serviceDate
                        );

                        if ($redeemResult['success']) {
                            Log::info('Points redeemed successfully from Airpoints', [
                                'followup_id' => $followup->id,
                                'points' => $pointsToRedeem,
                                'amount' => $amountValue,
                                'redeem_id' => $redeemResult['redeem_id'] ?? null
                            ]);
                        } else {
                            Log::warning('Failed to redeem points from Airpoints', [
                                'followup_id' => $followup->id,
                                'points' => $pointsToRedeem,
                                'error' => $redeemResult['message'] ?? 'Unknown error'
                            ]);

                            // Rollback transaction if point redemption fails
                            DB::rollBack();

                            $errorMessage = $redeemResult['message'] ?? 'Unknown error occurred';

                            if ($isAjaxRequest) {
                                return response()->json([
                                    'success' => false,
                                    'message' => 'Failed to redeem points: ' . $errorMessage
                                ], 422);
                            } else {
                                return back()->withInput()->withErrors([
                                    'redeem_points' => 'Failed to redeem points: ' . $errorMessage
                                ]);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Exception during point redemption', [
                            'followup_id' => $followup->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);

                        // Rollback transaction on exception
                        DB::rollBack();

                        // Extract meaningful error message from exception
                        $errorMessage = $e->getMessage();
                        if (strpos($errorMessage, 'User not found in Airpoints system') !== false) {
                            $errorMessage = 'User not found in Acepoint system. Please ensure the user is registered in Acepoint.';
                        } elseif (strpos($errorMessage, 'Insufficient points') !== false) {
                            $errorMessage = $e->getMessage(); // Keep the specific insufficient points message
                        } elseif (strpos($errorMessage, 'HTTP request returned status code 404') !== false) {
                            $errorMessage = 'User not found in Acepoint system. Please ensure the user is registered in Acepoint.';
                        } else {
                            $errorMessage = 'Unable to process point redemption. Please try again or contact support.';
                        }

                        if ($isAjaxRequest) {
                            return response()->json([
                                'success' => false,
                                'message' => $errorMessage
                            ], 422);
                        } else {
                            return back()->withInput()->withErrors([
                                'redeem_points' => $errorMessage
                            ]);
                        }
                    }
                }
            }

            // Update the lead's next follow-up date
            if ($request->status == 2) { // Completed
                $lead->update([
                    'next_follow_up' => $request->next_followup_date
                ]);
            }

            DB::commit();

            if ($isAjaxRequest) {
                $statusMessages = [
                    0 => 'Initiated',
                    1 => 'Active',
                    2 => 'Cancelled',
                    3 => 'Full Payment Received',
                    4 => 'Partial Payment Received',
                    5 => 'Confirmed'
                ];
                $statusMessage = $statusMessages[$request->status] ?? 'Updated';

                return response()->json([
                    'success' => true,
                    'message' => "Lead $statusMessage successfully",
                    'data' => $followup
                ]);
            } else {
                return redirect()->route('admin.leads.follow-up.create', $lead->id)
                    ->with('success', 'Follow-up added successfully!');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Followup creation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            if ($isAjaxRequest) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            } else {
                return back()->withInput()->with('error', 'Error adding follow-up: ' . $e->getMessage());
            }
        }
    }

    /**
     * Show the form for creating a follow-up for a specific lead
     */
    public function createLeadFollowUp(Lead $lead)
    {
        // Load the lead with its relationships
        $lead->load('rideSegments', 'client');

        // Get basic client info
        $clientInfo = [
            'name' => $lead->client->name,
            'email' => $lead->client->email,
            'phone' => $lead->client->contact_number,
            'services' => 'N/A', // Default value
            'trip_from' => 'N/A',
            'trip_to' => 'N/A'
        ];

        // Get ALL available services
        $services = Service::get();

        // Get distinct extra services with their amounts
        $allExtraServices = ExtraService::select('id', 'extra_service', 'extra_service_amount')
            ->get();

        // Get service IDs from this specific lead
        $leadServiceIds = [];
        if (!empty($lead->service_ids)) {
            try {
                $leadServiceIds = is_array($lead->service_ids) ?
                    $lead->service_ids :
                    json_decode($lead->service_ids, true) ?? [];
                $clientInfo['services'] = Service::whereIn('id', $leadServiceIds)
                    ->pluck('service')
                    ->implode(', ');
            } catch (\Exception $e) {
                Log::error("Error processing service IDs: " . $e->getMessage());
            }
        }

        // Get product names from this lead (if any) using Lead model accessor
        try {
            $productNames = $lead->product_names ?? [];
            $clientInfo['products'] = is_array($productNames) ? implode(', ', $productNames) : ($productNames ?: 'N/A');
        } catch (\Exception $e) {
            Log::error('Error processing product IDs for lead: ' . $e->getMessage());
            $clientInfo['products'] = 'N/A';
        }

        // Get trip details if available
        if ($lead->rideSegments->count() > 0) {
            $firstSegment = $lead->rideSegments->first();
            $lastSegment = $lead->rideSegments->last();

            $clientInfo['trip_from'] = date('Y-m-d', strtotime($firstSegment->from_date)) . ' - ' . $firstSegment->from_place;
            $clientInfo['trip_to'] = date('Y-m-d', strtotime($lastSegment->to_date)) . ' - ' . $lastSegment->to_place;
        }

        // Get followups for THIS specific lead only
        $followups = $lead->leadFollowups()->with('paymentAuditTrail')->latest()->get();
        $latestFollowup = $followups->first();

        // Get the last followup with total amount first (this will be our reference)
        $lastFollowupWithAmount = $lead->leadFollowups()
            ->whereNotNull('total_amount')
            ->latest('created_at')
            ->first();

        // Determine which services and extra services to pre-select
        $selectedServices = [];
        $selectedExtraServices = [];

        // Priority: 1. Last followup with amount, 2. Latest followup, 3. Lead services
        if ($lastFollowupWithAmount) {
            // Use services from the last followup that has a total amount
            if ($lastFollowupWithAmount->service_ids && !empty(trim($lastFollowupWithAmount->service_ids))) {
                $decodedServices = json_decode($lastFollowupWithAmount->service_ids, true) ?? [];
                $selectedServices = array_values(array_intersect($decodedServices, $services->pluck('id')->toArray()));
            } else {
                // If followup with amount exists but no services selected, fall back to lead services
                $selectedServices = $leadServiceIds;
            }

            if ($lastFollowupWithAmount->extra_service_ids && !empty(trim($lastFollowupWithAmount->extra_service_ids))) {
                $decodedExtraServices = json_decode($lastFollowupWithAmount->extra_service_ids, true) ?? [];
                $selectedExtraServices = array_values(array_intersect($decodedExtraServices, $allExtraServices->pluck('id')->toArray()));
            }
        } elseif ($latestFollowup) {
            // If no followup with amount, but there's a latest followup, use its selections
            if ($latestFollowup->service_ids && !empty(trim($latestFollowup->service_ids))) {
                $decodedServices = json_decode($latestFollowup->service_ids, true) ?? [];
                $selectedServices = array_values(array_intersect($decodedServices, $services->pluck('id')->toArray()));
            } else {
                // If followup exists but no services selected, fall back to lead services
                $selectedServices = $leadServiceIds;
            }

            if ($latestFollowup->extra_service_ids && !empty(trim($latestFollowup->extra_service_ids))) {
                $decodedExtraServices = json_decode($latestFollowup->extra_service_ids, true) ?? [];
                $selectedExtraServices = array_values(array_intersect($decodedExtraServices, $allExtraServices->pluck('id')->toArray()));
            }
        } else {
            // If no previous followup at all, use services from the lead
            $selectedServices = $leadServiceIds;
        }

        // Ensure selectedServices contains valid service IDs
        $selectedServices = array_values(array_filter($selectedServices));
        $selectedExtraServices = array_values(array_filter($selectedExtraServices));

        // Debug log the final selections
        Log::info('Final service selections for lead follow-up:', [
            'lead_id' => $lead->id,
            'client_id' => $lead->client->id,
            'lead_service_ids' => $leadServiceIds,
            'has_latest_followup' => $latestFollowup ? true : false,
            'has_followup_with_amount' => $lastFollowupWithAmount ? true : false,
            'selected_services' => $selectedServices,
            'selected_extra_services' => $selectedExtraServices
        ]);

        // Create service prices and extra service prices
        $servicePrices = [];
        $extraServicePrices = [];

        foreach ($services as $service) {
            $servicePrices[$service->id] = $service->service_amount;
        }

        // Add all extra services to the prices array
        foreach ($allExtraServices as $extraService) {
            $extraServicePrices[$extraService->id] = $extraService->extra_service_amount;
        }

        // Prepare data for JavaScript to handle amount calculation
        $lastFollowupTotalAmount = $lastFollowupWithAmount ? $lastFollowupWithAmount->total_amount : null;
        $lastFollowupServiceAmount = $lastFollowupWithAmount ? $lastFollowupWithAmount->service_amount : null;
        $lastFollowupDiscountAmount = $lastFollowupWithAmount ? $lastFollowupWithAmount->discount_amount : null;
        $lastFollowupServiceDetails = $lastFollowupWithAmount && $lastFollowupWithAmount->service_details
            ? (is_string($lastFollowupWithAmount->service_details) ? json_decode($lastFollowupWithAmount->service_details, true) : $lastFollowupWithAmount->service_details)
            : [];
        $lastFollowupServiceIds = [];
        $lastFollowupExtraServiceIds = [];

        if ($lastFollowupWithAmount) {
            $lastFollowupServiceIds = !empty($lastFollowupWithAmount->service_ids)
                ? (is_string($lastFollowupWithAmount->service_ids) ? json_decode($lastFollowupWithAmount->service_ids, true) : $lastFollowupWithAmount->service_ids)
                : [];

            $lastFollowupExtraServiceIds = !empty($lastFollowupWithAmount->extra_service_ids)
                ? (is_string($lastFollowupWithAmount->extra_service_ids) ? json_decode($lastFollowupWithAmount->extra_service_ids, true) : $lastFollowupWithAmount->extra_service_ids)
                : [];
        }

        return view('admin.pages.follow-ups.add-follow-up', [
            'clientInfo' => $clientInfo,
            'followups' => $followups,
            'client' => $lead->client,
            'lead' => $lead,
            'services' => $services,
            'allExtraServices' => $allExtraServices,
            'selectedServices' => $selectedServices,
            'selectedExtraServices' => $selectedExtraServices,
            'servicePrices' => $servicePrices,
            'lastFollowupTotalAmount' => $lastFollowupTotalAmount,
            'lastFollowupServiceAmount' => $lastFollowupServiceAmount,
            'lastFollowupDiscountAmount' => $lastFollowupDiscountAmount,
            'lastFollowupServiceDetails' => $lastFollowupServiceDetails,
            'lastFollowupServiceIds' => $lastFollowupServiceIds,
            'lastFollowupExtraServiceIds' => $lastFollowupExtraServiceIds,
            'extraServicePrices' => $extraServicePrices
        ]);
    }

    // public function getExtraServicesByServices(Request $request)
    // {
    //     try {
    //         $serviceIds = $request->input('service_ids', []);

    //         if (empty($serviceIds)) {
    //             return response()->json(['extra_services' => []]);
    //         }

    //         // Get extra services only for the selected services
    //         $services = Service::whereIn('id', $serviceIds)->with('extraServices')->get();

    //         $extraServices = [];
    //         foreach ($services as $service) {
    //             foreach ($service->extraServices as $extraService) {
    //                 $extraServices[] = [
    //                     'id' => $extraService->id,
    //                     'name' => $extraService->extra_service,
    //                     'amount' => $extraService->extra_service_amount,
    //                     'service_id' => $service->id,
    //                     'service_name' => $service->service
    //                 ];
    //             }
    //         }

    //         return response()->json([
    //             'extra_services' => $extraServices
    //         ]);

    //     } catch (\Exception $e) {
    //         Log::error("Error fetching services by products: " . $e->getMessage());
    //         return response()->json(['error' => 'Server error'], 500);
    //     }
    // }

    public function showImportForm()
    {
        return view('admin.pages.leads.import-leads');
    }


    public function importLeads(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'excel_file' => 'required_without:final_import|file|mimes:xlsx,xls,csv|max:10240', // 10MB max
            'filter_from_date' => 'nullable|date',
            'filter_to_date' => 'nullable|date|after_or_equal:filter_from_date',
        ], [
            'excel_file.required_without' => 'Please select an Excel file to import',
            'excel_file.mimes' => 'File must be in Excel format (xlsx, xls, or csv)',
            'excel_file.max' => 'File size cannot exceed 10MB',
            'filter_to_date.after_or_equal' => 'The to date must be after or equal to the from date',
        ]);

        // Handle final import from preview
        if ($request->has('final_import')) {
            return $this->handleFinalImport($request);
        }

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Handle preview request
        if ($request->has('preview')) {
            return $this->handlePreviewRequest($request);
        }

        // Handle direct import (existing functionality)
        try {
            $import = new LeadsImport();
            Excel::import($import, $request->file('excel_file'));

            $imported = $import->getImported();
            $skipped = $import->getSkipped();
            $errors = $import->getErrors();

            $message = "Import completed! {$imported} leads imported successfully";
            if ($skipped > 0) {
                $message .= ", {$skipped} leads skipped due to errors";
            }

            if (!empty($errors)) {
                session()->flash('import_errors', $errors);
            }

            return redirect()->route('admin.leads.import')
                ->with('success', $message)
                ->with('import_summary', [
                    'imported' => $imported,
                    'skipped' => $skipped,
                    'total' => $imported + $skipped
                ]);
        } catch (\Exception $e) {
            Log::error('Lead import failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle preview request from Excel file
     */
    private function handlePreviewRequest(Request $request)
    {
        try {
            $file = $request->file('excel_file');
            $filterFromDate = $request->input('filter_from_date');
            $filterToDate = $request->input('filter_to_date');
            // Log incoming preview request so we can trace why logs may be missing
            try {
                Log::info('Preview request received', [
                    'file' => $file ? $file->getClientOriginalName() : null,
                    'filter_from_date' => $filterFromDate,
                    'filter_to_date' => $filterToDate,
                    'user_id' => auth()->id()
                ]);
            } catch (\Exception $e) {
                // Don't fail preview if logging has issues; record to error log
                Log::error('Failed to write preview request log: ' . $e->getMessage());
            }

            $previewData = $this->parseExcelForPreview($file, $filterFromDate, $filterToDate);
            // Log preview result size for easier debugging
            try {
                Log::info('Preview generated', ['rows' => is_array($previewData) ? count($previewData) : 0]);
            } catch (\Exception $e) {
                Log::error('Failed to write preview result log: ' . $e->getMessage());
            }

            // Get dropdown data
            $products = Product::select('id', 'product')->where('status', 1)->get();
            $allStaff = $this->getUsersInHierarchy();
            $services = Service::select('id', 'service')->where('status', 1)->get();

            // Filter staff for preview based on current user's role for Sales hierarchy
            try {
                $currentUser = auth()->user();
                $staff = $allStaff; // default

                if ($currentUser && $currentUser->userType) {
                    $role = $currentUser->userType->user_type;

                    // Normalize role strings for comparison
                    $roleNorm = trim(strtolower($role));

                    // Helper: get users by user_type names
                    $getByTypeNames = function (array $typeNames) {
                        return User::whereIn('user_type_id', function ($q) use ($typeNames) {
                            $q->select('id')->from('user_types')->whereIn('user_type', $typeNames);
                        })->where('status', 1)->get();
                    };

                    if ($roleNorm === strtolower(\App\Models\UserType::SALES_EXECUTIVE)) {
                        // Sales Executive: only self
                        $staff = collect([$currentUser]);
                    } elseif ($roleNorm === strtolower(\App\Models\UserType::SALES_MANAGER)) {
                        // Sales Manager: allow seeing other Sales Managers + Sales Executives
                        $staff = $getByTypeNames([\App\Models\UserType::SALES_MANAGER, \App\Models\UserType::SALES_EXECUTIVE]);
                    } elseif ($roleNorm === strtolower(\App\Models\UserType::SENIOR_SALES_MANAGER)) {
                        // Senior Sales Manager: get Senior Sales Manager + Sales Manager + Sales Executive
                        $staff = $getByTypeNames([\App\Models\UserType::SENIOR_SALES_MANAGER, \App\Models\UserType::SALES_MANAGER, \App\Models\UserType::SALES_EXECUTIVE]);
                    } else {
                        // Other roles: return all sales roles
                        $staff = $getByTypeNames(\App\Models\UserType::SALES_ROLES);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error filtering staff for preview: ' . $e->getMessage());
                $staff = $allStaff; // fallback to original
            }

            // Build product -> service mapping (product id => [service ids]) for front-end auto-selection
            $productServiceMap = [];
            foreach ($products as $p) {
                $productServiceMap[$p->id] = $this->getServicesForProducts([$p->id]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'leads' => $previewData,
                    'products' => $products,
                    'services' => $services,
                    'product_service_map' => $productServiceMap,
                    'staff' => $staff
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Preview generation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Preview failed: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Parse Excel file and return preview data
     */
    private function parseExcelForPreview($file, $filterFromDate = null, $filterToDate = null)
    {
        $collection = Excel::toCollection(null, $file)->first();
        $previewData = [];
        $headers = [];
        $phoneToPreviewIndices = []; // map cleaned phone => array of preview indices
        $seenUploadedNumbers = []; // track normalized numbers seen in uploaded file to remove duplicates
        // Basic log so we see parsing started
        try {
            Log::info('Parsing Excel for preview', [
                'file' => $file ? ($file->getClientOriginalName() ?? null) : null,
                'filter_from_date' => $filterFromDate,
                'filter_to_date' => $filterToDate,
                'rows' => $collection ? $collection->count() : 0
            ]);
        } catch (\Exception $e) {
            // ignore logging failures
        }

        foreach ($collection as $index => $row) {
            if ($index === 0) {
                // Store headers for reference
                $headers = $row->toArray();
                // Convert headers to lowercase with underscores
                $headers = array_map(function ($header) {
                    return strtolower(trim(str_replace([' ', '-'], '_', $header)));
                }, $headers);
                continue;
            }

            // Skip empty rows
            if ($this->isRowEmpty($row)) {
                continue;
            }

            // Convert row to associative array using headers
            $rowData = [];
            foreach ($headers as $colIndex => $header) {
                $rowData[$header] = $row[$colIndex] ?? '';
            }

            // Check if lead exists (by phone number within date range)
            $phoneNumber = $this->cleanPhoneNumber($rowData['phone_number'] ?? '');
            // Log the preview row phone info to help debug missing duplicate logs
            try {
                if (!empty($phoneNumber)) {
                    Log::info('Preview row phone detected', [
                        'cleaned_phone' => $phoneNumber,
                        'raw_phone' => $rowData['phone_number'] ?? null,
                        'preview_index' => count($previewData)
                    ]);
                } else {
                    Log::info('Preview row has no phone or empty after cleaning', [
                        'raw_phone' => $rowData['phone_number'] ?? null,
                        'preview_index' => count($previewData)
                    ]);
                }
            } catch (\Exception $e) {
                // ignore logging failures
            }
            $existingClient = null;
            $isExisting = false;
            $matchedLeads = [];

            // Normalize whatsapp number for deduplication
            $whatsappNumber = $this->cleanPhoneNumber($rowData['whatsapp_number'] ?? '');

            // Check for duplicates within uploaded file (by phone OR whatsapp). If a number has already
            // been processed, skip this row from preview entirely so only one appears.
            $duplicateFoundInUpload = false;
            if (!empty($phoneNumber)) {
                $key = 'p_' . $phoneNumber;
                if (isset($seenUploadedNumbers[$key])) {
                    $duplicateFoundInUpload = true;
                }
            }
            if (!$duplicateFoundInUpload && !empty($whatsappNumber)) {
                $key = 'w_' . $whatsappNumber;
                if (isset($seenUploadedNumbers[$key])) {
                    $duplicateFoundInUpload = true;
                }
            }

            if ($duplicateFoundInUpload) {
                // Log duplicate and skip adding to preview
                try {
                    Log::info('Skipping duplicate row in uploaded file for preview', [
                        'raw_phone' => $rowData['phone_number'] ?? null,
                        'raw_whatsapp' => $rowData['whatsapp_number'] ?? null,
                        'filter_from_date' => $filterFromDate,
                        'filter_to_date' => $filterToDate,
                        'file_row_index' => $index + 1
                    ]);
                } catch (\Exception $e) {
                    // ignore logging failures
                }
                // continue without adding to previewData
                continue;
            }

            if (!empty($phoneNumber)) {
                // Mark this number as seen for uploaded-file dedupe (so later rows are skipped)
                $seenUploadedNumbers['p_' . $phoneNumber] = true;
            }
            if (!empty($whatsappNumber)) {
                $seenUploadedNumbers['w_' . $whatsappNumber] = true;
            }

            // Track preview indices for this phone for duplicate-in-preview detection
            if (!empty($phoneNumber)) {
                if (!isset($phoneToPreviewIndices[$phoneNumber])) {
                    $phoneToPreviewIndices[$phoneNumber] = [];
                }
                $phoneToPreviewIndices[$phoneNumber][] = count($previewData); // use current length as index
            }

            if (!empty($phoneNumber)) {
                // Build query to check for existing leads within date range
                // Normalize stored contact_number in SQL by stripping non-digits so digits-only match works.
                $dbType = config('database.default');
                if ($dbType === 'pgsql') {
                    // Use regexp_replace available in Postgres to strip non-digits
                    $digitStripExpr = "regexp_replace(contact_number, '[^0-9]', '', 'g')";
                } else {
                    // Fallback for MySQL: remove common formatting characters
                    $digitStripExpr = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(contact_number, '+', ''), '-', ''), ' ', ''), '(', ''), ')')";
                }
                $clientQuery = Client::whereRaw("{$digitStripExpr} LIKE ?", ['%' . $phoneNumber . '%']);

                // If date filters are provided, check for leads within that range
                if ($filterFromDate || $filterToDate) {
                    $clientQuery->whereHas('leads', function ($query) use ($filterFromDate, $filterToDate) {
                        if ($filterFromDate) {
                            $query->where('created_at', '>=', Carbon::parse($filterFromDate)->startOfDay());
                        }
                        if ($filterToDate) {
                            $query->where('created_at', '<=', Carbon::parse($filterToDate)->endOfDay());
                        }
                    });
                } else {
                    // If no date filters, check if client has any leads
                    $clientQuery->whereHas('leads');
                }

                $existingClient = $clientQuery->first();
                $isExisting = $existingClient !== null;

                // If no existing client was found, log that fact for traceability
                if (!$isExisting) {
                    try {
                        Log::info('No existing client found for preview phone', [
                            'cleaned_phone' => $phoneNumber,
                            'raw_phone' => $rowData['phone_number'] ?? null,
                            'preview_index' => count($previewData),
                            'filter_from_date' => $filterFromDate,
                            'filter_to_date' => $filterToDate
                        ]);
                    } catch (\Exception $e) {
                        // ignore logging failures
                    }
                }

                // Log for debugging: include matched leads (id and created_at)
                if ($isExisting) {
                    try {
                        $leadQuery = $existingClient->leads();
                        if ($filterFromDate) {
                            $leadQuery->where('created_at', '>=', Carbon::parse($filterFromDate)->startOfDay());
                        }
                        if ($filterToDate) {
                            $leadQuery->where('created_at', '<=', Carbon::parse($filterToDate)->endOfDay());
                        }

                        $matchedLeads = $leadQuery->get(['id', 'created_at'])->map(function ($l) {
                            return [
                                'id' => $l->id,
                                'created_at' => $l->created_at ? $l->created_at->toDateTimeString() : null
                            ];
                        })->toArray();
                        // Include preview indices (if any) that have this phone number
                        $previewIndices = $phoneToPreviewIndices[$phoneNumber] ?? [];

                        Log::info("Duplicate found for phone: {$phoneNumber}", [
                            'filter_from_date' => $filterFromDate,
                            'filter_to_date' => $filterToDate,
                            'existing_client_id' => $existingClient->id,
                            'client_name' => $existingClient->name,
                            'existing_contact_number' => $existingClient->contact_number ?? null,
                            'matched_leads' => $matchedLeads,
                            'preview_indices' => $previewIndices,
                            'preview_raw_phone' => $rowData['phone_number'] ?? null,
                            'current_preview_index' => count($previewData)
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Error logging duplicate leads: ' . $e->getMessage(), [
                            'phone' => $phoneNumber,
                            'client_id' => $existingClient->id ?? null
                        ]);
                    }
                }
            }

            // If existing, populate with database values
            if ($isExisting && $existingClient) {
                // Preserve any Excel-provided next_follow_up before we overwrite rowData with DB values
                $excelNextFollowUp = $rowData['next_follow_up'] ?? null;

                $existingLead = $existingClient->latestLead;
                $rowData = array_merge($rowData, [
                    'full_name' => $existingClient->name,
                    'email_address' => $existingClient->email ?? '',
                    'phone_number' => $existingClient->contact_number,
                    'whatsapp_number' => $existingClient->alternate_number ?? '',
                    'date_of_birth' => $existingClient->date_of_birth ? Carbon::parse($existingClient->date_of_birth)->format('Y-m-d') : '',
                    'address' => $existingClient->address ?? '',
                    'country' => $existingClient->country->name ?? '',
                    'city' => $existingClient->city->name ?? '',
                    'staff_representative' => $existingLead ? ($existingLead->representative->name ?? '') : '',
                    'number_of_passengers' => $existingLead ? $existingLead->number_of_passengers : 1,
                ]);

                if ($existingLead && $existingLead->rideSegments->count() > 0) {
                    $firstSegment = $existingLead->rideSegments->first();
                    $lastSegment = $existingLead->rideSegments->last();

                    $rowData = array_merge($rowData, [
                        'from_date' => $firstSegment->from_date,
                        'from_place' => $firstSegment->from_place ?? '',
                        'to_date' => $lastSegment->to_date,
                        'to_place' => $lastSegment->to_place ?? '',
                    ]);
                }

                // If the uploaded Excel provided a next_follow_up, preserve and format it for preview.
                // Otherwise fall back to the existing lead's latest followup date (if available).
                if (!empty($excelNextFollowUp)) {
                    $rowData['next_follow_up'] = $this->formatDateTime($excelNextFollowUp);
                } else {
                    $latestFollowup = null;
                    if ($existingLead) {
                        try {
                            $latestFollowup = $existingLead->leadFollowups()->orderByDesc('next_followup_date')->first();
                        } catch (\Exception $e) {
                            $latestFollowup = null;
                        }
                    }
                    $rowData['next_follow_up'] = ($latestFollowup && $latestFollowup->next_followup_date) ? $latestFollowup->next_followup_date->format('Y-m-d H:i:s') : '';
                }
            } else {
                // Clean and format the Excel data for new leads
                $rowData['phone_number'] = $this->formatPhoneNumber($rowData['phone_number'] ?? '');
                $rowData['whatsapp_number'] = $this->formatPhoneNumber($rowData['whatsapp_number'] ?? '');
                $rowData['date_of_birth'] = $this->formatDate($rowData['date_of_birth'] ?? '');
                $rowData['from_date'] = $this->formatDateTime($rowData['from_date'] ?? '');
                $rowData['to_date'] = $this->formatDateTime($rowData['to_date'] ?? '');
                $rowData['next_follow_up'] = $this->formatDateTime($rowData['next_follow_up'] ?? '');
            }

            $rowData['existing'] = $isExisting;
            // Attach matched leads details to the preview row so frontend can display them
            $rowData['matched_leads'] = $matchedLeads;
            $previewData[] = $rowData;
        }

        // Log any phone numbers that appeared multiple times in the uploaded file (for traceability)
        foreach ($phoneToPreviewIndices as $phone => $indices) {
            if (count($indices) > 1) {
                try {
                    Log::info('Duplicate phone present in uploaded file (after dedupe):', [
                        'phone' => $phone,
                        'preview_indices' => $indices,
                        'filter_from_date' => $filterFromDate,
                        'filter_to_date' => $filterToDate,
                    ]);
                } catch (\Exception $e) {
                    // ignore logging failures
                }

                // // mark each preview row that shares this phone
                // foreach ($indices as $i) {
                //     if (isset($previewData[$i])) {
                //         $previewData[$i]['preview_duplicate'] = true;
                //     }
                // }
            }
        }

        return $previewData;
    }

    /**
     * Handle final import from selected preview data
     */
    private function handleFinalImport(Request $request)
    {
        try {
            $selectedLeads = json_decode($request->input('selected_leads'), true);

            if (empty($selectedLeads)) {
                return back()->with('error', 'No leads selected for import');
            }

            $imported = 0;
            $skipped = 0;
            $skippedDuplicates = 0;
            $skippedErrors = 0;
            $errors = [];
            $skippedReasons = []; // detailed reasons for skipped rows

            $seenImportNumbers = []; // track numbers seen during this final import to avoid duplicates
            foreach ($selectedLeads as $index => $leadData) {
                try {
                    // Note: do not auto-skip preview-marked existing rows here.
                    // The user requested that duplicate rows should be importable; only respect the user's checkbox selection.

                    DB::beginTransaction();
                    $result = $this->processLeadData($leadData, $index + 1, $seenImportNumbers);
                    if ($result === 'duplicate') {
                        $skipped++;
                        $skippedDuplicates++;
                        continue;
                    }
                    DB::commit();
                    $imported++;
                } catch (\Exception $e) {
                    DB::rollBack();
                    $skipped++;
                    $skippedErrors++;
                    $errMsg = "Lead " . ($index + 1) . ": " . $e->getMessage();
                    $errors[] = $errMsg;
                    $skippedReasons[] = $errMsg . "; Row data: " . (isset($leadData['full_name']) ? $leadData['full_name'] : 'n/a') . "; phone: " . ($leadData['phone_number'] ?? 'n/a');
                    Log::error("Import error on lead " . ($index + 1) . ": " . $e->getMessage(), [
                        'lead_data' => $leadData
                    ]);
                }
            }

            $message = "Import completed! {$imported} leads imported successfully.";
            $message .= " Skipped: {$skipped} (duplicates: {$skippedDuplicates}, errors: {$skippedErrors})";

            // Always pass import_errors in redirect so the Blade can display them if present
            return redirect()->route('admin.leads.import')
                ->with('success', $message)
                ->with('import_summary', [
                    'imported' => $imported,
                    'skipped' => $skipped,
                    'total' => $imported + $skipped,
                    'skipped_duplicates' => $skippedDuplicates,
                    'skipped_errors' => $skippedErrors
                ])
                ->with('import_errors', $errors)
                ->with('skipped_reasons', $skippedReasons);
        } catch (\Exception $e) {
            Log::error('Final import failed: ' . $e->getMessage());
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    /**
     * Process individual lead data
     */
    private function processLeadData($leadData, $rowNumber, array &$seenImportNumbers = [])
    {
        // Validate required fields
        if (empty(trim($leadData['full_name'] ?? ''))) {
            throw new \Exception("Full Name is required");
        }

        if (empty(trim($leadData['phone_number'] ?? ''))) {
            throw new \Exception("Phone Number is required");
        }

        // Country is now optional
        // if (empty(trim($leadData['country'] ?? ''))) {
        //     throw new \Exception("Country is required");
        // }

        // Service and staff are optional during import; we'll try to resolve them below

        // Find or create country (only if country is provided)
        $country = null;
        if (!empty(trim($leadData['country'] ?? ''))) {
            $country = Country::where('name', 'LIKE', '%' . trim($leadData['country']) . '%')->first();
            if (!$country) {
                throw new \Exception("Country '{$leadData['country']}' not found");
            }
        }

        // Find or create city (only if country is provided)
        $city = null;
        if (!empty($leadData['city']) && $country) {
            $city = City::where('name', 'LIKE', '%' . trim($leadData['city']) . '%')
                ->where('country_id', $country->id)
                ->first();
        }

        // Find service (attempt), may be resolved from product if not provided
        $service = null;
        if (!empty(trim($leadData['service'] ?? ''))) {
            $service = Service::where('service', 'LIKE', '%' . trim($leadData['service']) . '%')->first();
        }

        // Find product(s)
        $productIds = [];
        if (!empty(trim($leadData['product'] ?? ''))) {
            // product field may include product name(s) or product id(s) depending on front-end
            $productNames = array_map('trim', explode(',', trim($leadData['product'])));
            foreach ($productNames as $productName) {
                if (!empty($productName)) {
                    // Try to find by product name first
                    $product = Product::where('product', 'LIKE', '%' . $productName . '%')->first();
                    if ($product) {
                        $productIds[] = $product->id;
                        continue;
                    }
                    // As a fallback, if the front-end passed an id string, try to find by id
                    try {
                        if (Str::isUuid($productName)) {
                            $productObj = Product::find($productName);
                            if ($productObj) $productIds[] = $productObj->id;
                        }
                    } catch (\Exception $e) {
                        // ignore
                    }
                }
            }
        }

        // Find staff representative. If not found, default to authenticated user (if any) or null.
        $staff = null;
        if (!empty(trim($leadData['staff_representative'] ?? ''))) {
            $staff = User::where('name', 'LIKE', '%' . trim($leadData['staff_representative']) . '%')->first();
        }

        if (!$staff) {
            try {
                $authUser = auth()->user();
                if ($authUser) {
                    $staff = $authUser;
                }
            } catch (\Exception $e) {
                // ignore auth errors and keep staff null
            }
        }

        // Normalize phone and whatsapp for deduplication within this import batch
        $normalizedPhoneDigits = $this->cleanPhoneNumber($leadData['phone_number'] ?? '');
        $normalizedWhatsappDigits = $this->cleanPhoneNumber($leadData['whatsapp_number'] ?? '');

        // If this number has already been imported in this run, skip and count as duplicate
        if (!empty($normalizedPhoneDigits)) {
            if (isset($seenImportNumbers['p_' . $normalizedPhoneDigits])) {
                // Return a marker so caller can handle duplicate counting
                return 'duplicate';
            }
        }
        if (!empty($normalizedWhatsappDigits)) {
            if (isset($seenImportNumbers['w_' . $normalizedWhatsappDigits])) {
                return 'duplicate';
            }
        }
        $searchDigits = $this->cleanPhoneNumber($leadData['phone_number']);
        $client = null;
        if (!empty($searchDigits)) {
            $dbType = config('database.default');
            if ($dbType === 'pgsql') {
                $digitStripExpr = "regexp_replace(contact_number, '[^0-9]', '', 'g')";
            } else {
                $digitStripExpr = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(contact_number, '+', ''), '-', ''), ' ', ''), '(', ''), ')')";
            }
            $client = Client::whereRaw("{$digitStripExpr} LIKE ?", ['%' . $searchDigits . '%'])->first();

            try {
                Log::info('Client lookup by digits', [
                    'search_digits' => $searchDigits,
                    'db_driver' => $dbType,
                    'used_expr' => $digitStripExpr,
                    'found_client_id' => $client->id ?? null,
                    'found_contact_number' => $client->contact_number ?? null
                ]);
            } catch (\Exception $e) {
                // ignore logging failures
            }
        }

        if (!$client) {
            // Create new client
            $client = Client::create([
                'id' => Str::uuid(),
                'name' => trim($leadData['full_name']),
                'email' => trim($leadData['email_address'] ?? ''),
                'contact_number' => trim($leadData['phone_number']),
                'alternate_number' => trim($leadData['whatsapp_number'] ?? '') ?: null,
                'date_of_birth' => $this->parseDate($leadData['date_of_birth'] ?? null),
                'address' => trim($leadData['address'] ?? ''),
                'city_id' => $city ? $city->id : null,
                'country_id' => $country ? $country->id : null,
                'status' => 1,
                'created_by' => auth()->id(),
            ]);
        }

        // Mark numbers as seen for this import so subsequent rows are skipped
        if (!empty($normalizedPhoneDigits)) {
            $seenImportNumbers['p_' . $normalizedPhoneDigits] = true;
        }
        if (!empty($normalizedWhatsappDigits)) {
            $seenImportNumbers['w_' . $normalizedWhatsappDigits] = true;
        }

        // Create lead
        $serviceIds = [];
        // If a service was provided/resolved, add it
        if ($service) {
            $serviceIds[] = $service->id;
        }

        // If product(s) selected, add services associated with those products
        if (!empty($productIds)) {
            $productServices = $this->getServicesForProducts($productIds);
            $serviceIds = array_unique(array_merge($serviceIds, $productServices));
        }

        // If still no services found, leave serviceIds empty (lead will be created without service_ids)

        $lead = Lead::create([
            'id' => Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' => $staff ? $staff->id : null,
            'service_ids' => !empty($serviceIds) ? json_encode($serviceIds) : null,
            'product_ids' => !empty($productIds) ? json_encode($productIds) : null,
            'number_of_passengers' => (int)($leadData['number_of_passengers'] ?? 1),
            'description' => "Imported lead from Excel preview",
        ]);

        // Create trip segment
        $fromDate = $this->parseDateTime($leadData['from_date'] ?? null);
        $toDate = $this->parseDateTime($leadData['to_date'] ?? null);

        if ($fromDate) {
            $computedToDate = $toDate ?: $fromDate;
            LeadRide::create([
                'id' => Str::uuid(),
                'lead_id' => $lead->id,
                'from_date' => $fromDate,
                'to_date' => $computedToDate,
                'from_place' => trim($leadData['from_place'] ?? ''),
                'to_place' => trim($leadData['to_place'] ?? ''),
            ]);
        }

        // Create follow-up
        $nextFollowUp = $this->parseDateTime($leadData['next_follow_up'] ?? null);
        if (!$nextFollowUp) {
            $nextFollowUp = now()->addDays(7)->format('Y-m-d H:i:s');
        }

        LeadFollowup::create([
            'id' => Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => $nextFollowUp,
            'followup_note' => 'Lead imported from Excel file via preview',
            'followed_by' => auth()->id(),
            'status' => '1',
        ]);
    }

    /**
     * Check if a row is empty
     */
    private function isRowEmpty($row)
    {
        $rowArray = $row->toArray();
        foreach ($rowArray as $value) {
            if (!empty(trim($value))) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get services for products
     */
    private function getServicesForProducts($productIds)
    {
        if (empty($productIds)) {
            return [];
        }

        try {
            $services = [];
            foreach ($productIds as $productId) {
                $productServices = DB::table('services')
                    ->select('id')
                    ->where('product_ids', 'like', '%"' . $productId . '"%')
                    ->pluck('id')
                    ->toArray();

                $services = array_merge($services, $productServices);
            }
            return array_unique($services);
        } catch (\Exception $e) {
            Log::warning('Failed to fetch services for products: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Clean phone number for comparison
     */
    private function cleanPhoneNumber($phoneNumber)
    {
        return preg_replace('/[^0-9]/', '', $phoneNumber);
    }

    /**
     * Format phone number
     */
    private function formatPhoneNumber($phoneNumber)
    {
        if (empty($phoneNumber)) {
            return null;
        }

        $cleaned = preg_replace('/[^\d+]/', '', trim($phoneNumber));

        if (str_starts_with($cleaned, '+91')) {
            $number = substr($cleaned, 3);
            return '+91-' . $number;
        } elseif (str_starts_with($cleaned, '+1')) {
            $number = substr($cleaned, 2);
            return '+1-' . $number;
        } elseif (str_starts_with($cleaned, '+')) {
            return $cleaned;
        } else {
            return '+91-' . $cleaned;
        }
    }

    /**
     * Format date
     */
    private function formatDate($date)
    {
        if (empty($date)) {
            return null;
        }

        try {
            // If it's already a DateTime object
            $appTz = config('app.timezone') ?: date_default_timezone_get();
            if ($date instanceof \DateTimeInterface) {
                return Carbon::instance($date)->setTimezone($appTz)->format('Y-m-d');
            }

            // If Excel stores it as a numeric serial
            if (is_numeric($date)) {
                try {
                    $dt = ExcelDate::excelToDateTimeObject((float) $date);
                    $appTz = config('app.timezone') ?: date_default_timezone_get();
                    $local = Carbon::createFromFormat('Y-m-d H:i:s', $dt->format('Y-m-d H:i:s'), $appTz);
                    return $local->format('Y-m-d');
                } catch (\Exception $e) {
                    // fall-through to string parsing
                }
            }

            // Try common formats (day-first then ISO)
            $formats = [
                'd-m-Y H:i',
                'd-m-Y H:i:s',
                'd-m-Y',
                'Y-m-d H:i',
                'Y-m-d H:i:s',
                'Y-m-d',
                'd/m/Y H:i',
                'd/m/Y H:i:s',
                'd/m/Y',
                'm/d/Y H:i',
                'm/d/Y',
            ];

            foreach ($formats as $fmt) {
                try {
                    $c = Carbon::createFromFormat($fmt, trim((string)$date));
                    if ($c !== false) {
                        return $c->setTimezone($appTz)->format('Y-m-d');
                    }
                } catch (\Exception $e) {
                    // try next format
                }
            }

            // Last resort: let Carbon try to parse intelligently
            return Carbon::parse((string)$date)->setTimezone($appTz)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Format datetime
     */
    private function formatDateTime($datetime)
    {
        if (empty($datetime)) {
            return null;
        }

        try {
            // If it's already a DateTime object
            $appTz = config('app.timezone') ?: date_default_timezone_get();
            if ($datetime instanceof \DateTimeInterface) {
                return Carbon::instance($datetime)->setTimezone($appTz)->format('Y-m-d\TH:i');
            }

            // If Excel provided a numeric serial date
            if (is_numeric($datetime)) {
                try {
                    $dt = ExcelDate::excelToDateTimeObject((float) $datetime);
                    $appTz = config('app.timezone') ?: date_default_timezone_get();
                    $local = Carbon::createFromFormat('Y-m-d H:i:s', $dt->format('Y-m-d H:i:s'), $appTz);
                    return $local->format('Y-m-d\TH:i');
                } catch (\Exception $e) {
                    // fall-through to string parsing
                }
            }

            // Try common formats (day-first then ISO)
            $formats = [
                'd-m-Y H:i',
                'd-m-Y H:i:s',
                'd-m-Y',
                'Y-m-d H:i',
                'Y-m-d H:i:s',
                'Y-m-d',
                'd/m/Y H:i',
                'd/m/Y H:i:s',
                'm/d/Y H:i',
                'm/d/Y',
            ];

            foreach ($formats as $fmt) {
                try {
                    $c = Carbon::createFromFormat($fmt, trim((string)$datetime));
                    if ($c !== false) {
                        return $c->setTimezone($appTz)->format('Y-m-d\TH:i');
                    }
                } catch (\Exception $e) {
                    // try next
                }
            }

            // Last attempt: flexible parsing
            return Carbon::parse((string)$datetime)->setTimezone($appTz)->format('Y-m-d\TH:i');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Parse date for database storage
     */
    private function parseDate($date)
    {
        if (empty($date)) {
            return null;
        }

        try {
            // If it's already a DateTime object
            if ($date instanceof \DateTimeInterface) {
                return Carbon::instance($date)->format('Y-m-d');
            }

            // If Excel stores it as a numeric serial
            if (is_numeric($date)) {
                try {
                    $dt = ExcelDate::excelToDateTimeObject((float) $date);
                    $appTz = config('app.timezone') ?: date_default_timezone_get();
                    $local = Carbon::createFromFormat('Y-m-d H:i:s', $dt->format('Y-m-d H:i:s'), $appTz);
                    return $local->format('Y-m-d');
                } catch (\Exception $e) {
                    // fall-through to string parsing
                }
            }

            // Try common formats (day-first then ISO)
            $formats = [
                'd-m-Y H:i',
                'd-m-Y H:i:s',
                'd-m-Y',
                'Y-m-d H:i',
                'Y-m-d H:i:s',
                'Y-m-d',
                'd/m/Y H:i',
                'd/m/Y H:i:s',
                'd/m/Y',
                'm/d/Y H:i',
                'm/d/Y',
            ];

            foreach ($formats as $fmt) {
                try {
                    $c = Carbon::createFromFormat($fmt, trim((string)$date));
                    if ($c !== false) {
                        return $c->format('Y-m-d');
                    }
                } catch (\Exception $e) {
                    // try next format
                }
            }

            // Last resort: let Carbon try to parse intelligently
            return Carbon::parse((string)$date)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Parse datetime for database storage
     */
    private function parseDateTime($datetime)
    {
        if (empty($datetime)) {
            return null;
        }

        try {
            // If it's already a DateTime object
            if ($datetime instanceof \DateTimeInterface) {
                return Carbon::instance($datetime)->format('Y-m-d H:i:s');
            }

            // If Excel provided a numeric serial date
            if (is_numeric($datetime)) {
                try {
                    $dt = ExcelDate::excelToDateTimeObject((float) $datetime);
                    $appTz = config('app.timezone') ?: date_default_timezone_get();
                    $local = Carbon::createFromFormat('Y-m-d H:i:s', $dt->format('Y-m-d H:i:s'), $appTz);
                    return $local->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    // fall-through to string parsing
                }
            }

            // Try common formats (day-first then ISO)
            $formats = [
                'd-m-Y H:i',
                'd-m-Y H:i:s',
                'd-m-Y',
                'Y-m-d H:i',
                'Y-m-d H:i:s',
                'Y-m-d',
                'd/m/Y H:i',
                'd/m/Y H:i:s',
                'm/d/Y H:i',
                'm/d/Y',
            ];

            foreach ($formats as $fmt) {
                try {
                    $c = Carbon::createFromFormat($fmt, trim((string)$datetime));
                    if ($c !== false) {
                        return $c->format('Y-m-d H:i:s');
                    }
                } catch (\Exception $e) {
                    // try next
                }
            }

            // Last attempt: flexible parsing
            return Carbon::parse((string)$datetime)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Download sample Excel file for lead import
     */
    public function downloadSampleExcel()
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set headers
            $headers = [
                'A1' => 'Full Name',
                'B1' => 'Email Address',
                'C1' => 'Phone Number',
                'D1' => 'WhatsApp Number',
                'E1' => 'Date of Birth',
                'F1' => 'Address',
                'G1' => 'Country',
                'H1' => 'City',
                'I1' => 'Service',
                'J1' => 'Product',
                'K1' => 'Number of Passengers',
                'L1' => 'From Date',
                'M1' => 'From Place',
                'N1' => 'To Date',
                'O1' => 'To Place',
                'P1' => 'Next Follow-up',
                'Q1' => 'Staff Representative'
            ];

            foreach ($headers as $cell => $value) {
                $sheet->setCellValue($cell, $value);
                $sheet->getStyle($cell)->getFont()->setBold(true);
                $sheet->getStyle($cell)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E2E8F0');
            }

            // Add sample data
            $sampleData = [
                [
                    'John Doe',
                    'john.doe@example.com',
                    '+91-9876543210',
                    '+91-9876543210',
                    '1990-01-15',
                    '123 Main Street, Downtown',
                    'India',
                    'Mumbai',
                    'Private Jet Charter',
                    'Helicopter',
                    '4',
                    '2025-09-01 10:00',
                    'Mumbai Airport',
                    '2025-09-01 14:00',
                    'Delhi Airport',
                    '2025-08-15 09:00',
                    'John Smith'
                ],
                [
                    'Jane Smith',
                    'jane.smith@example.com',
                    '+1-5551234567',
                    '+1-5551234567',
                    '1985-05-20',
                    '456 Oak Avenue, Business District',
                    'United States',
                    'New York',
                    'Helicopter Service',
                    'Private Island',
                    '2',
                    '2025-09-10 08:00',
                    'JFK Airport',
                    '2025-09-10 12:00',
                    'LAX Airport',
                    '2025-08-20 14:00',
                    'Sarah Wilson'
                ]
            ];

            $row = 2;
            foreach ($sampleData as $data) {
                $col = 'A';
                foreach ($data as $value) {
                    $sheet->setCellValue($col . $row, $value);
                    $col++;
                }
                $row++;
            }

            // Auto-resize columns
            foreach (range('A', 'Q') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Add instructions sheet
            $instructionsSheet = $spreadsheet->createSheet();
            $instructionsSheet->setTitle('Instructions');

            $instructions = [
                'LEAD IMPORT INSTRUCTIONS',
                '',
                '1. Fill in all required fields marked with * in the sample data sheet',
                '2. Required fields: Full Name, Phone Number, Country, Service, Staff Representative, Number of Passengers, From Date, From Place, To Date, To Place',
                '3. Optional fields: Email Address, WhatsApp Number, Date of Birth, Address, City, Product, Next Follow-up',
                '4. Date formats: Use YYYY-MM-DD for dates, YYYY-MM-DD HH:MM for date-time',
                '5. Phone numbers: Include country code (e.g., +91-9876543210)',
                '6. Country and City: Must match existing entries in the system',
                '7. Service: Must match existing service names in the system',
                '8. Product: Optional - can be single product or multiple products separated by commas',
                '9. Each product must have at least one service mapped to it',
                '10. Staff Representative: Must match existing staff member names',
                '11. Number of Passengers: Must be a positive integer',
                '12. From Date and To Date: Must be valid date-time in the future',
                '13. From Place and To Place: Must be valid locations',
                '',
                'FIELD DESCRIPTIONS:',
                '• Full Name: Complete name of the client (Required)',
                '• Email Address: Valid email address (Optional)',
                '• Phone Number: Primary contact number with country code (Required)',
                '• WhatsApp Number: WhatsApp contact number (Optional)',
                '• Date of Birth: Client\'s birth date (Optional)',
                '• Address: Complete address (Optional)',
                '• Country: Country name (must exist in system) (Optional)',
                '• City: City name (will be created if doesn\'t exist) (Optional)',
                '• Service: Service name (must exist in system) (Required)',
                '• Product: Product name(s) - single or comma-separated (Optional)',
                '  Example: "Helicopter" or "Helicopter, Private Island"',
                '  Note: Each product must have services mapped to it',
                '• Number of Passengers: Total passengers for the trip (Required)',
                '• From Date: Departure date and time (Required)',
                '• From Place: Departure location (Required)',
                '• To Date: Arrival date and time (Required)',
                '• To Place: Arrival location (Required)',
                '• Next Follow-up: Next follow-up date and time (Optional)',
                '• Staff Representative: Assigned staff member name (Required)',
                '',
                'PRODUCT FIELD NOTES:',
                '• Products are optional but recommended for better service mapping',
                '• You can specify multiple products separated by commas',
                '• Each product must have at least one service mapped to it',
                '• Products with no service mappings will cause import errors',
                '• Services associated with products will be automatically added to the lead',
                '',
                'NOTE: If any row has errors, it will be skipped and reported in the import summary.'
            ];

            $instructionRow = 1;
            foreach ($instructions as $instruction) {
                $instructionsSheet->setCellValue('A' . $instructionRow, $instruction);
                if ($instructionRow === 1) {
                    $instructionsSheet->getStyle('A' . $instructionRow)->getFont()->setBold(true)->setSize(14);
                }
                $instructionRow++;
            }

            $instructionsSheet->getColumnDimension('A')->setWidth(80);

            // Set active sheet back to data sheet
            $spreadsheet->setActiveSheetIndex(0);
            $spreadsheet->getActiveSheet()->setTitle('Lead Data');

            // Create response
            $filename = 'lead_import_sample_' . date('Y_m_d_H_i_s') . '.xlsx';

            return new StreamedResponse(function () use ($spreadsheet) {
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            }, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'max-age=0',
            ]);
        } catch (\Exception $e) {
            Log::error('Sample Excel generation failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to generate sample file: ' . $e->getMessage());
        }
    }

    /**
     * Export leads to Excel with filters
     */
    public function exportLeads(Request $request)
    {
        try {
            // Log incoming export filters for debugging
            // try {
            //     $filters = $request->only([
            //         'status', 'service_ids', 'product_ids', 'representative_user_id',
            //         'from_date', 'to_date', 'name', 'email', 'phone', 'format'
            //     ]);
            //     Log::info('ExportLeads called with filters: ' . json_encode($filters));
            // } catch (\Exception $e) {
            //     // ignore logging failures
            // }
            // Build the same query as the index method but with filters
            $query = Lead::with(['client', 'representative', 'rideSegments', 'leadFollowups.followedBy'])
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

            // Apply the same filters as in index method
            // Restrict exported leads to the current user's representative hierarchy (same as index)
            $currentUser = auth()->user();
            $representatives = getRepresentativeIds($currentUser);
            // If the user applied a specific staff filter, use that exact representative
            if ($request->filled('representative_user_id')) {
                $repId = $request->input('representative_user_id');
                // Ensure the selected rep is allowed within the current user's hierarchy when applicable
                if (is_array($representatives) && !empty($representatives)) {
                    if (in_array($repId, $representatives)) {
                        $query->where('representative_user_id', $repId);
                    } else {
                        // Selected rep is outside user's allowed scope - return empty
                        $dnpLeads = collect();
                        $services = Service::all()->where('status', 1);
                        $staff = $this->getUsersInHierarchy();

                        return view('admin.pages.leads.dnp-leads', compact('dnpLeads', 'services', 'staff'));
                    }
                } else {
                    // No representative scope, just filter by selection
                    $query->where('representative_user_id', $repId);
                }
            } else {
                if ($representatives) {
                    $query->whereIn('representative_user_id', $representatives);
                }
            }
            if ($request->filled('name')) {
                $query->whereHas('client', function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->name . '%');
                });
            }

            if ($request->filled('email')) {
                $query->whereHas('client', function ($q) use ($request) {
                    $q->where('email', 'like', '%' . $request->email . '%');
                });
            }

            if ($request->filled('phone')) {
                $query->whereHas('client', function ($q) use ($request) {
                    $q->where('contact_number', 'like', '%' . $request->phone . '%');
                });
            }

            if ($request->filled('representative_user_id')) {
                $query->where('representative_user_id', $request->representative_user_id);
            }

            // Status filter (latest follow-up) - keep same behavior as index
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

            // Exclude DNP (Call Not Connected) by service id if not explicitly filtering by service
            $dnpService = Service::where('service', 'Call Not Connected')->first();
            $dnpServiceId = $dnpService ? $dnpService->id : null;
            if ($dnpServiceId && !$request->filled('service_ids')) {
                $pattern = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $dnpServiceId) . '%';
                $query->whereRaw(
                    "replace(trim(both '\"' from service_ids::text), '\\', '') NOT LIKE ?",
                    [$pattern]
                );
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

            $leads = $query->orderBy('created_at', 'desc')->get();

            // Apply service filtering (PHP-based), same as index
            if ($request->filled('service_ids')) {
                $serviceIds = is_array($request->service_ids) ? $request->service_ids : [$request->service_ids];

                $leads = $leads->filter(function ($lead) use ($serviceIds) {
                    if (empty($lead->service_ids)) return false;

                    $json = trim($lead->service_ids, '"');
                    $json = str_replace('\\"', '"', $json);
                    $ids = json_decode($json, true);

                    $match = is_array($ids) && count(array_intersect($ids, $serviceIds)) > 0;
                    if (!$match) Log::info('Lead skipped by service filter', ['lead_id' => $lead->id, 'service_ids' => $ids]);
                    return $match;
                });
            }

            // Apply product filtering (PHP-based), same as index
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

            // Reindex collection keys so $index in foreach starts from 0..n-1
            $leads = $leads->values();

            // Create new Spreadsheet object
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set headers - matching the import format
            $headers = [
                'S.No',
                'Full Name',
                'Email Address',
                'Phone Number',
                'WhatsApp Number',
                'Date of Birth',
                'Address',
                'Country',
                'City',
                'Product',
                'Staff Representative',
                'Number of Passengers',
                'From Date',
                'To Date',
                'From Place',
                'To Place',
                'Next Follow Up',
                'Status',
                'Created Date',
                'Last Update',
                'Follow-up Notes'
            ];

            // Apply headers
            $sheet->fromArray($headers, NULL, 'A1');

            // Style the header row
            $headerRange = 'A1:' . chr(64 + count($headers)) . '1';
            $sheet->getStyle($headerRange)->getFont()->setBold(true);
            $sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('4472C4');
            $sheet->getStyle($headerRange)->getFont()->getColor()->setRGB('FFFFFF');

            // Auto-size columns
            foreach (range('A', chr(64 + count($headers))) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Add data rows
            $row = 2;
            foreach ($leads as $index => $lead) {
                // Get product names
                $productIds = json_decode($lead->product_ids, true) ?? [];
                $products = Product::whereIn('id', $productIds)->pluck('product')->toArray();
                $productNames = implode(', ', $products);

                // Get ride segments
                $firstSegment = $lead->rideSegments->first();
                $lastSegment = $lead->rideSegments->last();

                // Get latest follow-up
                $latestFollowup = $lead->leadFollowups->sortByDesc('created_at')->first();

                // Get status
                $status = $latestFollowup ? $latestFollowup->status : null;
                $statusText = 'N/A';
                if ($status == 0) $statusText = 'Initiated';
                elseif ($status == 1) $statusText = 'Active';
                elseif ($status == 2) $statusText = 'Cancelled';
                elseif ($status == 3) $statusText = 'Full payment received';
                elseif ($status == 4) $statusText = 'Partial payment received';
                elseif ($status == 5) $statusText = 'Completed';
                elseif ($status == 6) $statusText = 'pending';
                elseif ($status == 7) $statusText = 'Rescheduled';
                elseif ($status == 8) $statusText = 'Approved';
                elseif ($status == 9) $statusText = 'Rejected';

                // Get follow-up notes
                $followupNotes = $lead->leadFollowups->pluck('followup_note')->filter()->implode('; ');

                // Helper function to safely format dates
                $formatDate = function ($date, $format = 'Y-m-d') {
                    if (!$date) return '';
                    try {
                        if (is_string($date)) {
                            return Carbon::parse($date)->format($format);
                        }
                        return $date->format($format);
                    } catch (\Exception $e) {
                        return is_string($date) ? $date : '';
                    }
                };

                // Normalize phone numbers to digits-only (remove "+", "-", spaces etc.) per export requirements
                $phoneDigits = $lead->client && ($lead->client->contact_number ?? '') !== '' ? preg_replace('/\D/', '', $lead->client->contact_number) : '';
                $altDigits = $lead->client && ($lead->client->alternate_number ?? '') !== '' ? preg_replace('/\D/', '', $lead->client->alternate_number) : '';

                $rowData = [
                    $index + 1, // S.No
                    $lead->client->name ?? '', // Full Name
                    $lead->client->email ?? '', // Email Address
                    $phoneDigits, // Phone Number (digits-only)
                    $altDigits, // WhatsApp Number (digits-only)
                    $lead->client->date_of_birth ? $formatDate($lead->client->date_of_birth) : '', // Date of Birth
                    $lead->client->address ?? '', // Address
                    $lead->client->country->name ?? '', // Country
                    $lead->client->city->name ?? '', // City
                    $productNames, // Product
                    $lead->representative->name ?? '', // Staff Representative
                    $lead->number_of_passengers ?? 1, // Number of Passengers
                    $firstSegment ? $formatDate($firstSegment->from_date) : '', // From Date
                    $lastSegment ? $formatDate($lastSegment->to_date) : '', // To Date
                    $firstSegment ? $firstSegment->from_place : '', // From Place
                    $lastSegment ? $lastSegment->to_place : '', // To Place
                    $latestFollowup && $latestFollowup->next_followup_date ? $formatDate($latestFollowup->next_followup_date, 'Y-m-d H:i') : '', // Next Follow Up
                    $statusText, // Status
                    $formatDate($lead->created_at, 'Y-m-d H:i:s'), // Created Date
                    $formatDate($lead->updated_at, 'Y-m-d H:i:s'), // Last Update
                    $followupNotes // Follow-up Notes
                ];

                $sheet->fromArray($rowData, NULL, 'A' . $row);

                // Force phone and whatsapp cells to be stored as strings to avoid Excel scientific notation
                try {
                    // Column D = phone, Column E = whatsapp (1-based columns: A=1)
                    $sheet->setCellValueExplicit('D' . $row, (string)($rowData[3] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('E' . $row, (string)($rowData[4] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } catch (\Exception $e) {
                    // ignore failures; number-format fallback will still help
                }

                $row++;
            }

            // Ensure phone columns are treated as text in XLSX to avoid scientific notation in Excel
            $highestRow = $row - 1;
            try {
                $sheet->getStyle('D2:D' . $highestRow)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
                $sheet->getStyle('E2:E' . $highestRow)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
            } catch (\Exception $e) {
                // ignore if styling fails
            }

            // If caller requested CSV format, stream CSV instead and ensure phone formatting is preserved for Excel
            if ($request->query('format') === 'csv') {
                $csvFilename = 'leads_export_' . date('Y_m_d_H_i_s') . '.csv';
                $headersCsv = $headers; // from earlier

                $rows = [];
                // Rebuild rows same as spreadsheet but as simple arrays
                foreach ($leads as $index => $lead) {
                    $productIds = json_decode($lead->product_ids, true) ?? [];
                    $products = Product::whereIn('id', $productIds)->pluck('product')->toArray();
                    $productNames = implode(', ', $products);

                    $firstSegment = $lead->rideSegments->first();
                    $lastSegment = $lead->rideSegments->last();

                    $latestFollowup = $lead->leadFollowups->sortByDesc('created_at')->first();

                    $status = $latestFollowup ? $latestFollowup->status : null;
                    $statusText = 'N/A';
                    if ($status == 0) $statusText = 'Initiated';
                    elseif ($status == 1) $statusText = 'Active';
                    elseif ($status == 2) $statusText = 'Cancelled';
                    elseif ($status == 3) $statusText = 'Full payment received';
                    elseif ($status == 4) $statusText = 'Partial payment received';
                    elseif ($status == 5) $statusText = 'Completed';
                    elseif ($status == 6) $statusText = 'pending';
                    elseif ($status == 7) $statusText = 'Rescheduled';
                    elseif ($status == 8) $statusText = 'Approved';
                    elseif ($status == 9) $statusText = 'Rejected';

                    $followupNotes = $lead->leadFollowups->pluck('followup_note')->filter()->implode('; ');

                    $formatDate = function ($date, $format = 'Y-m-d') {
                        if (!$date) return '';
                        try {
                            if (is_string($date)) {
                                return Carbon::parse($date)->format($format);
                            }
                            return $date->format($format);
                        } catch (\Exception $e) {
                            return is_string($date) ? $date : '';
                        }
                    };

                    // Normalize phone numbers to digits-only for CSV as well so CSV matches the XLSX export.
                    // This removes +, dashes and spaces and preserves only digits (country code + number).
                    $rawPhone = $lead->client && ($lead->client->contact_number ?? '') !== '' ? trim($lead->client->contact_number) : '';
                    $rawAlt = $lead->client && ($lead->client->alternate_number ?? '') !== '' ? trim($lead->client->alternate_number) : '';

                    $digitsPhone = $rawPhone !== '' ? preg_replace('/\D/', '', $rawPhone) : '';
                    $digitsAlt = $rawAlt !== '' ? preg_replace('/\D/', '', $rawAlt) : '';

                    // Escape any double-quotes (unlikely in digits-only) but keep it safe
                    $digitsPhoneEscaped = $digitsPhone !== '' ? str_replace('"', '""', $digitsPhone) : '';
                    $digitsAltEscaped = $digitsAlt !== '' ? str_replace('"', '""', $digitsAlt) : '';

                    // Output plain digits-only for CSV (no leading apostrophe or ="...")
                    // This will make Excel display the numeric string and show the same value when
                    // double-clicking the cell. Note: very long numbers (>15 digits) may still be
                    // converted to scientific notation by Excel if opened directly — use XLSX or the
                    // Import Wizard if that happens.
                    $csvPhone = $digitsPhoneEscaped !== '' ? $digitsPhoneEscaped : '';
                    $csvAlt = $digitsAltEscaped !== '' ? $digitsAltEscaped : '';

                    $rowData = [
                        $index + 1,
                        $lead->client->name ?? '',
                        $lead->client->email ?? '',
                        $csvPhone,
                        $csvAlt,
                        $lead->client->date_of_birth ? $formatDate($lead->client->date_of_birth) : '',
                        $lead->client->address ?? '',
                        $lead->client->country->name ?? '',
                        $lead->client->city->name ?? '',
                        $productNames,
                        $lead->representative->name ?? '',
                        $lead->number_of_passengers ?? 1,
                        $firstSegment ? $formatDate($firstSegment->from_date) : '',
                        $lastSegment ? $formatDate($lastSegment->to_date) : '',
                        $firstSegment ? $firstSegment->from_place : '',
                        $lastSegment ? $lastSegment->to_place : '',
                        $latestFollowup && $latestFollowup->next_followup_date ? $formatDate($latestFollowup->next_followup_date, 'Y-m-d H:i') : '',
                        $statusText,
                        $formatDate($lead->created_at, 'Y-m-d H:i:s'),
                        $formatDate($lead->updated_at, 'Y-m-d H:i:s'),
                        $followupNotes
                    ];

                    $rows[] = $rowData;
                }

                return new StreamedResponse(function () use ($headersCsv, $rows) {
                    $out = fopen('php://output', 'w');
                    // Write UTF-8 BOM so Excel opens file with correct encoding
                    fprintf($out, "%s", chr(0xEF) . chr(0xBB) . chr(0xBF));
                    // Write header
                    fputcsv($out, $headersCsv);
                    foreach ($rows as $r) {
                        fputcsv($out, $r);
                    }
                    fclose($out);
                }, 200, [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                    'Content-Disposition' => 'attachment; filename="' . $csvFilename . '"'
                ]);
            }

            // Generate filename for XLSX
            $filename = 'leads_export_' . date('Y_m_d_H_i_s') . '.xlsx';

            // Create response for XLSX
            return new StreamedResponse(function () use ($spreadsheet) {
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            }, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'max-age=0',
            ]);
        } catch (\Exception $e) {
            Log::error('Leads export failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to export leads: ' . $e->getMessage());
        }
    }

    /**
     * Get service and extra service pricing data for JavaScript calculations
     */
    // public function getServicePricing()
    // {
    //     try {
    //         $services = Service::with('extraServices')->where('status', 1)->get();

    //         $servicePrices = [];
    //         $extraServicePrices = [];

    //         foreach ($services as $service) {
    //             $servicePrices[$service->id] = $service->service_amount;

    //             foreach ($service->extraServices as $extraService) {
    //                 $extraServicePrices[$extraService->id] = $extraService->extra_service_amount;
    //             }
    //         }

    //         return response()->json([
    //             'service_prices' => $servicePrices,
    //             'extra_service_prices' => $extraServicePrices
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error("Error fetching service pricing: " . $e->getMessage());
    //         return response()->json(['error' => 'Server error'], 500);
    //     }
    // }

    public function editLead(Lead $lead)
    {
        // Load the lead with necessary relationships
        $lead->load(['rideSegments', 'representative', 'client']);

        // Get the client for this lead
        $client = $lead->client;

        $services = Service::where('status', 1)->get();
        $products = Product::where('status', 1)->get();

        // Get staff based on logged-in user hierarchy
        $staff = $this->getUsersInHierarchy();

        $countries = Country::where('status', 1)->get();

        // Get cities for the client's country
        $cities = [];
        if ($client->country_id) {
            $cities = City::where('country_id', $client->country_id)
                ->where('status', 1)
                ->get();
        }

        // Get the latest followup for this specific lead
        $followups = $lead->leadFollowups()->latest('next_followup_date')->first();

        // Prepare trips for the edit view
        $trips = [];
        // Prefer old input if available on validation redirect
        if (old('trips')) {
            $trips = old('trips');
        } elseif ($lead->rideSegments && $lead->rideSegments->count() > 0) {
            // Convert collection of rideSegments to array of arrays
            $trips = $lead->rideSegments->map(function ($seg) {
                return [
                    'id' => $seg->id ?? '',
                    'from_date' => $seg->from_date ?? '',
                    'to_date' => $seg->to_date ?? '',
                    'from_place' => $seg->from_place ?? '',
                    'to_place' => $seg->to_place ?? '',
                ];
            })->toArray();
        } else {
            // No existing rideSegments - check if the lead includes Call Not Connected service
            $isCallNotConnected = false;
            try {
                if (!empty($lead->service_ids)) {
                    $serviceIds = is_string($lead->service_ids) ? json_decode($lead->service_ids, true) : $lead->service_ids;
                    if (is_array($serviceIds) && count($serviceIds) > 0) {
                        $isCallNotConnected = Service::whereIn('id', $serviceIds)
                            ->whereRaw('LOWER(service) = ?', ['call not connected'])
                            ->exists();
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Error detecting Call Not Connected in editLead: ' . $e->getMessage());
            }

            if ($isCallNotConnected) {
                $trips = [[
                    'id' => '',
                    'from_date' => '',
                    'to_date' => '',
                    'from_place' => '',
                    'to_place' => '',
                ]];
            } else {
                // Default single empty row to preserve UI when there is no data
                $trips = [[
                    'id' => '',
                    'from_date' => '',
                    'to_date' => '',
                    'from_place' => '',
                    'to_place' => '',
                ]];
            }
        }

        // Pass the lead as latestLead to maintain compatibility with the edit view
        $latestLead = $lead;

        return view('admin.pages.leads.edit-lead', compact(
            'client',
            'products',
            'services',
            'staff',
            'followups',
            'countries',
            'cities',
            'latestLead',
            'trips'
        ));
    }

    public function updateLead(Request $request, Lead $lead)
    {
        // Get the client associated with this lead
        $client = $lead->client;

        // Detect Call Not Connected similarly to store()
        $isCallNotConnected = false;
        try {
            // product selection should come via product_ids only
            if ($request->filled('product_ids')) {
                $pids = is_array($request->product_ids) ? $request->product_ids : json_decode($request->product_ids, true);
                if (is_array($pids) && count($pids) > 0) {
                    $exists = Product::whereIn('id', $pids)
                        ->where(function ($q) {
                            $q->whereRaw('LOWER(product) LIKE ?', ['%call not connected%'])
                                ->orWhereRaw('LOWER(product) LIKE ?', ['%no requirement%']);
                        })->exists();
                    if ($exists) $isCallNotConnected = true;
                }
            }

            if (!$isCallNotConnected && $request->filled('service_ids')) {
                $serviceIds = is_array($request->service_ids) ? $request->service_ids : json_decode($request->service_ids, true);
                if (is_array($serviceIds) && count($serviceIds) > 0) {
                    $exists = Service::whereIn('id', $serviceIds)
                        ->where(function ($q) {
                            $q->whereRaw('LOWER(service) LIKE ?', ['%call not connected%'])
                                ->orWhereRaw('LOWER(service) LIKE ?', ['%no requirement%']);
                        })->exists();
                    if ($exists) $isCallNotConnected = true;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Error checking Call Not Connected flag in updateLead: ' . $e->getMessage());
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => [
                'nullable',
                'email',
                'max:255',
            ],
            'contact_number' => 'required|numeric|digits_between:5,20',
            'alternate_number' => 'nullable|numeric|digits_between:5,20',
            'contact_country_code' => 'required|string|max:5|regex:/^\+\d{1,4}$/',
            'whatsapp_country_code' => 'nullable|string|max:5|regex:/^\+\d{1,4}$/',
            'country_id' => [
                'nullable',
                Rule::exists('countries', 'id')->where(function ($query) {
                    $query->where('status', 1);
                }),
            ],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => 'nullable|string|max:100',
            'date_of_birth' => [
                'nullable',
                'date',
                'before_or_equal:today',
                function ($attribute, $value, $fail) {
                    $birthDate = \Carbon\Carbon::parse($value);
                    $age = $birthDate->diffInYears(\Carbon\Carbon::now());
                    if ($birthDate->isFuture()) {
                        $fail('Date cannot be in future');
                    }
                }
            ],

            // Travel Details Validation
            'number_of_passengers' => ($isCallNotConnected ? 'nullable|integer|min:1|max:100' : 'required|integer|min:1|max:100'),
            'occasion' => 'nullable|string|max:255',

            // Trips Validation
            'trips' => ($isCallNotConnected ? 'nullable|array' : 'required|array|min:1'),
            'trips.*.from_date' => ($isCallNotConnected ? 'nullable|date_format:Y-m-d H:i|after_or_equal:today' : 'required|date_format:Y-m-d H:i|after_or_equal:today'),
            'trips.*.to_date' => ($isCallNotConnected ? 'nullable|date_format:Y-m-d H:i|after_or_equal:trips.*.from_date' : 'required|date_format:Y-m-d H:i|after_or_equal:trips.*.from_date'),
            'trips.*.from_place' => ($isCallNotConnected ? 'nullable|string|max:255|regex:/^[A-Za-z ]+$/' : 'required|string|max:255|regex:/^[A-Za-z ]+$/'),
            'trips.*.to_place' => ($isCallNotConnected ? 'nullable|string|max:255|regex:/^[A-Za-z ]+$/' : 'required|string|max:255|regex:/^[A-Za-z ]+$/'),

            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',

            // Service Validation
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'exists:services,id',

            // Follow-up Validation
            'next_followup_date' => 'nullable|date_format:Y-m-d H:i',
            'representative_user_id' => 'required|uuid|exists:users,id',
            'requirement_description' => 'nullable|string',
            'date_of_birth.date' => 'Please enter a valid date',
            'date_of_birth.before_or_equal' => 'Date of birth cannot be in the future',

        ], [
            'trips.required' => 'At least one trip segment is required.',
            'trips.*.from_date.required' => 'The departure date is required for all trip segments.',
            'trips.*.from_date.date_format' => 'The departure date must be a valid date and time format.',
            'trips.*.to_date.required' => 'The arrival date is required for all trip segments.',
            'trips.*.to_date.date_format' => 'The arrival date must be a valid date and time format.',
            'trips.*.to_date.after_or_equal' => 'The arrival date must be after or equal to the departure date.',
            'trips.*.from_place.required' => 'The departure location is required for all trip segments.',
            'trips.*.to_place.required' => 'The arrival location is required for all trip segments.',
            'trips.*.from_place.regex' => 'From Place should contain only letters and spaces.',
            'trips.*.to_place.regex' => 'To Place should contain only letters and spaces.',
            'service_ids.required' => 'At least one service must be selected.',
            'product_ids.required' => 'At least one product must be selected.',
            'representative_user_id.required' => 'A staff representative is required.',
            'contact_number.required' => 'Primary contact number is required',
            'contact_number.numeric' => 'Primary contact must contain only numbers',
            'contact_number.digits_between' => 'Primary contact must be 5-20 digits long',
            'alternate_number.numeric' => 'Alternate contact must contain only numbers',
            'alternate_number.digits_between' => 'Alternate contact must be 5-20 digits long',
            'contact_country_code.required' => 'Primary country code is required',
            'contact_country_code.max' => 'Primary country code cannot exceed 5 characters',
            'contact_country_code.regex' => 'Primary country code must be in format +XXX (e.g. +91)',
            'whatsapp_country_code.max' => 'WhatsApp country code cannot exceed 5 characters',
            'whatsapp_country_code.regex' => 'WhatsApp country code must be in format +XXX (e.g. +91)',
            'address.regex' => 'Address must contain letters and may only include letters, numbers and spaces',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            // Update client
            $strContactNumber = $request->contact_country_code . '-' . $request->contact_number;
            $strAlternateNumber = $request->alternate_number
                ? $request->whatsapp_country_code . '-' . $request->alternate_number : null;

            $client->update([
                'name' => $request->name,
                'email' => $request->email,
                'contact_number' => $strContactNumber,
                'alternate_number' => $strAlternateNumber,
                'date_of_birth' => $request->date_of_birth,
                'city_id' => $request->city ?: null,
                'country_id' => $request->country_id ?: null,
                'address' => $request->address,
                'description' => $request->description,
                'status' => $request->status ?? 1,
            ]);

            // Update the lead (enquiry)
            $leadProductIds = null;
            if ($request->filled('product_ids')) {
                $leadProductIds = is_array($request->product_ids) ? $request->product_ids : json_decode($request->product_ids, true);
                $leadProductIds = is_array($leadProductIds) ? array_values($leadProductIds) : null;
            }

            // capture old representative for audit
            $oldRep = $lead->representative_user_id;

            $lead->fill([
                'representative_user_id' => $request->representative_user_id,
                'product_ids' => !empty($leadProductIds) ? json_encode($leadProductIds) : null,
                'service_ids' => json_encode($request->service_ids),
                'number_of_passengers' => $request->number_of_passengers,
                'description' => $request->requirement_description,
                'occasion' => $request->occasion,
            ])->save();

            // If staff representative changed, record an audit trail
            try {
                $newRep = $lead->representative_user_id;
                if ($oldRep !== $newRep) {
                    LeadAuditTrail::create([
                        'id' => \Illuminate\Support\Str::uuid(),
                        'lead_id' => $lead->id,
                        'field_name' => 'representative_user_id',
                        'old_value' => $oldRep,
                        'new_value' => $newRep,
                        'changed_by' => auth()->id(),
                        'created_at' => now(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to write lead audit trail: ' . $e->getMessage());
            }

            // Sync requirement description to first followup
            if (trim((string)($request->requirement_description ?? '')) !== '') {
                try {
                    $firstFollowup = LeadFollowUp::where('lead_id', $lead->id)
                        ->orderBy('created_at', 'asc')
                        ->first();

                    if ($firstFollowup) {
                        $firstFollowup->update([
                            'followup_note' => $request->requirement_description,
                        ]);
                    } else {
                        Log::info('No existing LeadFollowUp found for lead ' . $lead->id . ' — skipping followup note sync as creation is disabled.');
                    }
                } catch (\Exception $e) {
                    Log::error('Error syncing call note to first followup: ' . $e->getMessage());
                }
            }

            // Sync trip segments
            $existingTripIds = $lead->rideSegments->pluck('id')->toArray();
            $updatedTripIds = [];

            // If trips are missing or empty and Call Not Connected, remove all existing trip segments
            if (empty($request->trips) || !is_array($request->trips)) {
                if (!empty($existingTripIds)) {
                    LeadRide::whereIn('id', $existingTripIds)->delete();
                }
            } else {
                foreach ($request->trips as $tripData) {
                    // Skip entirely empty rows
                    if (empty(array_filter($tripData))) continue;

                    // If no ID is provided or it's empty, create a new segment
                    if (empty($tripData['id'])) {
                        $tripId = Str::uuid();
                        $tripSegment = new LeadRide([
                            'id' => $tripId,
                            'lead_id' => $lead->id,
                            'from_date' => $tripData['from_date'] ?? null,
                            'to_date' => $tripData['to_date'] ?? null,
                            'from_place' => $tripData['from_place'] ?? null,
                            'to_place' => $tripData['to_place'] ?? null,
                        ]);
                        $tripSegment->save();
                    } else {
                        // Update existing segment
                        $tripSegment = LeadRide::updateOrCreate(
                            ['id' => $tripData['id']],
                            [
                                'lead_id' => $lead->id,
                                'from_date' => $tripData['from_date'] ?? null,
                                'to_date' => $tripData['to_date'] ?? null,
                                'from_place' => $tripData['from_place'] ?? null,
                                'to_place' => $tripData['to_place'] ?? null,
                            ]
                        );
                    }

                    $updatedTripIds[] = $tripSegment->id;
                }

                // Delete removed trip segments
                $tripsToDelete = array_diff($existingTripIds, $updatedTripIds);
                if (!empty($tripsToDelete)) {
                    LeadRide::whereIn('id', $tripsToDelete)->delete();
                }
            }

            // Persist next follow-up date if provided in the form
            $nextFollowupInput = $request->input('next_followup_date', $request->input('next_follow_up', null));
            if (!empty($nextFollowupInput)) {
                $normalized = null;
                try {
                    if (strpos($nextFollowupInput, 'T') !== false) {
                        $dt = Carbon::createFromFormat('Y-m-d\TH:i', $nextFollowupInput);
                    } else {
                        $dt = Carbon::createFromFormat('Y-m-d H:i', $nextFollowupInput);
                    }
                    $normalized = $dt->format('Y-m-d H:i');
                } catch (\Exception $e) {
                    try {
                        $dt = Carbon::parse($nextFollowupInput);
                        $normalized = $dt->format('Y-m-d H:i');
                    } catch (\Exception $e2) {
                        Log::warning('Unable to parse next_followup input: ' . $nextFollowupInput);
                        $normalized = $nextFollowupInput;
                    }
                }

                try {
                    $existingFollowup = LeadFollowUp::where('lead_id', $lead->id)->latest()->first();
                    if ($existingFollowup) {
                        $existingFollowup->update(['next_followup_date' => $normalized]);
                    } else {
                        Log::info('No existing LeadFollowUp found for lead ' . $lead->id . ' — skipping create as requested.');
                    }
                } catch (\Exception $e) {
                    Log::error('Error updating lead followup from edit: ' . $e->getMessage());
                }
            }

            // Persist status if provided in the form
            // $statusInput = $request->input('status', null);
            // if (!is_null($statusInput)) {
            //     try {
            //         $existingFollowup = LeadFollowUp::where('lead_id', $lead->id)->latest()->first();
            //         if ($existingFollowup) {
            //             $existingFollowup->update(['status' => (int) $statusInput]);
            //         } else {
            //             Log::info('No existing LeadFollowUp found for lead ' . $lead->id . ' — skipping status update.');
            //         }
            //     } catch (\Exception $e) {
            //         Log::error('Error updating lead followup status from edit: ' . $e->getMessage());
            //     }
            // }

            DB::commit();
            return redirect()->route('admin.leads.edit', $lead)->with('success', 'Lead updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Error updating lead: ' . $e->getMessage());
        }
    }

    public function viewLead(Lead $lead)
    {
        // Load the lead with necessary relationships
        $lead->load(['rideSegments', 'representative', 'client', 'leadFollowups.followedBy']);

        // Get the client for this lead
        $client = $lead->client;

        // Get services associated with this lead
        $serviceIds = json_decode($lead->service_ids, true) ?? [];
        $lead->services = Service::whereIn('id', $serviceIds)->pluck('service')->toArray();

        // Prepare ride_dates for views that expect a simple from/to map
        try {
            if ($lead->rideSegments && $lead->rideSegments->count() > 0) {
                $first = $lead->rideSegments->first();
                $last = $lead->rideSegments->last();
                $lead->ride_dates = [
                    'from_date' => $first->from_date ? $first->from_date->format('Y-m-d') : null,
                    'to_date' => $last->to_date ? $last->to_date->format('Y-m-d') : null,
                ];
            } else {
                $lead->ride_dates = null;
            }
        } catch (\Exception $e) {
            Log::warning('Error preparing ride_dates for lead view: ' . $e->getMessage());
            $lead->ride_dates = null;
        }

        // Provide a leads collection and set latestLead for the view which expects these variables
        $leads = collect([$lead]);
        $latestLead = $lead;

        // Get followups for this specific lead, eager-load payment audit trails so receipts are available
        $followups = LeadFollowup::with(['followedBy', 'paymentAuditTrail'])
            ->where('lead_id', $lead->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Get latest followup for this lead
        $latestFollowup = LeadFollowup::with('followedBy')
            ->where('lead_id', $lead->id)
            ->orderByDesc('created_at')
            ->first();

        // Get services and extra services with pricing information
        $selectedServices = collect();
        $selectedExtraServices = collect();
        $totalServiceAmount = 0;
        $totalExtraServiceAmount = 0;
        $totalAmount = 0;
        $isStoredAmount = false; // Flag to indicate if we're using stored amount vs calculated

        if ($latestFollowup) {
            // Check if we have a stored total amount from the followup
            if ($latestFollowup->total_amount && $latestFollowup->total_amount > 0) {
                $totalAmount = $latestFollowup->total_amount;
                $isStoredAmount = true;
            }

            // Get services from the latest followup with names
            if (!empty($latestFollowup->service_ids)) {
                $serviceIds = is_string($latestFollowup->service_ids) ? json_decode($latestFollowup->service_ids, true) : $latestFollowup->service_ids;
                $selectedServices = Service::whereIn('id', $serviceIds)->get();

                // Calculate current service amounts for display (may differ from stored amount)
                $totalServiceAmount = $selectedServices->sum('service_amount');
            }

            // Get extra services from the latest followup with names
            if (!empty($latestFollowup->extra_service_ids)) {
                $extraServiceIds = is_string($latestFollowup->extra_service_ids) ? json_decode($latestFollowup->extra_service_ids, true) : $latestFollowup->extra_service_ids;
                $selectedExtraServices = ExtraService::whereIn('id', $extraServiceIds)->get();

                // Calculate current extra service amounts for display (may differ from stored amount)
                $totalExtraServiceAmount = $selectedExtraServices->sum('extra_service_amount');
            }

            // If we don't have a stored total amount, calculate it from current amounts
            if (!$isStoredAmount) {
                $totalAmount = $totalServiceAmount + $totalExtraServiceAmount;
            }
        }

        // Get city name
        $cityName = null;
        if ($client->city_id) {
            $cityName = DB::table('cities')
                ->where('id', $client->city_id)
                ->value('name');
        }

        // Get all services for dropdown
        $services = Service::all();

        // Get staff based on logged-in user hierarchy
        $staff = $this->getUsersInHierarchy();

        // Get country name
        $country = DB::table('countries')
            ->where('id', $client->country_id)
            ->value('name');

        // Also build client payment history from PaymentAuditTrail (so receipts/files are available to the view)
        $clientPaymentHistory = [];
        try {
            $clientPaymentHistory = \App\Models\PaymentAuditTrail::with(['leadFollowup.followedBy'])
                ->whereHas('leadFollowup', function ($query) use ($lead) {
                    $query->where('lead_id', $lead->id);
                })
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($auditTrail) {
                    $followupRecord = $auditTrail->leadFollowup;

                    // If audit trail has no file, fallback to followup's file (some uploads may be stored on followup)
                    $filePath = $auditTrail->file;
                    if (empty($filePath) && $followupRecord && !empty($followupRecord->file)) {
                        $filePath = $followupRecord->file;
                    }

                    // Generate a controlled URL for the file so missing public symlinks do not break the UI.
                    $fileUrl = null;
                    if (!empty($filePath)) {
                        try {
                            $fileUrl = route('admin.followups.file', ['filename' => basename($filePath)]);
                        } catch (\Exception $e) {
                            // If URL generation fails for any reason, fall back to raw path (the view will handle it)
                            $fileUrl = $filePath;
                        }
                    }

                    return [
                        'id' => $auditTrail->id,
                        'lead_followup_id' => $auditTrail->lead_followup_id,
                        'amount' => $auditTrail->paid_amount,
                        'paid_date' => $auditTrail->paid_date,
                        'payment_method' => $auditTrail->payment_method,
                        'narration' => $auditTrail->narration,
                        'payment_status' => $auditTrail->payment_status,
                        'file' => $filePath,
                        'file_url' => $fileUrl,
                        'created_at' => $auditTrail->created_at,
                        'created_by_name' => $followupRecord && $followupRecord->followedBy ? $followupRecord->followedBy->name : null,
                    ];
                });
        } catch (\Exception $e) {
            Log::warning('Could not load client payment history for lead view: ' . $e->getMessage());
        }

        return view('admin.pages.leads.view-lead', compact(
            'client',
            'leads',
            'latestLead',
            'latestFollowup',
            'cityName',
            'services',
            'country',
            'staff',
            'selectedServices',
            'selectedExtraServices',
            'totalServiceAmount',
            'totalExtraServiceAmount',
            'totalAmount',
            'isStoredAmount',
            'followups',
            'clientPaymentHistory'
        ));
    }

    /**
     * Delete a lead and cascade-delete related data.
     */
    public function destroyLead(Lead $lead)
    {
        try {
            $lead->delete();

            // Flash a session message so a full page reload will show the blade alert
            session()->flash('success', 'Lead deleted successfully');

            return response()->json([
                'success' => true,
                'message' => 'Lead deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting lead: ' . $e->getMessage());
            // Also flash an error so a reload can show it in blade if desired
            session()->flash('error', 'Failed to delete lead: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete lead'
            ], 500);
        }
    }
    public function updateImage(Request $request, LeadFollowup $followup)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        try {
            // Prevent image update if there's any approved payment audit (1 = Approved)
            $hasApprovedAudit = \App\Models\PaymentAuditTrail::where('lead_followup_id', $followup->id)
                ->where('payment_status', 1)
                ->exists();

            if ($hasApprovedAudit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update image after payment approval.'
                ], 403);
            }
            // Delete old image if it exists
            if ($followup->file) {
                Storage::disk('public')->delete($followup->file);
            }

            // Store new image
            $imagePath = $request->file('image')->store('followups', 'public');

            // Update followup record
            $followup->update([
                'file' => $imagePath,
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Image updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating image: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Destroy a followup entry (payment-related) and record deletion audit.
     */
    public function destroyFollowup(Request $request, LeadFollowup $followup)
    {
        $user = auth()->user();
        if (! $user || ! $user->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        // Only allow deletion for payment-related statuses (3 = full, 4 = partial)
        if (! in_array((int) $followup->status, [3, 4])) {
            return response()->json(['success' => false, 'message' => 'Only payment-related followups can be deleted.'], 400);
        }

        try {
            // Capture related payment audit trail entries (including approved ones) before deletion
            try {
                $paymentAudits = \App\Models\PaymentAuditTrail::where('lead_followup_id', $followup->id)->get();
            } catch (\Throwable $e) {
                $paymentAudits = collect();
            }

            // Capture file path if exists so we can delete it and store it in audit details
            $filePath = $followup->file ?? null;

            // Build details payload for audit
            $details = [
                'followup' => $followup->toArray(),
                'payment_audits' => $paymentAudits->map(function ($p) {
                    return $p->toArray();
                })->toArray(),
                'file' => $filePath,
            ];

            // Create deletion audit record
            $audit = \App\Models\FollowupDeletionAudit::create([
                'id' => Str::uuid(),
                'followup_id' => $followup->id,
                'lead_id' => $followup->lead_id,
                'deleted_by_user_id' => $user->id,
                'created_by_user_id' => $followup->followed_by ?? null,
                'deleted_at' => now(),
                'deletion_reason' => $request->input('deletion_reason'),
                'details' => $details,
            ]);

            // Delete any payment audit trail entries (including approved ones)
            try {
                \App\Models\PaymentAuditTrail::where('lead_followup_id', $followup->id)->delete();
            } catch (\Throwable $e) {
                // ignore deletion errors; we still proceed to delete followup and record audit
            }

            // Delete stored file if exists
            try {
                if ($filePath) {
                    \Storage::disk('public')->delete($filePath);
                }
            } catch (\Throwable $e) {
                // ignore storage deletion errors
            }

            // Finally delete the followup record (this will also attempt cascaded deletes)
            $followup->delete();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => 'Followup deleted and audit recorded.']);
            }

            session()->flash('success', 'Followup deleted and audit recorded.');
            return redirect()->back();
        } catch (\Exception $e) {
            \Log::error('Error deleting followup: ' . $e->getMessage());
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Failed to delete followup.'], 500);
            }

            session()->flash('error', 'Failed to delete followup: ' . $e->getMessage());
            return redirect()->back();
        }
    }

    public function fetchServices($productIdCsv = null)
    {
        try {
            // Accept either path param CSV or query param 'productIds'
            $raw = $productIdCsv;
            if (empty($raw) && request()->has('productIds')) {
                $raw = request()->query('productIds');
            }

            $ids = [];
            if (is_string($raw)) {
                $ids = array_filter(array_map('trim', explode(',', $raw)));
            } elseif (is_array($raw)) {
                $ids = $raw;
            }

            if (empty($ids)) {
                return response()->json([]);
            }

            // Find services that have any of these product ids in their product_ids JSON array
            $query = Service::query();
            $query->where(function ($q) use ($ids) {
                foreach ($ids as $id) {
                    $q->orWhereJsonContains('product_ids', $id);
                }
            });

            $services = $query->get();
            return response()->json($services);
        } catch (\Exception $e) {
            Log::error('Error in fetchServices: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }

    public function getDnpLeads(Request $request)
    {
        $currentUser = auth()->user();
        $representatives = getRepresentativeIds($currentUser);
        $service = Service::where('service', 'Call Not Connected')->first();

        if ($service) {
            $query = Lead::with(['client', 'representative'])
                ->whereRaw("
                replace(trim(both '\"' from service_ids::text), '\\', '') LIKE ?
                ", ['%' . $service->id . '%']);

            if ($request->filled('from_date')) {
                $fromDate = Carbon::parse($request->from_date)->startOfDay();
                $query->whereHas('rideSegments', function ($q) use ($fromDate) {
                    $q->where('from_date', '>=', $fromDate);
                });
            }
            if ($request->filled('to_date')) {
                $toDate = Carbon::parse($request->to_date)->endOfDay();
                $query->whereHas('rideSegments', function ($q) use ($toDate) {
                    $q->where('to_date', '<=', $toDate);
                });
            }
            if ($request->filled('name')) {
                $query->whereHas('client', function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->name . '%');
                });
            }

            if ($request->filled('email')) {
                $query->whereHas('client', function ($q) use ($request) {
                    $q->where('email', 'like', '%' . $request->email . '%');
                });
            }

            if ($request->filled('phone')) {
                $query->whereHas('client', function ($q) use ($request) {
                    $q->where('contact_number', 'like', '%' . $request->phone . '%');
                });
            }

            // If the user selected a specific staff representative, apply that filter
            if ($request->filled('representative_user_id')) {
                $repId = $request->input('representative_user_id');
                // Ensure the selected rep is allowed within the current user's hierarchy when applicable
                if (is_array($representatives) && !empty($representatives)) {
                    if (in_array($repId, $representatives)) {
                        $query->where('representative_user_id', $repId);
                    } else {
                        // Selected rep is outside user's allowed scope - return empty
                        $dnpLeads = collect();
                        $services = Service::all()->where('status', 1);
                        $staff = $this->getUsersInHierarchy();

                        return view('admin.pages.leads.dnp-leads', compact('dnpLeads', 'services', 'staff'));
                    }
                } else {
                    // No representative scope, just filter by selection
                    $query->where('representative_user_id', $repId);
                }
            } else {
                if ($representatives) {
                    $query->whereIn('representative_user_id', $representatives);
                }
            }

            $dnpLeads = $query->get();
        } else {
            $dnpLeads = collect();
        }

        $services = Service::all()->where('status', 1);
        $staff = $this->getUsersInHierarchy();

        return view('admin.pages.leads.dnp-leads', compact('dnpLeads', 'services', 'staff'));
    }

    /**
     * Check user available points in Airpoints system (AJAX)
     */
    public function checkUserPoints(Request $request)
    {
        try {
            $clientId = $request->input('client_id');

            if (!$clientId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client ID is required'
                ], 400);
            }

            $client = Client::find($clientId);

            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found'
                ], 404);
            }

            $airpointsService = app(AirpointsIntegrationService::class);
            $result = $airpointsService->getUserPoints($client);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'points' => $result['points'] ?? 0,
                    'customer_id' => $result['customer_id'] ?? null,
                    'message' => 'Points fetched successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to fetch points',
                    'points' => 0
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error checking user points', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error checking user points: ' . $e->getMessage(),
                'points' => 0
            ], 500);
        }
    }
}
