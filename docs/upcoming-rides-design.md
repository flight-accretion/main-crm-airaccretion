# Upcoming Rides list and calendar

The existing `/admin/rides/upcoming-ride` page now has List and Calendar tabs. Both views and CSV export use the same filtered `ridesData` projection. Existing calendar-events and ride-details API contracts, route roles, lead statuses, payment approvals and booking writes are unchanged.

- Default period: today through the next 13 days, inclusive. Existing explicit date, representative, status, product and time filters remain available.
- Calendar uses the bundled FullCalendar 5 library. Previous/next/Today load the selected month using the existing page route and preserve non-date filters. Selecting a day shows its rides and an action to open that day's list.
- Last selected tab is stored locally per signed-in user. An explicit `view=list` or `view=calendar` URL overrides that preference.
- Payment balances retain the existing calculation based on approved PaymentAuditTrail entries. A zero balance without a recorded billable total is not labelled fully paid.
- TBA within 48 hours refers only to unconfirmed service time, not a new ride/lead status. Vendor assigned/unassigned is derived from existing vendor-payment records; assignment does not imply vendor confirmation. No In transit status, reminders or new confirmation workflow was introduced.
- Read-only View details and Open lead actions retain contact, route, service, extra services, passenger count, occasion and description. Existing server-side lead-access checks still apply when opening a lead.
- CSV includes the current filtered rows and neutralizes formula-leading text. No mail, WhatsApp, payment or status action is triggered by this page.
- Mobile uses stacked list rows and calendar event strips with day details below the calendar.

Verification covers projection calculations, real controller queries with isolated SQLite fixtures, escaped customer text, filtered CSV, desktop/mobile interactions, and local full-layout rendering for Sales, Operations and Super Admin. No database migration is needed.
