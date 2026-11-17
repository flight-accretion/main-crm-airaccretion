# Wave 2 T-006 / Tracker row 18, T-017

Verified locally 2026-11-16. Backend PASS; complete visual/browser acceptance NOT VERIFIED.
No production database writes, commits, pushes, deployments or provider mutations.
No migration or status-ID change is required.

## Before and after

Before PostgreSQL acceptance reproduced: Owner status-specific route missing (404);
Today's Follow-up ignored status=10 and returned Active plus Sales Follow-up;
View Leads used Confirm/Approve/Reject labels inconsistent with the model.
A separate reproduction showed tied follow-up timestamps matching both status filters.

After: one existing transition service supplies permitted actions; model labels supply
filter options. Operations/Admin may select their already-valid statuses, excluding
manual completion. Sales keep their existing installment entry actions. Owner has
one POST status exception, strict field allowlist, and an independent status-only form.
View Leads and its export use one deterministic latest-activity filter (created_at,
then ID). Today's Follow-up filters only the final eligible, authorized latest rows.
Its no-filter behavior is unchanged; Close Follow-up/Cancelled/Ride Completed remain
excluded by the existing eligibility policy. No new Operations access to Today's
Follow-up, or Accounts/HR access to lead mutation, was introduced.

The workspace's top badge and Current Status now use commercial/business status;
filtering uses latest activity. Sales/Close Follow-up do not overwrite commercial status.

## Role matrix

The before action lists are source inspection baselines. After lead workspace,
View Leads and Today's Follow-up access were exercised with authenticated HTTP for
all 13 roles. Completion column describes unchanged dedicated route middleware;
all manual Add Follow-up completion requests are rejected.

Sets: S = 1,2,3,4,10,11; P = 3,4,10,11; A = 0,1,2,3,4,6,7,8,9,10,11;
O = 0,1,2,6,7,8,9,10,11. All sets are further restricted by completed-ride state.
Statuses 3/4 are payment-entry actions requiring payment details, not approval rights.

| Role | Add Follow-up read | Before dropdown | After actions | View Leads | Today's | Completion via Ride Status |
|---|---|---|---|---|---|---|
| Super Admin | 200 | S | A | 200, all labels | 200 | Existing authorized route |
| Admin | 200 | S | A | 200, all labels | 200 | Existing authorized route |
| Owner | 200 | S, writes blocked | O, dedicated field-scoped POST only | 200, all labels | 200 | Remains denied |
| Senior Sales Manager | 200 | S / P after approval | S / P after approval | 200, existing own/team scope | 200 | Denied |
| Sales Manager | 200 | S / P after approval | S / P after approval | 200, existing own/team scope | 200 | Denied |
| Sales Executive | 200 | S / P after approval | S / P after approval | 200, own scope | 200 | Denied |
| Senior Operations Manager | 200 | S | A | 200, existing scope | 403 | Existing authorized route |
| Operations Manager | 200 | S | A | 200, existing scope | 403 | Existing authorized route |
| Operations Executive | 200 | S | A | 200, existing scope | 403 | Existing authorized route |
| Senior Accounts Manager | 403 | None | No new writes | 403 | 403 | Denied |
| Accounts Manager | 403 | None | No new writes | 403 | 403 | Denied |
| Accounts Executive | 403 | None | No new writes | 403 | 403 | Denied |
| HR | 403 | None | No new writes | Existing read-only 200 | 200 | Denied |

Owner ordinary follow-up POST, delete and unrelated writes remain 403. The T-034
Product/Service PUT exception is unchanged. Unknown fields on Owner status POST are
422, including pricing/payment/Product/Service/customer/travel/assignment fields.

## Runtime evidence

All PostgreSQL fixture tests check current_database=accretion_wave1_qa and
current_user=accretion_wave1_qa_role before their transaction; no default DB fallback.
External integrations are disabled by the existing QA bootstrap.

Commands, from repository root:

```powershell
$env:CRM_WAVE1_QA_ROOT=(Resolve-Path tests/Support/.wave1-qa-runtime).Path
php vendor/phpunit/phpunit/phpunit tests/Feature/Wave2LeadStatusPostgresAcceptanceTest.php
php -d max_execution_time=0 vendor/phpunit/phpunit/phpunit --filter 'Wave[12].*PostgresAcceptanceTest'
php vendor/phpunit/phpunit/phpunit --filter 'LeadVisibilityAndDuplicateTest|LeadStatusPreservationTest|UpcomingFollowUpFilterTest|LeadFollowupNoAnswerTest|RideStatusPresentationTest'
php vendor/phpunit/phpunit/phpunit --filter 'VendorRefundFromRideStatusTest|InvoiceCancelledAndAdjustedExportsTest|UnifiedLeadFollowupWorkspaceTest|ExtraServicePickerViewTest|TargetIncentiveTest|HistoricPerformanceTest'
node tests/Browser/LeadStatusForms.standalone.cjs
php artisan view:cache
node --check public/assets/admin/js/crm/lead-queues.js
git diff --check
```

