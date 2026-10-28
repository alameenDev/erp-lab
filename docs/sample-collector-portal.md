# Sample collector portal v1

Entry points:
- Laboratory: `/sample-logistics` (accounts, source assignments, requests and statistics).
- Laboratory waiting list: `/sample-logistics/waiting`, also linked beside invoice creation.
- Collector login: `/collector-portal/login`; installable web app: `/collector-portal/`.

## Workflow

The laboratory creates a dedicated collector account and assigns connected lab-to-lab sources. These accounts have no staff role and cannot access normal staff, referral, owner or results APIs. One collector is responsible for each source laboratory. Existing role-6 staff accounts are unchanged.

A committed source-laboratory invoice generates one pickup request. If no active collector is assigned it remains in the lab's unassigned queue. Managers can assign/reassign requested pickups. Collected/delivered requests cannot be reassigned. Collector confirms actual tube/container count at pickup; counts of requests and samples are separate. Delivery follows collection. Each transition records actor/time and uses a version check. Duplicate retries do not count twice. Statistics use Baghdad calendar dates for the actual collection/delivery time.

Collectors create invoices using the existing invoice form, including patient age/date of birth and additional information, photo, analyses, groups, packages, questions, referral/contract, payments, manual discount, notes and the independent work-register amount. The source is selected before the form. Historical patient search is intentionally unavailable to collector accounts. Loyalty redemption and promotional code actions require an existing patient and remain staff operations.

Submission creates only a pending record. The laboratory sees the full payload and can approve or reject with a reason. Approval requires `sample logistics review` and `invoices create`, and creates the patient, invoice and payments atomically through the existing invoice creation path. The invoice is then available for result entry; the review screen links directly to it. A repeated approval returns the same invoice. Changes in prices/bundle membership require a fresh reviewed submission. Pending records have no accounting, inventory or work-register effects. Neither collection nor delivery completes medical results.

## Installation

Deploy this PR using the normal build/release process and run `php artisan migrate --force`. The new migration grants these permissions to built-in API roles 1 and 2:
- `sample logistics view`
- `sample logistics manage`
- `sample logistics review`

Assign these permissions explicitly to custom staff roles as appropriate. Approval takes place under a laboratory/staff identity; the system administrator can manage accounts but cannot issue an invoice in a laboratory's name through this screen.

Web Push reuses existing `portal_push` configuration and persistent VAPID keys. If not configured, run `php artisan portal:push-setup` with the normal secure production configuration. Keep Laravel scheduler running every minute (`php artisan schedule:run`). It invokes `collectors:deliver-notifications --limit=20`, plus `collectors:reconcile` every five minutes to retry missing pickup captures for invoices created after collector setup. Notification rows and delivery retries are durable; the existing patient and owner notification tables/scopes remain separate. Push subscriptions require explicit opt-in and are revoked on explicit device logout, account disable/password changes. Expiring the bearer session alone does not cancel notification consent. Lock-screen text contains no patient names, analyses or results.

The PWA is a web app, not an APK or App Store binary. HTTPS and browser notification support are required. iPhone/iPad users add it through Safari to the Home Screen. The service worker caches only a generic offline page, never patient data or API responses. Data entry and status changes require a connection.

## Verification

`CollectorPortalTest` exercises tenant restrictions, pending/approval/rejection behavior, idempotency, full bundle expansion, source checks, actual sample counts, transaction rollback, Web Push validation and disabled-account revocation. The collector workflow also runs existing referral, account, work-register and owner-notification regressions. Frontend checks build the entire application, execute worker account-isolation tests, and exercise the actual Vue invoice form and management screens in Chromium on mobile/desktop viewport sizes. Real physical-phone notification delivery still requires verification on the deployed HTTPS host with its production scheduler and keys.
