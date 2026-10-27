<?php

namespace App\Http\Controllers;

use App\Services\Owner\OwnerDashboardService;
use App\Services\SidebarPendingCounts;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OwnerDashboardController extends Controller
{
    private OwnerDashboardService $dashboard;
    private SidebarPendingCounts $pendingCounts;

    public function __construct(OwnerDashboardService $dashboard, SidebarPendingCounts $pendingCounts)
    {
        $this->dashboard = $dashboard;
        $this->pendingCounts = $pendingCounts;
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'regex:/^20\d{2}-(0[1-9]|1[0-2])$/'],
        ]);

        $month = isset($validated['month'])
            ? Carbon::createFromFormat('!Y-m', $validated['month'])->startOfMonth()
            : now()->startOfMonth();

        $data = $this->dashboard->overview($month);

        $data['quickLinks'] = [
            'payment_review' => $this->pendingCounts->countFor($request->user(), 'payment_approval'),
            'customer_refunds' => $this->pendingCounts->countFor($request->user(), 'customer_refund'),
            'vendor_refunds' => $this->pendingCounts->countFor($request->user(), 'account_vendor_refund'),
        ];

        return response()
            ->view('admin.pages.dashboards.owner-dashboard', $data)
            ->header('Cache-Control', 'private, no-store');
    }
}
