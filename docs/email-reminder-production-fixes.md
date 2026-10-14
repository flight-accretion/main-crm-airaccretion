# Email Encoding and Reminder Schema Fixes

## Email

Before: transfer encoding was decoded without converting MIME charsets, and nested
multipart fallback could parse raw MIME/attachments. Invalid body/JSON strings
failed PostgreSQL inserts repeatedly.

After: recursively select text/plain, then text/html; skip named attachments and
non-text parts; decode transfer encoding and convert the declared charset to UTF-8.
Valid UTF-8 is preserved. Missing/unknown charset with invalid bytes falls back to
Windows-1252; unknown original encodings cannot be perfectly inferred. Remove NUL
characters rejected by PostgreSQL. Normalize parsed JSON values and keys before
saving. Existing source selection, phone validation, allocation and duplicate rules
are unchanged. A message without readable text follows the existing missing-phone
error-log path rather than treating attachment bytes as a customer enquiry.

## Reminders

Before: production legacy reminder_type/template_name and possibly sent_at could
remain NOT NULL while the current writer omits obsolete fields and queues a pending
row with sent_at = null.

After: new migration 2026_10_14_130000 relaxes only those nullability constraints.
History, types, defaults, current fields and unique delivery indexes are retained.
Rollback is intentionally a no-op to avoid breaking current pending rows. Existing
migrations were not edited. Reminder timing, recipients and sending rules unchanged.

## Verification

- Seven email encoding/MIME cases passed through an isolated harness.
- SQLite reproduced the old NOT NULL failure, then accepted the current model after
  migration; repeated application, rollback history and delivery uniqueness checked.
- Real local PostgreSQL tested the migration and real EmailLeadService text/JSON
  persistence, duplicate protection and missing-phone behavior in an isolated schema.
  The schema was rolled back; application records were untouched. No emails or
  WhatsApp messages were sent.
- Live mailbox fetching and live delivery were not tested. PHPUnit is absent from
  this checkout; standard regression test files are included for a dev/CI runner.

## Deployment

Back up the live database and deploy the complete verified code package. Check
`php artisan migrate:status` before running `php artisan migrate --force`, since the
command applies all pending migrations. Run the migration and `queue:restart` as
www-data inside main-crm-app. Existing watchdog should restart exited workers.
Confirm normal cron runs no longer produce the encoding/legacy-column errors.
No new packages, secrets, schedules or recipient settings are needed.
