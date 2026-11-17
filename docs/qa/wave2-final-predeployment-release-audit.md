# Wave 2 final predeployment release audit

16 November 2026 IST. Local checkout `main`, HEAD `2f146ebd29e1203786972611988a6d6153eafcc6`, with existing modified and untracked files. Testing used guarded loopback PostgreSQL `accretion_wave1_qa` / `accretion_wave1_qa_role`. Provider writes were faked. No deployment, commit, push or Tracker edit.

The live Tracker and Completed tabs were read. The matrix uses internal Wave 2 IDs and maps Tracker entries by requirement title. Tracker IDs alone are not equivalent to internal IDs. Rows 16 and 31 have no Tracker ID. Existing user-approved rules govern the narrower scope where original wording differs.

| Task | Backend | Browser | PostgreSQL | Result | Evidence |
|---|---|---|---|---|---|
| T-002 | Pass | Pending | Pass | Partial | Tracker row 14. Typed city, country scope and case reuse. |
| T-003 | UI task | Pending | N/A | Open | Row 15. Full cross-role shared CSS consistency remains unverified. |
| T-004 | Mocked pass | Pending | Pass | Partial | Row 16. Verified sender reconciliation, definition match and concurrent binding uniqueness. |
| T-005 | Mocked pass | Partial | Pass | Partial | Row 17. Delete confirmation previously measured at three widths. Provider dashboard reflection and explicit Super Admin individual creation need verification. |
| T-006 | Pass | Partial | Pass | Partial | Row 18. Role policies, Owner status-only action and filters. |
| T-007 | Pass | Pending | Pass | Partial | Row 19. Dry-run reported 3 candidates and 0 writes. Concurrent fixture yielded one cancellation. |
| T-008 | Pass | Pending | Pass | Partial | Client match choice and lead creation. No exact Tracker row match verified. |
| T-009 | Pass | Pending | Pass | Partial | Rows 21–22. Outreach cohort, approved payment date and Response Time parity. |
| T-012 | Pass | Pending | Pass | Partial | Row 24. Completed work actor, date and deduplication. |
| T-013 | Pass | Pending | Pass | Partial | Row 25. Corrections, approvals, rollback and audit. |
| T-014 | Pass | Pending | Pass | Partial | Row 26. Approved explicit team scope, not blanket manager grants. |
| T-016 | Pass | Pending | Pass | Partial | Row 27. First positive approved paid_date books full value once. |
| T-018 | Pass | Pending | Pass | Partial | Row 29. Independent HR pages and retained filters. |
| T-020 | Pass | Pending | Pass | Partial | Row 6. Inline editor persistence, historical snapshots and concurrent save conflict. |
| T-021 | Render checks | Pending | N/A | Partial | Row 7. Picker wrapping and chip interaction require visual acceptance. |
| T-023 | UI task | Pending | N/A | Open | Row 8. Cancelled Queue overflow, controls and layout remain unverified. |
| T-024 | Pass | Pending | Pass | Partial | Row 9. Query and Timeslot date guards, Refund exception and legacy purpose. |
| T-025 | Pass for filters | Pending | Pass | Partial | Row 30. Exact refund filter results covered. Intermittent navigation remains unverified. |
| T-026 | Pass | Pending | Pass | Partial | Row 31. Assessment prerequisite and vendor refund route. |
| T-034 | Pass | Pending | Pass | Partial | Row 36. Product/Service lock, permitted extras and Owner exception. |

## Role evidence

| Role | Boundaries exercised by integrated HTTP suite |
|---|---|
| Super Admin | Lead workspace, template create/delete, approval routes |
| Admin | Lead workspace and filters, no provider delete |
| Owner | Scoped status and Product/Service exceptions, unrelated writes denied |
| Senior Sales Manager | Assigned scope and approved-payment lock |
| Sales Manager | Team scope and approved-payment lock |
| Sales Executive | Own lead scope, approved-payment lock and personal templates |
| Senior Operations Manager | Operations routes and permitted Sales statuses |
| Operations Manager | Operations routes, status actions and vendor refund workflow |
| Operations Executive | Operations routes and permitted Sales statuses |
| Senior Accounts Manager | Existing refund and KPI routes, no provider delete |
| Accounts Manager | Existing refund and KPI routes, no provider delete |
| Accounts Executive | Own/explicit team KPI scope, no provider delete |
| HR | Attendance and authorized lead reads, no lead mutation or provider delete |

