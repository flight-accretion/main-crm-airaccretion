# Google Chat: Remaining Local Activation Steps

## Verified On 2026-09-30

- OAuth refresh and Google message send/read succeeded in Accretion Aviation Enquiry.
- The current integration sender is Suraj, `users/101345639298371392803`.
- Dedicated local queue settings and the integration-user ID are now set in .env.
- The repair migration `2026_09_30_130000_repair_google_chat_event_version.php` was applied only to the local PostgreSQL database. It adds the missing nullable column without altering existing messages.
- Membership reads now succeed after reauthorization with Suraj's account.
- Current scope is only Aviation and Yacht. Suraj OAuth and both Spaces were verified; the canonical identities of Harsh, Suraj and Jaffar were verified by email and mapped to CRM. Harsh is a member of Aviation only; Suraj and Jaffar are members of both.
- No Google Cloud IAM, public endpoint, subscription, or membership was created by the agent.
- Latest UI-label checks: 33 Google Chat tests / 118 assertions, PHP lint, JavaScript syntax and diff checks pass. Existing CRM regression selection previously passed 16 tests / 58 assertions. Authenticated public push setup and full two-way lead UI verification remain pending.

## 1. Renew OAuth Consent

Log into the local CRM as Super Admin. Open:

http://127.0.0.1:8000/admin/google-chat/oauth

Choose the same integration Google account and approve the requested Chat permissions, including `chat.memberships.readonly`. If a Workspace administrator blocks consent, ask that administrator to approve the app. The callback stores the new token encrypted; no manual token copy is needed. It also clears the cached access token.

Confirm with:

```sh
php artisan google-chat:list-spaces
php artisan google-chat:list-members spaces/AAAAWIIVzEQ
```

Both commands are read-only. If an employee's Space is absent, that employee must add the integration account to their Space first. Do not authorize using a different employee's account just to access one Space; all configured Spaces need to be accessible to the same integration account.

## 2. GOOGLE_CHAT_ALLOWED_SPACES

This is not an API key. The approved selection is Aviation and Yacht only. Other Spaces must not be added without approval.

The account currently exposes these named Spaces (this list does NOT approve all of them):

| Name | ID |
| --- | --- |
| Accretion Aviation Enquiry | spaces/AAAAWIIVzEQ |
| Self Tasks | spaces/AAQAqyC6QEU |
| Charter | spaces/AAAA6N_TY_4 |
| Operations | spaces/AAAA9yYK10A |
| Voucher improvement point | spaces/AAAAQjaAwlE |

Set your actual selection, not the placeholder text:

```dotenv
GOOGLE_CHAT_ALLOWED_SPACES=spaces/AAAAWIIVzEQ,spaces/AAAAuOpRJoM
GOOGLE_CHAT_SPACE_LABELS='{"spaces/AAAAWIIVzEQ":"Accretion Aviation Enquiry","spaces/AAAAuOpRJoM":"Accretion Yacht enquiry"}'
```

The existing verified integration setting is:

```dotenv
GOOGLE_CHAT_INTEGRATION_USER=users/101345639298371392803
```

Suraj is both an Operations employee and the shared authorized sender. His Google account sends CRM-originated messages; the actual CRM author is included in the text. Employee canonical identities have already been verified by email and mapped. For future employees, verify the actual person before using:

```sh
php artisan google-chat:map-user CRM_USER_UUID users/EMPLOYEE_GOOGLE_ID --space=spaces/EMPLOYEE_SPACE_ID --confirm-identity
```

## 3. Public Push Endpoint And Audience

`GOOGLE_CHAT_PUBSUB_AUDIENCE` is a value you choose, not a generated secret. For this setup, make it exactly the public HTTPS push endpoint:

```dotenv
GOOGLE_CHAT_PUBSUB_AUDIENCE=https://YOUR_TEST_HOST/api/google-chat/pubsub
```

Google cannot reach `127.0.0.1`. For local testing, provide a controlled HTTPS reverse proxy/tunnel forwarding only the webhook route to this local server, or use a staging deployment with its own database. Do not expose the full development CRM/debug pages publicly. Do not point test events at production while expecting them in your local database. Use the same audience string in the Pub/Sub push authentication settings.

## 4. Create The Push Service Account

In Google Cloud Console, select project `accretion-aviation-crm-509807` and open **IAM & Admin > Service Accounts > Create Service Account**.

Use a name such as `crm-chat-push`. Do not grant broad project roles and do not create/download a JSON key; this account represents the authenticated Pub/Sub push identity, not the OAuth Chat user. Copy its actual email into:

```dotenv
GOOGLE_CHAT_PUBSUB_SERVICE_ACCOUNT=crm-chat-push@accretion-aviation-crm-509807.iam.gserviceaccount.com
```

Only use this example email after creating that exact account. On this account's permissions, grant the project's Pub/Sub service agent `service-PROJECT_NUMBER@gcp-sa-pubsub.iam.gserviceaccount.com` the **Service Account Token Creator** role if it does not already have the necessary token-creation permission. PROJECT_NUMBER is the numeric project number shown in project settings, not the textual project ID. The administrator creating the push subscription also needs permission to act as this service account.

## 5. Configure Topic And Push Subscription

The CRM already contains this topic name:

`projects/accretion-aviation-crm-509807/topics/accretion-crm-chat-events`

Verify in Cloud Console that it exists. Enable Google Chat API, Google Workspace Events API and Pub/Sub API for the intended project. On the topic, grant the Google Chat event publisher **Pub/Sub Publisher**. Google's current instructions distinguish standard Chat apps (`chat-api-push@system.gserviceaccount.com`) from Workspace add-ons (use the publisher account shown in Chat API configuration); select the one matching your app, not the new push service account above.

Create or configure a Pub/Sub subscription for this topic:

1. Delivery type: **Push**.
2. Endpoint: the exact public HTTPS webhook from step 3.
3. Authentication: enabled; select the push service account from step 4.
4. Audience: exactly the .env audience value.
5. Keep the normal wrapped JSON payload; do not enable payload unwrapping.

There are two different subscriptions: the Pub/Sub push subscription delivers HTTP requests to CRM, while `google-chat:ensure-subscription` creates Workspace Events subscriptions that watch the approved Spaces. Both are needed for event-driven replies.

Official instructions:
- https://developers.google.com/workspace/events/guides/create-subscription
- https://docs.cloud.google.com/pubsub/docs/authenticate-push-subscriptions
- https://docs.cloud.google.com/pubsub/docs/create-push-subscription

## 6. Local Worker And Final Verification

These are internal Laravel settings, not Google-generated keys; they are already set locally:

```dotenv
GOOGLE_CHAT_QUEUE_CONNECTION=google_chat
GOOGLE_CHAT_SYNC_QUEUE=google-chat
```

Once the missing OAuth, Spaces, identities and push settings are ready:

```sh
php artisan config:clear
php artisan google-chat:ensure-subscription
php artisan queue:work google_chat --queue=google-chat --timeout=120 --tries=5 --sleep=3
```

Keep the worker running in a separate terminal. In another terminal, run `php artisan schedule:work` for local testing only. Production uses a supervised worker and one scheduler. Windows PHP workers do not enforce Unix signal-based timeouts in the same way as the Linux deployment; production timeout/recovery checks must run in Docker/Linux.

Open the provided lead's Add Follow-up chat, select the correct employee and Space, and Connect. Send clearly labelled test text, reply in that exact Google thread, and verify the reply appears in CRM with the actual sender. Repeat for each employee/Space. Do not submit the follow-up/payment form to test chat. A direct API test message without a lead mapping proves connectivity only; it will not appear in a lead conversation automatically.
