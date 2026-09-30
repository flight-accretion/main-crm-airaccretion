# Google Chat Lead Conversation Bridge

Status: user-approved design; local implementation complete. Live staging acceptance remains pending.

## Approved Intent

Sales and Operations can continue the same lead conversation from the CRM Add Follow-up page or Google Chat. One Lead UUID maps to one CRM conversation and one Google thread in an approved Operations Space. Another lead for the same customer gets a different thread.

The first release synchronizes text and supported message edits/deletions. Binary attachments, reactions, pins, Google task state, and read receipts are not synchronized. Existing CRM versions of these features remain available.

## Safety Boundary

- Preserve Sales ownership, LeadFollowup, next follow-up, payment, receipts, KPI, booking, ride, refund, and OperationCase behavior.
- Preserve existing conversation/message UUIDs and Google resource mappings.
- Keep Add Follow-up structure unchanged; chat remains in the existing History/chat area.
- No production migration, deployment, Google account changes, or real customer test messages during implementation.
- Google failures must not prevent saving a CRM conversation message.

## Current Implementation To Reuse

Reuse LeadChatConversation, LeadChatMessage, LeadChatMessageObserver, SyncLeadChatMessageToGoogleChat, GoogleChatClient, GoogleChatBridgeService, GoogleChatInboundService, GoogleChatSubscriptionService, and the existing Pub/Sub endpoint.

Do not add the attachment document's second set of outbound jobs, duplicate subscription table, or parallel google_chat_thread_name fields. Existing google_space_name, google_thread_name, and google_thread_key remain authoritative.

Correct the current inbound creation path: withoutEvents suppresses the UUID generator, so an explicit UUID is required when inserting Google-origin messages under that mechanism.

## Routing And Provisioning

- Keep one unique conversation per lead. Provision a Google thread on the first explicit Operations chat connection, not every page load.
- Choose from an administrator-approved Space allowlist. Verify integration-account membership and the selected Operations member's identity/membership before connection.
- Store the Operations CRM UUID separately from Sales ownership. Never validate this value as an integer.
- Persist the first enquiry/template message and stable Google client message ID before sending. Subsequent messages remain pending until that thread is known.
- Existing mapped leads reuse the exact saved Space/thread. Unmapped existing leads retain their CRM history; historical messages are not automatically exported.
- Once mapped, ordinary users cannot move a conversation to another thread/Space. Changing the Operations assignee must not silently change the thread or Sales owner.
- A Google thread created manually without a CRM mapping is not automatically matched by customer name or phone.

## Outbound Reliability

CRM messages and local notifications commit together. Google delivery is independent through one outbound mechanism, reusing the observer/job rather than adding controller dispatches.

Persist recoverable pending synchronization state in the same transaction as the message. A scheduled recovery dispatcher retries pending work if queue dispatch fails after commit. Workers serialize provisioning and message operations per conversation; stable request/client message IDs make retries idempotent. An existing-thread reply must not silently fall back to creating a new thread.

Expose pending, syncing, synced, and failed states plus an authorized retry action. Retry targets the same local message and Google identity. Edits/deletes use current local state to prevent a delayed send or retry from restoring deleted content. Google-origin messages are not re-exported.

## Inbound Reliability And Security

Preserve the existing /api/google-chat/pubsub route. Verify authenticated Pub/Sub push JWT signature, issuer, audience, expected service-account email, and verified-email claim. A query-string secret alone is not the production trust boundary.

Persist validated event metadata in a durable inbox before acknowledging delivery. Process asynchronously, supporting individual and batch create/update/delete events. Deduplicate deliveries and Google message names using database constraints, not only a prior existence check. Preserve tombstones and event versions so delayed events cannot resurrect deleted messages or overwrite newer state.

Match the exact Space and thread. Resolve the actual canonical Google users/{id} to a verified CRM user mapping. Do not attribute every reply to the selected Operations assignee. Unknown identities remain recorded as external/unmapped identities, never impersonate CRM users, and require an approved Space membership policy before their content is accepted.

Maintain approved-Space subscriptions and renew them before expiry. Record delivery/renewal failures and provide retry/reconciliation for outages. Do not acknowledge failed persistence as success.

## Identity And Visibility

Use the existing dedicated integration OAuth account, subject to administrator configuration. CRM outbound messages appear under that Google account; text includes the actual CRM sender's name. This does not impersonate individual employees' Google identities.

Space membership controls Google-side visibility. A thread is not a private room within a shared Space. CRM authorization remains enforced separately for messages, attachments, routing, and retry actions.

## Data Compatibility

Use additive migrations against the actual existing Google bridge schema. Add only absent routing/sync metadata, verified Google identity mappings, and durable inbox/recovery data that the existing schema cannot represent. Keep user-reference columns UUID-compatible. Do not recreate google_chat_subscriptions or drop legacy mappings.

Template service/date/passenger values come from verified existing Lead service/ride/follow-up readers, not guessed attributes. Never expose OAuth tokens or sensitive webhook credentials in UI, logs, or reports.

## Acceptance Criteria

1. Two leads produce two distinct threads; repeated use of either lead always reuses its thread.
2. CRM-to-Google and Google-to-CRM text delivery works without echo loops or duplicate rows under retries.
3. Google replies retain the verified actual sender identity; cross-lead/Space UUID tampering is rejected.
4. Google/API/queue outages preserve CRM saves and produce recoverable sync state.
5. Supported edit/delete paths handle retries and out-of-order delivery without resurrecting content.
6. Individual and batch Pub/Sub events, invalid JWTs, duplicate events, and renewal failures have focused tests.
7. Existing chat features and authorization remain working; unrelated business records do not change in isolated tests.
8. Existing mappings and history survive additive schema changes.
9. PHP lint, Blade rendering, focused chat/bridge tests, and relevant existing regression tests run before completion.
10. Authenticated Google end-to-end tests and browser checks require configured test accounts/Spaces; unavailable checks are reported NOT TESTED, never inferred as passed.

## Review Gate

Review this written design before the implementation plan is prepared. No application code has been changed as part of this design document.
