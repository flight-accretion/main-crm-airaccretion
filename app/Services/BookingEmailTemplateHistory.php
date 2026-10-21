<?php

namespace App\Services;

use App\Models\BookingEmailTemplate;
use App\Models\BookingEmailTemplateRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingEmailTemplateHistory
{
    public function save(BookingEmailTemplate $template, string $subject, string $body, ?string $actorId, ?int $expectedVersion = null, ?string $note = null): void
    {
        DB::transaction(function () use ($template, $subject, $body, $actorId, $expectedVersion, $note) {
            $locked = BookingEmailTemplate::whereKey($template->id)->lockForUpdate()->firstOrFail();
            $latest = (int) BookingEmailTemplateRevision::where('booking_email_template_id', $locked->id)->max('version');
            if ($expectedVersion !== null && $expectedVersion !== $latest) {
                throw ValidationException::withMessages(['template'=>'This template changed in another session. Reload before saving or restoring.']);
            }
            if ($latest === 0) {
                $this->snapshot($locked, 1, $locked->updated_by, 'Original template');
                $latest = 1;
            }
            $locked->fill(['subject'=>$subject, 'body'=>$body, 'updated_by'=>$actorId])->save();
            $this->snapshot($locked, $latest + 1, $actorId, $note);
        });
    }

    public function restore(BookingEmailTemplate $template, BookingEmailTemplateRevision $revision, ?string $actorId, int $expectedVersion): void
    {
        abort_unless($revision->booking_email_template_id === $template->id, 404);
        $this->save($template, $revision->subject, $revision->body, $actorId, $expectedVersion, 'Restored from v'.$revision->version);
    }

    private function snapshot(BookingEmailTemplate $template, int $version, ?string $actorId, ?string $note): void
    {
        BookingEmailTemplateRevision::create([
            'booking_email_template_id'=>$template->id, 'version'=>$version,
            'subject'=>$template->subject, 'body'=>$template->body,
            'created_by'=>$actorId, 'change_note'=>$note ?: 'Template updated',
        ]);
    }
}
