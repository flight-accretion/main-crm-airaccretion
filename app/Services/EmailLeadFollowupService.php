<?php

namespace App\Services;

use App\Models\EmailLeadLog;
use App\Models\Lead;
use App\Models\LeadFollowup;
use Illuminate\Support\Str;

class EmailLeadFollowupService
{
    public function createIfNeeded(
        Lead $lead,
        EmailLeadLog $emailLog
    ): void {
        if (
            !empty(
                $emailLog->followup_created_at
            )
        ) {
            return;
        }

        /*
         * Followed_by needs an actual
         * CRM representative.
         *
         * If lead is not assigned yet,
         * scheduler will create it after allocation.
         */
        if (
            empty(
                $lead->representative_user_id
            )
        ) {
            return;
        }

        LeadFollowup::create([
            'id' =>
                (string) Str::uuid(),

            'lead_id' =>
                $lead->id,

            /*
             * "Today's follow-up"
             */
            'next_followup_date' =>
                now(),

            'followup_note' =>
                $this->buildNote(
                    $emailLog
                ),

            'followed_by' =>
                $lead->representative_user_id,

            /*
             * Active
             */
            'status' => 1,
        ]);

        $emailLog->followup_created_at =
            now();

        $emailLog->save();
    }

    private function buildNote(
        EmailLeadLog $emailLog
    ): string {
        $parts = [
            'Lead received automatically from Email.',
        ];

        if ($emailLog->subject) {
            $parts[] =
                'Subject: '
                . $emailLog->subject;
        }

        if ($emailLog->customer_name) {
            $parts[] =
                'Name: '
                . $emailLog->customer_name;
        }

        if ($emailLog->customer_phone) {
            $parts[] =
                'Phone: '
                . $emailLog->customer_phone;
        }

        if ($emailLog->service_name) {
            $parts[] =
                'Service: '
                . $emailLog->service_name;
        }

        if ($emailLog->departure_date) {
            $parts[] =
                'Departure Date: '
                . $emailLog
                    ->departure_date
                    ->format('Y-m-d');
        }

        if ($emailLog->departure_time) {
            $parts[] =
                'Departure Time: '
                . $emailLog->departure_time;
        }

        if ($emailLog->passenger_count) {
            $parts[] =
                'Passenger: '
                . $emailLog->passenger_count;
        }

        /*
         * User requirement:
         * complete email message in follow-up note.
         */
        if ($emailLog->email_body) {
            $parts[] = '';
            $parts[] = 'Email Message:';
            $parts[] =
                trim(
                    $emailLog->email_body
                );
        }

        return implode(
            PHP_EOL,
            $parts
        );
    }
}