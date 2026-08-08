<?php

namespace App\Http\Controllers;

use App\Models\IvrCallType;
use App\Models\IvrCallTypeUser;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IvrCallTypeController extends Controller
{
    public function index()
    {
        $this->ensureSuperAdmin();
        $items = IvrCallType::with(['users.user'])->orderBy('sort_order')->orderBy('code')->get();
        return view('admin.pages.ivr.call-types.index', compact('items'));
    }

    public function create()
    {
        $this->ensureSuperAdmin();
        return view('admin.pages.ivr.call-types.form', [
            'item' => new IvrCallType(),
            'users' => $this->salesUsers(),
            'selectedUsers' => [],
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureSuperAdmin();
        $data = $this->validated($request);
        $userIds = $data['user_ids'] ?? [];
        unset($data['user_ids']);
        $data['is_active'] = $request->boolean('is_active');
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        $item = IvrCallType::create($data);
        $this->syncUsers($item, $userIds);
        return redirect()->route('admin.ivr.call-types.index')->with('success', 'IVR Call Type created successfully.');
    }

    public function show(IvrCallType $callType)
    {
        $this->ensureSuperAdmin();
        $callType->load(['users.user']);
        return view('admin.pages.ivr.call-types.show', ['item' => $callType]);
    }

    public function edit(IvrCallType $callType)
    {
        $this->ensureSuperAdmin();
        return view('admin.pages.ivr.call-types.form', [
            'item' => $callType,
            'users' => $this->salesUsers(),
            'selectedUsers' => $callType->users()->pluck('user_id')->toArray(),
        ]);
    }

    public function update(Request $request, IvrCallType $callType)
    {
        $this->ensureSuperAdmin();
        $data = $this->validated($request, $callType->id);
        $userIds = $data['user_ids'] ?? [];
        unset($data['user_ids']);
        $data['is_active'] = $request->boolean('is_active');
        $data['updated_by'] = Auth::id();
        $callType->update($data);
        $this->syncUsers($callType, $userIds);
        return redirect()->route('admin.ivr.call-types.index')->with('success', 'IVR Call Type updated successfully.');
    }

    private function validated(Request $request, ?string $id = null): array
    {
        return $request->validate([
            'code' => 'required|string|max:50|unique:ivr_call_types,code' . ($id ? ',' . $id : ''),
            'name' => 'required|string|max:150',
            'category' => 'nullable|string|max:100',
            'assignment_mode' => 'required|in:balanced,random',
            'sort_order' => 'nullable|integer|min:0',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'uuid|exists:users,id',
        ]);
    }

    private function syncUsers(IvrCallType $item, array $userIds): void
    {
        IvrCallTypeUser::where('ivr_call_type_id', $item->id)->delete();
        foreach (array_values(array_unique($userIds)) as $priority => $userId) {
            IvrCallTypeUser::create([
                'ivr_call_type_id' => $item->id,
                'user_id' => $userId,
                'priority' => $priority,
                'is_active' => true,
            ]);
        }
    }

    private function salesUsers()
    {
        return User::whereHas('userType', function ($query) {
            $query->whereIn('user_type', UserType::SALES_ROLES);
        })->where('status', 1)->orderBy('name')->get();
    }

    private function ensureSuperAdmin(): void
    {
        abort_unless(Auth::check() && Auth::user()->isSuperAdmin(), 403);
    }
}
