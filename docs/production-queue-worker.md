# Production Queue Worker Guide

Use one controlled worker per queue group on the current Hostinger KVM 4 server. The goal is to keep CPU stable while allowing WhatsApp AI and lead sync to stay fast.

## Recommended Worker Shape

Default queue:

```bash
php artisan queue:work --queue=default --sleep=10 --tries=3 --timeout=120 --memory=128 --max-jobs=50 --max-time=900 --env=production
```

Google Chat queue, if separate:

```bash
php artisan queue:work --queue=google-chat --sleep=10 --tries=3 --timeout=120 --memory=128 --max-jobs=50 --max-time=900 --env=production
```

WhatsApp AI is scheduler-driven in this CRM and should remain small-batch. Keep `WHATCRM_AI_PROCESS_LIMIT` conservative, for example `5`.

## CPU Safety Rules

1. Do not run many duplicate queue workers.
2. Keep email lead fetch at every 5 minutes.
3. Keep Google Chat retry and call summary retry at every 5 minutes.
4. Keep Skyrack lead sync every minute, but make sure failed records are marked or skipped safely.
5. Keep WhatsApp AI fast, but small-batch.

## Health Commands

```bash
php artisan queue:health
php artisan schedule:list
php artisan crm:scheduler-health
```

If CPU spikes again, first check:

```bash
php artisan queue:health
php artisan tinker --execute="echo DB::table('jobs')->count();"
```

Then inspect top job classes from `queue:health` before deleting anything.
