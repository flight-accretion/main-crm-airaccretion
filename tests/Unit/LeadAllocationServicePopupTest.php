<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\UserType;
use App\Services\LeadAllocationService;
use Carbon\Carbon;
use Tests\TestCase;

class LeadAllocationServicePopupTest extends TestCase
{
    public function test_popup_only_shows_once_per_day_until_interval_expires(): void
    {
        $user = new User();
        $user->id = 1;
        $user->last_login = Carbon::create(2026, 7, 28, 10, 30, 0);
        $user->userType = new \stdClass();
        $user->userType->user_type = UserType::SALES_EXECUTIVE;

        $service = new LeadAllocationService();
        $lastPromptAt = Carbon::create(2026, 7, 28, 10, 30, 0);

        $this->assertTrue($service->shouldShowPopup($user, true, null, Carbon::create(2026, 7, 28, 10, 35, 0), 120));
        $this->assertFalse($service->shouldShowPopup($user, true, $lastPromptAt, Carbon::create(2026, 7, 28, 10, 40, 0), 120));
        $this->assertTrue($service->shouldShowPopup($user, true, $lastPromptAt, Carbon::create(2026, 7, 28, 12, 40, 0), 120));
    }

    public function test_popup_shows_after_prior_day_last_prompt(): void
    {
        $user = new User();
        $user->id = 1;
        $user->last_login = Carbon::create(2026, 7, 28, 10, 30, 0);
        $user->userType = new \stdClass();
        $user->userType->user_type = UserType::SALES_EXECUTIVE;

        $service = new LeadAllocationService();
        $lastPromptAt = Carbon::create(2026, 7, 27, 17, 00, 0);

        $this->assertTrue($service->shouldShowPopup($user, true, $lastPromptAt, Carbon::create(2026, 7, 28, 10, 40, 0), 120));
    }
}
