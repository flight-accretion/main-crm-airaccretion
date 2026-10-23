# Operations Dashboard Implementation Plan

**Goal:** Implement the supplied Kimi dashboard and Operations menu tree without changing existing workflows.

**Architecture:** Add a read-only dashboard at `/admin/operations/dashboard`. Preserve `/admin/operations` as Daily Work Done, including its filters and exports. Use current case assignment, linked rides and KPI calculations. Clearly identify illustrative values where integration is deferred. No migrations or notification sends.

**Navigation:** Reorganize only server-authorized links. Operations gets Dashboard, KPI (dashboard, review, reschedule, refund sub-tree, vendor follow-ups, Daily Work Done, manual KPI), then the supplied ride/catalog/payment tools. Keep other permitted tools available without duplicate destinations. Super Admin can choose All, HR, Operations, Sales, or Accounts menu views; this changes presentation only, never identity or permissions. Default is All. HR/other existing role navigation remains intact.

**Final sidebar revision:** Operations and the Super Admin Operations view now use OVERVIEW, RIDE MANAGEMENT, OPERATIONS QUEUES, LEADS & PAYMENTS, and MASTER DATA & TOOLS. KPI contains only KPI Dashboard, Daily Work Done, and Manual KPI. Queues are top-level links, with Customer/Vendor Refund nested under Refund Queue. Leads contains only View Leads: Add Lead and DNP menu links are intentionally hidden for this view, not revoked in the backend. Services & Products groups the five authorized catalog links; Vendors and Import Leads are direct links. Other existing authorized tools remain under Other tools. Desktop headings and mobile More share the same tree; other roles and Super Admin All tools retain their existing links.

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
- Incentives now reuse TargetIncentiveService for the employee's current active target and existing qualifying company/product sales rules. No sample amount or invented criteria remain. Missing/disabled configuration is explicit; duplicate active targets show a configuration warning instead of double-counting. Admin team view does not invent a personal payout. This read-only estimate never creates eligibility or a payout.
- Operations mobile tabs are Dashboard, Voucher Generation, Upcoming Rides, More. More mirrors the entire desktop tree. Department KPI is removed from Operations and Super Admin's Operations view; other workspaces keep their existing permissions and KPI links.
- Super Admin selects Workspace above the desktop sidebar, or inside mobile More. The choice persists per user/browser and does not change permissions. All tools restores every permitted destination.
- Existing login redirects and `/admin/operations` Work Done remain unchanged. Open the new dashboard through the Operations Dashboard menu.

## Verification
- 61 affected feature tests, 534 assertions passed, including Work Done, manual KPI permissions, review, reschedule, upcoming rides, vendor follow-ups, and the new dashboard.
- Actual local PostgreSQL reads and full shared-layout rendering passed for Operations and Super Admin, including all-cases/due-today selections, inside enforced read-only transactions.
- Full-shell dashboard presentation checked at 320/390/768/1024/1440px in light/dark modes. External widget scripts are excluded in this isolated presentation test; shared menu scripts run normally.
- All 12 role trees preserve authorized destinations; all 24 role/area mobile-navigation checks passed. Seven representative Operations/workspace scenarios passed.
- No live deployment, notification delivery test, database mutation, commit or staging operation is part of this task.

## Follow-up Verification Notes
- Dashboard rendered successfully with the local PostgreSQL database under enforced read-only transactions after dynamic incentive integration.
- The broader 124-test selection initially surfaced four inherited lead-list fixture-count failures. Resolved during release preparation by extracting LeadListFixtureTestCase: lead-list tests retain their original assertions and fixtures, while incentive tests reuse setup without inheriting unrelated test methods. No test is skipped or assertion weakened. The final affected feature run passes all 145 tests / 1,172 assertions without exclusions.
- Sandboxed runs also reported inaccessible phone metadata; repeating outside sandbox removed those metadata errors and isolated the four fixture-count failures above.
- Mobile regression testing caught two-line tab labels overlapping the Call/WhatsApp action bar. Fixed icon/label spacing preserves the existing bottom-bar height; all-role navigation tests were rerun.
- Ride Status's large diff was verified as formatting plus the requested removal of export controls; whitespace-normalized script and remaining template tokens match HEAD. Stale-request/wrong-record, pending-save lock, timeline and protected-receipt tests pass. Removed dead export markup and trailing whitespace without reverting the user's formatting.
- Release evidence and remaining server-side deployment steps are recorded in deployment-readiness-2026-10-23.md.
