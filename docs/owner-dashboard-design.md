# Owner Role & Dashboard — Design

Status: design (visual preview approved structure)
Audience: dev implementing the Owner login + dashboard

**Visual preview:** open `docs/owner-dashboard-preview.html` in a browser — a static
mockup using the real theme CSS (`style.css`/`custom.css`/`navigation.css`), real icon
fonts, and sample data. The inline `<style>` block is exactly what ships as
`public/assets/admin/css/crm/dashboards/owner-dashboard.css`.

## 1. Goal

Give the business owner a single screen that answers, in under 10 seconds:

1. How much money came in / went out this month, and is that better than last month?
2. What needs attention right now (approvals pending, refunds overdue, vendor money stuck)?
3. Is the machine running (sales activity, operations load, people attendance, automations healthy)?

The Owner **oversees, does not operate**. The dashboard is read-first: every tile/number
deep-links into the existing module page where the underlying work happens.

## 2. Role definition

### 2.1 New role: `Owner` — one step below Super Admin

`Owner` sits at the **same level as `Admin`**, directly under `Super Admin`. Rationale:
Super Admin carries the tech surface (Google Chat OAuth, AI agent/model settings, IVR
config, notification master, user types, backups, log downloads) that a business owner
doesn't need. Admin carries the business surface — that's the Owner's shape too, minus
write powers.

Hierarchy in `database/seeders/UserTypeSeeder.php`:

```php
$roleHierarchy = [
    'Super Admin' => [
        'Admin' => [ /* existing tree unchanged */ ],
        'Owner' => [],   // sibling of Admin, no children
    ],
];
```

`app/Models/UserType.php` (a commented `// const owner` hint already exists there):

```php
const OWNER = 'Owner';

// Read-only oversight. NEVER add OWNER to ADMIN_ROLES —
// ADMIN_ROLES is the key that unlocks every operational + tech route.
const OWNER_ROLES = [self::OWNER];
```

> **WARNING — seeder destructive behaviour.** `UserTypeSeeder::run()` deletes every
> `user_types` row not present in `$roleHierarchy` (`whereNotIn(...)->delete()`).
> Re-running the seeder *before* `Owner` is added to the hierarchy will silently
> delete the live `Owner` role. Add the constant and the seeder line in the same deploy.

The seeder auto-creates `owner@accretion.in` / `password123` (convention for all roles).
Force a password change on first login.

### 2.2 Where the role must be wired

| Location | Change |
|---|---|
| `app/Models/UserType.php` | `OWNER` const + `OWNER_ROLES` group (keep OUT of `ADMIN_ROLES`) |
| `database/seeders/UserTypeSeeder.php` | `Owner` as child of `Super Admin`, sibling of `Admin` |
| `app/Helpers/helper.php` — `getRepresentativeIds()` (line ~44) | treat `Owner` like oversight roles → `return null` (sees all leads). Without this the hierarchy fallback returns only the owner's own id and every lead list renders empty |
| `app/Http/Controllers/UserController.php` — `login()` (line ~52) | `elseif ($userType === UserType::OWNER) $path = '/owner/dashboard';` |
| `resources/views/admin/layouts/header.blade.php` | Owner sidebar menu (see §5) + `Owner` added to `$canAccess` gates for read-only pages |
| `routes/web.php` | existing route groups gain `OWNER` where read access is granted — `role:ADMIN_ROLES,SALES_ROLES` becomes `role:ADMIN_ROLES,SALES_ROLES,OWNER` etc. `RoleMiddleware` expands the constant and matches the literal `'Owner'` string |

Owner logs in through the **existing** login page (optionally Google OAuth like everyone
else). No separate login screen; the role decides the landing page.

## 3. Access matrix

Principle: **view everything business, change nothing; never see tech settings.**

