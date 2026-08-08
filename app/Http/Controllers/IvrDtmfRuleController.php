<?php

namespace App\Http\Controllers;

use App\Models\IvrCallType;
use App\Models\IvrDtmfRule;
use App\Models\IvrDtmfRuleUser;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IvrDtmfRuleController extends Controller
{
    public function index()
    {
        $this->ensureSuperAdmin();
        $items = IvrDtmfRule::with(['callType','users.user'])->orderBy('sort_order')->orderBy('dtmf_value')->get();
        return view('admin.pages.ivr.dtmf-rules.index', compact('items'));
    }

    public function create()
    {
        $this->ensureSuperAdmin();
        return view('admin.pages.ivr.dtmf-rules.form', [
            'item' => new IvrDtmfRule(),
            'callTypes' => IvrCallType::where('is_active', true)->orderBy('sort_order')->get(),
            'users' => $this->salesUsers(),
            'selectedUsers' => [],
            'matchValuesText' => '',
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureSuperAdmin();
        $data = $this->validated($request);
        $userIds = $data['user_ids'] ?? [];
        $matchValues = $this->parseMatchValues($data['match_values_text'] ?? '');
        unset($data['user_ids'], $data['match_values_text']);
        $data['match_values'] = $matchValues;
        $data['is_default'] = $request->boolean('is_default');
        $data['is_active'] = $request->boolean('is_active');
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        $item = IvrDtmfRule::create($data);
        $this->syncUsers($item, $userIds);
        return redirect()->route('admin.ivr.dtmf-rules.index')->with('success', 'DTMF rule created successfully.');
    }

    public function show(IvrDtmfRule $dtmfRule)
    {
        $this->ensureSuperAdmin();
        $dtmfRule->load(['callType','users.user']);
        return view('admin.pages.ivr.dtmf-rules.show', ['item' => $dtmfRule]);
    }

    public function edit(IvrDtmfRule $dtmfRule)
    {
        $this->ensureSuperAdmin();
        return view('admin.pages.ivr.dtmf-rules.form', [
            'item' => $dtmfRule,
            'callTypes' => IvrCallType::where('is_active', true)->orderBy('sort_order')->get(),
            'users' => $this->salesUsers(),
            'selectedUsers' => $dtmfRule->users()->pluck('user_id')->toArray(),
            'matchValuesText' => implode(', ', $dtmfRule->match_values ?? []),
        ]);
    }

    public function update(Request $request, IvrDtmfRule $dtmfRule)
    {
        $this->ensureSuperAdmin();
        $data = $this->validated($request);
        $userIds = $data['user_ids'] ?? [];
        $matchValues = $this->parseMatchValues($data['match_values_text'] ?? '');
        unset($data['user_ids'], $data['match_values_text']);
        $data['match_values'] = $matchValues;
        $data['is_default'] = $request->boolean('is_default');
        $data['is_active'] = $request->boolean('is_active');
        $data['updated_by'] = Auth::id();
        $dtmfRule->update($data);
        $this->syncUsers($dtmfRule, $userIds);
        return redirect()->route('admin.ivr.dtmf-rules.index')->with('success', 'DTMF rule updated successfully.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'ivr_call_type_id' => 'nullable|uuid|exists:ivr_call_types,id',
            'dtmf_value' => 'required|string|max:100',
            'name' => 'required|string|max:150',
            'category' => 'nullable|string|max:100',
            'match_values_text' => 'nullable|string|max:2000',
            'assignment_mode' => 'required|in:balanced,random',
            'sort_order' => 'nullable|integer|min:0',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'uuid|exists:users,id',
        ]);
    }

    private function parseMatchValues(string $value): array
    {
        return collect(explode(',', $value))->map(fn ($v) => trim($v))->filter(fn ($v) => $v !== '')->unique()->values()->all();
    }

    private function syncUsers(IvrDtmfRule $item, array $userIds): void
    {
        IvrDtmfRuleUser::where('ivr_dtmf_rule_id', $item->id)->delete();
        foreach (array_values(array_unique($userIds)) as $priority => $userId) {
            IvrDtmfRuleUser::create([
                'ivr_dtmf_rule_id' => $item->id,
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
