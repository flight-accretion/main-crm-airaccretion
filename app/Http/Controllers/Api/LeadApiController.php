<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lead;
use App\Models\Client;
use App\Models\LeadRide;
use App\Models\LeadFollowup;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class LeadApiController extends Controller
{
    // GET /api/leads
    public function index(Request $request)
    {
        $query = Lead::query()->with(['client', 'representative', 'rideSegments']);

        if ($request->has('id')) {
            $query->where('id', $request->get('id'));
        }

        if ($request->has('client_id')) {
            $query->where('client_id', $request->get('client_id'));
        }

        $perPage = (int) $request->get('per_page', 50);
        $leads = $query->paginate($perPage);

        return response()->json($leads);
    }

    // POST /api/leads
    public function store(Request $request)
    {
        // Determine if using existing client
        $usingExistingClient = $request->client_id && $request->client_id !== 'new';

        // Detect Call Not Connected from products/services (makes trip fields optional)
        $isCallNotConnected = false;
        try {
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
                $sids = is_array($request->service_ids) ? $request->service_ids : json_decode($request->service_ids, true);
                if (is_array($sids) && count($sids) > 0) {
                    $exists = Service::whereIn('id', $sids)
                        ->where(function ($q) {
                            $q->whereRaw('LOWER(service) LIKE ?', ['%call not connected%'])
                                ->orWhereRaw('LOWER(service) LIKE ?', ['%no requirement%']);
                        })->exists();
                    if ($exists) $isCallNotConnected = true;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Error checking Call Not Connected flag: ' . $e->getMessage());
        }

        // Build validation rules based on web form rules
        $commonRules = [
            'number_of_passengers' => ($isCallNotConnected ? 'nullable|integer|min:1|max:100' : 'required|integer|min:1|max:100'),
            'occasion' => ['nullable', 'string', 'max:255'],
            'trips' => ($isCallNotConnected ? 'nullable|array' : 'required|array|min:1'),
            'trips.*.from_date' => ($isCallNotConnected ? 'nullable|date_format:Y-m-d H:i' : 'required|date_format:Y-m-d H:i'),
            'trips.*.to_date' => ($isCallNotConnected ? 'nullable|date_format:Y-m-d H:i' : 'required|date_format:Y-m-d H:i|after_or_equal:trips.*.from_date'),
            'trips.*.from_place' => ($isCallNotConnected ? ['nullable', 'string', 'max:255'] : ['required', 'string', 'max:255']),
            'trips.*.to_place' => ($isCallNotConnected ? ['nullable', 'string', 'max:255'] : ['required', 'string', 'max:255']),
            'address' => ['nullable', 'string', 'max:500'],
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'exists:services,id',
            'next_follow_up' => ['nullable', 'date_format:Y-m-d H:i', function ($attr, $value, $fail) use ($request) {
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
            'requirement_description' => ['nullable', 'string', 'max:1000'],
            'status' => 'required|integer|in:0,2',
        ];

        $clientRules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email:rfc,dns', 'max:255'],
            'contact_number' => ['required', 'string', 'regex:/^[0-9]{5,20}$/'],
            'alternate_number' => ['nullable', 'string', 'regex:/^[0-9]{5,20}$/'],
            'contact_country_code' => ['required', 'string', 'max:5', 'regex:/^\\+\\d{1,4}$/'],
            'whatsapp_country_code' => ['nullable', 'string', 'max:5', 'regex:/^\\+\\d{1,4}$/'],
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
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];

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
            'representative_user_id.required' => 'A staff representative is required.',
            'email.email' => 'Please enter a valid email address (e.g., example@domain.com).',
            'contact_number.required' => 'Phone number is required.',
            'contact_number.regex' => 'Phone number must contain only digits and be 5 to 20 characters long.',
            'contact_country_code.required' => 'Country code is required',
            'contact_country_code.max' => 'Country code cannot exceed 5 characters',
            'contact_country_code.regex' => 'Country code must be in format +XXX',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // Handle client create/update
            if ($usingExistingClient) {
                $client = Client::findOrFail($request->client_id);
                $strContactNumber = $request->contact_country_code . '-' . $request->contact_number;
                $strAlternateNumber = $request->alternate_number ? ($request->whatsapp_country_code . '-' . $request->alternate_number) : null;
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
                $strContactNumber = $request->contact_country_code . '-' . $request->contact_number;
                $strAlternateNumber = $request->alternate_number ? ($request->whatsapp_country_code . '-' . $request->alternate_number) : null;
                $client = Client::create([
                    'id' => (string) Str::uuid(),
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

            // Prepare product ids
            $leadProductIds = null;
            if ($request->filled('product_ids')) {
                $leadProductIds = is_array($request->product_ids) ? $request->product_ids : json_decode($request->product_ids, true);
                $leadProductIds = is_array($leadProductIds) ? array_values($leadProductIds) : null;
            }

            $enquiry = Lead::create([
                'id' => (string) Str::uuid(),
                'client_id' => $client->id,
                'representative_user_id' => $request->representative_user_id,
                'product_ids' => !empty($leadProductIds) ? json_encode($leadProductIds) : null,
                'service_ids' => json_encode($request->service_ids),
                'number_of_passengers' => $request->number_of_passengers,
                'description' => $request->requirement_description,
                'occasion' => $request->occasion,
            ]);

            // Create trips
            if (!empty($request->trips) && is_array($request->trips)) {
                foreach ($request->trips as $trip) {
                    if (empty(array_filter($trip))) continue;
                    LeadRide::create([
                        'id' => (string) Str::uuid(),
                        'lead_id' => $enquiry->id,
                        'from_date' => $trip['from_date'] ?? null,
                        'to_date' => $trip['to_date'] ?? null,
                        'from_place' => $trip['from_place'] ?? null,
                        'to_place' => $trip['to_place'] ?? null,
                    ]);
                }
            }

            $followupNote = trim((string)($request->requirement_description ?? '')) !== '' ? $request->requirement_description : ($request->status == 2 ? 'Lead cancelled during creation' : 'Initial lead created');

            $leadFollowUp = LeadFollowup::create([
                'id' => (string) Str::uuid(),
                'lead_id' => $enquiry->id,
                'next_followup_date' => $request->next_follow_up,
                'followup_note' => $followupNote,
                'followed_by' => auth()->id(),
                'status' => $request->status,
            ]);

            DB::commit();

            return response()->json(['lead' => $enquiry->load('client','rideSegments','representative'), 'followup' => $leadFollowUp], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API Lead creation failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Failed to create lead', 'message' => $e->getMessage()], 500);
        }
    }
 }
