# Google Chat task messages

- New Google connections use the lead's CRM product names in the Operations task title and outbound Product field. Multiple products are joined; missing products show N/A. Services are not substituted for products.
- Task messages list each saved travel segment separately, with its own date/time and origin/destination. Equal start/end times appear once; differing times show the range for that segment only. Missing dates show Date not set. Travel rows are not collapsed into an earliest/latest overall range. The shared Lead ride_dates accessor and previously sent messages are unchanged; no automatic resend is triggered.
- The initial task message stores the formatted outbound text for the sender's CRM history. The task UI now shows that body and refreshes immediately after a successful Connect response.
- Existing title-only template messages get a read-only formatted display fallback. This does not resend them or rewrite historical Google messages.
- Every task has a Send message action that selects the existing reply composer. Replies inherit the replied message's task_id after the existing same-conversation lookup and access checks. Text replies do not duplicate task cards.
- Existing thread mapping, asynchronous sync worker, stable remote message identifiers, retry status and duplicate-delivery protection are retained. Pending follow-ups sent before initial thread creation continue through the existing reconciliation process.
- Existing Sales/Operations/Super Admin chat permissions are unchanged. This does not grant HR or unrelated sales staff new access. No migration is required.

Local verification uses isolated SQLite with a fake Google client and desktop/mobile browser checks. It covers persisted product content, reconnect idempotency, sender history, old title-only display, both Sales and Operations replies using the same task/thread, delivery failure/retry and cross-conversation rejection. No real Google messages are sent during these checks. Authenticated production delivery still requires a running Google Chat queue worker and valid integration credentials.
