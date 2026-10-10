# P13 Data Integrity Audit

This audit documents the verified duplicate protection and the remaining safe checks for production. It does not change CRM business logic.

## Verified Existing Protection

1. Google Chat Pub/Sub events
   - Table: `google_chat_events`
   - Protection: `delivery_id` is unique, and the controller uses `insertOrIgnore`.
   - Result: repeated Pub/Sub deliveries do not create duplicate inbox events.

2. Email lead fetch
   - Table: `email_lead_logs`
   - Protection: `message_id` is unique.
   - Result: the same email message should not create duplicate CRM leads.

3. WhatsApp message ingestion
   - Table: `whatsapp_messages`
   - Protection: `provider_message_id` is unique when the provider sends it.
   - Result: repeated provider delivery should update/ignore instead of duplicating the message.

4. WhatsApp lead integration
   - Table: `whatsapp_lead_integrations`
   - Protection: lead and external message identifiers are unique.
   - Result: the same WhatsApp-origin lead should not be inserted repeatedly.

5. Call summary ingestion
   - Table: `call_summary_integrations`
   - Protection: `call_fingerprint` is unique.
   - Result: the same call summary refreshes an existing integration instead of creating duplicate follow-ups.

6. Ride reminders
   - Table: `ride_reminder_logs`
   - Protection: unique `ride_id + hours_before + channel`.
   - Result: the same ride reminder window should not send repeatedly for the same channel.

## Implemented Low-Risk Improvements

1. Sensitive log masking
   - Added masking through `App\Support\SafeLogContext::mask()`.
   - Applied to WhatsApp lead webhook failure logs and WhatCRM message webhook failure logs.
   - Call summary debug logging now masks phone numbers and hides summary preview unless `CRM_DEBUG_LOGS=true`.

2. Queue visibility
   - `php artisan queue:health` now shows latest failed job time and top failed job classes.
   - It does not print exception payloads or secrets.

3. Scheduler visibility
   - Added `crm:scheduler-heartbeat` and `crm:scheduler-health`.
   - This helps confirm the Laravel scheduler is actually running.

4. Production safety verification
   - Added `crm:production-safety-check`.
   - It checks production-critical config without printing secret values.

## Pending Manual Verification

Run these before deployment:

```bash
php artisan queue:health
php artisan crm:scheduler-heartbeat
php artisan crm:scheduler-health
php artisan crm:production-safety-check
php artisan crm:prune-technical-data --dry-run
```

## Do Not Change Without Business Review

1. Payment approval idempotency
   - Do not auto-block edits unless the current payment lifecycle is reviewed with accounts.

2. Invoice/voucher finalization rules
   - Do not add hard locks unless finance confirms when edits are still allowed.

3. Lead merge or duplicate customer logic
   - This affects sales ownership and must be reviewed separately.
