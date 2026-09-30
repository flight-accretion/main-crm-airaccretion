# Google Chat Lead Conversation Bridge Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Synchronize each lead's CRM conversation with one Google Chat thread without affecting CRM business workflows.

**Architecture:** Extend the existing Google Chat bridge, observer, queue job, and resource mappings. Add explicit connection provisioning, durable inbound processing, verified identities, and recoverable outbound state. Keep Google failures outside CRM message-saving transactions.

**Tech Stack:** Existing Laravel 8, PHP 8.2+, Eloquent UUID models, Laravel queues, Google API client, Google Workspace Events/Pub/Sub, PHPUnit 9.

**Spec:** `docs/superpowers/specs/2026-09-29-google-chat-bridge-design.md` (approved by user).

## Global Constraints

- Preserve Sales ownership, LeadFollowup, next follow-up, payment, receipts, KPI, booking, ride, refund, and OperationCase behavior.
- Preserve existing conversation/message UUIDs and Google resource mappings.
- Keep Add Follow-up structure unchanged; chat remains in the existing History/chat area.
- No production migration, deployment, Google account changes, or real customer test messages during implementation.
- Google failures must not prevent saving a CRM conversation message.
- Text and supported edits/deletions only; existing attachments, reactions, pins, tasks and read receipts remain CRM-native.
- Preserve dirty working-tree changes. No automatic commits, dependency upgrades, or database resets.
- Test setup must override database connection before application bootstrap; default phpunit.xml currently does not isolate the database.

## Review Focus

- Two simultaneous connection requests must create only one mapping/template (Task 2).
- A queued send after local deletion must never recreate deleted text (Task 3).
- Queue dispatch failure after database commit must leave recoverable state (Task 3).
- Out-of-order batch events and duplicate redelivery must not resurrect messages (Task 4).
- A reply from a different Space member must not impersonate the selected Operations user (Task 4).

## Task 1: Safe Test Harness And Additive Schema

**Files:** Create `tests/Feature/GoogleChat/GoogleChatTestCase.php`, `tests/Feature/GoogleChat/GoogleChatSchemaTest.php`, `database/migrations/2026_09_29_180000_harden_google_chat_bridge.php`, `app/Models/GoogleChatIdentity.php`, `app/Models/GoogleChatEvent.php`; modify existing `LeadChatConversation`, `LeadChatMessage`, and `GoogleChatSubscription` models.

**Interfaces:** Keep existing `google_space_name`, `google_thread_name`, `google_thread_key`, and `google_message_name`. Add conversation `operations_user_id` (nullable UUID), `google_connection_status` (unmapped/pending/ready/failed), and `google_template_message_id` (nullable UUID). Add message `google_sync_version` integer, `google_synced_version` integer, `google_sync_attempted_at` timestamp. Identity stores unique canonical `google_user_name`, nullable unique CRM UUID, and verified timestamp. Event inbox stores unique Pub/Sub message ID, event type/time, JSON payload, processing status/error, attempts, and processed timestamp. Subscription storage remains existing table.

- [ ] Write `test_existing_mappings_survive_migration`, `test_user_references_accept_uuids`, and `test_event_delivery_id_is_unique`; assert original IDs/thread names unchanged and duplicate event insertion rejected.
- [ ] Run `php vendor/bin/phpunit tests/Feature/GoogleChat/GoogleChatSchemaTest.php`; expect failure for absent schema, never connection to configured CRM database. Harness uses isolated SQLite memory with explicit minimal fixtures; PostgreSQL concurrency verification requires a separately supplied disposable database.
- [ ] Implement additive migration and model casts/relations. Do not recreate subscriptions or rename existing fields. Preserve legacy rows and mapping readiness; do not enqueue historical messages.
- [ ] Rerun command; expect all schema assertions PASS.

## Task 2: Explicit Lead Connection And Membership Validation

**Files:** Create `app/Services/GoogleChat/LeadGoogleChatConnectionService.php`, `app/Http/Controllers/LeadGoogleChatConnectionController.php`, `tests/Feature/GoogleChat/GoogleChatConnectionTest.php`; modify `GoogleChatClient.php`, `config/services.php`, `routes/web.php`.

**Interfaces:** `LeadGoogleChatConnectionService::connect(Lead $lead, User $actor, User $operationsUser, string $spaceName): LeadChatConversation`. `GoogleChatClient::listSpaceMembers(string $spaceName): array` returns all pages. Controller supplies authenticated `options`, `connect`, and `retry` endpoints under existing lead-chat routing; reuse existing chat access policy, with connection restricted to authorized roles. Space allowlist lives in `services.google_chat.allowed_spaces`; missing allowlist denies new connections, not legacy local chat.