## Fresh checks and repair

`php -d max_execution_time=0 vendor/phpunit/phpunit/phpunit --filter 'Wave[12].*PostgresAcceptanceTest'` exited 0 with 74 tests and 2,031 assertions. Coverage includes historic timestamp normalization, the T-016 cohort, T-006 status rules, MSG91 fake-provider failures, vendor and customer refund snapshots, voucher/invoice rows and independent Extra Services quote changes. The related Laravel regression ran with DB_CONNECTION=sqlite and DB_DATABASE=:memory: and passed 214 tests / 1,225 assertions. PHP lint on modified and untracked PHP, Blade compilation, Node syntax and focused handler checks, and git diff --check passed. The port-8001 identity endpoint confirmed the isolated database, restricted role and disabled outbound HTTP. The browser tool was unavailable.

`app/Console/Kernel.php:85` previously scheduled DNP writes at 00:45 Asia/Kolkata without a release activation gate. Added a default-off `CRM_DNP_AUTO_CANCEL_ENABLED` gate. PHP lint passed. The existing 00:30 cancellation command was preserved. Before enabling DNP automation, authorized operators should run `php artisan lead:cancel-aged-dnp --dry-run` inside the deployed application container with automation disabled, review candidate age and paid/completed exclusions, confirm zero cancellations, then configure the scheduler gate explicitly.

## Migration and release preparation

| Migration | QA coverage | Impact and rollback |
|---|---|---|
| 2026_11_14_120000_add_extra_service_quote_to_leads.php | Quote persistence exercised | Adds lead quote storage. Rollback discards independent quote state. |
| 2026_11_15_120000_create_attendance_corrections.php | Correction persistence exercised | Adds correction, payroll-period, event and review records plus attendance/import revision data. Rollback loses correction audit data. |
| 2026_11_15_121000_add_attendance_correction_void_state.php | Reversal exercised | Adds nullable void timestamp. Removing it can expose previously voided records to readers. |
| 2026_11_16_120000_add_msg91_template_reconciliation.php | Mocked deletion and sync persistence exercised | Adds sender sync state and uniquely keyed per-sender deletion records. Database rollback cannot reverse provider deletion. |

These four files are present locally and untracked. Production migration state was not inspected. Verify migration history against the intended target before rollout, review PostgreSQL DDL lock windows on existing data, and retain a tested backup. Pricing revision migration remains a release dependency. No separate status-policy migration was identified.

After acceptance, review the exact file manifest including untracked migrations, exclude QA runtime and secrets, back up database and persistent uploads, quiesce affected workers and scheduler, apply additive migrations before code processes use the new columns, refresh Laravel caches, restart workers, then smoke-test permissions, reports and synthetic workflows. Keep DNP activation off and review MSG91 creation, sync, send and dry-run settings before enabling provider operations.

## Release blockers

- Run interactive browser acceptance on isolated QA at 375, 768 and 1280px across the relevant roles, including shared CSS, Cancelled Queue, MSG91 Create success and HTTP 400, and full status/refund workflows. The interactive browser tool was unavailable in this execution. Prior visual evidence covers only selected screens.
- Confirm internal T-008's Tracker mapping and finish the outstanding Tracker conditions. Confirm the intended Super Admin individual-template creation path against Tracker row 17 before signing off that scope.
- Verify MSG91 deletion acknowledgment, name-only language blast radius and dashboard reflection in a controlled provider environment before enabling provider deletes. Current backend evidence uses fakes.

**NO-GO — Wave 2 deployment blocked**


