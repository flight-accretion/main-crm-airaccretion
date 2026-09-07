<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\BookingConfirmationEmailService;
use Illuminate\Support\Facades\Auth;

class LeadBookingConfirmationEmailController extends Controller
{
    public function send(
        Lead $lead,
        BookingConfirmationEmailService $service
    ) {
        $result = $service->sendForLead($lead, Auth::user());

        return response()->json(
            $result,
            ($result['success'] ?? false) ? 200 : 422
        );
    }
}
