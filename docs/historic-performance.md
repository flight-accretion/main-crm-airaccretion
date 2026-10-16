# Historic Performance

`GET /admin/kpi-history` and `/admin/kpi-history/download` are read-only and restricted to HR and Super Admin, including direct requests. The sidebar follows the same restriction. Existing KPI, target and payment workflows are unchanged.

## Data and periods

- Default: last twelve calendar months, including the current partial month. Maximum range: twenty-four months. Future months and invalid employee/department combinations are rejected.
- Employee options include inactive Sales, Operations and Accounts employees so former staff remain available for historic review.
- A finalized `kpi_monthly_snapshots` score takes precedence. Otherwise an applicable active KPI assignment is calculated through the existing dashboard service and rating rules. Past calculations are labelled **Recalculated**, current calculations **Month to date**. Missing assignments/scores remain null and are excluded from averages.
- Recalculation uses current CRM records and applicable active configuration, not an immutable historic reconstruction. Existing snapshot tables currently have no automated monthly finalization producer; this feature does not silently create or finalize snapshots.
- Recalculated scalar scores and aggregate net-sales totals are cached for five minutes. Employee/month inputs are scoped and validated before cache access. Snapshots, assignments and targets are batch-loaded; shared net-sales ledgers are loaded once per required month on a cold cache and reused across employees and export batches. Cached aggregates remain server-side; only authorized employees' rows are returned.
- The history-only target result supplied to `KpiDashboardService::forUser` avoids the normal resolver's `updateAchievedAmount` write. Existing callers retain their original behavior.
- Target graphs use explicit active monthly target records, not carry-forward assignments. Missing target months are labelled **Not assigned**, not zero. If no active target exists in the selected period, target statistics, chart and columns are hidden.
- Achieved sales reuse `TargetIncentiveService`'s approved-payment, qualifying follow-up, net sales after GST/refund calculation. This is individual-owner target achievement as in Target Master, not incentive team/company sales bases. Historical target amounts remain live CRM amounts and may change when source records/refunds change.

## Exports

The download dialog supports employee, department scope, month range, Excel and CSV. Excel can include KPI and Target History as separate worksheets; CSV requires one dataset. All-employee exports include all eligible employees in the chosen department, including inactive staff. Target exports omit unassigned months. Numeric scores, INR amounts and achievement percentages remain numeric; missing values remain blank. Text cells use a literal string binder and formula-injection escaping. Both download routes independently enforce permissions and input validation.

## Verification

`tests/Feature/Kpi/HistoricPerformanceTest.php` checks snapshot precedence, null months, net-sales use, non-mutating target calculations, roles, validation, actual workbook/CSV round trips and rendered no-target states. Local standalone adapters can run these tests where PHPUnit is absent. Browser verification covers charts, label collisions, no-target states, export controls and horizontal overflow at 1440, 1024, 768, 390 and 320 pixels.

No schema migration is required beyond the existing KPI core tables. This change does not add a month-end scheduler or alter existing finalization policies.
