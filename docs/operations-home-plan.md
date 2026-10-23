# Operations Dashboard Implementation Plan

**Goal:** Implement the supplied Kimi dashboard and Operations menu tree without changing existing workflows.

**Architecture:** Add a read-only dashboard at `/admin/operations/dashboard`. Preserve `/admin/operations` as Daily Work Done, including its filters and exports. Use current case assignment, linked rides and KPI calculations. Clearly identify illustrative values where integration is deferred. No migrations or notification sends.

**Navigation:** Reorganize only server-authorized links. Operations gets Dashboard, KPI (dashboard, review, reschedule, refund sub-tree, vendor follow-ups, Daily Work Done, manual KPI), then the supplied ride/catalog/payment tools. Keep other permitted tools available without duplicate destinations. Super Admin can choose All, HR, Operations, Sales, or Accounts menu views; this changes presentation only, never identity or permissions. Default is All. HR/other existing role navigation remains intact.

**Compatibility:** The user's repeated request to complete the supplied design is the execution instruction. Separate dashboard URL is a conservative implementation choice pending any correction to the earlier route question. Existing public routes and controller actions are not renamed.

## Steps
- [x] Add failing isolated dashboard tests: personal/team scope, oldest cases, no writes, route preservation, static labels, escaping, empty state.
- [x] Implement read-only dashboard service/controller/route and responsive view/assets matching the supplied layout.
- [x] Test and implement authorized-only Operations tree, deduplication and Super Admin presentation selector, including mobile More.
- [x] Run dashboard and existing workflow tests, browser checks at 320/390/768/1024/1440px and all-role navigation tests.

## Review Focus
- Personal cards must not count another employee's cases or expose their financial data.
- Empty data must be an honest empty state, not invented customer records.
- Illustrative financial/KPI values must not appear as live facts or trigger writes.
- Department switching must preserve all authorized destinations in All and never grant additional permissions.
- Long labels, errors, storage-disabled browsers and mobile More must remain usable.

## Data And Usage
- Operations staff see personal cases and bookings linked by active assignment or open assigned cases. Admin/Super Admin see team totals.
- Rides use existing voucher, cancellation and TBA rules. Confirmation and partial-payment indicators use the latest created follow-up, with ID tie-breaking; no new lead status is introduced.
- Due today uses the case follow-up date. Age bands use opened dates. Counts link to the appropriate read-only case selection or existing queue.
- Reschedule expiry uses the existing original-date-plus-90-days policy; no reminder job is triggered here.
- Refund totals use pending customer/vendor records. Part-paid balances use the latest follow-up amounts for rides within seven days; these are not a new payment reconciliation engine.
- KPI scores reuse the current 1-5 calculation. Missing configuration is identified, not fabricated.
- Only the incentive projection is sample data; it does not create eligibility or a payout.
- Super Admin selects Workspace above the desktop sidebar, or inside mobile More. The choice persists per user/browser and does not change permissions. All tools restores every permitted destination.
- Existing login redirects and `/admin/operations` Work Done remain unchanged. Open the new dashboard through the Operations Dashboard menu.

## Verification
- 61 affected feature tests, 534 assertions passed, including Work Done, manual KPI permissions, review, reschedule, upcoming rides, vendor follow-ups, and the new dashboard.
- Actual local PostgreSQL reads and full shared-layout rendering passed for Operations and Super Admin, including all-cases/due-today selections, inside enforced read-only transactions.
- Full-shell dashboard presentation checked at 320/390/768/1024/1440px in light/dark modes. External widget scripts are excluded in this isolated presentation test; shared menu scripts run normally.
- All 12 role trees preserve authorized destinations; all 24 role/area mobile-navigation checks passed. Seven representative Operations/workspace scenarios passed.
- No live deployment, notification delivery test, database mutation, commit or staging operation is part of this task.