- [ ] Write `test_two_leads_receive_distinct_thread_keys`, `test_existing_mapping_is_reused`, `test_concurrent_connect_is_idempotent`, `test_cross_lead_access_is_denied`, `test_nonmember_and_nonallowlisted_space_are_rejected`; assert one template per lead and unchanged Sales owner.
- [ ] Run `php vendor/bin/phpunit tests/Feature/GoogleChat/GoogleChatConnectionTest.php`; expect missing service/routes failures.
- [ ] Implement member verification using canonical Google identity and verified CRM mapping, including integration-account membership. Unknown identities require explicit administrator mapping, never guessed display-name matching. Use transactional conversation locking plus existing lead uniqueness and race recovery.
- [ ] Persist initial template using existing service/ride/follow-up readers, stable local UUID and deterministic Google client ID before asynchronous delivery. Only explicit connection creates template; no backfill. Mapped conversation cannot be silently moved.
- [ ] Rerun tests; expect PASS. True concurrent locking test runs on disposable PostgreSQL; report unavailable environment as NOT TESTED.

## Task 3: Recoverable Outbound Delivery

**Files:** Modify `app/Observers/LeadChatMessageObserver.php`, `app/Jobs/SyncLeadChatMessageToGoogleChat.php`, `app/Services/GoogleChat/GoogleChatBridgeService.php`, `app/Services/GoogleChat/GoogleChatClient.php`, `app/Console/Kernel.php`; create `app/Console/Commands/RetryGoogleChatSync.php`, `tests/Feature/GoogleChat/GoogleChatOutboundTest.php`.

**Interfaces:** Extend `GoogleChatClient::createMessage(string $text, string $threadKey, string $crmMessageId, ?string $spaceName = null, ?string $threadName = null): array`. Existing job remains the only outbound job. `google-chat:retry-pending` redispatches pending/failed/stale-syncing current messages without creating new local rows. Authenticated retry accepts a local message UUID, verifies lead access, and redispatches the same job.

- [ ] Write `test_google_failure_preserves_crm_save`, `test_after_commit_dispatch_failure_is_recoverable`, `test_retry_reuses_client_id`, `test_deleted_message_is_not_sent`, `test_google_origin_is_not_exported`, `test_reply_uses_exact_saved_thread`; assert one observer dispatch path and matching sync versions.
- [ ] Run `php vendor/bin/phpunit tests/Feature/GoogleChat/GoogleChatOutboundTest.php`; expect failures in current reliability paths.
- [ ] Store pending version/state during message transaction, catch/log queue dispatch failure without failing committed CRM save, and use shared conversation locks in workers. Provision template first; later messages wait for saved canonical thread. For known threads use `REPLY_MESSAGE_OR_FAIL`, never fallback.
- [ ] Derive create/update/delete from latest row state and version under lock, avoiding stale job actions. Record Google resource IDs before marking matching version synced. Reconcile timeouts with deterministic client ID; preserve deletion tombstones.
- [ ] Schedule bounded recovery batches with backoff and attempt timestamps; include failed provisioning. Rerun tests; expect PASS.

## Task 4: Authenticated Durable Inbound Processing

**Files:** Modify `app/Http/Controllers/Api/GoogleChatPubSubController.php`, `app/Services/GoogleChat/GoogleChatInboundService.php`, `config/services.php`; create `app/Services/GoogleChat/GoogleChatPushVerifier.php`, `app/Jobs/ProcessGoogleChatEvent.php`, `tests/Feature/GoogleChat/GoogleChatInboundTest.php`.

**Interfaces:** `GoogleChatPushVerifier::verify(string $jwt): array` returns validated claims or throws authentication exception. `ProcessGoogleChatEvent` takes inbox UUID. Preserve `/api/google-chat/pubsub`. Existing inbound service handles individual/batch message events, using event time and stored Google update time for ordering.

