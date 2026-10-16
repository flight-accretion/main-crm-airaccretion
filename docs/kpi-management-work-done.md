# KPI Management and Work Done

## Approved Scope

Page-scoped Kimi light palette, dark-toggle compatibility, responsive metric rows,
mobile table cards and permission-derived Dashboard/Leads/Follow-up/KPI/More bar.
Preview banners, illustrative people and hardcoded sample totals are not shipped.

HR now shares every existing KPI Management endpoint with Super Admin: template
and metric creation/update, assignments, working calendar, manual values, sync,
and Accounts/Operations team memberships. Other roles remain denied. No unrelated
route permission or global role group was broadened. Existing validation and
CSRF-protected methods remain in use. Calendar swap guards are retained.

## Management Presentation

Department rubrics prefer actual active CRM template metrics; defaults are shown
only when no active template is available. Source pills and automatic/manual
counts reflect measurement types, not the sample screenshot. Assignment coverage
counts distinct currently eligible active employees with effective assignments.
The displayed update timestamp is explicitly a template update, not a fabricated
last-sync timestamp. Sync still uses the existing department-specific endpoint.

Existing configuration forms remain accessible in an expandable management area;
Assign/Manual links open that area. View/Edit opens each template's editor and
its existing metrics. No scoring rules or weights are silently changed by design.

## Work Done Data

Team summary and detail actions preserve existing business definitions and scope.
The new breakdown batches person events, latest active follow-ups, outreach counts
and active KPI assignments; it does not issue a database query per employee.
Each person's lead buckets use completion > cancellation > active precedence,
matching the existing rules within that person's activity. Shared leads can
appear for multiple people: row totals must not be added to derive the unique
team total. Historical lead tracking gaps remain N/A, not invented zero activity.

Daily outreach target comes from the person's effective active KPI template and
sales_outreach metric, not a hardcoded 50. Missing configuration is explicit.
Daily-target progress is shown only for a single selected day, not against a
multi-day total. Breakdown queries are opt-in for Work Done; existing HR summary
calls do not fetch the new breakdown.
Outreach DNP retains its existing meaning and remains included in the existing
outreach-completed total; this redesign does not redefine completion rules.

No mid-day reminder, notification, conversion formula, scheduler, or new migration
is introduced for these two pages. Pending attendance migration from the earlier
task is separate and still required when deploying all pending changes together.

## Verification

Focused SQLite tests exercise role middleware for all management routes, real
HR template/metric/manual writes, actual-controller Blade rendering, scoped
breakdown/cutover, shared lead precedence, configured targets and query counts.
Browser checks exercise desktop/mobile layouts, light/dark styles, editor links,
custom dates, existing details action and fixed authorized navigation. Browser
detail responses are mocked; real data queries are covered separately.

Full PHPUnit and authenticated live smoke/load testing remain unverified.
No commit, push, live migration or deployment is part of this implementation.
