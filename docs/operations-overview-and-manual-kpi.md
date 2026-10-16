# Operations Overview and Manual KPI

## Access

- HR has read-only access to Operations Overview (`/admin/operations`) and its filters through an Operations Work Done sidebar link. The Overview GET route has a separate role guard; the existing Operations route group is unchanged. HR does not gain queue, history, reschedule, follow-up, lead-assignment or status-mutation access. Overview count cards omit queue links for HR.

- HR and Super Admin may enter Operations employees' manual values and approve/reject submissions.
- Active Operations staff may only submit their own assigned manual metrics for the selected month. The active assignment is resolved as of today for the current month, month-end for past months, and month-start for future months.
- Self-entry is checked on the server, including employee identity, effective assignment, active template/metric, and the existing approved manual metric codes. Forged review requests are denied by both route middleware and controller checks.
- Operations staff see only their own pending/history records; managers do not gain team submission visibility through this page.
- Draft/pending/rejected records do not write KPI values. Approval uses the existing manual value storage and calculation path. Review transitions lock and recheck the pending record in a transaction.

## Presentation

- Responsive labelled forms, named months, metric weight/rating-band preview, history month/year/employee filters, and a required rejection-reason dialog.
- Real records and empty states only. No illustrative preview employees or sample statistics are shipped.
- Operations Overview is now Operations Work Done: Completed, In Progress, Pending and Total unique cases worked, status composition, four work-category summaries, and per-employee totals. Recent Activity and its pagination/queries are removed from this page; audit records are not deleted or changed.
- The page defaults to Today, with Yesterday, This Month, Custom dates and All dates plus an employee filter. Existing bookmarked type/status/customer-name filters remain supported by the summary backend. Queue/history and workflow routes are unchanged.
- Work means a case with at least one human (`user_id` non-null) activity during the selected period. System-only opening events are not employee work. Repeated actions on a case count once in team/category totals. The status shown is the case's **current** status, not its status as of the historical date or a count of completion transitions. These totals intentionally replace the old open-backlog counts.
- Employee rows count distinct cases that each employee acted on. If multiple employees worked on the same case, their rows overlap; summing employee rows is not the unique team total. Staff who acted but are now inactive remain credited; unknown/deleted staff are labelled Former employee. Employee filtering is by recorded actor, not current case assignee.
- SQL aggregates deduplicate before counting and batch-load employee names. No activity records or customer details are loaded for the initial summary display; query count does not grow per employee/case. Mobile uses labelled stacked employee rows and the shared role-aware bottom navigation.
- Completed/In Progress/Pending/Total cards, category totals, and category status counts open an inline case-details table. Details are fetched only when selected through the read-only `admin.operations.work-details` route, with the same role guard as the overview. HR can view this table but no mutation controls or restricted queue links are included.
- Drill-down reuses the summary's human-work/date/employee/type/status query. Case IDs are deduplicated. Columns are customer, phone, category, current status, current assignee, opened and completed dates, with 25 cases per page. Employee filtering refers to the recorded worker; Assigned To is the current assignee. Pagination preserves the applied filters and card selection; unsaved filter changes do not silently alter the selected summary basis.
- Switching cards aborts obsolete requests; closing details cancels loading and restores focus. Empty selections and request failures have explicit states. Detail HTML escapes customer-supplied values and the response is private/no-store.
- Lead/payment/ride mutations, KPI formulas, reminders and other history pages are unchanged.

## Deployment

No new migration is required for this feature. The existing Operations, KPI core and manual-submission migrations must already be installed. Ship the page views (including `operations/partials/work-details.blade.php`), controller/model/route/sidebar updates, `OperationsWorkSummaryService`, `operations-work.js` and Operations CSS alongside the previously added shared KPI CSS/JS/mobile-navigation partial. Refresh Laravel route/view caches using the deployment's normal application-user procedure. Deploy the new GET details route and versioned CSS/JS together.

## Verification

`tests/Feature/Kpi/OperationsManualAccessTest.php` exercises self submission, effective assignments, cross-user isolation, reviewer middleware, approved-only value writes, rejection reasons and HR read-only overview access. `OperationsWorkSummaryTest.php` covers unique-case counting, human/system distinction, overlapping employee contributions, date/type/status/employee filters, empty periods, real rendering without the activity log and constant query count. Tests use isolated in-memory databases. Desktop/mobile browser checks exercise real rendered summary views and local assets at 1440, 1024, 390 and 320 pixels, including dark mode, custom dates and fixed bottom navigation.
