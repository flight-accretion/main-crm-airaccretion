# Lead Chat: Google Chat Deployment

## Scope

One lead UUID maps to one CRM conversation and one Google thread in an approved Space. A second lead for the same customer gets a separate thread. Existing mappings are reused. Connecting is explicit from the Add Follow-up chat panel; simply opening a lead does not create a Google thread. Existing unconnected message history is not automatically exported.

CRM text messages, edits and deletions are synchronized asynchronously. Google replies use the actual Google sender's verified CRM identity, or an external sender label when no identity is mapped. Attachments, reactions, pins, tasks and read receipts stay CRM-native. The integration account sends CRM messages with the staff name in the message text; it does not impersonate that person's Google account.

All members of a shared Google Space can see its threads. Selecting an Operations employee does NOT make the thread private to that employee. Use dedicated approved Spaces where visibility must be restricted.

## Before Deployment

Back up the database and persistent storage. Keep `GOOGLE_CHAT_ENABLED=false` while deploying and migrating. Review all other pending migrations in this dirty checkout separately; this guide covers only Lead Chat. No real database migration or Google account setup was performed during implementation.

Run inside the PHP application container from `/var/www/html`:

```sh
php artisan migrate:status
# After reviewing the pending migrations and taking a backup:
php artisan migrate --force
```

The additive hardening migration requires the existing Lead Chat and Google Chat bridge migrations. It preserves existing IDs and thread mappings. Do not run migrate:fresh or reset.

## Configuration

Use raw values, not Markdown links. Replace all example identifiers:

```dotenv
GOOGLE_CHAT_ENABLED=false
GOOGLE_CHAT_CLIENT_ID=YOUR_OAUTH_CLIENT_ID
GOOGLE_CHAT_CLIENT_SECRET=YOUR_OAUTH_CLIENT_SECRET
GOOGLE_CHAT_REDIRECT_URI=https://YOUR_CRM_HOST/admin/google-chat/oauth/callback
GOOGLE_CHAT_ALLOWED_SPACES=spaces/SPACE_ID
GOOGLE_CHAT_SPACE_NAME=spaces/SPACE_ID
GOOGLE_CHAT_INTEGRATION_USER=users/GOOGLE_ACCOUNT_ID
GOOGLE_CHAT_PUBSUB_TOPIC=projects/PROJECT_ID/topics/TOPIC_ID
GOOGLE_CHAT_PUBSUB_AUDIENCE=https://YOUR_CRM_HOST/api/google-chat/pubsub
GOOGLE_CHAT_PUBSUB_SERVICE_ACCOUNT=push-account@PROJECT_ID.iam.gserviceaccount.com
GOOGLE_CHAT_QUEUE_CONNECTION=google_chat
GOOGLE_CHAT_SYNC_QUEUE=google-chat
```

Multiple approved Spaces can be comma-separated. Canonical `users/ID` is not an email, employee name or CRM UUID. Both the dedicated integration account and selected Operations employee must be joined members of the selected Space. Configure a shared lock-capable cache such as Redis when web and queue processes run in multiple containers; isolated file caches cannot coordinate those containers.

## Google Setup

Enable Google Chat API, Google Workspace Events API and Pub/Sub in the intended Google Cloud project. Configure the OAuth consent screen and the exact callback URL above. Use a dedicated Workspace integration account that can read the selected Spaces. Workspace/admin restrictions and OAuth consent requirements must be satisfied before activation.

As CRM Super Admin, open `/admin/google-chat/oauth` and authorize that account with:

- `https://www.googleapis.com/auth/chat.messages`
- `https://www.googleapis.com/auth/chat.spaces.readonly`
- `https://www.googleapis.com/auth/chat.memberships.readonly`

The callback saves the refresh token encrypted using `APP_KEY` to `storage/app/private/google-chat/refresh-token.enc`. It does not display the token. Persist this private storage volume and share it with queue containers; keep the same APP_KEY. Never expose this directory through the web server or commit it. An existing `GOOGLE_CHAT_REFRESH_TOKEN` remains supported when no encrypted token file exists. A newly authorized encrypted file takes precedence.

Configure Workspace Events publishing permissions on the topic and an authenticated Pub/Sub push subscription targeting `/api/google-chat/pubsub`. Configure its OIDC service account email and audience to exactly match the environment values. Grant the Pub/Sub service agent the required token-creation permission on the push identity. Do not confuse this push service account with the Workspace OAuth account.

The receiver verifies Google's signature, issuer, audience, service-account email and email verification. The legacy query-string webhook secret alone is no longer accepted. Coordinate the authenticated push configuration before enabling the bridge.

Official setup references:

- https://developers.google.com/workspace/events/guides/create-subscription
- https://docs.cloud.google.com/pubsub/docs/authenticate-push-subscriptions
- https://developers.google.com/workspace/chat/list-members

## Identity Mapping And Activation

Verify each person's canonical Google identity through Workspace administration before mapping. Names alone are not proof of identity. Run for each relevant Operations employee (and other CRM employees whose inbound messages should show their CRM identity):

```sh
php artisan google-chat:map-user CRM_USER_UUID users/GOOGLE_USER_ID --space=spaces/SPACE_ID --confirm-identity
```

Set `GOOGLE_CHAT_ENABLED=true`, then:

```sh
php artisan config:cache
php artisan google-chat:ensure-subscription
php artisan queue:restart
```

Supervise a dedicated, continuously running worker:

```sh
php artisan queue:work google_chat --queue=google-chat --timeout=120 --tries=5 --sleep=3
```

The dedicated database connection uses a 240-second retry visibility window, longer than the job timeout. Do not use the sync driver. Ensure the existing jobs/failed-jobs tables exist. Leave other CRM workers running unchanged. Supervisor/container stop grace should exceed the job timeout.

Run Laravel's existing scheduler every minute, without adding duplicate scheduler instances:

```cron
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

Subscription renewal and reconciliation run hourly. Pending outbound messages and incomplete inbound events are re-enqueued every minute. Manual recovery commands are `google-chat:retry-pending` and `google-chat:reconcile`. Reconciliation reads mapped threads in rotating batches; its latency and API usage depend on thread count and history size.

## Acceptance Checks In A Test Space

1. Open an authorized lead, select a verified Operations employee and approved Space, then Connect. Repeat Connect: one mapping and one initial message must remain.
2. Send CRM text, reply directly in that Google thread, and verify both sides with the actual sender. A different lead must use a different thread.
3. Edit/delete text on each supported side. Redeliver an event and interrupt/restart the worker: no duplicate messages or resurrected deleted text.
4. Stop the Google worker, save CRM text, then restart it. Local text must remain and eventually synchronize.
5. Verify unauthorized users cannot connect/retry another lead, and invalid/unsigned push requests return 403.
6. Verify existing Add Follow-up, Sales owner, payments, KPI and Operations workflows remain unchanged.

Automated tests use isolated SQLite and fake Google responses. Actual Google OAuth/Space permissions, browser interactions and production-database concurrency still need these staging checks. No live customer messages should be used for smoke testing.

## Rollback

Set `GOOGLE_CHAT_ENABLED=false`, rebuild config cache and restart workers. Keep schema and mappings; do not roll back migrations or delete conversations. CRM-local chat remains available. Google-side messages during downtime can be reconciled after re-enabling the integration, subject to retained history and access permissions.
