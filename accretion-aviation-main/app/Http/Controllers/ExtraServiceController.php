<?php

namespace App\Http\Controllers;

use App\Models\ExtraService;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ExtraServiceController extends Controller
{
    // Extra Service List
    public function index(Request $request)
    {
        $query = ExtraService::latest();

        // Apply search filters
        if ($request->filled('extra-service')) {
            $query->where('extra_service', 'like', '%' . $request->input('extra-service') . '%');
        }

        if ($request->filled('status') && in_array($request->input('status'), [0, 1])) {
            $query->where('status', $request->input('status'));
        }

        // Get all results
        $extraServices = $query->get();
        $isSuperAdmin = auth()->user() && auth()->user()->isSuperAdmin();

        return view('admin.pages.extra-services.index-extra-services', [
            'extraServices' => $extraServices,
            'isSuperAdmin' => $isSuperAdmin,
        ]);
    }

    // Create Extra Service
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'extra_service_name' => [
                'required',
                'string',
                'max:100',
                'regex:/^(?=.*[a-zA-Z])[a-zA-Z0-9\s\-\_\,\.\(\)\&]+$/'
            ],
            'extra_service_description' => [
                'nullable',
                'string',
                'max:255'
            ],
            'extra_service_amount' => 'required_with:extra_service|integer|min:0|max:9999999',
        ], [
            'extra_service_name.string' => 'Each extra service name must be a string.',
            'extra_service_name.max' => 'Each extra service name may not be greater than 100 characters.',

            'extra_service_description.string' => 'Each extra service description must be a string.',
            'extra_service_description.max' => 'Each extra service description may not be greater than 255 characters.',

            'extra_service_amount.required_with' => 'Each extra service requires an amount.',
            'extra_service_amount.integer' => 'Each extra service amount must be an integer.',
            'extra_service_amount.min' => 'Each extra service amount must be at least 0.',
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
            // Create new extra service from user input
            ExtraService::create([
                'id' => Str::uuid(),
                'extra_service' => $request->extra_service_name,
                'extra_service_amount' => $request->extra_service_amount,
                'description' => $request->extra_service_description ?? null,
                'status' => 1,
            ]);

            DB::commit();

            return redirect()->route('admin.extra-services.index')->with('success', 'Extra Service added successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to create service: ' . $e->getMessage());
        }
    }

    // View details of an extra service
    public function viewModal($id)
    {
        try {
            $extraService = ExtraService::findOrFail($id);

            return response()->json([
                'success' => true,
                'extraService' => $extraService,

            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch extra service details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch extra service details.'
            ], 500);
        }
    }

    // Update extra service
    public function update(Request $request, $id)
    {
        $extraService = ExtraService::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'extra_service_name' => [
                'required',
                'string',
                'max:100',
                'regex:/^(?=.*[a-zA-Z])[a-zA-Z0-9\s\-\_\,\.\(\)\&]+$/'
            ],
            'extra_service_description' => [
                'nullable',
                'string',
                'max:255'
            ],
            'extra_service_amount' => 'required_with:extra_service|integer|min:0|max:9999999',
        ], [
            'extra_service_name.string' => 'Each extra service name must be a string.',
            'extra_service_name.max' => 'Each extra service name may not be greater than 100 characters.',

            'extra_service_description.string' => 'Each extra service description must be a string.',
            'extra_service_description.max' => 'Each extra service description may not be greater than 255 characters.',

            'extra_service_amount.required_with' => 'Each extra service requires an amount.',
            'extra_service_amount.integer' => 'Each extra service amount must be an integer.',
            'extra_service_amount.min' => 'Each extra service amount must be at least 0.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please correct the errors below.');
        }

        DB::beginTransaction();

        try {

            $extraService->update([
                'extra_service' => $request->extra_service_name,
                'extra_service_amount' => $request->extra_service_amount,
                'description' => $request->extra_service_description ?? null,
            ]);

            DB::commit();

            return redirect()->route('admin.extra-services.index')
                ->with('success', 'Extra Service updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Extra Service update failed: ' . $e->getMessage());
            return back()
                ->withInput()
                ->with('error', 'Failed to update Extra Service: ' . $e->getMessage());
        }
    }

    // Change the status of an extra service
    public function toggleStatus(ExtraService $extraService)
    {
        try {
            $extraService->update(['status' => !$extraService->status]);
            return response()->json([
                'success' => true,
                'message' => 'Extra Service status updated successfully.',
                'new_status' => $extraService->status
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update extraService status: ' . $e->getMessage()
            ], 500);
        }
    }

    // Delete an extra service
    public function destroy(ExtraService $extraService)
    {
        $user = auth()->user();
        if (!$user || !$user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only Super Admin can delete extra services.'
            ], 403);
        }
        try {
            $extraService->delete();
            return response()->json([
                'success' => true,
                'message' => 'Extra Service deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete service: ' . $e->getMessage()
            ], 500);
        }
    }
}
