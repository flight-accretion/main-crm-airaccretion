<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\UserType;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class UserTypeController extends Controller
{
    public function index()
    {
        $arrobjUserTypes = UserType::all();
        return response()->json($arrobjUserTypes);
    }

    public function create()
    {
        $arrobjParentUserTypes = UserType::where('status', 1)->get();
        $perPage = min(max((int) request()->input('per_page', 20), 1), 100);
        $arrobjUserTypes = UserType::with('parent')->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->appends(request()->query());
        return view('admin.pages.user-types.add-user-type', compact('arrobjParentUserTypes', 'arrobjUserTypes'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_type' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:0,1',
            'parent_id' => 'nullable|uuid|exists:user_types,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('error', 'Please correct the errors below.');
        }

        UserType::create([
            'id' => Str::uuid(),
            'user_type' => $request->user_type,
            'description' => $request->description,
            'status' => $request->status,
            'parent_id' => $request->parent_id,
        ]);
        return redirect()->route('admin.user-types.create')->with('success', 'User role created successfully.');
    }

    public function edit(string $id)
    {
        if (!Str::isUuid($id)) {
            return redirect()->back()->with('error', 'Invalid ID format.');
        }

        $objFollowUp = UserType::findOrFail($id);
        $arrobjParentUserTypes = UserType::where('status', 1)->get();
        $perPage = min(max((int) request()->input('per_page', 20), 1), 100);
        $arrobjUserTypes = UserType::with('parent')->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->appends(request()->query());

        return view('admin.pages.user-types.add-user-type', compact('objFollowUp', 'arrobjParentUserTypes', 'arrobjUserTypes'));
    }

    public function update(Request $request, string $id)
    {
        if (!Str::isUuid($id)) {
            return back()->with('error', 'Invalid ID format.');
        }

        $validator = Validator::make($request->all(), [
            'user_type' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:0,1',
            'parent_id' => 'nullable|uuid|exists:user_types,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('error', 'Validation failed.');
        }

        $objUserType = UserType::findOrFail($id);
        $objUserType->update($request->only(['user_type', 'description', 'status', 'parent_id']));

        return redirect()->route('admin.user-types.create')->with('success', 'User role updated successfully.');
    }

    public function toggleStatus(string $id)
    {
        $objUserType = UserType::findOrFail($id);
        $objUserType->status = $objUserType->status ? 0 : 1;
        $objUserType->save();

        return redirect()->back()->with('success', 'Status updated successfully.');
    }
}
