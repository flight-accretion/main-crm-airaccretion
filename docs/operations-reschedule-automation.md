# Reschedule Window Automation

## Deployment

1. Back up the database using the deployment's standard process.
2. Run `php artisan migrate --force`. The new migration adds the resource lock registry and a nullable indexed aircraft/vehicle identifier on rides. It does not assign identifiers to old bookings.
3. Keep the existing Laravel scheduler running every minute. `operations:process-reschedule-windows` is registered hourly in Asia/Kolkata.
4. After checking original service dates and case ownership, run `php artisan operations:process-reschedule-windows` once if immediate catch-up is needed.

## Behavior

- The original service date is immutable across reschedules. Expiry is original date plus 90 calendar days; the expiry date itself remains valid until local midnight.
- At ten days remaining (day 80), an internal customer-call task is created for the case owner. Missed scheduler runs catch up inside that window. Unassigned tasks remain visible for Operations allocation.
- Expired open cases create a separate refund-eligibility assessment task. This is not a refund, invoice, payment, cancellation or approval.
- Parent-case row locks make repeated scheduler calls idempotent. Existing completed reminders/assessments are not recreated.
- Confirming a new date or cancelling through the reschedule page supersedes still-open linked tasks. Dates changed outside that page are reconciled by the hourly job.
- Accounts and Admin can record the final no-refund decision. Operations can recommend review or request more information. A refund referral stays open until handled manually; the existing refund workflow remains the only place where money is processed.
- Assessment activity is append-only. Scheduler activity has no human actor and is excluded from human-work counts.

## Conflict Coverage

- Use the same canonical aircraft/vehicle identifier on every booking for that physical resource, such as `VT-UML`. Case and whitespace are normalized.
- Availability is checked before save and again under a per-resource database lock when Eloquent creates/updates an assigned ride. Overlaps are blocked; adjacent slots are allowed.
- Assigned fixed-date rides require explicit start and end times. Unknown end times on older assigned bookings conservatively block overlapping proposals.
- Cancelled bookings (latest lead follow-up status) and no-date rides do not reserve a slot. Cancellation remains lead-scoped because that is the existing CRM status model.
- Bookings without a resource identifier cannot be checked. A clear result means no overlap among identified CRM rides, not external vendor availability.
- Direct SQL/bulk query updates bypass Eloquent protection. Imports must use model saves or explicitly call the conflict service. No external aircraft inventory integration is included.

## Verification

`tests/Feature/Kpi/OperationsRescheduleTest.php` covers date preservation, reminder catch-up/idempotency, expiry boundaries, task superseding, role restrictions, no financial writes, overlap rejection and save rollback. Local browser verification covers responsive list/editor/assessment layouts and conflict UI responses.