- Focused final status PostgreSQL suite: 9 tests / 308 assertions, zero failures.
- Integrated Wave 1/2 PostgreSQL run: 60 tests / 1,737 assertions, zero failures.
  This run discovered 8 status tests; the subsequently added financial-snapshot test
  passed separately (1 test / 16 assertions) and is included in the final focused 9.
- Lead/follow-up/visibility regression: 76 tests / 467 assertions, zero failures.
- Invoice/refund/workspace/incentive regression: 74 tests / 507 assertions, zero failures.
- Node: 2 runtime initialization scenarios pass (not browser/visual acceptance).
  Also parsed 34 rendered inline scripts across authenticated Owner, Sales Executive
  and Operations Manager pages; all passed Node syntax checking.
- Changed PHP lint, Blade compilation, shared queue JS syntax and diff whitespace pass.

Coverage includes all three Sales and Operations roles, partial/full approved and
rejected-only states, valid uploaded installment attempts, forged approval input,
Owner partial/full actions and forged fields, manager team versus foreign scope,
latest-status timestamp ties, empty/invalid status filters and decoded CSV/XLSX.
New payment attempts retain attempt status 3/4; forged payment_status=1 is ignored.
Original approved audit rows remain identical.

The status-only snapshot test compares all persisted QA rows in payment_audit_trail,
vouchers, invoices, lead_vendor_payments, vendor_payments, lead_refunds and vendor_refunds.
They remain identical after Operations Sales Follow-up and Close Follow-up actions;
original historical follow-up remains identical and business status remains 4.
Separate integrated tests retain T-007, T-013/T-014 and T-020/T-034 protections.

Legacy preservation test harness was aligned with existing pricing_revision schema.
Its rejected-activity case now contains an earlier valid commercial quote, because
rejected activity is deliberately not a pricing source. Original value/status
assertions remain; an additional full-history comparison was added. No financial
production rule was changed to accommodate these fixtures.

## Changed locations

- app/Services/LeadStatusTransitionService.php:19 latest-activity filter; :146 role action policy.
- app/Http/Controllers/ClientController.php:553 status validation; :716 list query;
  :908 labels; :3329 Owner status handler; :3407 AJAX payment validation;
  :4196 dropdown policy; :5649 export query.
- app/Http/Middleware/OwnerReadOnly.php:36 narrow status POST exception.
- routes/web.php:1137 dedicated Owner status route.
- app/Http/Controllers/UpcomingFollowUpController.php:24 validated status input; :257 final-result filter.
- resources/views/admin/pages/follow-ups/index-upcoming-follow-up.blade.php:41 filter control.
- resources/views/admin/pages/follow-ups/partials/status-select.blade.php:5 commercial label; :111 payment-entry help.
- resources/views/admin/pages/follow-ups/add-follow-up.blade.php:85 commercial badge;
  :491 Owner form; payment initialization guards near :3360.
- tests/Feature/Wave2LeadStatusPostgresAcceptanceTest.php:38 authenticated Owner acceptance;
  :103 all-role matrix; :204 export/team scope; :248 financial/history snapshots.
- tests/Feature/Kpi/LeadStatusPreservationTest.php:29, :68 fixture alignment and history assertions.
- tests/Browser/LeadStatusForms.standalone.cjs:1 runtime null-selector regression.

## Browser evidence and exact remaining gate

Built-in in-app browser opened http://127.0.0.1:8001 and authenticated as a new
synthetic Sales Executive. Approved-payment workspace DOM showed 3,4,10,11 and
the explanatory installment text; Active/Cancelled were absent from that dropdown.
Today's Follow-up default DOM showed three eligible rows. Selecting status 10 and
clicking Apply Filters navigated with the existing date/product/score parameters
and produced exactly one synthetic lead (217db855-7bad-45e3-82f4-eab5f36fc490).

Visual acceptance did not pass: screenshots showed the full-page loading overlay;
document.readyState remained loading and the page included the pending external
https://code.jquery.com/jquery-3.6.1.min.js dependency. At 375px the DOM measured
scrollWidth 360 <= viewport 375, but the overlay makes that insufficient visual proof.
Viewport was reset. No overlay was hidden and no security/network restriction bypassed.
768px and 1280px visual checks, other-role browser sessions, UI payment submission,
Owner status form submit, browser export and pagination were not completed.

Next manual/browser gate on isolated QA: allow normal dependency loading, then test
375/768/1280 widths; Sales before/after approval; Operations actions; Owner status-only
save and unrelated-write denial; View Leads all labels; Today's status plus date/staff/
product filters; paging/export and validation errors. No live records or real providers.

QA-only browser fixtures and logs are under ignored tests/Support/.wave1-qa-runtime.
They are not application release contents. No new release package was generated.
