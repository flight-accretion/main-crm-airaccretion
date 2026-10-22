# Lead edit and follow-up status preservation

- Normal Add Follow-up defaults to `keep` and displays the latest recorded lead status. Validation redirects retain an explicitly chosen status.
- The server resolves `keep`, empty or omitted status from the latest follow-up ordered by `created_at DESC, id DESC`, inside the save transaction. A lead without prior status starts at Initiated, not Active.
- Keeping a status never records a received amount, payment method, paid date, payment audit or points redemption. Existing explicit payment and cancellation actions retain their behavior and validations.
- A note-only follow-up without pricing inputs carries the existing quotation, but never copies a receipt or received amount. Explicit service and total edits retain the existing calculation path.
- Edit Lead shows the latest recorded status rather than the status attached to the furthest scheduled follow-up date. Saving contact/trip fields does not update client Active/Inactive status or lead payment status.
- Operations case follow-ups retain their separate existing status workflow. No new backend lead statuses or permission changes were introduced.
- No migration or historical status repair is required by this fix. Leads already reset by earlier saves require individual history review before any corrective status change.

Regression coverage: `tests/Feature/Kpi/LeadStatusPreservationTest.php` uses isolated SQLite data and fake external messaging.
