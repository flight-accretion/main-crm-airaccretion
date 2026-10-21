# Booking Email Editor

The editor remains at `/admin/booking-email-template`, restricted to the existing Admin/Super Admin roles. Only the existing Booking Confirmation template is listed. Customer email generation, supported variables, payment processing, WhatsApp sends, and send timing are unchanged.

## Deployment

Run the additive migration before enabling saves:

```sh
php artisan migrate --path=database/migrations/2026_10_21_190000_create_booking_email_template_revisions_table.php --force
```

It snapshots the existing template without changing its subject or body. Every subsequent save appends a revision; restoring an old revision creates a new live revision. An expected-version check rejects stale browser saves/restores. The migration rollback removes revision history only, not the current template.

The preview uses clearly marked synthetic booking values, not customer or bank records. It preserves the current plain-text format; it does not introduce HTML templates or new scheduled sends. Actual booking sends can additionally apply lead-specific payment formatting and reference suffixes through the existing service.

## Test Email

`POST /admin/booking-email-template/test` renders the unsaved subject/body with the same sample values and sends only to the authenticated user's account email. Request-provided recipient/CC/BCC fields are ignored. The dedicated test mailable bypasses Operations CC and does not invoke the customer-send service, create lead notes, send WhatsApp messages, or update the saved template. The subject begins with `[TEST]`. The endpoint is CSRF protected and rate limited to five requests per minute. It requires the existing mail configuration.

## Verification

Focused coverage: `tests/Feature/Kpi/BookingEmailEditorTest.php` and `tests/Unit/BookingEmailTemplateDesignTest.php`. Test outbound mail with `Mail::fake()`, never a real recipient. Browser checks should use intercepted test-send responses and synthetic template data.
