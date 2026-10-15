# Targets and Incentives

## Approved Scope

Redesign Target Master using the approved Kimi light/mobile preview. Retain target achievement, latest-follow-up sales rules, fee/refund handling, target period defaults and target resolver behavior. Add monthly incentives on every eligible sale, without a target-achievement gate. HR and Super Admin alone may edit/delete targets or configure incentives. Existing assignment permission remains for Admin and Sales Managers, but those requests cannot write incentive settings.

Individual uses the target employee's existing representative-owned sales. Team pays the selected Sales Manager two components: own monthly sales times the own multiplier, plus active assigned-team monthly sales times the team multiplier. Team members' individual target/incentive settings are not modified. Company uses all company sales, optionally filtered by CRM product IDs. Operations incentives are restricted to Company/product basis. A matching multi-product lead is counted once, not once per selected product, consistent with the CRM's lead-based sales totals. Eligibility is explicitly configured on the employee's target; old targets are not automatically made eligible.

## Implementation Plan

- [x] Add isolated regression checks for monthly net sales, duplicate payments, refunds/fees, product/team scope, permissions, settings validation and export formats.
- [x] Add nullable/disabled-by-default incentive columns; do not rewrite existing target amounts or achievement calculation.
- [x] Add a request-local monthly sales ledger in batches of 250 leads, reuse SalesAmountCalculator, and calculate each configured target's incentive from that ledger.
- [x] Extend TargetController validation, scoped employee options, HR/Super Admin mutation gates and authenticated export route. Export uses the same incentive computation as the table.
- [x] Replace only Target Master view; add page-scoped styles and script, checkbox product dropdown, assignment/edit/delete dialogs, export dialog and mobile navigation derived from authorized CRM sidebar.
- [x] Verify syntax, Blade rendering, isolated database/controller regressions, XLSX/CSV contents and desktop/mobile interactions. No local/live database migration, commit, push or deployment was performed.

## Verification

Focused PHPUnit test files: `tests/Feature/TargetIncentiveTest.php` and `tests/Feature/TargetIncentivePersistenceTest.php`. The latter creates a separate in-memory SQLite database, not the configured CRM database. This workstation lacks vendor PHPUnit; the test methods were run through standalone verification adapters. Additional isolated controller/ledger/export checks and Playwright checks cover five roles at 320, 390, 1024 and 1440 pixels. Full application/production acceptance still requires migration and a signed-in smoke test against the deployed schema.

Only HR/Super Admin can download incentives, including direct export requests. XLSX and CSV are real files produced by the existing Laravel Excel package, with formula-looking employee/product text forced to literal strings. Export recalculates sales for the selected period without changing payroll or recording payments.

## Deployment

Run `php artisan migrate --force` before using the updated target page. No new Composer package is required; maatwebsite/excel is already a project dependency. Existing records have incentives disabled. Monthly export is a calculated report, not a payout or payroll write.
