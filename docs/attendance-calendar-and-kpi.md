# Attendance Calendar and Presence KPI

## Scope and Compatibility

Attendance Import and Settings use the approved light design with responsive
mobile controls. Existing office-time policies, their permissions, and stored
shift snapshots remain. Calendar writes require HR or Super Admin at both the
route and controller. No payroll, extra-pay, cron or queue changes are included.

The shared `KpiWorkingDayService` is unchanged. Only the attendance resolver
(Sales and Operations) uses the new actual calendar rather than a fixed 22-day
cap. Other KPI metrics retain their existing working-day calculations.

## Working Dates

- Each employee has effective-dated weekly-off schedules. A schedule can contain
  zero or multiple weekdays. Until a schedule applies, Sunday remains the default.
- HR selects actual holiday dates for a month/year, not only a numerical count.
  Additional holidays and inclusive total-off lists are merged with each person's
  weekly offs. The union removes overlaps once. Existing approved KPI date
  exclusions remain honored.
- Saving a schedule at a new effective date preserves earlier schedules. Explicit
  corrections at the same effective date and monthly holiday edits are audited;
  they intentionally recalculate the affected attendance periods.
- An approved swap links one non-working day with imported P/MIS presence to one
  different scheduled working day. Dates may cross months. Dates cannot be reused
  in multiple swaps. Calendar edits that invalidate approved swaps are rejected;
  undo the affected swap first. Approval and undo are audited.
- Attendance on an off-day without an approved swap cannot offset an absence.

## Attendance KPI

`P` and `MIS` count as present even when a punch is missing. `A` counts as absent.
Other codes remain raw source/audit data but do not count as present or absent.
They are not inferred to be holidays; holidays come from the HR calendar.

Presence percentage = Present P/MIS scheduled days / known A/P/MIS scheduled days.
100% maps to 5/5 under the existing attendance rating bands. The dashboard shows
monthly working days, present/eligible days, MIS counts and provisional coverage.
Missing and ignored rows never silently become absence. An incomplete import is
provisional: its score reflects observed records, not a final full-month result.
Future working days are not judged. Shift/late-arrival evidence stays separate;
missing In time is not automatically counted as late or absent.

## Deployment and Verification

Run `php artisan migrate --force` before enabling the new forms/import logic.
The additive migration creates calendar/schedule/swap/audit tables and a nullable
indexed import hash; existing attendance/import rows are not rewritten. Preserve
backups and deploy all changed PHP, Blade, CSS and JS together. Rebuild compiled
views using the container web user as in the normal deployment process.

Focused SQLite tests cover calendar overlaps, effective dates, cross-month swaps,
P/MIS presence, unapproved extra attendance, incomplete data, HR authorization,
validation/audit behavior, additive migration/rollback and actual 5/5 dashboard
scoring. Local/production migrations and production performance are not implied
by isolated tests. No salary calculations or external messages are performed.

Verification also covers future-dated rows, future-month planning, legacy KPI
calendar mutation guards, and a full template/preview/confirm/presence flow through
the real application services. Empty attendance issues no shift-policy queries;
bulk shift resolution fetches assignments/default policy once, not per date.
Real controller-rendered Import/Settings pages were checked at 1440, 1024, 390
and 320 pixels, with separate large-preview and light/dark browser checks.

Full PHPUnit is unavailable in this checkout. Focused test methods were run with
a lightweight local adapter, plus standalone importer suites. Simultaneous live
PostgreSQL race tests and authenticated production smoke tests remain outstanding.
