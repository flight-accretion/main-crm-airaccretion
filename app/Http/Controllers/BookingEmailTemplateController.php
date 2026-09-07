<?php

namespace App\Http\Controllers;

use App\Models\BookingEmailTemplate;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingEmailTemplateController extends Controller
{
    public function edit()
    {
        $this->ensureSuperAdmin();

        return view('admin.pages.email-template.booking-confirmation', [
            'template' => BookingEmailTemplate::active(),
            'variables' => BookingEmailTemplate::variables(),
        ]);
    }

    public function update(Request $request)
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'subject' => [
                'required',
                'string',
                'max:500',
            ],
            'body' => [
                'required',
                'string',
                'max:30000',
            ],
        ]);

        $template = BookingEmailTemplate::active();
        $template->fill([
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()
            ->route('admin.booking-email-template.edit')
            ->with('success', 'Booking email template updated successfully.');
    }

    private function ensureSuperAdmin(): void
    {
        $role = optional(Auth::user()->userType)->user_type;

        abort_unless($role === UserType::SUPER_ADMIN, 403);
    }
}
