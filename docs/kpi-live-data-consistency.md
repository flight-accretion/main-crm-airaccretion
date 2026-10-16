# KPI and Work Done data consistency

## Fixes

- HR KPI and Sales Work Done results are no longer cached across requests for 60 seconds.
- HR selected-period sales are calculated from approved CRM payments and the existing net-sales calculator, not stored target achieved_amount values. Monthly target amounts use the existing carry-forward resolver for eligible sales staff.
- KPI target achievement uses selected payment dates and employee ownership, independently of the month of the carried-forward target record. Dashboard reads do not update target records. Multi-month target denominators sum applicable monthly amounts without daily proration.
- Outreach displayed actual, expected amount and score all use the selected period. Previously the displayed numerator was month-to-date even for Today.
- Outreach working dates use the attendance calendar, employee weekly offs, holidays and approved swaps across every selected month.
- Sales Work Done card details apply the same history cutover as their summary.
- Preset filters disable hidden custom dates. Department links retain normalized applied filters instead of stale hidden dates.
- Conversion payments and response/follow-up communication evidence are bounded by the selected end date.

## Unchanged semantics

All automatic values are read from CRM records on a new page/filter request. There is no background polling or forced page reload; a browser tab already open is a snapshot until refreshed. Manual metrics still require approved CRM values.

HR Operations pending/overdue boxes are backlog/due-date indicators; Operations Work Done counts unique cases with human activity in the period. They are not interchangeable totals. Work Done keeps its explicitly labelled current-status classification; this work does not create immutable historical status snapshots or replace accounting/approval rules.

## Evidence

Regression checks cover a manual score changing from 4 to 5 between HR requests, payment approval/date changes reflected in period sales, carried-forward target dates without writes, multi-month outreach dates, displayed outreach numerator/denominator alignment, Sales cutover details and Operations new activity matching card totals. Existing authorization, rendered views, exports and query-growth tests are also rerun using local standalone SQLite adapters.

Production database reconciliation and authenticated production department-tab behaviour remain unverified. No deployment or schema migration is performed.
