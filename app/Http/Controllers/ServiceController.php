<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ExtraService;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ServiceController extends Controller
{
    // List all services with pagination
    public function index(Request $request)
    {
        $query = Service::with('extraServices')->latest();

        // Apply search filters
        if ($request->filled('service')) {
            $query->where('service', 'like', '%' . $request->input('service') . '%');
        }

        if ($request->filled('status') && in_array($request->input('status'), [0, 1])) {
            $query->where('status', $request->input('status'));
        }

        // Get all results
        $services = $query->get();
        $isSuperAdmin = auth()->user() && auth()->user()->isSuperAdmin();

        return view('admin.pages.services.index-services', [
            'services' => $services,
            'isSuperAdmin' => $isSuperAdmin,
        ]);
    }

    // Show create form
    public function create()
    {
        $products = Product::where('status', 1)->get();
        $extraServices = ExtraService::where('status', 1)
            ->orderBy('extra_service')
            ->get();

        return view('admin.pages.services.add-services', compact('products', 'extraServices'));
    }

    // Store new service
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'service' => [
                'required',
                'string',
                'max:100',
                'regex:/^(?=.*[a-zA-Z])[a-zA-Z0-9\s\-\_\,\.\(\)\&]+$/'
            ],
            'description' => 'nullable|string|max:255',
            'service_amount' => 'required|integer|min:0|max:9999999',
            'terms_and_conditions' => 'nullable',
            // Accept single selected product from form but store as product_ids array
            'product_id' => 'required|exists:products,id',
            'extra_service_ids' => 'nullable|array',
            'extra_service_ids.*' => [
                'string',
                Rule::exists('extra_services', 'id')->where(function ($query) {
                    return $query->where('status', 1);
                }),
            ],
            // 'extra_services.*.name' => [
            //     function ($attribute, $value, $fail) use ($request) {
            //         $index = explode('.', $attribute)[1];
            //         $extraService = $request->extra_services[$index];

            //         if (empty($extraService['id']) && empty($value)) {
            //             $fail('The ' . $attribute . ' field is required when not selecting an existing service.');
            //         }

            //         if (!empty($extraService['id']) && $extraService['id'] !== 'new' && !ExtraService::where('id', $extraService['id'])->exists()) {
            //             $fail('The selected ' . $attribute . ' is invalid.');
            //         }
            //     },
            //     'max:100',
            // ],
            // 'extra_services.*.description' => [
            //     'nullable',
            //     'string',
            //     'max:255'
            // ],
            // 'extra_services.*.amount' => 'required_with:extra_services|integer|min:0|max:9999999',
        ], [
            'service.required' => 'The service name field is required.',
            'service_amount.required' => 'The service amount field is required.',
                'product_id.required' => 'The product field is required.',
                'product_id.exists' => 'The selected product is invalid.',
            'extra_service_ids.array' => 'The extra services must be an array.',
            'extra_service_ids.*.exists' => 'The selected extra service is invalid or inactive.',
            // 'extra_services.array' => 'The extra services must be an array.',

            // 'extra_services.*.name.required_without' => 'Service name is required when not selecting an existing service',
            // 'extra_services.*.name.required_with' => 'Each extra service requires a name.',
            // //'extra_services.*.name.string' => 'Each extra service name must be a string.',
            // 'extra_services.*.name.max' => 'Each extra service name may not be greater than 100 characters.',

            // 'extra_services.*.description.string' => 'Each extra service description must be a string.',
            // 'extra_services.*.description.max' => 'Each extra service description may not be greater than 255 characters.',

            // 'extra_services.*.amount.required_with' => 'Each extra service requires an amount.',
            // 'extra_services.*.amount.integer' => 'Each extra service amount must be an integer.',
            // 'extra_services.*.amount.min' => 'Each extra service amount must be at least 0.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please correct the errors below.');
        }

        // Start a database transaction
        DB::beginTransaction();

        try {
            $service = Service::create([
                'id' => Str::uuid(),
                'service' => $request->service,
                'description' => $request->description,
                'service_amount' => $request->service_amount,
                'terms_and_conditions' => $request->terms_and_conditions,
                // Store selected product as single-element array in product_ids
                'product_ids' => [$request->product_id],
                'status' => 1
            ]);

            $service->extraServices()->sync($this->uniqueIds($request->input('extra_service_ids', [])));

            // if (!empty($request->extra_services)) {
            //     foreach ($request->extra_services as $extra) {
            //         // Skip empty entries
            //         if (empty($extra['name']) && empty($extra['id'])) {
            //             continue;
            //         }

            //         if (!empty($extra['id']) && $extra['id'] !== 'new') {
            //             $existing = ExtraService::find($extra['id']);
            //             if ($existing) {
            //                 ExtraService::create([
            //                     'id' => Str::uuid(),
            //                     'service_id' => $service->id,
            //                     'extra_service_id' => $existing->id,
            //                     'extra_service' => $existing->extra_service,
            //                     'description' => $existing->description,
            //                     'extra_service_amount' => $existing->extra_service_amount,
            //                     'status' => 1,
            //                 ]);
            //             }
            //         } else {
            //             // Create new extra service from user input
            //             ExtraService::create([
            //                 'id' => Str::uuid(),
            //                 'service_id' => $service->id,
            //                 'extra_service' => $extra['name'],
            //                 'description' => $extra['description'] ?? null,
            //                 'extra_service_amount' => $extra['amount'],
            //                 'status' => 1,
            //             ]);
            //         }
            //     }
            // }
            DB::commit();

            return redirect()->route('admin.services.create')
                ->with('success', 'Service created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to create service: ' . $e->getMessage());
        }
    }

    public function edit(Service $service)
    {
        $service->load('extraServices');
        $products = Product::where('status', 1)->get();
        $extraServices = ExtraService::where('status', 1)
            ->orderBy('extra_service')
            ->get();

        return view('admin.pages.services.edit-services', compact('service', 'products', 'extraServices'));
    }

    // Update service
    public function update(Request $request, Service $service)
    {
        $validator = Validator::make($request->all(), [
            'service' => [
                'required',
                'string',
                'max:100',
                'regex:/^(?=.*[a-zA-Z])[a-zA-Z0-9\s\-\_\,\.\(\)\&]+$/'
            ],
            'description' => 'nullable|string|max:255',
            'service_amount' => 'required|integer|min:0|max:9999999',
            'terms_and_conditions' => 'nullable',
            'product_id' => 'required|exists:products,id',
            'extra_service_ids' => 'nullable|array',
            'extra_service_ids.*' => [
                'string',
                Rule::exists('extra_services', 'id')->where(function ($query) {
                    return $query->where('status', 1);
                }),
            ],
            // 'extra_services.*.name' => [
            //     function ($attribute, $value, $fail) use ($request) {
            //         $index = explode('.', $attribute)[1];
            //         $extraService = $request->extra_services[$index];

            //         if (empty($extraService['id']) && empty($value)) {
            //             $fail('The ' . $attribute . ' field is required when not selecting an existing service.');
            //         }

            //         if (!empty($extraService['id']) && $extraService['id'] !== 'new' && !ExtraService::where('id', $extraService['id'])->exists()) {
            //             $fail('The selected ' . $attribute . ' is invalid.');
            //         }
            //     },
            //     'max:100',
            //     // 'regex:/^(?=.*[a-zA-Z])[a-zA-Z0-9\s\-\_\,\.\(\)\&]+$/',
            //     // Rule::in(ExtraService::pluck('extra_service')->toArray()) // Allow dropdown values
            // ],
            // 'extra_services.*.description' => [
            //     'nullable',
            //     'string',
            //     'max:255'
            // ],
            // 'extra_services.*.amount' => 'required_with:extra_services|integer|min:0|max:9999999',
        ], [
            'service.required' => 'The service name field is required.',
            'service_amount.required' => 'The service amount field is required.',
            'product_id.required' => 'The product field is required.',
            'product_id.exists' => 'The selected product is invalid.',
            'extra_service_ids.array' => 'The extra services must be an array.',
            'extra_service_ids.*.exists' => 'The selected extra service is invalid or inactive.',
            // 'extra_services.array' => 'The extra services must be an array.',

            // 'extra_services.*.name.required_without' => 'Service name is required when not selecting an existing service',
            // 'extra_services.*.name.required_with' => 'Each extra service requires a name.',
            // //'extra_services.*.name.string' => 'Each extra service name must be a string.',
            // 'extra_services.*.name.max' => 'Each extra service name may not be greater than 100 characters.',

            // 'extra_services.*.description.string' => 'Each extra service description must be a string.',
            // 'extra_services.*.description.max' => 'Each extra service description may not be greater than 255 characters.',

            // 'extra_services.*.amount.required_with' => 'Each extra service requires an amount.',
            // 'extra_services.*.amount.integer' => 'Each extra service amount must be an integer.',
            // 'extra_services.*.amount.min' => 'Each extra service amount must be at least 0.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please correct the errors below.');
        }

        // Start a database transaction
        DB::beginTransaction();

        try {
            // Update main service
            $service->update([
                'service' => $request->service,
                'description' => $request->description,
                'service_amount' => $request->service_amount,
                'terms_and_conditions' => $request->terms_and_conditions,
                'product_ids' => [$request->product_id], // Store as array with single product
            ]);

            $service->extraServices()->sync($this->uniqueIds($request->input('extra_service_ids', [])));

            // // Handle extra services
            // $processedExtraServiceIds = [];

            // if (!empty($request->extra_services)) {
            //     foreach ($request->extra_services as $index => $extra) {
            //         // Skip empty entries
            //         if (empty($extra['name'] ?? null) && empty($extra['id'] ?? null)) {
            //             continue;
            //         }

            //         // Check if we have an existing_id (for updates) or id (for dropdown selections)
            //         $existingId = $extra['existing_id'] ?? null;
            //         $dropdownId = (!empty($extra['id']) && $extra['id'] !== 'new' && Str::isUuid($extra['id'])) ? $extra['id'] : null;

            //         if ($existingId) {
            //             // This is updating an existing extra service association for this service
            //             $existingAssociation = ExtraService::where('id', $existingId)
            //                 ->where('service_id', $service->id)
            //                 ->first();

            //             if ($existingAssociation) {
            //                 $existingAssociation->update([
            //                     'extra_service' => $extra['name'] ?? $existingAssociation->extra_service,
            //                     'description' => $extra['description'] ?? $existingAssociation->description,
            //                     'extra_service_amount' => $extra['amount'] ?? $existingAssociation->extra_service_amount,
            //                     'status' => 1
            //                 ]);
            //                 $processedExtraServiceIds[] = $existingAssociation->id;
            //             }
            //         } elseif ($dropdownId) {
            //             // This is selecting an existing extra service from dropdown
            //             if (Str::isUuid($dropdownId)) {
            //                 $originalExtraService = ExtraService::find($dropdownId);
            //                 if ($originalExtraService) {
            //                     // Check if we already have this extra service associated with current service
            //                     $existingAssociation = ExtraService::where('service_id', $service->id)
            //                         ->where('extra_service_id', $dropdownId)
            //                         ->first();

            //                     if ($existingAssociation) {
            //                         // Update existing association
            //                         $existingAssociation->update([
            //                             'extra_service' => $extra['name'] ?? $originalExtraService->extra_service,
            //                             'description' => $extra['description'] ?? $originalExtraService->description,
            //                             'extra_service_amount' => $extra['amount'] ?? $originalExtraService->extra_service_amount,
            //                             'status' => 1
            //                         ]);
            //                         $processedExtraServiceIds[] = $existingAssociation->id;
            //                     } else {
            //                         // Create new association
            //                         $newAssociation = ExtraService::create([
            //                             'id' => Str::uuid(),
            //                             'service_id' => $service->id,
            //                             'extra_service_id' => $dropdownId,
            //                             'extra_service' => $extra['name'] ?? $originalExtraService->extra_service,
            //                             'description' => $extra['description'] ?? $originalExtraService->description,
            //                             'extra_service_amount' => $extra['amount'] ?? $originalExtraService->extra_service_amount,
            //                             'status' => 1,
            //                         ]);
            //                         $processedExtraServiceIds[] = $newAssociation->id;
            //                     }
            //                 }
            //             }
            //         } else {
            //             // Create completely new extra service from manual input
            //             $newExtraService = ExtraService::create([
            //                 'id' => Str::uuid(),
            //                 'service_id' => $service->id,
            //                 'extra_service' => $extra['name'] ?? '',
            //                 'description' => $extra['description'] ?? null,
            //                 'extra_service_amount' => $extra['amount'] ?? 0,
            //                 'status' => 1
            //             ]);
            //             $processedExtraServiceIds[] = $newExtraService->id;
            //         }
            //     }
            // }

            // // Remove extra services that are no longer associated with this service
            // if (!empty($processedExtraServiceIds)) {
            //     ExtraService::where('service_id', $service->id)
            //         ->whereNotIn('id', $processedExtraServiceIds)
            //         ->delete();
            // } else {
            //     // If no extra services provided, remove all existing ones
            //     ExtraService::where('service_id', $service->id)->delete();
            // }

            DB::commit();

            return redirect()->route('admin.services.edit', $service)
                ->with('success', 'Service updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to update service: ' . $e->getMessage());
        }
    }

    public function view($id)
    {
        $service = Service::with('extraServices')->findOrFail($id);
        return view('admin.pages.services.view-services', compact('service'));
    }

    private function uniqueIds(array $ids): array
    {
        return array_values(array_unique(array_filter($ids)));
    }

    public function destroy(Service $service)
    {
        $user = auth()->user();
        if (!$user || !$user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only Super Admin can delete services.'
            ], 403);
        }
        try {
            $service->delete();
            return response()->json([
                'success' => true,
                'message' => 'Service deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete service: ' . $e->getMessage()
            ], 500);
        }
    }

    public function toggleStatus(Service $service)
    {
        try {
            $service->update(['status' => !$service->status]);
            return response()->json([
                'success' => true,
                'message' => 'Service status updated successfully.',
                'new_status' => $service->status
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update service status: ' . $e->getMessage()
            ], 500);
        }
    }
}