| Area | Routes (existing) | Owner access |
|---|---|---|
| Owner Dashboard (new) | `/owner/dashboard` | Full |
| Sales Dashboard | `admin.sales-dashboard` | View |
| Accounts Dashboard | `admin.account.dashboard` | View |
| Operations Dashboard / Home / Work Details | `admin.operations.*` | View |
| KPI Dashboard + History + Work Done | `admin.kpi.*`, `admin.kpi-history*` | View |
| All reports (Sales, KPI, Vendor, Profit/Loss, Incentive) + exports | `admin.report.*` | View + export |
| Leads / Clients / DNP / Upcoming Follow-ups / Lead Tracking | `admin.lead*`, `admin.client*` | View only — no create/edit/delete/import/follow-up-write |
| Upcoming Rides / Ride Status / Reschedules | `admin.rides.*`, `admin.operations.reschedules*` | View |
| Payment Review | `admin.account.payment-review*` | View — **no** approve/reject |
| Refunds (customer + vendor) | `admin.refunds.*`, `admin.account.vendor-refunds` | View — no approval actions |
| Invoices | `admin.account.invoices*` | View + PDF/download |
| Vendor Payments / Exceptional / Vendors / Vouchers | `admin.account.vendor-payments`, `admin.account.exceptional`, `admin.vendors.*`, `admin.vouchers*` | View |
| HR Dashboard / Attendance calendar + import results | `admin.hr*`, `admin.attendance.import*` | View |
| Staff list | `admin.users.index` | View only — no create/edit/toggle (Admin keeps manage rights) |
| WhatsApp Inbox / Lead Chat | `admin.whatsapp.index`, `admin.lead-chat*` | View |
| Lead AI Score pages | `admin.leads.ai-score.*` | View |
| **Tech / config (Super Admin only)** — Google Chat OAuth, Notification Master, User Types, AI agent & model settings, WhatsApp AI-agent settings, IVR management, Product Sync, Lead Allocation Settings, Booking Email Template editor, log download, `/clear-all` | `admin.google-chat.oauth*`, `admin.notification-master*`, `admin.user-types*`, `admin.whatsapp.ai-agent*`, `admin.ivr.*`, `admin.product-sync*`, `admin.lead-allocation*`, `admin.booking-email-template*`, `log.download` | **No access** |

Implementation note: pages that mix read + write under one role group (e.g.
`admin.leads.*` allows create/edit for `SALES_ROLES`) render action buttons
conditionally — `@if($userType !== UserType::OWNER)` around Edit/Delete/Approve/Reject.
POST routes stay protected by their existing groups, so even a crafted request 403s.

## 4. Dashboard information architecture

Single scrollable page on a soft gray canvas (`#f4f6fa`), cards elevated with soft
shadows and 14px radius. Section order: greeting → KPI row → needs attention →
collections chart + lead-source donut → sales pipeline + operations/people →
automation health + quick links. Rendered mockup: `docs/owner-dashboard-preview.html`
(interactive charts, sample data).

```
┌──────────────────────────────────────────────────────────────────────────┐
│ ♛ Good afternoon, Owner · Fri 25 Oct        [● Live all healthy] [Month▼]│
├──────────────────────────────────────────────────────────────────────────┤
│ NET INFLOW ₹48.2L ▲12.4%│COLLECTED ₹61.0L ▲8%│PAID OUT ₹14.6L │BOOKINGS │
│ (sparkline) green         (sparkline)         ▼3.2% neutral   214 ▲31   │
├─ NEEDS YOUR ATTENTION · 4 open items ────────────────────────────────────┤
│ [◐ 12 ₹4.1L] payments pending   [◑ 6 ₹2.2L] vendor overdue (red)         │
│ [◐ 4 ₹1.8L] customer refunds    [◐ 3 ₹90K ] vendor refunds due           │
├─ SALES (area chart, 14d) · scope: Company/Managers/People ─┬─ LEAD SOURCES│
│ company trend · manager lines (legend) · person dropdown   │ donut 1,204  │
│ total pill updates with scope                              │ + legend     │
├─ SALES PIPELINE (7/12) ──────────────────────────┬─ OPERATIONS & PEOPLE 5 │
│ funnel 1,204→412→214→187 (gradient bars)         │ stats 2×2 · case stage │
│ manager attainment rows (avatar+bar+pct)         │ bar · attendance pills │
│                                                  │ KPI ring 78 · updates  │
├─ AUTOMATION HEALTH (7/12) ───────────────────────┬─ JUMP BACK IN (5/12)   │
│ WhatsApp AI / call summaries / email / AI score /│ quick links w/ badges  │
│ failed jobs — status dots (ok / catching up)     │ → P&L, reports, exc.   │
└──────────────────────────────────────────────────────────────────────────┘
```

Section → data source mapping (reuse, never re-query):

