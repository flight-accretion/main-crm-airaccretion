<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\State;
use Illuminate\Http\Request;
use App\Models\Vendor;
use Illuminate\Validation\Rule;
use App\Models\Country;
use App\Models\Product;
use App\Models\Service;
use App\Models\ExtraService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $query = Vendor::with(['city.state.country']);
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        // Search filters
        if ($request->filled('vendor_name')) {
            $query->where('name', 'like', '%' . $request->input('vendor_name') . '%');
        }

        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . $request->input('email') . '%');
        }

        if ($request->filled('status') && in_array($request->input('status'), [0, 1])) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('product_id')) {
            $query->whereJsonContains('product_ids', $request->input('product_id'));
        }

        if ($request->filled('service_id')) {
            $query->whereJsonContains('service_ids', $request->input('service_id'));
        }

        $vendors = $query->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->appends($request->query());
        $countries = Country::all();
        $allProducts = Product::where('status', 1)
            ->whereRaw('LOWER(product) != ?', ['call not connected'])
            ->get();
        $allServices = Service::where('status', 1)
            ->whereRaw('LOWER(service) != ?', ['call not connected'])
            ->get();
        $allExtraServices = ExtraService::where('status', 1)
            ->select('extra_service', 'id', 'description', 'extra_service_amount')
            // ->distinct('extra_service_amount')
            ->get();

        return view('admin.pages.vendors.index-vendor', compact('vendors', 'countries', 'allProducts', 'allServices', 'allExtraServices'));
    }

    // public function create()
    // {
    //     $countries = Country::all();


    //     $states = collect();
    //     $cities = collect();
    //     $allProducts = Product::where('status', 1)->get();
    //     $allServices = Service::where('status', 1)->get();
    //     $allExtraServices = ExtraService::where('status', 1)
    //         ->select('extra_service', 'id', 'description', 'extra_service_amount')
    //         //->distinct('extra_service_amount')
    //         ->get();
    //     if (old('country_id')) {
    //         $states = State::where('country_id', old('country_id'))
    //             ->where('status', 1)
    //             ->get();
    //     }

    //     if (old('state_id')) {
    //         $cities = City::where('state_id', old('state_id'))
    //             ->where('status', 1)
    //             ->get();
    //     }

    //     return view('admin.pages.vendors.add-vendor', compact('countries', 'states', 'cities', 'allProducts', 'allServices', 'allExtraServices'));
    // }

    public function store(Request $request)
    {
         $validator = Validator::make($request->all(), [
        // Name: alphabetic characters (unicode letters) and spaces only
        'name' => [
            'required',
            'string',
            'max:100',
            'regex:/^[\p{L} ]+$/u'
        ],
        // Email: nullable but must be a valid email and include a domain with a dot
        'email' => [
            'nullable',
            'email',
            'max:100',
            'regex:/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/'
        ],
        'country_code' => 'required',
        'contact_number' => [
            'unique:vendors,contact_number',
            'required',
            'numeric',
            'digits_between:5,20',
            function ($attribute, $value, $fail) use ($request) {
                if ($value < 0) {
                    $fail('The contact number cannot be negative.');
                }
                if (!preg_match('/^[0-9]+$/', $value)) {
                    $fail('The contact number must contain only numbers.');
                }
                $fullNumber = $request->country_code . '-' . $value;
                if (Vendor::where('contact_number', $fullNumber)->exists()) {
                    $fail('This contact number already exists in our system.');
                }
            },
        ],
        'country_id' => [
            'nullable',
            'string',
            function ($attribute, $value, $fail) {
                if (!Str::isUuid($value)) {
                    $fail('The '.$attribute.' must be a valid UUID.');
                }
            },
            Rule::exists('countries', 'id')
        ],
        'state_id' => [
            'nullable',
            'string',
            function ($attribute, $value, $fail) {
                if (!Str::isUuid($value)) {
                    $fail('The '.$attribute.' must be a valid UUID.');
                }
            },
            Rule::exists('states', 'id')
        ],
        'city_id' => [
            'nullable',
            'string',
            function ($attribute, $value, $fail) {
                if (!Str::isUuid($value)) {
                    $fail('The '.$attribute.' must be a valid UUID.');
                }
            },
            Rule::exists('cities', 'id')
        ],
        // Address: allow alphanumeric and common punctuation (space, comma, dot, hyphen, slash)
        'address' => [
            'nullable',
            'string',
            'max:255',
            'regex:/^[A-Za-z0-9\s,\.\-\/]+$/'
        ],
        'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
        'map_link' => 'nullable|url',
        'product_ids' => 'nullable|array',
        'product_ids.*' => 'uuid|exists:products,id',
        'service_ids' => 'nullable|array',
        'service_ids.*' => 'uuid|exists:services,id',
        'extra_service_ids' => 'nullable|array',
        'extra_service_ids.*' => 'uuid|exists:extra_services,id',
        // Bank details: allow alphanumeric and common punctuation
        'bank_details' => [
            'nullable',
            'string',
            'max:255',
            //'regex:/^[A-Za-z0-9\s,\.\-\/]+$/'
        ],
    ], [
        'name.required' => 'Vendor name is required.',
        'name.regex' => 'The name may only contain letters and spaces.',
        'name.max' => 'The name must not exceed 100 characters.',
       // 'email.required' => 'Email is required.',
        'email.email' => 'Please enter a valid email address.',
        'email.regex' => 'Please enter a valid email address with a proper domain (e.g., example@domain.com).',
       // 'email.unique' => 'This email already exists.',
        'country_code.required' => 'Country code is required.',
        'contact_number.required' => 'Contact number is required.',
        'contact_number.numeric' => 'The contact number must be numeric.',
        'contact_number.unique' => 'This contact number is already registered.',
        // 'country_id.required' => 'Country is required.',
        // 'state_id.required' => 'State is required.',
        // 'city_id.required' => 'City is required.',
        //'address.required' => 'Address is required.',
        'address.regex' => 'Address may only contain letters, numbers, spaces and , . - / characters.',
        'address.max' => 'Address must not exceed 255 characters.',
        'bank_details.regex' => 'Bank details may only contain letters, numbers, spaces and , . - / characters.',
        'bank_details.max' => 'Bank details must not exceed 255 characters.',
        'profile_image.image' => 'Profile image must be an image file.',
        'profile_image.max' => 'Profile image size should not exceed 10MB.',
        'map_link.url' => 'Map link must be a valid URL.',
    ]);

   

    if ($validator->fails()) {
        return back()->withErrors($validator)->withInput()->with('error', 'Please check the form validations and try again');
    }
  // Additional validation: at least one service or extra service should be selected
    if (empty($request->service_ids) && empty($request->extra_service_ids)) {
        return back()->withErrors(['service_validation' => 'At least one service or extra service must be selected.'])
                   ->withInput()
                   ->with('error', 'At least one service or extra service must be selected.');
    }
        DB::beginTransaction();

        try {
            // Handle file upload
            $profileImagePath = null;
            if ($request->hasFile('profile_image')) {
                $profileImagePath = $request->file('profile_image')->store('vendor_images', 'public');
            } 
            $fullContactNumber = ($request->country_code) . '-' . ($request->contact_number);
            // Create vendor
            $vendor = Vendor::create([
                'id' => Str::uuid(),
                'product_ids' => $request->product_ids ?? [],
                'service_ids' => $request->service_ids ?? [],
                'extra_service_ids' => $request->extra_service_ids ?? [],
                'name' => $request->name,
                'email' => $request->email,
                'contact_number' => $fullContactNumber,
                'country_id' => $request->country_id,
                'state_id' => $request->state_id,
                'city_id' => $request->city_id,
                'address' => $request->address,
                'bank_details' => $request->bank_details,
                'profile_image' => $profileImagePath,
                'map_link' => $request->map_link,
                'status' => $request->status ?? 1,
            ]);

            DB::commit();

            return redirect()->route('admin.vendors.index')->with('success', 'Vendor added successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Error creating vendor: ' . $e->getMessage());
        }
    }

    public function getStatesByCountry($countryId)
    {
        try {
            // Confirm UUID validation
            if (!Str::isUuid($countryId)) {
                return response()->json(['error' => 'Invalid UUID'], 400);
            }

            // Check if country exists
            $countryExists = Country::where('id', $countryId)->exists();
            if (!$countryExists) {
                return response()->json(['error' => 'Country not found'], 404);
            }

            // Try fetching states
            $states = State::where('country_id', $countryId)
                ->where('status', 1)
                ->select('id', 'name')
                ->get();

            return response()->json(['states' => $states]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Exception: ' . $e->getMessage(),
                'trace' => $e->getTrace()
            ], 500);
        }
    }

    public function getCitiesByState($stateId)
    {
        try {
            if (!Str::isUuid($stateId)) {
                return response()->json(['error' => 'Invalid state ID'], 400);
            }

            $cities = City::where('state_id', $stateId)
                ->where('status', 1)
                ->select('id', 'name')
                ->get();

            return response()->json($cities);
        } catch (\Exception $e) {
            Log::error("Error fetching cities: " . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    public function edit(Vendor $vendor)
    {
        // Load relationships
        $vendor->load(['city.state.country']);

        // Split the stored contact_number (e.g., "+91-88882345888")
        $contactParts = explode('-', $vendor->contact_number, 2);
        $countryCode = $contactParts[0] ?? '+91'; // Default to +91 if missing
        $contactNumber = $contactParts[1] ?? $vendor->contact_number; // Fallback to full number

        // Get all products and services (exclude 'Call Not Connected' for vendor UI)
        $allProducts = Product::where('status', 1)
            ->whereRaw('LOWER(product) != ?', ['call not connected'])
            ->get();
        $allServices = Service::where('status', 1)
            ->whereRaw('LOWER(service) != ?', ['call not connected'])
            ->get();
        $allExtraServices = ExtraService::where('status', 1)
            ->select('extra_service', 'id', 'description', 'extra_service_amount')
            // ->distinct('extra_service_amount')
            ->get();

        // Pass the split values to the view
        // Safely determine country and state ids to avoid null property access when
        // a vendor doesn't have city/state related models populated.
        $countryId = optional(optional($vendor->city)->state)->country_id ?? $vendor->country_id ?? null;
        $stateId = optional($vendor->city)->state_id ?? $vendor->state_id ?? null;

        $states = collect();
        $cities = collect();

        if ($countryId) {
            $states = State::where('country_id', $countryId)
                ->where('status', 1)
                ->select('id', 'name')
                ->get();
        }

        if ($stateId) {
            $cities = City::where('state_id', $stateId)
                ->where('status', 1)
                ->select('id', 'name')
                ->get();
        }

        return response()->json([
            'success' => true,
            'vendor' => $vendor,
            'countries' => Country::all(),
            'states' => $states,
            'cities' => $cities,
            'country_code' => $countryCode, // Extracted country code (e.g., "+91")
            'contact_number' => $contactNumber, // Pure number without country code (e.g., "88882345888")
            'allProducts' => $allProducts,
            'allServices' => $allServices,
            'allExtraServices' => $allExtraServices,
        ]);
    }

    // public function update(Request $request, Vendor $vendor)
    // {
    //     $validator = Validator::make($request->all(), [
    //     // Name: alphabetic characters (unicode letters) and spaces only
    //     'name' => [
    //         'required',
    //         'string',
    //         'max:100',
    //         'regex:/^[\p{L} ]+$/u'
    //     ],
    //     // Email: nullable but must be a valid email and include a domain with a dot
    //     'email' => [
    //         'nullable',
    //         'email',
    //         'max:100',
    //         'regex:/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/'
    //        // Rule::unique('vendors', 'email')->ignore($vendor->id),
    //     ],
    //     'country_code' => 'required|numeric',
    //     'contact_number' => [
    //         Rule::unique('vendors', 'contact_number')->ignore($vendor->id),
    //         'required',
    //         'numeric',
    //         function ($attribute, $value, $fail) {
    //             if ($value < 0) {
    //                 $fail('The contact number cannot be negative.');
    //             }
    //             if (!preg_match('/^[0-9]+$/', $value)) {
    //                 $fail('The contact number must contain only numbers.');
    //             }
    //         },
    //     ],
    //     'country_id' => [
    //         'nullable',
    //         'string',
    //         function ($attribute, $value, $fail) {
    //             if (!Str::isUuid($value)) {
    //                 $fail('The '.$attribute.' must be a valid UUID.');
    //             }
    //         },
    //         Rule::exists('countries', 'id')
    //     ],
    //     'state_id' => [
    //         'nullable',
    //         'string',
    //         function ($attribute, $value, $fail) {
    //             if (!Str::isUuid($value)) {
    //                 $fail('The '.$attribute.' must be a valid UUID.');
    //             }
    //         },
    //         Rule::exists('states', 'id')
    //     ],
    //     'city_id' => [
    //         'nullable',
    //         'string',
    //         function ($attribute, $value, $fail) {
    //             if (!Str::isUuid($value)) {
    //                 $fail('The '.$attribute.' must be a valid UUID.');
    //             }
    //         },
    //         Rule::exists('cities', 'id')
    //     ],
    //     // Address: allow alphanumeric and common punctuation (space, comma, dot, hyphen, slash)
    //     'address' => [
    //         'nullable',
    //         'string',
    //         'max:255',
    //         'regex:/^[A-Za-z0-9\s,\.\-\/]+$/'
    //     ],
    //     'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
    //     'map_link' => 'nullable|url',
    //     'product_ids' => 'nullable|array',
    //     'product_ids.*' => 'uuid|exists:products,id',
    //     'service_ids' => 'nullable|array',
    //     'service_ids.*' => 'uuid|exists:services,id',
    //     'extra_service_ids' => 'nullable|array',
    //     'extra_service_ids.*' => 'uuid|exists:extra_services,id',
    //     // Bank details: allow alphanumeric and common punctuation
    //     'bank_details' => [
    //         'nullable',
    //         'string',
    //         'max:255',
    //         //'regex:/^[A-Za-z0-9\s,\.\-\/]+$/'
    //     ],
    // ], [
    // 'name.required' => 'Vendor name is required.',
    // 'name.regex' => 'The name may only contain letters and spaces.',
    // 'name.max' => 'The name must not exceed 100 characters.',
    //    // 'email.required' => 'Email is required.',
    // 'email.email' => 'Please enter a valid email address.',
    // 'email.regex' => 'Please enter a valid email address with a proper domain (e.g., example@domain.com).',
    //    // 'email.unique' => 'This email already exists.',
    // 'country_code.required' => 'Country code is required.',
    // 'contact_number.required' => 'Contact number is required.',
    // 'contact_number.numeric' => 'The contact number must be numeric.',
    // 'contact_number.unique' => 'This contact number is already registered.',
    // // 'country_id.required' => 'Country is required.',
    // // 'state_id.required' => 'State is required.',
    // // 'city_id.required' => 'City is required.',
    // //'address.required' => 'Address is required.',
    // 'address.regex' => 'Address may only contain letters, numbers, spaces and , . - / characters.',
    // 'address.max' => 'Address must not exceed 255 characters.',
    // 'bank_details.regex' => 'Bank details may only contain letters, numbers, spaces and , . - / characters.',
    // 'bank_details.max' => 'Bank details must not exceed 255 characters.',
    // 'profile_image.image' => 'Profile image must be an image file.',
    // 'profile_image.max' => 'Profile image size should not exceed 2MB.',
    // 'map_link.url' => 'Map link must be a valid URL.',
    // ]);

    //     // Additional validation: at least one service or extra service should be selected
    //     if (empty($request->service_ids) && empty($request->extra_service_ids)) {
    //         return back()->withErrors(['service_validation' => 'At least one service or extra service must be selected.'])
    //                    ->withInput()
    //                    ->with('error', 'At least one service or extra service must be selected.');
    //     }

    //     if ($validator->fails()) {
    //         return back()->withErrors($validator)->withInput()->with('error', 'Something went wrong while updating. Please check the form and try again.');
    //     }

    //     DB::beginTransaction();

    //     try {
    //         // Handle file upload
    //         $profileImagePath = $vendor->profile_image;
    //         if ($request->hasFile('profile_image')) {
    //             // Delete old image if exists
    //             if ($profileImagePath && Storage::disk('public')->exists($profileImagePath)) {
    //                 Storage::disk('public')->delete($profileImagePath);
    //             }
    //             $profileImagePath = $request->file('profile_image')->store('vendor_images', 'public');
    //         }
    //         $fullContactNumber = ($request->country_code) . '-' . ($request->contact_number);
    //         $serviceIds = $request->has('service_ids') ? $request->service_ids : [];
    //         $productIds = $request->has('product_ids') ? $request->product_ids : [];
    //         $extraServiceIds = $request->has('extra_service_ids') ? $request->extra_service_ids : [];
    //         // Update vendor
    //         $vendor->update([
    //             'name' => $request->name,
    //             'product_ids' => $productIds,
    //             'service_ids' => $serviceIds,
    //             'extra_service_ids' => $extraServiceIds,
    //             'email' => $request->email,
    //             'contact_number' => $fullContactNumber,
    //             'country_id' => $request->country_id,
    //             'state_id' => $request->state_id,
    //             'city_id' => $request->city_id,
    //             'address' => $request->address,
    //             'bank_details' => $request->bank_details,
    //             'profile_image' => $profileImagePath,
    //             'map_link' => $request->map_link,
    //             'status' => $request->status ?? $vendor->status,
    //         ]);
    //         DB::commit();

    //         return redirect()->route('admin.vendors.index')->with('success', 'Vendor updated successfully!');
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return back()->withInput()->with('error', 'Error updating vendor: ' . $e->getMessage());
    //     }
    // }

    public function update(Request $request, Vendor $vendor)
{
    $isAjax = $request->ajax() || $request->wantsJson();

    $validator = Validator::make($request->all(), [
        'name' => [
            'required',
            'string',
            'max:100',
            'regex:/^[\p{L} ]+$/u'
        ],
        'email' => [
            'nullable',
            'email',
            'max:100',
            'regex:/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/'
        ],
        'country_code' => 'required|numeric',
        'contact_number' => [
            Rule::unique('vendors', 'contact_number')->ignore($vendor->id),
            'required',
            'numeric',
            function ($attribute, $value, $fail) {
                if ($value < 0) {
                    $fail('The contact number cannot be negative.');
                }
                if (!preg_match('/^[0-9]+$/', $value)) {
                    $fail('The contact number must contain only numbers.');
                }
            },
        ],
        'country_id' => [
            'nullable',
            'string',
            function ($attribute, $value, $fail) {
                if (!Str::isUuid($value)) {
                    $fail('The '.$attribute.' must be a valid UUID.');
                }
            },
            Rule::exists('countries', 'id')
        ],
        'state_id' => [
            'nullable',
            'string',
            function ($attribute, $value, $fail) {
                if (!Str::isUuid($value)) {
                    $fail('The '.$attribute.' must be a valid UUID.');
                }
            },
            Rule::exists('states', 'id')
        ],
        'city_id' => [
            'nullable',
            'string',
            function ($attribute, $value, $fail) {
                if (!Str::isUuid($value)) {
                    $fail('The '.$attribute.' must be a valid UUID.');
                }
            },
            Rule::exists('cities', 'id')
        ],
        'address' => [
            'nullable',
            'string',
            'max:255',
            'regex:/^[A-Za-z0-9\s,\.\-\/]+$/'
        ],
        'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        'map_link' => 'nullable|url',
        'product_ids' => 'nullable|array',
        'product_ids.*' => 'uuid|exists:products,id',
        'service_ids' => 'nullable|array',
        'service_ids.*' => 'uuid|exists:services,id',
        'extra_service_ids' => 'nullable|array',
        'extra_service_ids.*' => 'uuid|exists:extra_services,id',
        'bank_details' => [
            'nullable',
            'string',
            'max:255',
            //'regex:/^[A-Za-z0-9\s,\.\-\/]+$/'
        ],
    ], [
        'name.required' => 'Vendor name is required.',
        'name.regex' => 'The name may only contain letters and spaces.',
        'name.max' => 'The name must not exceed 100 characters.',
        'email.email' => 'Please enter a valid email address.',
        'email.regex' => 'Please enter a valid email address with a proper domain (e.g., example@domain.com).',
        'country_code.required' => 'Country code is required.',
        'contact_number.required' => 'Contact number is required.',
        'contact_number.numeric' => 'The contact number must be numeric.',
        'contact_number.unique' => 'This contact number is already registered.',
        'address.regex' => 'Address may only contain letters, numbers, spaces and , . - / characters.',
        'address.max' => 'Address must not exceed 255 characters.',
        'bank_details.regex' => 'Bank details may only contain letters, numbers, spaces and , . - / characters.',
        'bank_details.max' => 'Bank details must not exceed 255 characters.',
        'profile_image.image' => 'Profile image must be an image file.',
        'profile_image.max' => 'Profile image size should not exceed 2MB.',
        'map_link.url' => 'Map link must be a valid URL.',
    ]);

    // Additional validation: at least one service or extra service should be selected
    if (empty($request->service_ids) && empty($request->extra_service_ids)) {
        $errors = ['service_validation' => 'At least one service or extra service must be selected.'];
        if ($isAjax) {
            return response()->json(['success' => false, 'errors' => $errors], 422);
        }
        return back()->withErrors($errors)->withInput()
                     ->with('error', 'At least one service or extra service must be selected.');
    }

    if ($validator->fails()) {
        if ($isAjax) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        return back()->withErrors($validator)->withInput()
                     ->with('error', 'Something went wrong while updating. Please check the form and try again.');
    }

    DB::beginTransaction();

    try {
        // Handle file upload
        $profileImagePath = $vendor->profile_image;
        if ($request->hasFile('profile_image')) {
            // Delete old image if exists
            if ($profileImagePath && Storage::disk('public')->exists($profileImagePath)) {
                Storage::disk('public')->delete($profileImagePath);
            }
            $profileImagePath = $request->file('profile_image')->store('vendor_images', 'public');
        }

        $fullContactNumber = ($request->country_code) . '-' . ($request->contact_number);
        $serviceIds = $request->has('service_ids') ? $request->service_ids : [];
        $productIds = $request->has('product_ids') ? $request->product_ids : [];
        $extraServiceIds = $request->has('extra_service_ids') ? $request->extra_service_ids : [];

        // Update vendor
        $vendor->update([
            'name' => $request->name,
            'product_ids' => $productIds,
            'service_ids' => $serviceIds,
            'extra_service_ids' => $extraServiceIds,
            'email' => $request->email,
            'contact_number' => $fullContactNumber,
            'country_id' => $request->country_id,
            'state_id' => $request->state_id,
            'city_id' => $request->city_id,
            'address' => $request->address,
            'bank_details' => $request->bank_details,
            'profile_image' => $profileImagePath,
            'map_link' => $request->map_link,
            'status' => $request->status ?? $vendor->status,
        ]);

        DB::commit();

        if ($isAjax) {
            return response()->json(['success' => true, 'message' => 'Vendor updated successfully!']);
        }
        return redirect()->route('admin.vendors.index')->with('success', 'Vendor updated successfully!');

    } catch (\Exception $e) {
        DB::rollBack();
        if ($isAjax) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => 'Error updating vendor: ' . $e->getMessage()]
            ], 500);
        }
        return back()->withInput()->with('error', 'Error updating vendor: ' . $e->getMessage());
    }
}

    // public function view(Vendor $vendor)
    // {
    //     // Load relationships
    //     $vendor->load(['city.state.country']);

    //     // Split the stored contact_number (e.g., "+91-88882345888")
    //     $contactParts = explode('-', $vendor->contact_number, 2);
    //     $countryCode = $contactParts[0] ?? '+91';
    //     $contactNumber = $contactParts[1] ?? $vendor->contact_number;

    //     // Get related products and services using helper methods
    //     $relatedProducts = $this->getVendorProducts($vendor);
    //     $relatedServices = $this->getVendorServices($vendor);

    //     return view('admin.pages.vendors.view-vendor', [
    //         'vendor' => $vendor,
    //         'country_code' => $countryCode,
    //         'contact_number' => $contactNumber,
    //         'relatedProducts' => $relatedProducts,
    //         'relatedServices' => $relatedServices,
    //     ]);
    // }

    public function viewModal(Vendor $vendor)
    {
        // Load relationships
        $vendor->load(['city.state.country']);

        // Split the stored contact_number (e.g., "+91-88882345888")
        $contactParts = explode('-', $vendor->contact_number, 2);
        $countryCode = $contactParts[0] ?? '+91';
        $contactNumber = $contactParts[1] ?? $vendor->contact_number;

        // Get related products and services using helper methods
        $relatedProducts = $this->getVendorProducts($vendor);
        $relatedServices = $this->getVendorServices($vendor);
        $relatedExtraServices = $this->getVendorExtraServices($vendor);

        return response()->json([
            'success' => true,
            'vendor' => $vendor,
            'country_code' => $countryCode,
            'contact_number' => $contactNumber,
            'relatedProducts' => $relatedProducts,
            'relatedServices' => $relatedServices,
            'relatedExtraServices' => $relatedExtraServices,
        ]);
    }

    private function getVendorProducts(Vendor $vendor)
    {
        $productIds = $vendor->product_ids;
        
        // Handle different cases of product_ids
        if (is_null($productIds)) {
            return collect();
        }
        
        if (is_string($productIds)) {
            $productIds = json_decode($productIds, true);
        }
        
        if (!is_array($productIds) || empty($productIds)) {
            return collect();
        }
        
        return Product::whereIn('id', $productIds)->get();
    }

    private function getVendorServices(Vendor $vendor)
    {
        $serviceIds = $vendor->service_ids;
        
        // Handle different cases of service_ids
        if (is_null($serviceIds)) {
            return collect();
        }
        
        if (is_string($serviceIds)) {
            $serviceIds = json_decode($serviceIds, true);
        }
        
        if (!is_array($serviceIds) || empty($serviceIds)) {
            return collect();
        }
        
        return Service::whereIn('id', $serviceIds)->get();
    }

    private function getVendorExtraServices(Vendor $vendor)
    {
        $extraServiceIds = $vendor->extra_service_ids;
        
        // Handle different cases of extra_service_ids
        if (is_null($extraServiceIds)) {
            return collect();
        }
        
        if (is_string($extraServiceIds)) {
            $extraServiceIds = json_decode($extraServiceIds, true);
        }
        
        if (!is_array($extraServiceIds) || empty($extraServiceIds)) {
            return collect();
        }
        
        return ExtraService::whereIn('id', $extraServiceIds)->get();
    }

    public function destroy(Vendor $vendor)
    {
        try {
            $vendor->delete();

            return response()->json([
                'success' => true,
                'message' => 'Vendor deleted successfully',
                'alert' => [
                    'type' => 'success',
                    'message' => 'Vendor deleted successfully'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete vendor',
                'alert' => [
                    'type' => 'error',
                    'message' => 'Failed to delete vendor'
                ]
            ], 500);
        }
    }

    public function toggleStatus(Vendor $vendor)
    {
        try {
            $newStatus = $vendor->status == 1 ? 0 : 1;
            $vendor->update(['status' => $newStatus]);

            return redirect()->route('admin.vendors.index')
                ->with('success', 'Vendor status changed to ' . ($newStatus ? 'Active' : 'Inactive'));
        } catch (\Exception $e) {
            return redirect()->route('admin.vendors.index')
                ->with('error', 'Failed to update vendor status: ' . $e->getMessage());
        }
    }


    public function getServiceProducts(Request $request)
    {
        try {
            $serviceIds = $request->input('service_ids', []);
            if (empty($serviceIds)) {
                return response()->json([
                    'products' => [],
                    'selected_product_ids' => []
                ]);
            }

            $productIds = [];
            $products = [];

            foreach ($serviceIds as $serviceId) {
                $service = Service::find($serviceId);
                
                if (!$service) {
                    Log::warning("Service not found: $serviceId");
                    continue;
                }
                
                // Get product IDs from service's product_ids field
                if (!empty($service->product_ids)) {
                    $serviceProductIds = is_string($service->product_ids) ? 
                        json_decode($service->product_ids, true) : $service->product_ids;
                    
                    if (is_array($serviceProductIds)) {
                        foreach ($serviceProductIds as $productId) {
                            if (!in_array($productId, $productIds)) {
                                $product = Product::find($productId);
                                if ($product) {
                                    $productIds[] = $product->id;
                                    $products[] = [
                                        'id' => $product->id,
                                        'product' => $product->product
                                    ];
                                }
                            }
                        }
                    }
                }
            }

            return response()->json([
                'products' => $products,
                'selected_product_ids' => $productIds
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getServiceProducts: ' . $e->getMessage());
            return response()->json([
                'error' => 'Server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

}
