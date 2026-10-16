# Target Master Incentive Download

The HR/Super Admin-only download keeps its existing month, year, person and
Excel/CSV controls. It does not modify Account Reports, Sales Reports, target
configuration, ownership, approvals, or scheduling. There is no new migration.

## Files and Source

Excel contains Sales Detail followed by Employee Summary. CSV contains Sales
Detail only. Both include a final TOTAL row. The original 17 Sales Report columns,
their order, green Excel header, text contacts and monetary formats are retained.
The uploaded sample file is a design reference, never a source of production data.

Sales Detail reuses SalesReportExport and the existing monthly incentive ledger.
Month/year eligibility is approved Paid Date, not booking date or file date.
Existing qualifying statuses, net service fees/GST calculation, completed/
processed refunds, and full-sale-per-paid-lead semantics are unchanged. A sale
appears once even if multiple approved payments exist. Received/Pending Amount
retain the existing report's definitions; they are not redefined as month-only
receipts. GST/fees and refunds are not deducted again for incentives.

SalesReportExport has an optional internal lead-ID restriction, internal lead_id
metadata, deterministic latest-followup ordering and cached product/service
lookups. Normal report callers need no changes; their 17-column output remains
unchanged. Selected-person downloads fetch only relevant report detail rows.

## Attribution

New columns are Individual Incentive, Own Incentive, Team Incentive,
Company/Product Incentive, Total Incentive and Incentive Recipients.

- Individual pays the selected employee on their own qualifying sales.
- Own pays a manager on their own sales at the manager's own multiplier.
- Team pays that manager on assigned-team sales at the team multiplier, excluding
  their own sales. It does not replace a team member's separate individual payout.
- Company/Product pays the configured employee on matching company-wide sales.
  A sale matching multiple selected products is counted only once for that person.
- Incentive Recipients explicitly identifies each beneficiary/category/amount.

All-employees detail keeps monthly CRM sales unique. Incentive amounts aggregate
all included active beneficiaries, without repeating financial sale rows. A
selected person's detail includes their own, team or product sales as configured,
but only their payout. An employee without incentive settings has real own sales
and no invented incentive. Employee Summary includes all selected active employees
and distinguishes Not configured from a configured zero payout.

## Reconciliation and Totals

The existing TargetIncentiveService owns eligibility and monthly payout rules.
Team assignments are primed in one query; no per-employee or per-sale database
lookup is introduced. Source sales are fetched in existing lead-based batches.

Fractional paise are assigned by largest remainder, with lead ID as a stable tie
break, separately for each employee/component. This makes detail sums equal the
existing rounded monthly payout rather than independently rounding each row and
accumulating a discrepancy. Duplicate active targets, missing detail or differing
source amounts stop export with a validation message instead of double-paying or
silently returning an unreconciled file.

Excel monetary totals use trusted SUM formulas with pre-calculated cached values;
CSV totals are numeric values. Text, contacts, dates, years and multipliers are
not summed. Text is bound as literal text and spreadsheet/CSV formula injection
is escaped. Zero is retained, not silently converted to a blank.

Employee Summary sales bases may overlap (for example an employee and their
manager share the same sale as an incentive basis). Its header and total-cell
comment explicitly say so. Unique company sales total is the Sales Detail Sales
Amount total; summary sales bases must not be treated as unique company revenue.

## Verification

SQLite/controller tests cover real source reuse, paid-date boundaries, refunds,
cancelled net sales, multiple approvals, individual/team/company attribution,
product overlap, employee scoping, missing settings, fractional paise, duplicate
configuration, chunk boundaries and constant query count as employees increase.
XLSX is reopened to verify sheet names, cached/calculated formula totals, styles,
literal text and matching payout totals. CSV includes the same detail and numeric
totals. Existing target persistence, permission and lead regression checks remain
separate. Live production smoke testing is not performed by these tests.
