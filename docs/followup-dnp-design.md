# Follow-up and DNP Design

## Scope

These pages retain their existing representative scope, backend filters, list eligibility and lead actions. No archive automation, bulk reassignment, new role or migration is introduced. HR remains read-only on DNP: existing server-side mutation/export restrictions are unchanged, and HR links open client details.

## Today's Follow-up

Four cards show total matching follow-ups, completed HOT scores, no next-follow-up date, and overdue follow-ups. Counts use the complete authorized result collection after date/product/score/staff filters, not the visible ten rows. Scheduled-date and overdue classification follow the selected date and existing open-status rules. Latest cancelled/completed records remain excluded by the existing controller logic.

Local list tabs partition the existing result set into All, Selected date, Overdue and No follow-up date. They do not add future leads or change default eligibility. List search, sorting and pagination do not change the top server-filter totals. CSV export includes all locally matching rows, not just the current page; user-originated cells are quoted and protected against spreadsheet-formula interpretation.

## DNP

Age is calendar days since lead creation in the application timezone, never since DNP marking. Buckets are inclusive 0-30, inclusive 31-90, and strictly over 90 days. Future dates clamp to zero; missing dates are displayed as unknown and are not silently added to a bucket. Percentages use all authorized filtered leads as the denominator and are rounded independently to one decimal, so totals may round to 99.9 or 100.1 percent.

Sales Executives see their own age distribution. Sales Managers, Senior Sales Managers, Admin, Super Admin and HR also see a staff breakdown from the same scoped result set. Sales Managers retain their existing assigned-team-plus-self scope; unrestricted roles retain their existing scope. Other authorized roles retain their list access without gaining the new staff overview. Unassigned leads are grouped separately. Staff with no DNP entries are not added to the pending list.

Oldest lead uses created-date age. Retry scheduled counts records whose existing latest scheduled-date follow-up is on/after the start of today. The scheduled-date ordering of the old `latest_followup` accessor is preserved by a single batched query. Ties use creation time.

The DNP export still uses the existing export endpoint and its permissions, filters, and all matching row IDs. Email remains available as a filter but is not shown as a table column. Existing View/Edit actions are retained; no new Retry mutation is introduced.

## Performance and Verification

`LeadQueuePresentationService` computes summaries from already scoped collections. Service names are fetched once for the collected service IDs. Clients, ride dates and service fallback follow-ups are eager loaded in batches. This does not change global model accessors or the established follow-up latest-entry rules.

`tests/Feature/Kpi/LeadQueueDesignTest.php` covers full-list summary counts, manager/executive/HR scopes, forged representative selection, latest cancelled exclusion, created-date boundaries, percentages, empty lists, actual view rendering, HR controls and constant summary query count. Browser checks use actual rendered views/local assets across 320/390/1024/1440 pixels for three roles, including search, tabs, sorting, pagination, exports, empty results, dark mode and fixed mobile navigation.

Deploy with the shared KPI mobile-navigation partial, CSS and JavaScript already introduced by the earlier redesign. Refresh the route/view caches through the usual application-user deployment procedure. No database migration is required for this change.
