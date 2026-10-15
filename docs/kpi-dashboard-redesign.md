# KPI Dashboard Design

## Scope

The approved Kimi palette and desktop/mobile layout are applied to the existing
shared KPI Blade view. Both general KPI and Operations KPI routes reuse this
view. Controllers, services, metric resolvers, scoring thresholds, weights,
period validation, assignments, permissions, routes and database schema are
unchanged. Other pending Leads/voucher changes were not reverted or edited.

## Presentation

- Desktop: existing authorized employees in Team Summary, with selectable
  person-specific Metric Detail. Mobile: employee selector and compact metrics.
- Overall, actual values, rating bands, next threshold, improvement payloads and
  weighted raw scores come from existing dashboard data. The view does not
  calculate a replacement overall score or assume total weight is 100.
- Summary tone/status and weakest-metric tie-breaking match the existing HR
  dashboard presentation conventions; these are not new workflow statuses.
- Trend shows N/A because the existing controller does not provide comparison
  data. No previous-period calculation or sample values were added.
- Search, metric pagination, date presets/custom dates, hidden person filter,
  department switching flag and Operations filter action are retained.
- Metric popup, How to Improve Score and manual-entry modal keep their existing
  payloads and handlers. The existing improvementUsers input is unchanged;
  this design does not populate or repair missing improvement data upstream.
- Mobile navigation and More use only already-authorized sidebar links.
  Accounts therefore does not gain a Leads or Sales Follow-up shortcut.
- Large improvement popups scroll internally so Close remains reachable.

## Verification

Actual Blade render and browser checks for Super Admin, Admin, HR, Sales
Executive/Manager, Operations, Accounts and the dedicated Operations manual
entry view at 1440, 1024, 390 and 320 pixels. Checks cover employee selection,
search/pagination, custom dates, existing score/actual payloads, popups, manual
entry visibility/period, mobile navigation, overflow and escaping.

Four presentation regressions, four existing KPI data-scope regressions and
sixteen HR regressions ran using local lightweight runners because this
checkout has no PHPUnit binary. The full PHPUnit HTTP suite has not been run.
Existing matrix-test text expectations were updated for approved design labels;
its template, weight, assignment and metric assertions remain unchanged.

No live changes, production messages, commits, pushes, migrations or dependency
installs were performed. Deploy the new view, partial, desktop/mobile CSS and JS
together, following the existing compiled-view/cache deployment procedure.
