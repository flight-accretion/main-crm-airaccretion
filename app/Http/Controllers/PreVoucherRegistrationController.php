<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadPassenger;
use App\Models\Service;
use App\Models\ExtraService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PreVoucherRegistrationController extends Controller
{
    // Public form for external registration (before voucher creation)
    public function showForm($lead_id, Request $request)
    {
        $token = $request->input('token') ?? $request->query('token') ?? $request->route('token');
        
        // Load lead with related data
        $lead = Lead::with([
            'client.country',
            'client.city',
            'rideSegments.serviceAddress.service',
            'rideSegments.serviceAddress.city',
            'latestFollowup'
        ])->findOrFail($lead_id);

        // Verify token
        $passenger = LeadPassenger::where('lead_id', $lead_id)
            ->where('registration_token', $token)
            ->whereNull('voucher_id')
            ->first();

        if (!$passenger || !$passenger->isTokenValid()) {
            Log::warning('Registration token invalid or expired', ['lead_id' => $lead_id, 'token' => $token]);
            abort(403, 'Invalid or expired registration token');
        }

        // Get selected services from latest followup or lead.service_ids
        $selectedServiceIds = [];
        $selectedExtraServiceIds = [];

        if ($lead) {
            $latestFollowup = $lead->leadFollowups()->orderBy('created_at','desc')->first();
            if ($latestFollowup) {
                $serviceIds = $latestFollowup->service_ids ?? null;
                $extraIds = $latestFollowup->extra_service_ids ?? null;
                $selectedServiceIds = $this->cleanIds($serviceIds);
                $selectedExtraServiceIds = $this->cleanIds($extraIds);
            }

            if (empty($selectedServiceIds) && !empty($lead->service_ids)) {
                $selectedServiceIds = is_array($lead->service_ids) ? $lead->service_ids : json_decode($lead->service_ids, true) ?? [];
            }
        }

        $selectedServices = Service::whereIn('id', $selectedServiceIds ?? [])->get();
        $selectedExtraServices = ExtraService::whereIn('id', $selectedExtraServiceIds ?? [])->get();

        // Get existing passengers for this lead (if any)
        $existingPassengers = $lead->preVoucherPassengers()
            ->where('registration_token', $token)
            ->where('name', '!=', '')
            ->get();

        // If registration already submitted (we have saved passengers for this token), redirect to thanks
        if ($existingPassengers->isNotEmpty()) {
            return redirect()->route('lead.register.thanks');
        }

        return view('public.shared.register', compact(
            'lead', 
            'selectedServices', 
            'selectedExtraServices', 
            'existingPassengers',
            'token'
        ));
    }

    public function store($lead_id, Request $request)
    {
        $lead = Lead::findOrFail($lead_id);
        
        // Accept token from POST body, query string, or route parameter
        $token = $request->input('token') ?? $request->query('token') ?? $request->route('token');
        
        // Verify token
        $tokenPassenger = LeadPassenger::where('lead_id', $lead_id)
            ->where('registration_token', $token)
            ->whereNull('voucher_id')
            ->first();

        if (!$tokenPassenger || !$tokenPassenger->isTokenValid()) {
            Log::warning('Registration store token invalid', ['lead_id' => $lead_id, 'token' => $token]);
            abort(403, 'Invalid or expired registration token');
        }

        // Validation rules
        $lettersRegex = '/^[\p{L}\s]+$/u';

        $rules = [
            'passengers' => 'required|array|min:1',
            'passengers.*.name' => ['required','string','max:255','regex:'.$lettersRegex],
            'passengers.*.age' => 'nullable|integer|min:0|max:150',
            'passengers.*.contact_number' => 'nullable|string|max:20',
            'passengers.*.traveller_type' => 'nullable|string|max:50',
            'passengers.*.weight' => 'nullable|numeric|min:0|max:200',
            'passengers.*.front_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'passengers.*.back_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ];

        $messages = [
            'passengers.required' => 'At least one passenger must be added.',
            'passengers.min' => 'At least one passenger must be added.',
            'passengers.*.front_document.file' => 'Passenger front document must be a file.',
            'passengers.*.front_document.mimes' => 'Passenger front document must be JPG, JPEG, PNG, or PDF.',
            'passengers.*.front_document.max' => 'Passenger front document cannot exceed 5MB.',
            'passengers.*.back_document.file' => 'Passenger back document must be a file.',
            'passengers.*.back_document.mimes' => 'Passenger back document must be JPG, JPEG, PNG, or PDF.',
            'passengers.*.back_document.max' => 'Passenger back document cannot exceed 5MB.',
            'passengers.*.name.regex' => 'Passenger name should contain only letters and spaces.',
            'passengers.*.weight.max' => 'Passenger weight cannot exceed 200 kg.',
        ];

        if ($request->has('passengers')) {
            foreach ($request->passengers as $index => $passenger) {
                $num = $index + 1;
                $messages["passengers.{$index}.name.required"] = "Passenger {$num} name is required.";
                $messages["passengers.{$index}.name.string"] = "Passenger {$num} name must be valid text.";
                $messages["passengers.{$index}.name.max"] = "Passenger {$num} name cannot exceed 255 characters.";
                $messages["passengers.{$index}.age.integer"] = "Passenger {$num} age must be a valid number.";
                $messages["passengers.{$index}.age.min"] = "Passenger {$num} age must be at least 0.";
                $messages["passengers.{$index}.age.max"] = "Passenger {$num} age cannot exceed 150.";
                $messages["passengers.{$index}.weight.numeric"] = "Passenger {$num} weight must be a valid number.";
                $messages["passengers.{$index}.weight.min"] = "Passenger {$num} weight must be at least 0.";
                $messages["passengers.{$index}.weight.max"] = "Passenger {$num} weight cannot exceed 200 kg.";
            }
        }

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Server-side guard: ensure submitted passenger count does not exceed lead's allowed number
        $submittedCount = is_array($request->input('passengers')) ? count($request->input('passengers')) : 0;
        $maxAllowed = $lead->number_of_passengers ?? null;
        if ($maxAllowed !== null && $submittedCount > $maxAllowed) {
            return redirect()->back()
                ->withErrors(['passengers' => "You can add at most {$maxAllowed} passengers."])
                ->withInput();
        }

        // Clear existing passengers for this lead and token (except the token record)
        LeadPassenger::where('lead_id', $lead->id)
            ->whereNull('voucher_id')
            ->where('registration_token', $token)
            ->where('name', '!=', '')
            ->delete();

        // Save new passengers
        foreach ($request->passengers as $index => $p) {
            $passenger = LeadPassenger::create([
                'id' => (string) Str::uuid(),
                'lead_id' => $lead->id,
                'registration_token' => $token,
                'name' => $p['name'],
                'age' => $p['age'] ?? null,
                'contact_number' => $p['contact_number'] ?? null,
                'traveller_type' => $p['traveller_type'] ?? null,
                'weight' => $p['weight'] ?? null,
                'is_handler' => false,
                'is_additional_person' => false,
            ]);

            if ($request->hasFile("passengers.{$index}.front_document")) {
                $file = $request->file("passengers.{$index}.front_document");
                $path = $file->storeAs('leads/documents', time() . '_front_' . $file->getClientOriginalName(), 'public');
                $passenger->update(['front_document' => $path]);
            }

            if ($request->hasFile("passengers.{$index}.back_document")) {
                $file = $request->file("passengers.{$index}.back_document");
                $path = $file->storeAs('leads/documents', time() . '_back_' . $file->getClientOriginalName(), 'public');
                $passenger->update(['back_document' => $path]);
            }
        }

        return redirect()->route('lead.register.thanks');
    }

    public function thanks()
    {
        return view('public.shared.thanks');
    }

    /**
     * Resolve a short registration slug and redirect to the full registration form.
     */
    public function redirectBySlug($slug)
    {
        try {
            $passenger = LeadPassenger::where('registration_slug', $slug)->first();
            if (! $passenger) {
                abort(404);
            }

            // If a registration has already been submitted for this lead (any saved passengers with name),
            // redirect to the thanks page so reopening the same short link doesn't show the form again.
            $hasSubmitted = LeadPassenger::where('lead_id', $passenger->lead_id)
                ->whereNull('voucher_id')
                ->where('name', '!=', '')
                ->exists();

            if ($hasSubmitted) {
                return redirect()->route('lead.register.thanks');
            }

            // Ensure token exists and is valid; regenerate only if no submitted registration exists
            if (! $passenger->isTokenValid()) {
                $passenger->generateRegistrationToken();
            }

            return redirect()->route('lead.register.form', ['lead' => $passenger->lead_id, 'token' => $passenger->registration_token]);
        } catch (\Throwable $e) {
            Log::error('Shortlink redirect error in PreVoucherRegistrationController: ' . $e->getMessage());
            abort(500);
        }
    }

    protected function cleanIds($ids)
    {
        if (empty($ids)) return [];
        if (is_array($ids)) return $ids;
        $cleaned = str_replace('"', '"', $ids);
        $decoded = json_decode($cleaned, true);
        return is_array($decoded) ? $decoded : [];
    }
}