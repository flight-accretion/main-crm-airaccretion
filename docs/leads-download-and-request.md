# Leads Downloads and Search Requests

HR can view DNP leads and use the existing HR-safe lead detail route. DNP Edit
and Export remain unavailable to HR, including direct requests to those routes.
HR may download the main Leads list in Excel or CSV. Only this read endpoint is
opened to HR; the enclosing lead mutation group is unchanged.

The Target Master incentive dialog accepts an optional person_id. Empty means
all active permitted employees. A selected person must belong to the same
existing assignable employee list. Incentive calculations, sales definitions,
and HR/Super Admin export permissions remain unchanged.

The main Leads Download dialog preserves existing filters and export scope.
Person defaults to the current employee filter; All permitted staff removes
only that filter. Selected month replaces existing date ranges with that month's
range on the selected Service, Created, or Follow-up date basis. Other filters
and backend representative checks remain in force. Leap years are supported.

Request Lead is available only to active Super Admin and Sales roles. The
search endpoint independently checks this, requires 3+ name characters or 6+
phone digits, rejects wildcard patterns, and is throttled to 30 requests/minute.
Names match case-insensitive prefixes; phones match normalized digit fragments.
No initial lead list or pagination is provided. At most 20 matching leads are
returned, using batch eager loads and a single product lookup. Results contain
name, phone, products, service date, owner and permitted action only: no email,
notes, payments, history, or links into another owner's full lead details.

Sales can request another owner's assigned lead, or offer their own lead to an
active sales recipient. Both reuse the existing service's pending-request and
owner-approval rules. Super Admin uses the existing direct assignment endpoint,
not a new self-request exception. Pending/unassigned search results have no
Sales request action. Ownership is rechecked by the existing action services;
search results do not confer write access. The main Leads list remains scoped.

Existing Requested Leads inbox, pending-action count and notification sound are
reused. The dialog count is updated by the existing poll, without another poll
loop. Browser sound requires prior user interaction. Closing/changing search
clears results and aborts pending fetches; stale responses cannot repopulate it.

Verification covers real controller/service permissions, bounded discovery,
query counts, unchanged ownership until approval, pending counts, HR main-list
exports, filtered incentive exports, and browser layouts/interactions. Browser
search/export responses are mocked; real backend tests use isolated SQLite.
No production migrations, commits, pushes or deployment are performed.
