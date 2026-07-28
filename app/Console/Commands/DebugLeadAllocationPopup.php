<?php

namespace App\Console\Commands;

use App\Models\LeadAllocationSetting;
use App\Models\LeadAllocationQueue;
use App\Models\SalespersonAvailability;
use App\Models\User;
use App\Models\UserType;
use App\Services\LeadAllocationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DebugLeadAllocationPopup extends Command
{
    protected $signature = 'lead:debug-popup {--user= : Specific user email or id} {--show-all : Show all sales users and their popup state}';
    protected $description = 'Debug why the lead allocation popup does or does not appear for sales users';

    public function handle(LeadAllocationService $service): int
    {
        $settings = LeadAllocationSetting::getActiveSettings();
        $now = Carbon::now();
        $isOfficeOpen = $service->isOfficeOpenForDebug($settings, $now);

        $this->info('Office hours: ' . ($isOfficeOpen ? 'open' : 'closed'));
        $this->info('Current time: ' . $now->format('Y-m-d H:i:s'));
        $this->info('Popup interval: ' . ($settings->popup_interval_minutes ?? 120) . ' minutes');

        $query = User::query()->whereHas('userType', function ($q) {
            $q->whereIn('user_type', UserType::SALES_ROLES);
        });

        if ($this->option('user')) {
            $value = $this->option('user');
            $query->where(function ($q) use ($value) {
                if (Str::isUuid($value)) {
                    $q->where('id', $value);
                }
                $q->orWhere('email', $value)
                    ->orWhere('name', 'like', '%' . $value . '%');
            });
        }

        $users = $query->with('userType')->get();

        if ($users->isEmpty()) {
            $this->warn('No matching sales users found.');
            return Command::SUCCESS;
        }

        $this->info('Queued leads: ' . LeadAllocationQueue::where('status', 'queued')->count());

        $rows = [];
        foreach ($users as $user) {
            $availability = SalespersonAvailability::firstOrCreate(
                ['user_id' => $user->id],
                ['state' => 'offline', 'is_available' => false, 'is_opted_in' => false]
            );

            $lastPromptAt = $availability->last_response_at ?? $availability->last_popup_at;
            $popupIntervalMinutes = max(120, (int) ($settings->popup_interval_minutes ?? 120));
            $hasPromptedToday = $lastPromptAt && $lastPromptAt->isSameDay($now);
            $isPopupDue = !$lastPromptAt || !$hasPromptedToday || $lastPromptAt->diffInMinutes($now) >= $popupIntervalMinutes;
            $firstLoginToday = false;
            if ($user->last_login) {
                $lastLoginAt = Carbon::parse($user->last_login);
                $firstLoginToday = $lastLoginAt->isSameDay($now) && (!$lastPromptAt || $lastLoginAt->gt($lastPromptAt));
            }
            $showPopup = $isOfficeOpen && ($firstLoginToday || $isPopupDue);

            $rows[] = [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->userType->user_type ?? 'N/A',
                'status' => $availability->is_opted_in ? 'opted_in' : 'not_opted_in',
                'available' => $availability->is_available ? 'yes' : 'no',
                'last_login' => $user->last_login ? Carbon::parse($user->last_login)->format('Y-m-d H:i:s') : 'never',
                'last_popup' => $availability->last_popup_at ? $availability->last_popup_at->format('Y-m-d H:i:s') : 'never',
                'last_response' => $availability->last_response_at ? $availability->last_response_at->format('Y-m-d H:i:s') : 'never',
                'show_popup' => $showPopup ? 'yes' : 'no',
            ];
        }

        $this->table(['Name', 'Email', 'Role', 'Status', 'Available', 'Last Login', 'Last Popup', 'Last Response', 'Show Popup'], $rows);

        return Command::SUCCESS;
    }
}