| Section | Source |
|---|---|
| KPI row | `AccountsHomeService::forMonth()` for finance values; active bookings = distinct leads with rides in the selected month whose latest follow-up is not cancelled; sparkline = daily approved totals for the last 14 days |
| Needs attention | `AccountsHomeService::forMonth()['pending']` verbatim |
| Sales chart | Same sales-amount base as `ReportController::salesReport` / `SalesAmountCalculator`, grouped per day over the last 14 days. Scope switcher: **Company** (all representatives), **Managers** (one series per sales manager — filter via `getRepresentativeIds($manager)` per manager), **People** (single `representative_user_id` chosen from a dropdown of sales executives). ApexCharts gradient area; header total recomputes per scope |
| Lead-source donut | Leads created in the selected month, classified from `EmailLeadLog.received_at` and `IvrCallLog.call_start_at`; unmatched leads are **Manual / Other**. Email and IVR buckets are distinct, and unknown sources are not labeled Website or WhatsApp |
| Sales pipeline (funnel + attainment) | Monthly leads are counted by their latest follow-up status (ordered by `created_at`, then `id`); partial payment remains active and paid-in-full uses `STATUS_FULL_PAYMENT_RECEIVED`. Attainment = `TargetResolverService` / `DashboardController` per manager |
| Operations & people | `OperationCase` stage counts, distinct ride leads excluding those whose latest follow-up is cancelled, `AttendanceRecord` today, `KpiMetricSnapshot` avg, `SalesDailyUpdate` submission ratio |
| Automation health | `WhatsAppAiReplyBatch`, `CallSummaryIntegration`, failed jobs vs `crm.failed_jobs_warning`, heartbeat vs `crm.scheduler_heartbeat_max_age_minutes` |
| Quick links | static route list + `SidebarPendingCounts` badges |

### 4.1 Visual language (hard requirements)

- Design tokens (v2, from the preview): canvas `#f4f6fa`, card `#ffffff`, border `#e8ecf1`,
  text `#101828`, muted `#667085`, faint `#98a2b3`, brand navy `#102e49`, blue `#2f6fed`,
  green `#178a4c`, red `#e5484d`, amber `#d97706`, owner-violet `#7c3aed` (crown + bookings only)
- Cards: white, 1px border, **14px radius**, soft shadow `0 1px 2px rgba(16,24,40,.05)`;
  clickable cards lift 2px + larger shadow on hover
- KPI cards: 11px uppercase faint label + tinted icon chip, **30px bold tabular number**,
  delta pill (green ▲ / red ▼ / gray neutral for "less spend" metrics), inline SVG sparkline right
- Needs-attention cards: tinted icon square + big count + amount, tone top-border 3px
  (amber = decision waiting, red = money at risk), `oldest Xd` tag, "Review →" link
- Charts: **ApexCharts** (already bundled at `public/assets/admin/libs/apexcharts`) — gradient
  area chart with smooth curve + ₹ tooltip; donut with white stroke and HTML center total
- Funnel: gradient rounded bars with % inside; team attainment: initials avatar + name/role +
  7px bar (green ≥80, blue 50–79, red <50) + right-aligned %
- Ops & people: 2×2 soft-gray stat tiles, single stacked "cases by stage" bar with dot legend,
  3 attendance pills (present/absent/leave), conic-gradient KPI ring (78) + daily-update mini bar
- Automation rows: icon tile + name/desc + right value + pulsing status dot (ok / catching up)
- "Jump back in": quick-link rows with tinted icons, live pending badges, chevrons
- Greeting header: time-aware "Good morning/afternoon/evening, Owner", date line, green
  pulsing `● Live` chip, month picker
- Grid: 12-col, 16px gap; spans 8/4 and 7/5 for chart rows; collapses ≤1200px (→6/12), ≤680px (→12)
- Icons: Remix classes (`ri-*`) already loaded by the layout; numbers use `font-variant-numeric: tabular-nums`
- Dark mode via the same `.dark` token-override block (tokens redefined, no per-component rules)
- Rupee compact form `₹48.2L` / `₹4.20Cr` (extract the formatter into a shared blade include —
  it currently lives inline in `dashboard.blade.php` line 5)

### 4.2 Empty / degraded states

Zero-state line per tile ("None waiting" style) — never a blank card. The header reports
whether the scheduler heartbeat is current or stale (>10 min by default,
`crm.scheduler_heartbeat_max_age_minutes`); that heartbeat does not claim that every
queue or automation is healthy.

## 5. Owner sidebar menu

Rendered inside the existing `main-menu` in `header.blade.php`, gated by
`$userType === UserType::OWNER`. Categories use the existing `slide__category` pattern;
badges reuse `pending-count` partials where a queue count exists.

