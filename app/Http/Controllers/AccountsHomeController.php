<?php

namespace App\Http\Controllers;

use App\Services\Accounts\AccountsHomeService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AccountsHomeController extends Controller
{
    public function index(Request $request, AccountsHomeService $dashboard)
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m', 'regex:/^20\d{2}-(0[1-9]|1[0-2])$/']]);
        $month = isset($data['month']) ? Carbon::createFromFormat('!Y-m', $data['month']) : now()->startOfMonth();
        return response()->view('admin.account.dashboard', $dashboard->forMonth($month))->header('Cache-Control', 'private, no-store');
    }
}
