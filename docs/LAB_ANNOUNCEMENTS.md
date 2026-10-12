# Administration-to-lab notifications

## Scope and usage

A new **Administration notifications / إشعارات الإدارة** bar appears above the page content inside the authenticated application layout. On the super-admin pages it contains **Send a lab notification / إرسال إشعار للمختبرات**. Lab owners and staff instead see their notification inbox and unread count. Public pages, patient portals and referral/doctor portals do not mount this feature.

Administrators enter a title, plain-text message, type (information, update, warning or urgent), optional expiry in their local time, and an optional HTTP/HTTPS button label and link. The default audience is **selected labs**: search and choose one or multiple laboratories. **All current labs** snapshots the eligible lab-owner accounts at send time; future registrations do not retroactively receive old broadcasts. Deleted and referral-only lab accounts are excluded. Expired subscriptions are not removed from the audience; access still depends on the application's existing login rules.

The review step shows the audience count and selected lab names before sending. The draft and request key are preserved after connection failures, making an unchanged retry idempotent. Sent history includes recipient-lab counts and counts of labs with at least one acknowledgement. An administrator can stop a message from future display; a message already seen cannot be recalled.

## Delivery semantics

This is an **in-application popup**, not operating-system web push. No browser notification permission, push keys, cron job, queue worker, WebSocket or additional paid service is required. It does not deliver while the website is closed.

Pending messages are checked immediately when the authenticated layout opens, on return to the tab/focus/reconnection, and every **5 seconds after the previous successful request completes** while the page is visible. Actual latency includes request time and browser scheduling; this is near-real-time polling, not an instantaneous server-push guarantee. Hidden tabs do not poll the server. Temporary failures use exponential backoff capped at 60 seconds and never redirect a user or activate the full-page loading overlay.

Messages are queued oldest first, twenty at a time. **Mark as read / تمت القراءة** persists acknowledgement separately for each user and tenant; another staff member reading a message does not dismiss it for everyone. A previously acknowledged popup does not reappear on reopening the site. **Later / لاحقاً** postpones only for this mounted application session; the message remains unread and can reappear after reopening/reloading. Read messages remain in the inbox while active. Expired and stopped messages leave the recipient inbox but remain in administration history.

A popup opens only in the authenticated laboratory workspace, never in public reports or patient/doctor/referral portals. The notification bar and dialogs are excluded from printing. No existing invoices, patient records, report layouts or patient-notification flows are modified.

## Security and storage

New tables: `lab_announcements`, `lab_announcement_targets`, `lab_announcement_reads`. Tenant IDs are lab-owner **users.id**. Lab identity is derived server-side from the authenticated account's creator hierarchy with cycle/depth protection; client-supplied `lab_id` is rejected. Patients, doctors, orphan accounts, referral-only accounts and cross-tenant receipt attempts are denied. Only role 1 can send, inspect all recipients/history or stop messages.

All sends and target creation occur in one transaction. Per-administrator UUID keys plus payload hashes and a user-row lock prevent duplicate retries or key reuse with changed content. Read-receipt composite keys enforce per-user/per-tenant idempotency. Rendering uses escaped text (no `v-html`), and action URLs are constrained on both backend and frontend to HTTP/HTTPS. All feature timestamps are stored as UTC. The existing authentication, referral restriction and activity middleware are applied to the new routes.

## Deployment

Review and merge the feature pull request first. Back up the database and preserve the production `.env`, `APP_KEY`, uploaded files and local deployment changes. Pull only with a clean working tree; resolve local changes deliberately, never using `reset --hard`.

```sh
# From the existing erp-lab checkout, after reviewing/merging the PR:
git status --short --branch
git pull --ff-only origin main
cd backend
php artisan migrate --force
php artisan optimize:clear
php artisan route:list --path=lab-announcements
cd ../frontend
npm ci
npm run build
```

Publish the generated frontend assets and backend files using the existing Hostinger deployment mapping. Building alone does not copy assets to a separate `public_html` directory. Run the migration before serving the updated frontend. No additional Composer/npm dependency is added. Never run `migrate:fresh`, `db:wipe`, `erp:install` or regenerate the app key for this update.

For an application rollback, revert the feature code/build while leaving the new tables intact to preserve notification history. The migration's `down()` deliberately drops only these three new tables and deletes their history; do not run it unless that loss is intended.

## Verification

**Run database tests only against a disposable, isolated test database. `DatabaseMigrations` rebuilds tables. Never point these tests at the live laboratory database or reuse production database credentials. The workflow creates its own synthetic database.**

`php artisan test --filter=LabAnnouncementTest` exercises role authorization, one/multiple/all recipients, tenant isolation, nested staff, read persistence, staff moves, target snapshots, retry idempotency, atomic validation, unsafe links, expiry/cancellation, patient exclusion, hierarchy cycles and backlog pagination on the existing migration set. The focused GitHub Actions job uses MariaDB 10.11 and PHP 8.4.

The Playwright harness uses synthetic accounts and intercepted API responses; it does **not** send to real laboratories. It exercises composing, selected/all audiences, safe retries after interrupted responses, escaped content, popup receipt on entry, read persistence, inbox access, live arrival, stale-poll acknowledgement races, logout cleanup and patient exclusion. The workflow also builds the real frontend. Browser mocks and backend feature tests complement each other; production-host and live-account end-to-end testing is still required after deployment.
