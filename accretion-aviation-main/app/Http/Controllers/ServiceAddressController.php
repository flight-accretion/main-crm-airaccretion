<?php
namespace App\Http\Controllers;
use App\Models\ServiceAddress;
use App\Models\Product;
use App\Models\Service;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ServiceAddressController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $addresses = ServiceAddress::with(['product', 'service', 'city'])->latest()
            ->paginate($perPage)
            ->appends($request->query());
        $products = Product::where('status', 1)->get();
        $services = Service::where('status', 1)->get();
        $countries = Country::all();

        $states = State::where('country_id', old('country_id'))
            ->where('status', 1)
            ->get();

        $cities = City::where('state_id', old('state_id'))
            ->where('status', 1)
            ->get();
        // Explicitly pass $address as null so the view can safely reference it (edit form uses optional($address))
        $address = null;
        return view('admin.pages.service-address.index-service-address', compact('addresses','products', 'services', 'countries', 'states', 'cities', 'address'));
    }

    public function create()
    {
        $products = Product::where('status', 1)->get();
        $services = Service::where('status', 1)->get();
        $countries = Country::all();
        $states = collect();
        $cities = collect();

        if (old('country_id')) {
            $states = State::where('country_id', old('country_id'))
                ->where('status', 1)
                ->get();
        }

        if (old('state_id')) {
            $cities = City::where('state_id', old('state_id'))
                ->where('status', 1)
                ->get();
        }

        return view('admin.pages.service-address.add-service-address', compact('products', 'services', 'countries', 'states', 'cities'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'service_id' => 'required|exists:services,id',
            'contact_person_name' => [
                'required',
                'string',
                'max:100',
                // function ($attribute, $value, $fail) use ($request) {
                //     $trimmed = trim($value);
                //     if ($trimmed === '') {
                //         $fail('Invalid name');
                //     }
                //     if (!preg_match('/^[A-Za-z\s]+$/', $trimmed)) {
                //         $fail('Name must contain only letters and spaces.');
                //     }
                //     if (preg_match('/\d/', $trimmed)) {
                //         $fail('Name must contain letters.');
                //     }
                //     if (strlen($trimmed) > 100) {
                //         $fail('Name must not exceed 100 characters.');
                //     }
                //     // Check duplicate in same city and contact number
                //     $fullNumber = $request->country_code . '-' . $request->contact_number;
                //     if (ServiceAddress::where('contact_person_name', $trimmed)
                //         ->where('city_id', $request->city_id)
                //         ->where('contact_number', $fullNumber)
                //         ->exists()) {
                //         $fail('Duplicate contact already exists');
                //     }
                // }
            ],
            'address' => 'required|string',
            'country_code' => 'required',
            'contact_number' => [
                'required',
                'numeric',
                'digits_between:6,15',
                // function ($attribute, $value, $fail) use ($request) {
                //     if ($value < 0) {
                //         $fail('The contact number cannot be negative.');
                //     }
                //     if (!preg_match('/^[0-9]+$/', $value)) {
                //         $fail('Enter numeric value');
                //     }
                //     if (preg_match('/\s/', $value)) {
                //         $fail('Contact number must not contain spaces.');
                //     }
                //     if (preg_match('/[^0-9]/', $value)) {
                //         $fail('Contact number must contain only digits.');
                //     }
                //     // $fullNumber = $request->country_code . '-' . $value;
                //     // if (ServiceAddress::where('contact_number', $fullNumber)->exists()) {
                //     //     $fail('This contact number already exists in our system.');
                //     // }
                // },
            ],
            'country_id' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (!Str::isUuid($value)) {
                        $fail('The '.$attribute.' must be a valid UUID.');
                    }
                },
                Rule::exists('countries', 'id')
            ],
            'state_id' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (!Str::isUuid($value)) {
                        $fail('The '.$attribute.' must be a valid UUID.');
                    }
                },
                Rule::exists('states', 'id')
            ],
            'city_id' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (!Str::isUuid($value)) {
                        $fail('The '.$attribute.' must be a valid UUID.');
                    }
                },
                Rule::exists('cities', 'id')
            ],
            'map_link' => 'nullable|url',
        ], [
            'product_id.exists' => 'The selected product is invalid.',
            'service_id.exists' => 'The selected service is invalid.',
            'city_id.required' => 'The city field is required.',
            'city_id.exists' => 'The selected city is invalid.',
            'contact_person_name.required' => 'The contact person name field is required.',
            'contact_person_name.max' => 'Name must not exceed 100 characters.',
            'contact_person_name.string' => 'Name must be a string.',
            'address.required' => 'The address field is required.',
            'contact_number.required' => 'The contact number field is required.',
            'contact_number.numeric' => 'Enter numeric value',
            'contact_number.digits_between' => 'Number must be between 6 and 15 digits.',
            'country_id.required' => 'The country field is required.',
            'state_id.required' => 'The state field is required.',
            'product_id.required' => 'The product field is required.',
            'service_id.required' => 'The service field is required.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator,'add')
                ->withInput()
                ->with('error', 'Please correct the errors below.');
        }

        DB::beginTransaction();

        try {
            $fullContactNumber = ($request->country_code) . '-' . ($request->contact_number);
            $address = ServiceAddress::create([
                'id' => Str::uuid(),
                'product_id' => $request->product_id,
                'service_id' => $request->service_id,
                'contact_person_name' => $request->contact_person_name,
                'address' => $request->address,
                'contact_number' => $fullContactNumber,
                'city_id' => $request->city_id,
                'map_link' => $request->map_link,
            ]);
            DB::commit();

            return redirect()->route('admin.service-addresses.index')
                ->with('success', 'ServiceAddress created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ServiceAddress creation failed: ' . $e->getMessage());
            return back()
                ->withInput()
                ->with('error', 'Failed to create ServiceAddress: ' . $e->getMessage());
        }
    }
    public function update(Request $request, $id)
    {
        $serviceAddress = ServiceAddress::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'service_id' => 'required|exists:services,id',
            'contact_person_name' => 'required|string|max:100',
            'address' => 'required|string',
            'contact_country_code' => 'nullable|string|max:5|regex:/^\+\d{1,4}$/',
            'contact_number' => 'required|numeric|digits_between:5,20',
            'country_id' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (!Str::isUuid($value)) {
                        $fail('The '.$attribute.' must be a valid UUID.');
                    }
                },
                Rule::exists('countries', 'id')
            ],
            'state_id' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (!Str::isUuid($value)) {
                        $fail('The '.$attribute.' must be a valid UUID.');
                    }
                },
                Rule::exists('states', 'id')
            ],
            'city_id' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (!Str::isUuid($value)) {
                        $fail('The '.$attribute.' must be a valid UUID.');
                    }
                },
                Rule::exists('cities', 'id')
            ],
            'map_link' => 'nullable|url',
        ], [
            'product_id.exists' => 'The selected product is invalid.',
            'service_id.exists' => 'The selected service is invalid.',
            'city_id.required' => 'The city field is required.',
            'city_id.exists' => 'The selected city is invalid.',
            'contact_person_name.required' => 'The contact person name field is required.',
            'address.required' => 'The address field is required.',
            'contact_number.required' => 'The contact number field is required.',
            'contact_number.numeric' => 'The contact number must be a valid number.',
            'country_id.required' => 'The country field is required.',
            'state_id.required' => 'The state field is required.',
            'product_id.required' => 'The product field is required.',
            'service_id.required' => 'The service field is required.',
            'contact_country_code.required' => 'Primary country code is required',
            'contact_country_code.max' => 'Primary country code cannot exceed 5 characters',
            'contact_country_code.regex' => 'Primary country code must be in format +XXX (e.g. +91)',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator,'edit')
                ->withInput()
                ->with('error', 'Please correct the errors below.');
        }

        DB::beginTransaction();

        try {
            $fullContactNumber = $request->contact_country_code . '-' . $request->contact_number;

            $serviceAddress->update([
                'product_id' => $request->product_id,
                'service_id' => $request->service_id,
                'contact_person_name' => $request->contact_person_name,
                'address' => $request->address,
                'contact_number' => $fullContactNumber,
                'city_id' => $request->city_id,
                'map_link' => $request->map_link,
            ]);

            DB::commit();

            return redirect()->route('admin.service-addresses.index')
                ->with('success', 'ServiceAddress updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ServiceAddress update failed: ' . $e->getMessage());
            return back()
                ->withInput()
                ->with('error', 'Failed to update ServiceAddress: ' . $e->getMessage());
        }
    }
