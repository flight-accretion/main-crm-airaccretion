# Bounded October 2026 Queue Repair

This is an operator-invoked incident repair, not an automatic scheduler task.
It does not send, retry, dispatch or regenerate customer messages.

## Scope

- Default database queue `retry_after` is 240 seconds, above the observed default worker timeout of 120 seconds and known job-level timeouts up to 120 seconds. Google Chat remains on its own existing 240-second retry configuration.
- Only processing batches matching the two approved abandoned locks (`2026-09-26 14:01:50` and `2026-10-07 16:07:05`) are eligible to become skipped. A recently updated batch is not touched, and multiple batches matching one lock abort the entire repair.
- Only `whatsapp-ai` jobs older than 24 hours, due now, unreserved and with zero attempts are considered. Their payload must identify exactly `ProcessWhatsAppAiReply` with one valid public UUID batchId. Payloads are never unserialized.
- Only jobs linked to an existing sent/skipped/handoff batch or one of the approved abandoned batches are removable. Pending/failed/other processing batches, missing batches, other queues, fresh/future/reserved/retried/unknown jobs are retained.
- More than 750 removable jobs aborts without database changes.
- Apply uses row locks and one database transaction. Before changes, it writes and verifies an encrypted snapshot of the affected rows under `storage/app/private/repairs` on the local storage disk. Backup failure aborts the transaction. Keep the existing APP_KEY securely for snapshot decryption. Protect persistent storage and do not publish the backup.
- No failed_jobs row, chat message, lead, payment, refund, voucher, assignment or scheduler configuration is changed.

## Execution

Ship these source changes through the existing reviewed release process first. An old image will not contain this command. Do not create an ad-hoc WhatsApp consumer or drain the backlog as a test.

From the Docker host:

```bash
docker exec main-crm-app php artisan crm:repair-whatsapp-ai-backlog
```

This defaults to dry-run. Review eligible batch IDs and counts. Take a normal database/storage backup before apply as well; the encrypted row snapshot is not a substitute for the full backup. If output differs from the approved incident scope, stop.

```bash
docker exec main-crm-app php artisan crm:repair-whatsapp-ai-backlog --apply
docker exec main-crm-app php artisan queue:health
```

Some queued jobs may intentionally remain. Do not force the queue to zero by broad deletion. Keep the encrypted snapshot for controlled recovery. Recovery must be reviewed; blindly reinserting jobs could trigger delayed notifications or duplicate work.

Refresh config in every PHP application/worker/scheduler container using this release, according to the deployment's existing config/cache lifecycle. For the application container:

```bash
docker exec main-crm-app php artisan config:cache
docker exec main-crm-app php artisan tinker --execute="dump(config('queue.connections.database.retry_after'));"
```

Expected: 240. Queue workers load configuration when starting. Restart them through their actual process manager only after the backlog/no-message plan is reviewed. `queue:restart` by itself neither starts nor supervises a worker, and the observed default worker inside the app container may be manually launched. Do not run `queue:retry all` or start a WhatsApp worker as part of this repair.

## Failed Lead Score

The observed failed ProcessLeadAiScore job reports MaxAttemptsExceededException. This is a terminal symptom, not proof of an API failure. Its handler also releases itself when an older score for the same lead is pending/processing; such releases consume attempts. Correlate the failed score with predecessor state and earlier application logs before retrying. The repair deliberately leaves the failed job untouched and does not claim the scoring root cause is confirmed.

## Verification

`tests/Feature/WhatsAppBacklogRepairTest.php` uses isolated SQLite, encrypted fake storage and fake mail/HTTP/queue transports to verify dry-run, bounded apply, idempotency, exclusions, ambiguous-lock abort, 750-job bound, backup failure and timeout configuration. Live repair is not implied by a local test result.
