# Final Deployment Regression Checklist

## Login And Roles

1. Super Admin can access all CRM modules.
2. Admin can access allowed admin modules.
3. Operations can access ride, refund, vendor, and operational modules.
4. Sales manager can access own leads and team leads.
5. Sales executive can access own leads.
6. HR cannot access finance-sensitive exports unless explicitly allowed.

## Booking Communication

1. Booking email sends to customer.
2. Booking email CC sends to sales executive and sales manager when emails exist.
3. Booking WhatsApp template sends at booking email time.
4. Passenger registration link is clickable in WhatsApp.

## Lead And Follow-Up

1. Lead without follow-up date appears in today follow-up.
2. Direct lead URL respects logged-in user scope.
3. Follow-up POST respects logged-in user scope.
4. Lead transfer works according to approved business rules.

## Payments, Invoice, Voucher

1. Payment approval is limited to approved roles.
2. Invoice finalization is limited to approved roles.
3. Voucher/invoice/general CRM timeline appears on the same lead where required.
4. Refund actions are limited to approved roles.

## Jobs And Scheduler

1. `php artisan queue:health` shows low pending jobs.
2. `php artisan crm:scheduler-health` is healthy.
3. Failed jobs older than 3 days are pruned.
4. Google Chat retry and call summary retry do not create infinite loops.
5. WhatsApp AI queue stays small and responsive.

## Server Load

1. CPU stays near the 30-35 percent target during normal traffic.
2. No duplicate queue workers are running.
3. Logs are not growing unusually fast.
4. Backup completes and local backup ZIP files are deleted only after upload succeeds.
