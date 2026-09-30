# Accretion CRM Chat Release: Docker Checklist

This is a deployment runbook based on the current working tree, not a certification that every changed file is regression-free. The release also includes Operations KPI, voucher delivery tracking and booking-email changes. Production pending migration state and infrastructure have not been inspected.

## 1. Before Building Or Deploying

- Commit the complete intended release, including new/untracked PHP files, views, tests, seeders and all five migrations listed below. Do not commit .env, tokens, private uploads or credentials.
- Back up the production database and persistent storage, and retain the previous image/release. Verify the restore procedure before migrating.
- Preserve the existing production APP_KEY. Never run key:generate during deployment. Keep APP_ENV=production, APP_DEBUG=false and APP_URL=https://airaccretion.com.
- Do not replace the production .env with the local .env. Preserve database, mail, WhatsApp, KPI cutover and other existing production settings.
- Important build precaution: the current Dockerfile uses COPY . ., while .dockerignore does not exclude storage/app/private/google-chat. Exclude that private directory from the Docker build context before building from a local workspace, or build from a clean committed checkout that does not contain it. Gitignore does not control Docker COPY.
- Mount persistent storage/app for uploads and private credentials. Share the private OAuth token directory between web and Google Chat workers, using the same production APP_KEY. Use a shared lock-capable cache when web/worker containers do not share the same cache filesystem.
- Rebuild/recreate the application image through the existing deployment system. Running Composer inside an old image does not deploy the new source files. The supplied Dockerfile already installs production Composer dependencies; it only starts PHP-FPM, not workers or cron.

## 2. Database And Cache Commands

Enter the new application container from the Docker host (replace APP_CONTAINER with the actual name):

```sh
docker exec -it APP_CONTAINER sh
cd /var/www/html
```

Stop/pause the old queue workers during the schema transition using your existing process manager. Perform these commands in a maintenance window; run them as the application's writable-storage user where practical:

```sh
php artisan down
# Needed only if dependencies were not installed while building the image:
composer install --no-dev --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan migrate:status
php artisan migrate --pretend --force
```

Review every pending migration before continuing. This release introduces:

1. 2026_09_28_110135_create_lead_chat_tables
2. 2026_09_28_162820_add_google_sync_to_lead_chat
3. 2026_09_29_171610_add_customer_sent_tracking_to_vouchers
4. 2026_09_29_180000_harden_google_chat_bridge
5. 2026_09_30_130000_repair_google_chat_event_version

Already applied migrations are skipped. The final repair handles databases where the earlier hardening migration was applied before the event-version column was added. Unexpected additional pending migrations need their own review.

```sh
php artisan migrate --force
php artisan migrate:status
```

Stop on any failure. Do not run migrate:fresh, migrate:reset, or automatically roll back chat-table migrations containing real messages. Do not bring the application up until required schema and configuration checks pass.

## 3. Operations KPI Seed

If this release is intended to activate the new Operations KPI defaults, review/back up any existing customized Operations KPI template, then run this specific seeder once:

```sh
php artisan db:seed --class=OperationsKpiTemplateSeeder --force
```

It updates metric definitions, targets and weights, deactivates codes outside the definition, and creates assignments for eligible users without an active assignment. It is not a harmless read-only check. Do not run the entire DatabaseSeeder or --seed indiscriminately. Existing voucher delivery timestamps are not backfilled by this release; never fabricate historic delivery times.

## 4. Production Google Chat Configuration

Keep Google Chat disabled until OAuth, identity mappings, public push authentication and workers are ready. Local CRM chat does not require enabling the Google bridge.

```dotenv
GOOGLE_CHAT_ENABLED=false
GOOGLE_CHAT_CLIENT_ID=YOUR_EXISTING_OAUTH_CLIENT_ID
GOOGLE_CHAT_CLIENT_SECRET=YOUR_EXISTING_OAUTH_CLIENT_SECRET
GOOGLE_CHAT_REDIRECT_URI=https://airaccretion.com/admin/google-chat/oauth/callback
GOOGLE_CHAT_ALLOWED_SPACES=spaces/AAAAWIIVzEQ,spaces/AAAAuOpRJoM
GOOGLE_CHAT_SPACE_LABELS='{"spaces/AAAAWIIVzEQ":"Accretion Aviation Enquiry","spaces/AAAAuOpRJoM":"Accretion Yacht enquiry"}'
GOOGLE_CHAT_INTEGRATION_USER=users/101345639298371392803
GOOGLE_CHAT_SPACE_NAME=spaces/AAAAWIIVzEQ
GOOGLE_CHAT_PUBSUB_TOPIC=projects/accretion-aviation-crm-509807/topics/accretion-crm-chat-events
GOOGLE_CHAT_PUBSUB_AUDIENCE=https://airaccretion.com/api/google-chat/pubsub
GOOGLE_CHAT_PUBSUB_SERVICE_ACCOUNT=YOUR_ACTUALLY_CREATED_PUSH_SERVICE_ACCOUNT_EMAIL
GOOGLE_CHAT_QUEUE_CONNECTION=google_chat
GOOGLE_CHAT_SYNC_QUEUE=google-chat
```

