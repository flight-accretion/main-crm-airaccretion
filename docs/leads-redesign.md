# Leads List Redesign

## Scope

The existing shared `/admin/lead` view uses the approved Kimi light palette with
page-scoped desktop and mobile CSS. The existing dark toggle is retained.
No routes, role middleware, lead ownership rules, cron jobs, booking/payment
workflows, message sending, or database schema were changed.

## Filters

- Default list order remains newest-created first; column sorting applies only
  to the loaded page, as with the previous client-side table.
- All Leads and In Queue tabs, allocation filtering, direct transfer, bulk
  transfer/cancel, and delete remain Super Admin-only.
- Hot uses the latest completed AI score and existing configured thresholds.
  Numeric scores take precedence over stored temperature. Missing/pending AI
  scores are not Hot. Reading this list never creates AI settings or queues AI.
- Date filtering uses the latest-created follow-up (UUID breaks timestamp ties),
  not an earlier follow-up with a matching date. Range endpoints include the
  full day in the application timezone. Overdue uses existing open-status rules.
- No Follow-up Date means the latest follow-up has no date, or no follow-up
  exists. The separate Today Follow-up page is unchanged.
- Service, Created, and Follow-up ranges can coexist. Switching the date editor
  does not erase previously entered ranges; active ranges are shown below it.
- Tab counts obey the existing user scope and other selected list filters.
- New queue/follow-up filters also apply to the existing server-side export.
  The existing export columns and pre-existing export-specific rules remain.

## UI Compatibility

Existing action URLs, delegated click handlers, lead IDs, cancellation notes,
voucher eligibility, repeat-lead eligibility, transfer controls and follow-up
history popup are retained. HR sees read-only controls, matching route access.
The table shows Name and Phone in separate columns and Product without service
text. Email is omitted from the table; existing detail pages, email filtering
and export fields are unchanged. Created/updated dates remain in Details.
The Action column has more space. Pagination stays server-side with the existing
10/25/50/100 sizes.

On mobile, the same rows become cards. The fixed bottom bar contains Dashboard,
Leads, Follow-up, KPI and More. Operations links to its existing vendor follow-up
page instead of the sales-only Today Follow-up route. More reads the already
authorized server-rendered sidebar; it does not invent additional permissions.

## Verification

- Ten isolated SQLite filter regressions in `tests/Feature/LeadListFilterTest.php`.
- Nine controller integration regressions in
  `tests/Feature/LeadListControllerIntegrationTest.php`, covering real list queries,
  scoped tab counts, CSV/XLSX filtering, validation/access errors, missing AI
  settings and table rendering with completed AI scores. These caught and fixed
  missing controller integration that service-only tests did not detect.
- Actual Blade render and browser checks for six roles at 1440, 1024, 390 and
  320 pixels; dates, history, cancel popup, More, and fixed bottom navigation.
- Earlier read-only local PostgreSQL checks were smoke checks, not proof of
  filter integration. The controller regressions use an isolated in-memory
  database, without AI calls, messages or changes to CRM business records.
- PHP lint and Git whitespace checks.

The checkout has no PHPUnit binary. Regression methods were executed with a
local lightweight runner; a complete PHPUnit suite was not run. Production
deployment and authenticated live end-to-end tests are not part of this change.

## Deployment

Deploy all changed/new PHP, Blade, CSS and JS files together. No new migration
or package install is required for this redesign. Clear/rebuild compiled views
using the existing deployment process, as the container's web user. Verify the
list as Super Admin, HR, Sales Executive/Manager and Operations before declaring
the live rollout complete. Do not change live role middleware for this design.
