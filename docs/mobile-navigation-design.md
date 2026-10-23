# Shared Mobile Navigation

All authenticated roles using the shared admin layout now receive one bottom
navigation bar below 768px, including pages outside Operations. Primary tabs
follow the user's department. More renders the existing authorized sidebar tree;
links that are not present in that sidebar are hidden from the primary bar too.
No route authorization or backend workflow has changed.

The shared bar replaces page-specific HR, KPI, Leads, Targets and Attendance
navigation on phones. The top sidebar toggle is hidden only on phones. Desktop
navigation remains unchanged. Lead-detail Call/WhatsApp actions sit above the
bar; the footer and Back to top control also leave room for it.

## Verification

- Render the real authorized sidebar and shared navigation for all 12 roles:
  `php tests/Fixtures/navigation/operations-mobile.php <output-directory>`.
- Run `node tests/Browser/OperationsMobileNavigation.standalone.cjs <output-directory>`.
  Checks include 24 role/page fixtures at 320px, 390px, 767px and desktop;
  authorized links, More, Escape, outside-click dismissal, no duplicate bottom
  bars, hidden phone sidebar toggle and lead action-bar separation.
- Catalog and Payment Review browser checks cover 320px, 390px, 768px, 1024px
  and 1440px, including filters, popups, cancellation and mocked submissions.

These are local presentation and isolated regression checks, not production
notification delivery or a guarantee that every historical data combination
has been tested. The earlier local lead-queue metadata blocker was traced to
sandbox file-access restrictions, not a missing dependency: the same phone parse
succeeds outside the sandbox. On recheck, all 42 isolated lead-queue and Google
Chat tests passed outside the sandbox (190 assertions), including the credential
storage test. No dependency or application-code change was needed to clear these
environment-only test blockers. Live Google Chat delivery still requires the
separate server and Pub/Sub checks.
