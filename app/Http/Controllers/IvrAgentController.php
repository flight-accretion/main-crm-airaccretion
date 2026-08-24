<?php

namespace App\Http\Controllers;

use App\Models\IvrAgent;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IvrAgentController extends Controller
{
    public function index()
    {
        $this->ensureSuperAdmin();
        $items = IvrAgent::with('mappedUser')->orderBy('vi_agent_number')->orderBy('vi_agent_name')->get();
        return view('admin.pages.ivr.agents.index', compact('items'));
    }

    public function create()
    {
        $this->ensureSuperAdmin();
        return view('admin.pages.ivr.agents.form', ['item' => new IvrAgent(), 'users' => $this->salesUsers()]);
    }

    public function store(Request $request)
    {
        $this->ensureSuperAdmin();
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active');
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();
        IvrAgent::create($data);
        return redirect()->route('admin.ivr.agents.index')->with('success', 'IVR agent mapping created successfully.');
    }

    public function show(IvrAgent $agent)
    {
        $this->ensureSuperAdmin();
        $agent->load('mappedUser');
        return view('admin.pages.ivr.agents.show', ['item' => $agent]);
    }

    public function edit(IvrAgent $agent)
    {
        $this->ensureSuperAdmin();
        return view('admin.pages.ivr.agents.form', ['item' => $agent, 'users' => $this->salesUsers()]);
    }

    public function update(Request $request, IvrAgent $agent)
    {
        $this->ensureSuperAdmin();
        $data = $this->validated($request, $agent->id);
        $data['is_active'] = $request->boolean('is_active');
        $data['updated_by'] = Auth::id();
        $agent->update($data);
        return redirect()->route('admin.ivr.agents.index')->with('success', 'IVR agent mapping updated successfully.');
    }

    private function validated(Request $request, ?string $id = null): array
    {
        $agentNumber = $this->normalizePhone($request->input('vi_agent_number'));
        $agentName = trim((string) $request->input('vi_agent_name'));

        $request->merge([
            'vi_agent_number' => $agentNumber,
            'vi_agent_name' => $agentName !== '' ? $agentName : $agentNumber,
        ]);

        return $request->validate([
            'vi_agent_number' => 'required|string|digits:10|unique:ivr_agents,vi_agent_number' . ($id ? ',' . $id : ''),
            'vi_agent_name' => 'required|string|max:150|unique:ivr_agents,vi_agent_name' . ($id ? ',' . $id : ''),
            'mapped_user_id' => 'required|uuid|exists:users,id',
            'remarks' => 'nullable|string|max:1000',
        ]);
    }

    private function normalizePhone($value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === '') {
            return null;
        }

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
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
