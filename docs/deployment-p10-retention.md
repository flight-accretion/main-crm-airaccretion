# P10 Performance And Retention Release

This supplements deployment-accretion-crm-chat.md. It is not confirmation that
production infrastructure or credentials have been verified.

## Release Contents

- Include all staged application changes and the new WhatsApp retention service,
  command, view, tests, and index-repair migration in one reviewed release.
- Do not include local .env, credentials, uploads, or generated bootstrap caches.
- Docker build exclusions now omit runtime storage/app contents, storage keys,
  generated bootstrap caches, public/storage links, and local tool credentials.
  Existing runtime uploads/tokens must remain mounted from persistent storage;
  this change does not delete any existing local or server files.
- Preserve production APP_KEY and existing database, mail, WhatsApp and queue settings.
- Back up the database and persistent storage; retain the previous compatible release.

## Migration And Configuration

Use the existing deployment system to build/install the complete release. Production
requires PHP 8.2+ and the extensions required by composer.lock. Pause old workers
during the schema transition. Run commands inside the application container or
server release directory, as the application user:

```sh
php artisan down
composer install --no-dev --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan migrate:status
```

Review every pending migration. This release adds:

1. 2026_10_09_170000_add_p10_safe_performance_indexes
2. 2026_10_09_180000_add_p10_queue_search_cleanup_indexes
3. 2026_10_09_190000_remove_duplicate_crm_lead_code_index

The first two skip equivalent indexes. The third removes
p10_leads_crm_code_search_idx only when another full crm_lead_code index exists.
It preserves the sole index and does not delete lead records. Index creation may
lock large tables; allow an appropriate maintenance window. A pretend migration
is not proof of schema-dependent operations because schema checks may be skipped.

Keep these effective retention settings in production configuration:

```dotenv
CRM_WHATSAPP_HISTORY_RETENTION_MONTHS=6
CRM_GOOGLE_CHAT_EVENT_RETENTION_MONTHS=2
```

Do not replace production .env with .env.example. The deprecated email-log-days
option no longer deletes email lead logs; these are retained for duplicate protection.

```sh
php artisan migrate --force
php artisan migrate:status
php artisan config:cache
php artisan view:cache
php artisan crm:prune-whatsapp-history --dry-run
php artisan crm:prune-technical-data --dry-run
php artisan queue:health
php artisan schedule:list
php artisan queue:restart
php artisan up
```

Stop on any failure. Do not use migrate:fresh/reset or indiscriminate rollback.
Do not run backup, seed, retry, or destructive cleanup commands merely as a test.
Existing Supervisor/container services must restart workers after queue:restart.

## Scheduler And Media Permissions

- Keep one active scheduler running schedule:run every minute, on the same release
  and production configuration. Do not add a second scheduler accidentally.
- Confirm schedule:list includes crm:prune-whatsapp-history daily at 02:40 in the
  configured application timezone. Confirm the existing Google Chat cleanup too.
- WhatsApp messages and stored Drive media older than six calendar months are
  deleted by the scheduled job. Exact-cutoff records remain until they expire.
- Confirm the existing Google Drive identity can delete a controlled test upload.
  Do not use a customer file for this check. Failed deletions keep the message for
  retry and are logged; verify these failures do not accumulate.
- Google Chat technical events older than two months are removed regardless of
  pending/failed/processed status. This intentionally prevents retrying those events.
- This cleanup affects CRM copies and uploaded Drive media, not external provider
  message history or retained backups. Maintain a separate backup-retention policy.

## Smoke Checks

1. Open an approved test conversation: latest 50 messages load in chronological order.
2. Load older messages repeatedly: no gaps/duplicates, stable scroll position, and
   polling preserves the loaded history. Exhausted history hides the older control.
3. Confirm Sales users cannot fetch another user's out-of-scope conversation/media.
4. Confirm a previously processed email message ID still returns duplicate_email.
5. Confirm existing lead pages, reports/exports, booking mail and payment workflows
   using controlled test records; do not mutate real payment records for testing.
6. Inspect application/worker logs and scheduled cleanup summaries after deployment.

Local regression commands (development PHP with SQLite and Node.js):

```sh
php tests/Support/verify_whatsapp_retention.php
node tests/Support/verify_whatsapp_history_ui.js
```

These use isolated fixtures and do not delete business messages or remote media.
They do not replace production smoke testing or a full PHPUnit suite.
