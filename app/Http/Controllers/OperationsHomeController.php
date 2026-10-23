<?php

namespace App\Http\Controllers;

use App\Services\Operations\OperationsHomeService;
use Illuminate\Http\Request;

class OperationsHomeController extends Controller
{
    public function index(Request $request, OperationsHomeService $dashboard)
    {
        $filters = $request->validate(['cases' => 'nullable|in:all,due,recent,week,older', 'page' => 'nullable|integer|min:1|max:1000000']);
        return response()->view('admin.pages.operations.home', $dashboard->forUser($request->user(), $filters['cases'] ?? null))
            ->header('Cache-Control', 'private, no-store');
    }
}