public function getEditDetails($id)
{
    DB::beginTransaction();

    try {
        $serviceAddress = ServiceAddress::with(['product', 'service', 'city.state.country'])->findOrFail($id);

        // Get all active products and services (not product-related)
        $products = Product::where('status', 1)->get(['id', 'product']);
        $services = Service::where('status', 1)->get(['id', 'service', 'product_ids']);

        // Get countries
        $countries = Country::all();

        // Get states for the current country
        $states = State::where('country_id', $serviceAddress->city->state->country_id)
                    ->where('status', 1)
                    ->get();

        // Get cities for the current state
        $cities = City::where('state_id', $serviceAddress->city->state_id)
                    ->where('status', 1)
                    ->get();

        DB::commit();

        return response()->json([
            'success' => true,
            'data' => [
                'serviceAddress' => $serviceAddress,
                'products' => $products,
                'services' => $services,
                'countries' => $countries,
                'states' => $states,
                'cities' => $cities
            ]
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Failed to fetch edit details: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch address details.'
        ], 500);
    }
}

    public function show(ServiceAddress $address)
    {
        return view('admin.pages.service-address.view-service-address', compact('address'));
    }

public function toggleStatus(ServiceAddress $address)
{
    try {
        DB::enableQueryLog();

        $newStatus = $address->status ? 0 : 1;

        // Force assign and save
        $address->status = $newStatus;
        $result = $address->save();

        Log::info('DB Queries: ', DB::getQueryLog());
        Log::info('Was model saved? ' . json_encode($result));

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save new status to DB.'
            ]);
        }

        return response()->json([
            'success' => true,
            'status' => $newStatus,
            'message' => $newStatus ? 'Address activated successfully' : 'Address deactivated successfully'
        ]);
    } catch (\Exception $e) {
        Log::error('Toggle Exception: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Exception: ' . $e->getMessage()
        ], 500);
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

    public function viewModal($id)
    {
        try {
            $serviceAddress = ServiceAddress::with(['product', 'service', 'city.state.country'])->findOrFail($id);

            // Split the stored contact_number (e.g., "+91-88882345888")
            $contactParts = explode('-', $serviceAddress->contact_number, 2);
            $countryCode = $contactParts[0] ?? '+91';
            $contactNumber = $contactParts[1] ?? $serviceAddress->contact_number;

            return response()->json([
                'success' => true,
                'serviceAddress' => $serviceAddress,
                'country_code' => $countryCode,
                'contact_number' => $contactNumber,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch service address details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch service address details.'
            ], 500);
        }
    }

}
