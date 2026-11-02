# MSG91 WhatsApp campaign module

## Safety posture

- This module is separate from the existing WhatsCRM, booking, voucher and Operations WhatsApp flows.
- It is disabled by default. The MSG91_CAMPAIGNS_ENABLED setting prevents campaign queueing and sending until it is explicitly enabled.
- The browser never receives the MSG91 auth key and cannot choose a sender number.
- A campaign requires: an active non-expired subscription, a verified active sender, a fresh approved sender-template binding, an in-scope lead, a valid normalized phone and no suppression entry.
- A provider acceptance response is stored as **accepted**, never as delivered.

## Local installation

1. Put the private shared key in MSG91_CAMPAIGNS_AUTH_KEY; never commit it.
2. Keep MSG91_CAMPAIGNS_ENABLED=false during setup.
3. Set a long random MSG91_CAMPAIGNS_WEBHOOK_SECRET.
4. Run php artisan migrate, then php artisan view:clear.
5. As Admin or Super Admin, open Sender access and record the paid subscription, integrated number and admin verification for each permitted employee.
6. Create or sync only templates approved for the specific sender number. Store an allowlisted component-to-field mapping.
7. Configure the callback URL as https://YOUR-CRM.example/api/webhooks/msg91/whatsapp/campaigns and send the configured secret in the X-MSG91-Webhook-Secret header.

Supported CRM fields are customer_name, product_name, service_date, next_followup_date, and salesperson_name.

## Controlled pilot

1. Set MSG91_CAMPAIGNS_DRY_RUN=true and MSG91_CAMPAIGNS_ENABLED=true.
2. Select controlled test leads from Leads or Today’s Follow-up and open Bulk WhatsApp.
3. Confirm the preview’s eligible, duplicate, invalid and suppressed totals.
4. Confirm employee sender and approved template before queueing.
5. Review Campaign Records and recipient events. Dry-run acceptance is not delivery verification.
6. Only after template, callback and delivery behavior are verified with approved test contacts may an authorized operator set MSG91_CAMPAIGNS_DRY_RUN=false.

## Workers and retries

Use the project’s existing production worker and scheduler convention. A local verification command is php artisan queue:work --tries=1 --timeout=60.

- Timeout or connection uncertainty becomes unknown and is not resent automatically.
- Only explicit retryable provider failures retry with capped exponential backoff and rate limits.
- Invalid destination, suppression, sender loss, subscription expiry and template approval loss are terminal.
- A callback updates accepted, sent, delivered or read; HTTP success alone is not delivery.

## Before enabling production

- Confirm the exact MSG91 template lookup, creation, response and callback formats against the company test account.
- Capture redacted fixtures before activating any live template synchronisation.
- Review phone ownership, contact permission policy and the company-wide suppression list.
