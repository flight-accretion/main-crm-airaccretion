# Sidebar and Mobile Navigation

The sidebar follows the existing CRM light design with compact native disclosure groups. `navigation.js` runs immediately after the server-rendered sidebar and before the legacy menu scripts. It moves existing authorized links rather than manufacturing new route access.

Primary order, omitting groups without permitted links:

1. HR Dashboard
2. Attendance: Import, Settings
3. KPI: Dashboard; Daily Work Done (Sales, Operations); Management; Operations Manual KPI; Historic Performance; existing Sales Daily Outreach when permitted
4. Target: Assign Target (existing target-list page), Incentive
5. Today's Follow-up
6. Leads: View Leads, View DNP Leads, other existing authorized lead actions

Other authorized CRM sections remain below these groups. Duplicate HR department links are consolidated into one KPI Dashboard entry; dashboard department tabs replace the department select. No middleware, controllers, role definitions or record scopes are changed.

## Department Tabs

HR sees Sales and Operations. Admin/Super Admin also retain Accounts. Other users see only their own department. Tabs preserve preset/date/source filters and discard employee selection when switching department. The active department remains a hidden field for subsequent filter submissions.

## Mobile More

`window.CrmNavigation.tree()` reads the organized desktop menu. All existing More panels receive this same complete hierarchy through `renderMore`, including nested Daily Work Done, active links and their open parents. A capture-phase listener refreshes panels when their mobile More button is pressed; page-specific scripts still own opening, closing and Escape behavior. The shared tree does not infer authorization from client role names.

## Verification

The sidebar is a viewport-height flex column. Its logo header has a fixed layout height; `#sidebar-scroll` consumes only the remaining height, with `min-height: 0` and independent vertical scrolling. This avoids the legacy `100vh` menu extending below its header. Multiple groups can remain expanded, and both native scrolling and SimpleBar can reach the bottom links without scrolling the logo. No accordion-only restriction is introduced.

`tests/Browser/SidebarScroll.standalone.cjs` checks HR/Super Admin with every group open at 600, 768 and 900 pixel desktop heights, both with and without SimpleBar.

`tests/Fixtures/navigation/render.php` renders the actual sidebar for twelve roles and checks department-tab scope and retained filters. `tests/Browser/SidebarNavigation.standalone.cjs` compares before/after authorized URL sets, primary ordering and exact mobile tree hierarchy at desktop and mobile widths. Local integration checks cover HR, Attendance Import/Settings, KPI Dashboard/Management/History, Incentive and Leads More panels. Existing KPI-scope and HR hidden-navigation checks remain applicable.
