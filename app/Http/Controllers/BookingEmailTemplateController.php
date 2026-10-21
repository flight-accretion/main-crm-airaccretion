<?php

namespace App\Http\Controllers;

use App\Models\BookingEmailTemplate;
use App\Models\BookingEmailTemplateRevision;
use App\Mail\BookingTemplateTestMail;
use App\Services\BookingEmailTemplateHistory;
use App\Services\BookingEmailTemplatePreview;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class BookingEmailTemplateController extends Controller
{
    public function edit()
    {
        $this->ensureAdminOrSuperAdmin();

        $template = BookingEmailTemplate::active();
        $historyReady = Schema::hasTable('booking_email_template_revisions');
        $revisions = $historyReady ? BookingEmailTemplateRevision::where('booking_email_template_id', $template->id)->with('author')->orderByDesc('version')->paginate(10, ['*'], 'history_page') : collect();

        return view('admin.pages.email-template.booking-confirmation', [
            'template' => $template,
            'variables' => BookingEmailTemplate::variables(),
            'revisions' => $revisions,
            'historyReady' => $historyReady,
            'currentVersion' => $historyReady ? (int) BookingEmailTemplateRevision::where('booking_email_template_id', $template->id)->max('version') : 0,
        ]);
    }

    public function update(Request $request)
    {
        $this->ensureAdminOrSuperAdmin();

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
            'expected_version' => ['nullable', 'integer', 'min:0'],
            'change_note' => ['nullable', 'string', 'max:500'],
        ]);

        $template = BookingEmailTemplate::active();
        abort_unless(Schema::hasTable('booking_email_template_revisions'), 503, 'Run the booking email history migration before saving.');
        app(BookingEmailTemplateHistory::class)->save($template, $validated['subject'], $validated['body'], Auth::id(), isset($validated['expected_version']) ? (int) $validated['expected_version'] : null, $validated['change_note'] ?? null);

        return redirect()
            ->route('admin.booking-email-template.edit')
            ->with('success', 'Booking email template updated successfully.');
    }

    public function restore(Request $request, BookingEmailTemplateRevision $revision)
    {
        $this->ensureAdminOrSuperAdmin();
        $data = $request->validate(['expected_version'=>['required','integer','min:0']]);
        app(BookingEmailTemplateHistory::class)->restore(BookingEmailTemplate::active(), $revision, Auth::id(), (int) $data['expected_version']);
        return redirect()->route('admin.booking-email-template.edit')->with('success', 'Template restored as a new version.');
    }

    public function sendTest(Request $request)
    {
        $this->ensureAdminOrSuperAdmin();
        $data = $request->validate(['subject'=>['required','string','max:500'], 'body'=>['required','string','max:30000']]);
        $recipient = trim((string) Auth::user()->email);
        abort_unless(filter_var($recipient, FILTER_VALIDATE_EMAIL), 422, 'Your account needs a valid email address.');
        try {
            Mail::to($recipient)->send(new BookingTemplateTestMail(BookingEmailTemplatePreview::render($data['subject']), BookingEmailTemplatePreview::render($data['body'])));
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['message'=>'The test email could not be sent. Check the mail configuration and retry.'], 502);
        }
        return response()->json(['message'=>'Test email sent to '.$recipient.'. No customer email was sent.']);
    }

    private function ensureAdminOrSuperAdmin(): void
    {
        $role = optional(Auth::user()->userType)->user_type;

        abort_unless(
            $role === UserType::SUPER_ADMIN
            || in_array($role, UserType::ADMIN_ROLES, true),
            403
        );
    }
}
