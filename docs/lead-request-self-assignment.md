# Unassigned Lead Self-Assignment

- Lead-request search remains limited to Super Admin and active Sales roles, only after a validated name/phone search, with the existing limited fields and 20-result cap.
- Active Sales Executives, Sales Managers and Senior Sales Managers receive an **Assign to me** action for unassigned results without a pending transfer.
- The POST endpoint fixes the destination to the authenticated user. Posted recipient IDs cannot select another person. Both route middleware and controller checks deny other roles and inactive users.
- A locked database recheck rejects any lead that has since been assigned, including repeat claims. Pending transfers are preserved, not cancelled by a self-claim.
- Assignment uses the existing shared ownership/audit/queue-update implementation. Ownership, queue updates, allocation audit and the active follow-up note commit together; failure rolls them all back.
- Queued claims retain the existing source handoff through `finalizeManualQueuedAssignment`. A failed external handoff is logged and reported as a warning without pretending ownership failed.
- Super Admin can assign unassigned results to an active Sales recipient through the existing direct-assignment flow. Super Admin is not added as a Sales recipient: self-assignment follows the CRM's existing active-Sales destination rule.
- Other-owned lead requests and own-lead transfer offers still require the existing approval flow. Direct reassignment permissions are not broadened. HR remains excluded from this popup/mutation.

No migration is needed. Deploy the route/controller/service/JS changes together and refresh route/view caches through the normal deployment process. The popup script version is bumped to avoid stale cached JavaScript.

Tests cover all Sales roles, stale ownership, duplicate submission, denied roles/inactive users, pending transfers, forged recipients, queue source handoff, transaction rollback, and existing direct reassignment/request/offer behavior. Browser checks exercise the actual popup script and CSRF POST form on desktop/mobile. Local standalone adapters run these against an isolated SQLite database; three older tests requiring the full Laravel HTTP test harness are not run by that adapter.
