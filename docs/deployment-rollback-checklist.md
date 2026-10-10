# Deployment And Rollback Checklist

## Before Deployment

1. Confirm working tree changes are intentional.
2. Run syntax checks:

```bash
php -l app/Support/SafeLogContext.php
php -l app/Console/Commands/ProductionSafetyCheck.php
php -l app/Console/Commands/SchedulerHeartbeat.php
php -l app/Console/Commands/SchedulerHealth.php
php -l app/Console/Commands/QueueHealthCheck.php
php -l app/Http/Controllers/Api/CallSummaryController.php
php -l app/Http/Controllers/Api/WhatsAppLeadController.php
php -l app/Http/Controllers/Api/WhatCrmMessageController.php
```

3. Run application checks:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:list
php artisan queue:health
php artisan crm:production-safety-check
php artisan crm:prune-technical-data --dry-run
```

4. Take database backup and code backup.
5. Confirm backup uploaded to Google Drive.

## Deployment

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Restart queue workers after deployment.

## After Deployment

1. Login as Super Admin.
2. Open sales dashboard.
3. Send a test booking email and WhatsApp message.
4. Check queue health.
5. Check scheduler health.
6. Check latest logs for new errors.

## Rollback

1. Stop queue workers.
2. Revert to previous release commit.
3. Run:

```bash
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan config:cache
```

4. Restart queue workers.
5. Only restore database backup if the deployment migration or data write damaged production data.
