<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Models\LeadPassenger;
use App\Models\Lead;
use App\Models\Service;
use App\Models\ExtraService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class RegistrationController extends Controller
{
    // Public form for external registration
    public function showForm($voucher_id, Request $request)
    {
        // Accept token either via query (?token=...) or as a route segment (/voucher/register/{voucher}/{token})
        $token = $request->input('token') ?? $request->query('token') ?? $request->route('token');
        // Load voucher with lead and related data (including vendor payments/details so we can derive services from voucher if present)
        $voucher = Voucher::with([
            'passengers',
            'lead.client.country',
            'lead.client.city',
            'lead.rideSegments.serviceAddress.service',
            'lead.rideSegments.serviceAddress.city',
            'vendorPayments.paymentDetails'
        ])->findOrFail($voucher_id);

        if (empty($voucher->registration_token) || $voucher->registration_token !== $token) {
            Log::warning('Registration token mismatch', ['voucher_id'=>$voucher_id, 'provided_token'=>$token, 'expected'=>$voucher->registration_token]);
            abort(403, 'Invalid or missing token');
        }

        // Prepare selected services from latest followup or lead.service_ids
        $lead = $voucher->lead;
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

        // If the voucher already has vendorPayments (i.e. services were finalized), prefer listing
        // services and extra services from the voucher's LeadVendorPaymentDetail records so the
        // public registration shows exactly what the voucher contains (may be multiple entries).
        $selectedServices = collect();
        $selectedExtraServices = collect();

        if (!empty($voucher->vendorPayments) && $voucher->vendorPayments->isNotEmpty()) {
            foreach ($voucher->vendorPayments as $vendorPayment) {
                foreach ($vendorPayment->paymentDetails as $detail) {
                    if (!empty($detail->is_extra_service)) {
                        $extra = ExtraService::find($detail->service_id);
                        if ($extra) {
                            // attach vendor and amounts for display if needed in the view
                            $extra->vendor_id = $vendorPayment->vendor_id;
                            $extra->amount = $detail->service_amount;
                            $extra->vendor_amount = $detail->vendor_service_amount;
                            $selectedExtraServices->push($extra);
                        }
                    } else {
                        $service = Service::find($detail->service_id);
                        if ($service) {
                            $service->vendor_id = $vendorPayment->vendor_id;
                            $service->amount = $detail->service_amount;
                            $service->vendor_amount = $detail->vendor_service_amount;
                            $selectedServices->push($service);
                        }
                    }
                }
            }
        } else {
            $selectedServices = Service::whereIn('id', $selectedServiceIds ?? [])->get();
            $selectedExtraServices = ExtraService::whereIn('id', $selectedExtraServiceIds ?? [])->get();
        }

        // Determine if any selected product requires patient/air ambulance (to enforce patient rules)
        $isAirAmbulance = false;
        foreach ($selectedServices as $service) {
            if (method_exists($service, 'getProducts')) {
                $products = $service->getProducts();
                foreach ($products as $product) {
                    if (!empty($product->is_airambulance) || !empty($product->is_air_ambulance) ) {
                        $isAirAmbulance = true;
                        break 2;
                    }
                }
            }
        }

        return view('public.shared.register', compact('voucher','lead','selectedServices','selectedExtraServices','isAirAmbulance','token'));
    }

    public function store($voucher_id, Request $request)
    {
        $voucher = Voucher::findOrFail($voucher_id);
        // Accept token from POST body, query string, or route parameter
        $token = $request->input('token') ?? $request->query('token') ?? $request->route('token');
        if (empty($voucher->registration_token) || $voucher->registration_token !== $token) {
            Log::warning('Registration store token mismatch', ['voucher_id'=>$voucher_id, 'provided_token'=>$token, 'expected'=>$voucher->registration_token]);
            abort(403, 'Invalid or missing token');
        }

        // allow only letters and spaces for names (unicode aware)
        $lettersRegex = '/^[\p{L}\s]+$/u';

        $rules = [
            'passengers' => 'required|array|min:1',
            'passengers.*.name' => ['required','string','max:255','regex:'.$lettersRegex],
            'passengers.*.age' => 'nullable|integer|min:0|max:150',
            'passengers.*.contact_number' => 'nullable|string|max:20',
            'passengers.*.traveller_type' => 'nullable|string|max:50',
            // weight must be numeric and not exceed 200 kg
            'passengers.*.weight' => 'nullable|numeric|min:0|max:200',
            'passengers.*.front_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'passengers.*.back_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ];

        // Build friendly validation messages for passengers (per-row) and file uploads
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

        // We will replace existing non-handler passengers with submitted ones
        LeadPassenger::where('voucher_id', $voucher->id)->where('is_handler', false)->where('is_additional_person', false)->delete();

        foreach ($request->passengers as $index => $p) {
            $passenger = LeadPassenger::create([
                'id' => (string) Str::uuid(),
                'voucher_id' => $voucher->id,
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
                $path = $file->storeAs('vouchers/documents', time() . '_front_' . $file->getClientOriginalName(), 'public');
                $passenger->update(['front_document' => $path]);
            }

            if ($request->hasFile("passengers.{$index}.back_document")) {
                $file = $request->file("passengers.{$index}.back_document");
                $path = $file->storeAs('vouchers/documents', time() . '_back_' . $file->getClientOriginalName(), 'public');
                $passenger->update(['back_document' => $path]);
            }
        }

        return redirect()->route('voucher.register.thanks');
    }

    public function thanks()
    {
        return view('public.shared.thanks');
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
