# Service Addresses Design

The service-address list uses the shared Kimi styling with dynamic summaries, city chips, search, status filters and server-side pagination. Address, contact person and contact number are separate columns. Product/service links and contact flags remain visible. Shared-number flags count distinct nonempty digit-normalized phone numbers reused by multiple records; they are advisory and never block saving.

The table now defaults to 10 rows. The existing Rows selector allows larger pages and preserves GET filters.

Add Address, Edit and View Address open accessible dialogs. Every row shows Activate or Deactivate according to the existing status. Status changes require confirmation. Dialog loads verify record identity, discard stale requests and clear previous details on failure. Map URLs are only clickable for HTTP/HTTPS.

## Compatibility

- Existing routes, middleware, create/update validation, transactions, status toggles and stored phone format are retained.
- Service remains required, unlike the optional field in the reference mockup.
- Country/state/city and map link remain available in the dialogs.
- The calling code is an explicit field such as +91, separate from the national number. Enter digits only in the national-number field.
- Existing active product/service options remain available; edit also retains an assigned inactive product/service rather than silently clearing it.
- Validation errors reopen the relevant dialog with prior input and the edit record ID.
- Direct create/edit/view routes remain available for compatibility; list actions use dialogs.
- Missing legacy geography no longer prevents the edit-details endpoint from loading.
- No migrations, notifications, deletion actions or new booking policies were introduced.

## Verification

`ServiceAddressesDesignTest` exercises dynamic summary/columns, city/status filters, create/update persistence, phone format, validation recovery, view/edit identity, missing legacy geography, escaping, status toggles and Operations/Sales access.

`tests/Fixtures/service-addresses/render.php` uses only synthetic in-memory SQLite data. `tests/Browser/ServiceAddressesDesign.standalone.cjs` checks 1440, 1024, 768, 390 and 320px viewports, popup loading/error/close, correct status targets, cancellation, country/state/city dependencies, create/update submission payloads and horizontal overflow. Browser writes are intercepted, not sent to the CRM.

Targeted combined regression: 70 tests, 519 assertions passed. All five browser widths passed. A full page render using the actual shared CRM header also passed against the local PostgreSQL database inside a read-only transaction. These checks do not certify unrelated CRM pages or production notification delivery.
