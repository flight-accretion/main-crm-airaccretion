# Target Master: Incentive

`/admin/targets/incentives` is a read-only Target Master sub-page for HR and Super Admin. Both the route and controller enforce this access; the sidebar uses the same roles. Other Target Master access remains unchanged.

The former Target View **Download Incentive** button and export dialog now live on Incentive. The existing `/admin/targets/download-incentive` endpoint, individual/all-person selection, Excel/CSV formats, report columns, payout calculations and authorization are unchanged.

## Table

- Employee Name: active CRM employees, including Sales and Operations.
- Target Amount: explicitly assigned active target for the chosen month; missing/inactive targets show Not assigned.
- Achieved Sales Amount: employee-owned monthly net sales from the existing approved-payment ledger, matching the employee summary export.
- Achievement (%): achieved sales divided by positive target amount; otherwise unavailable.
- Earned Incentive: existing individual, own/team, or company/product calculation. No 100%-target eligibility gate is introduced. Unconfigured/disabled incentives show Not configured; a configured zero payout displays zero.
- Download: opens the existing dialog preselected for that employee and table month/year. The header button resets the dialog to the current employee filter (or All employees).

Monthly filters default to the current year/month. Year options stop at the current year. Employee, search and page-size filters are server-validated; pagination retains filters. The export dialog can download any individual or all active employees, independently of table search/pagination.

## Integrity and performance

No achieved amounts or incentive settings are written while viewing this page. Staff and role data are loaded together, target records are loaded for the displayed employees, one shared monthly ledger is reused, and manager team memberships are batch-primed. Duplicate active monthly targets are rejected rather than silently producing an inconsistent payout.

`tests/Feature/IncentivePageTest.php` verifies values against the real calculation engine, no target writes, role restrictions, filtering/pagination and download-control relocation. Existing incentive report tests verify the unchanged export behavior.