- [ ] Write `test_invalid_signature_audience_or_email_is_rejected`, `test_inbox_failure_is_not_acknowledged`, `test_duplicate_event_is_processed_once`, `test_batch_create_update_delete`, `test_delayed_create_cannot_resurrect_deleted_message`, `test_actual_member_identity_is_preserved`, and `test_inbound_create_has_uuid`.
- [ ] Run `php vendor/bin/phpunit tests/Feature/GoogleChat/GoogleChatInboundTest.php`; expect failures for absent verifier/inbox and current UUID suppression.
- [ ] Verify Google-signed JWT with installed Google client/auth library, cached public keys, issuer, expiry, audience, configured service-account email and email_verified. Do not hand-roll cryptography. Missing verification config fails closed; query secret is insufficient.
- [ ] Persist validated envelope before acknowledgement; dispatch after commit. Recovery command also redispatches inbox rows. Process batches idempotently; use canonical Google resource names and `clientAssignedMessageId` for echo suppression.
- [ ] Require mapped exact Space/thread and current approved membership. Verified identities resolve CRM user; other approved members retain external sender identity, never assignee impersonation. Allocate explicit UUID when using withoutEvents. Store soft-delete tombstones even when delete precedes create; ignore stale versions. Retry fetch/network failures, without duplicate notifications.
- [ ] Rerun tests; expect PASS.

## Task 5: Subscription Lifecycle And Outage Recovery

**Files:** Modify `app/Services/GoogleChat/GoogleChatSubscriptionService.php`, `app/Console/Commands/EnsureGoogleChatSubscription.php`, `app/Console/Kernel.php`; create `app/Console/Commands/ReconcileGoogleChatMessages.php`, `tests/Feature/GoogleChat/GoogleChatSubscriptionTest.php`.

**Interfaces:** `GoogleChatSubscriptionService::ensureForSpace(string $spaceName): GoogleChatSubscription`; existing ensure command iterates approved mapped Spaces and preserves compatible legacy subscriptions. `google-chat:reconcile` reads paginated messages only for known mapped threads, feeding the same inbound ingestion without exporting history.

- [ ] Write `test_subscription_is_reused_per_space`, `test_expiring_subscription_is_renewed`, `test_renewal_failure_is_recorded`, `test_reconciliation_deduplicates_existing_messages`; assert no unauthorized Space subscriptions.
- [ ] Run `php vendor/bin/phpunit tests/Feature/GoogleChat/GoogleChatSubscriptionTest.php`; expect missing scoped behavior failures.
- [ ] Implement scoped renewal, recreate-on-expiry/not-found, logged errors and retry; reconcile using durable per-conversation watermark with overlap and pagination. Ignore unmapped threads. Do not treat temporary fetch errors as deletion.
- [ ] Rerun tests; expect PASS. Explicitly document edits/deletions outside available event retention as a recovery limitation until verified against live API.

## Task 6: Existing Chat UI, Regression And Handoff

**Files:** Modify `resources/views/admin/pages/follow-ups/partials/lead-chat.blade.php`, `app/Http/Controllers/LeadChatController.php`; create `tests/Feature/GoogleChat/GoogleChatUiTest.php`, `docs/google-chat-bridge-setup.md`.

**Interfaces:** Chat response adds connection status and per-message sync status without removing current fields. UI uses Task 2 connection/retry endpoints. Display actual sender and Google origin; editing/deleting Google-origin messages from CRM remains restricted to supported authorization.

- [ ] Write `test_chat_remains_usable_while_google_is_unavailable`, `test_connection_controls_follow_permissions`, `test_retry_does_not_duplicate_message`, `test_existing_chat_features_and_business_records_are_unchanged`; assert no mutation of Sales/finance/KPI fixtures during chat operations.
- [ ] Run `php vendor/bin/phpunit tests/Feature/GoogleChat`; expect UI-specific failures before changes.
- [ ] Integrate connection selection and status/retry controls in existing History/chat area, preserving attachments/tasks/reactions and existing design. No page-load provisioning, secret exposure, or disabling local sends due to Google outage.
- [ ] Rerun focused suite and safe isolated existing lead-view, follow-up, Operations and authorization tests. Lint every changed PHP file, compile Blade in test storage, and run `git diff --check`. Record pre-existing failures separately.
- [ ] Verify desktop/mobile chat rendering and interaction with test data when browser access exists. Live Google test: two synthetic leads, both directions, actual second Operations sender, retries, edit/delete, reconnect and subscription renewal in approved test Space only. Unavailable checks are NOT TESTED.
- [ ] Document required OAuth scopes/member setup, approved Spaces/identities, authenticated Pub/Sub audience/email, queue worker, scheduler, additive migration, rollback feature-disable and sync recovery. No credentials committed; no live deployment performed.

## Execution Handoff

Recommended execution: Native, task-by-task in this session, because the existing bridge interfaces are tightly coupled and the user requests fast delivery. Review this plan before source changes. Independent final review is performed only if reviewer tooling is available; otherwise disclose that limitation and provide local test evidence. No guarantee of live Google behavior without configured test credentials.
