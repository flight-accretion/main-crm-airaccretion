# Execution: docs/superpowers/plans/2026-09-29-google-chat-bridge.md

Final local verification (2026-09-30): Google Chat suite 28 tests / 102 assertions; existing CRM regression selection 16 tests / 58 assertions; PHP lint 41 files; both embedded chat scripts parsed; git diff --check clean. Live staging gates below remain pending.

- Native execution authorized; user explicitly requested edits in the current folder and will commit themselves.
- Ruling: Work in the existing dirty main checkout, preserving unrelated edits, per explicit user direction. No commits, pushes, deployments or live migrations.
- Task 1: complete; schema tests RED -> GREEN, UUID and mapping preservation verified in SQLite.
- Tasks 2-5: implemented; connection, outbound, inbound, webhook and subscription tests passing, including failure recovery and fractional timestamp ordering.
- Task 6: UI implemented; Blade rendering and embedded JavaScript syntax checked. Setup guide: docs/google-chat-bridge-setup.md. Existing CRM regression selection passes (16 tests, 58 assertions).
- Ruling: Keep the initial connection message protected from manual edit/delete; deleting it before provisioning strands queued replies. Ordinary chat controls remain unchanged.
- Ruling: Use a dedicated google_chat database queue connection (240-second visibility, 120-second jobs); the existing connection has 90-second visibility and would redeliver running jobs. Other CRM queues are unchanged; deployment needs a dedicated worker.
- Ruling: Reconciliation pages all messages in selected mapped threads rather than filtering on creation time, which would miss edits to older messages. Rotation uses google_reconciled_at; cost is extra API reads.
- Live Google and production/PostgreSQL concurrency tests require separate test infrastructure; never infer success from mocks.
- Final review: independent reviewer could not run because of its usage limit; self-review performed instead. Found and fixed 404/409 retry handling, fractional timestamp deduplication, revoked-Space sends, remote mapping validation, and in-flight edit state handling.
- OAuth callback now persists encrypted private credentials without displaying the refresh token. Legacy environment token remains a fallback. Private credential volume and APP_KEY must be shared with workers.
- No commit, push, deployment, live migration or real Google message was performed. Browser interaction and production-engine concurrency remain staging acceptance gates, not completed checks.