Do not leave placeholder values configured as though setup were complete. Use these production URLs only on the deployed production app. Add the exact production OAuth callback to Google Cloud's authorized redirect URIs; the localhost callback can remain separately for local testing.

Authorize Suraj's account from the production Super Admin session at /admin/google-chat/oauth. This creates a token encrypted with the production APP_KEY. A token file copied from local may be undecryptable if APP_KEY differs; do not solve that by changing the production APP_KEY. The encrypted token file takes precedence over GOOGLE_CHAT_REFRESH_TOKEN.

After editing environment values, refresh the production configuration. If environment values are injected by Docker rather than a mounted .env file, recreate the affected containers so they receive them.

```sh
php artisan config:cache
php artisan view:cache
php artisan up
```

Ensure storage and bootstrap/cache are writable by the application and worker users. If public/storage is absent and your existing public-upload flows require it, run php artisan storage:link. Never link storage/app/private to a public path.

## 5. Production Identity Mapping

Mappings created locally do not automatically exist in production. Verify each employee's production CRM UUID; do not assume it equals the local UUID. The Google identities verified during local testing were:

| Employee | Google user | Approved membership verified |
| --- | --- | --- |
| Harsh / info@accretion.in | users/114162594837211432266 | Aviation only |
| Suraj / suraj@accretion.in | users/101345639298371392803 | Aviation and Yacht |
| Jaffar / marine@accretion.in | users/104688877920060498547 | Aviation and Yacht |

```sh
php artisan google-chat:list-spaces
php artisan google-chat:list-members spaces/AAAAWIIVzEQ
php artisan google-chat:list-members spaces/AAAAuOpRJoM
php artisan google-chat:map-user HARSH_PRODUCTION_UUID users/114162594837211432266 --space=spaces/AAAAWIIVzEQ --confirm-identity
php artisan google-chat:map-user SURAJ_PRODUCTION_UUID users/101345639298371392803 --space=spaces/AAAAWIIVzEQ --confirm-identity
php artisan google-chat:map-user JAFFAR_PRODUCTION_UUID users/104688877920060498547 --space=spaces/AAAAuOpRJoM --confirm-identity
```

Set up authenticated Pub/Sub push to the production webhook with the exact audience and created push service account. Keep wrapped JSON payloads. Topic publisher/IAM details are in google-chat-local-activation.md; do not create a second production push subscription accidentally if one already exists.

Set GOOGLE_CHAT_ENABLED=true and refresh configuration once the preceding setup is ready:

```sh
php artisan config:cache
php artisan google-chat:ensure-subscription
```

Confirm successful subscriptions for both approved Spaces. Failure here means incoming push is not ready, even if outbound messages work.

## 6. Persistent Worker And Scheduler

Run a dedicated worker through Supervisor or a separate Docker service based on the same release image, with the same database, configuration, persistent token storage and shared cache:

```sh
php artisan queue:work google_chat --queue=google-chat --timeout=120 --tries=5 --sleep=3
```

Use automatic restart and a stop grace longer than 120 seconds. The dedicated database queue has retry_after=240. Do not run this as a one-off interactive terminal and assume it survives disconnects/container replacement. Keep the CRM's existing default-queue workers running after deployment as well.

Restart existing workers after the release/configuration switch:

```sh
php artisan queue:restart
```

This asks workers to exit gracefully; a process manager must restart them. It does not create or supervise a worker by itself.

The scheduler must run once per minute. If already configured, retain the existing single scheduler instead of adding a duplicate. For example, on the Docker HOST's crontab, replace APP_CONTAINER with the actual name:

```cron
* * * * * docker exec -u www-data -w /var/www/html APP_CONTAINER php artisan schedule:run >> /var/log/accretion-crm-scheduler.log 2>&1
```

Verify the chosen user has the required filesystem access. A dedicated supervised scheduler container is also valid. The PHP-FPM Dockerfile itself does not install/start cron.

## 7. Smoke Tests And Recovery

- Confirm production login, Lead View and Add Follow-up render without errors. Check chat Space labels and employee dropdown.
- On an approved test lead, connect the appropriate employee/Space, send labelled CRM text and reply in that exact Google thread. Verify sender identity and no duplicate delivery. Do not test by changing real customer payment/follow-up data.
- Check attachments, existing notifications/tasks, Operations KPI page, booking-email preview and voucher delivery tracking with controlled test records. A Google connectivity test alone does not cover these workflows.
- Run php artisan migrate:status, php artisan queue:failed, and inspect storage/logs/laravel.log. Check worker/container logs and Pub/Sub delivery status. Do not bulk retry old failed production jobs without reviewing their side effects.
- If the Google bridge fails, set GOOGLE_CHAT_ENABLED=false, refresh config and restart the Google worker. Preserve chat schema/data. Incoming messages are not automatically guaranteed during downtime; reconcile after restoring access.
- For an application regression, use the previous compatible image and a reviewed database restore/forward-fix plan. Avoid destructive migration rollback of new chat tables after users have sent messages.

No production commands in this document were executed by the coding agent. The chat suite and selected CRM tests passed locally; this is not a full audit of every file in the larger release.