```
Main
  ♛ Owner Dashboard                        (active)

Business Overview
  Sales Dashboard
  Operations Dashboard
  Accounts Dashboard
  KPI Dashboard
  HR Dashboard

Sales
  Leads · DNP Leads · Upcoming Follow-ups

Operations
  Upcoming Rides · Ride Status · Reschedules · Vendor Follow-ups

Accounts
  Payment Review [badge] · Refunds ▸ (Customer [badge], Vendor [badge])
  Invoices [badge] · Vendor Payments · Exceptional

Insights
  Sales Report · KPI Report · Vendor Report · Profit / Loss · Incentive Report

People
  Staff (read-only) · Attendance

More
  Vendors · Vouchers · WhatsApp Inbox · Lead Chat
```

Deliberately excluded from the owner tree: User Management, User Types, Notification
Master, Booking Email Template, Lead Allocation Settings, AI Agent/Model settings,
IVR, Product Sync, Google Chat OAuth, Assign More Leads, Requested Leads (transfer
inbox — owner doesn't action transfers), Sales Executive Management.

### 5.1 Mobile bottom tabs (≤767px) — same component as every other login

The CRM's shared mobile navigation (`admin/layouts/partials/operations-mobile-nav.blade.php`,
styled by `crm/operations-mobile-nav.css`, driven by `crm/navigation.js` +
`crm/operations-mobile-nav.js`) renders a fixed bottom tab bar on phones with the
primary destinations + a **More** button that opens a bottom sheet listing the rest of
the authorized sidebar. Owner uses the exact same component — add one case to the
`$mobileTabs` match in the partial:

```php
$navigationRole === \App\Models\UserType::OWNER => 'owner',
```

```php
'owner' => [
    [route('admin.owner.dashboard'), 'ri-vip-crown-line', 'Dashboard'],
    [route('admin.clients.index'), 'ri-group-line', 'Leads'],
    [route('admin.kpi.index'), 'ri-bar-chart-line', 'KPI'],
],
```

Behavior comes for free: tabs the sidebar doesn't authorize auto-hide, the active tab
is detected from the URL, column count self-adjusts, the More sheet is built from the
owner sidebar tree (badges included), and the sidebar hamburger is hidden on phones.
No owner-specific CSS or JS.

## 6. Implementation plan

1. `UserType::OWNER` + `OWNER_ROLES`; seeder hierarchy (same PR — destructive seeder!).
2. `helper.php::getRepresentativeIds()` → owner oversight (`return null`).
3. `UserController::login()` owner redirect → `/owner/dashboard`.
4. `app/Services/Owner/OwnerDashboardService.php` — one method per §4 section, delegating
   to the existing services listed in the mapping table. Cache aggregate 5 min keyed by
   month (`crm.master_cache_ttl` pattern).
5. `app/Http/Controllers/OwnerDashboardController.php` — `index(Request)` → service → view.
6. `routes/web.php` inside the `auth` group:
   `Route::get('/owner/dashboard', [OwnerDashboardController::class,'index'])->middleware('role:OWNER')->name('admin.owner.dashboard');`
   plus add `OWNER` to the role list of every read route the owner may open (per §3).
7. `resources/views/admin/pages/dashboards/owner-dashboard.blade.php` — `@extends('admin.layouts.header')`, markup per the preview file (copy the preview's `<main class="od2">` and replace sample data with variables).
8. `public/assets/admin/css/crm/dashboards/owner-dashboard.css` — lift the `<style>` block from the preview file verbatim.
9. `header.blade.php` — insert the §5 menu behind `$userType === UserType::OWNER`; body already carries `crm-operations-mobile-page`.
10. `operations-mobile-nav.blade.php` — add the `'owner'` case per §5.1 (tabs: Dashboard, Leads, KPI + More). The partial is already included by the layout and the JS is global — nothing else to wire.
11. Hide action buttons for owner on shared pages (`@if($userType !== UserType::OWNER)` on leads, refunds, payment-review, ride-status, staff).
12. `tests/Feature/OwnerDashboardTest.php` — owner 200, non-owner 403 on `/owner/dashboard`; seeder idempotency (run `UserTypeSeeder` twice, owner row survives).

## 7. Non-goals

- No separate owner login page / 2FA (later, uniform for all roles).
- No new JS chart library, no Livewire.
- No owner notifications yet (v2 — reuse `NotificationMaster`).
- No editing powers for the owner anywhere.
