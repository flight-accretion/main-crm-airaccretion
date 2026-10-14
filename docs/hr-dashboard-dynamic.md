# Dynamic HR Dashboard

## Scope

The existing HR design now reads CRM data. No incentive eligibility, payout,
lead allocation, payment approval, or Operations workflow writes were added.
No migrations or new dependencies are required.

## Before and After

- Before: month filter only; KPI and work tables were placeholders.
- After: Today (default), Yesterday, and Custom From/To dates drive date-based data.
- Attendance comes exclusively from imported attendance for every role.
  Missing records remain Not Available. Single-day summary shows present / active
  staff; range summary shows present / recorded employee-days and missing coverage.
- Leads Created counts creations in the selected period. Want Service counts
  distinct leads whose service start date is in the period, excluding currently
  cancelled leads. Historical cancellation alone does not exclude a reactivated lead.
- Due follow-ups use the latest follow-up; active no-date entries remain visible
  when the selected range includes Today. Missed Follow-ups remains today's
  outstanding backlog, independent of the selected range.
- KPI uses the existing scoring engine. Lowest metric score is weakest; highest
  weight breaks ties. Display badges: 4-5 On track, 3 Watch, 1-2 Below.
  Missing configuration remains Not configured.
- Person rows open only that person's KPI in a new tab with the same dates.
  Date changes retain the person selection; authorization is checked server-side.
- Sales work uses existing completed/active/outreach rules and history cutover.
  Active lead status is fetched in bulk instead of once per candidate lead.
- Operations completed work counts distinct completed leads credited to the
  completing employee. Active counts currently in-progress assigned cases with
  that assignee's activity during the period. Pending tasks, unresolved reviews,
  and vendor follow-ups use their due date (opened date if no due date exists).
  Older pending cases appear in a separate overdue count. Operations outreach
  is N/A because this dashboard does not invent an untracked metric.
- Targets and missing manual KPI entries remain monthly. They show N/A when
  custom dates span multiple months; there is no target proration or new formula.
- Incentives remain Not configured for a later feature.

## Performance and Verification

- Ten employees per department page; all active employees remain accessible.
- Employee KPI/Sales work calculations cached for 60 seconds after scope checks.
  Attendance and Operations counts are read fresh; no automatic refresh loop.
- Thirteen new regression cases plus three existing attendance shift-policy
  cases passed through an isolated SQLite/controller harness. PHPUnit was not
  installed in this checkout, so the full PHPUnit suite was not run.
- Read-only local PostgreSQL check passed. Latest run: cold calculation 535
  queries / 1.25 seconds; cached repeat 25 queries / 0.14 seconds. These are local
  observations, not production capacity or CPU guarantees. Existing KPI resolvers
  still have a high cold query count that should be monitored under concurrency.
- Actual HR Blade fixture passed browser checks at 1440, 1024, 390, and 320 pixels:
  date controls, tabs, person new tab, mobile navigation, no page overflow or JS errors.
- PHP syntax and Git whitespace checks passed.
- Live deployment and full authenticated production browser testing were not done.

## Main Files

- app/Services/Hr/HrDashboardDataService.php
- app/Http/Controllers/Hr/HrDashboardController.php
- resources/views/admin/pages/hr/dashboard.blade.php
- resources/views/admin/pages/hr/partials/pagination.blade.php
- app/Http/Controllers/KpiDashboardController.php
- resources/views/admin/pages/kpi/dashboard.blade.php
- app/Services/Kpi/KpiWorkDoneService.php
- app/Services/Kpi/SalesAttendanceKpiResolver.php (date-binding correction only)
- public/assets/admin/css/crm/dashboards/hr-dashboard.css
- public/assets/admin/css/crm/responsive/hr-dashboard-mobile.css
- tests/Feature/Kpi/HrDashboardDynamicTest.php
