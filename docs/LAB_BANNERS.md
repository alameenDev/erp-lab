# Laboratory homepage advertising banners

## Where it appears

On `/dashboard`, the banner region is placed directly below **Administration notifications / إشعارات الإدارة** and before the existing welcome card and dashboard data. It is not mounted on invoices, result entry, print pages, public reports, patient portals or doctor/referral portals. On `/super-admin` pages, administrators see **Manage advertising banners / إدارة البنرات الإعلانية** instead of lab advertisements.

The new feature does not modify announcement popups, lab statistics, patient notifications, invoices or report output.

## Administrator controls

Create, edit, preview, enable/disable and soft-delete banners. Fields include title, plain-text details, a required main image, an optional separate mobile image, an optional button label + HTTP/HTTPS URL, all laboratories or selected laboratories, display order, optional start/end dates and enabled state. Saving an enabled banner publishes it during its selected display period; there is no separate publish action. Disabled banners are retained for later activation.

The default audience is all eligible laboratories, including future registrations. For selected delivery choose one or more lab-owner accounts. Staff inherit their owning laboratory's audience. Administrative sending does not grant any access to patients, doctors or referral-only accounts. All targeting is enforced server-side; client-supplied tenant IDs are rejected.

Main image suggestion: **1600 x 400 pixels**. Mobile suggestion: **800 x 600 pixels**. These are design recommendations, not fixed required sizes. Accepted formats are JPEG, PNG and WebP, maximum **5 MB per image**, dimensions from 100 x 50 through 6000 x 6000. GIF/SVG and arbitrary attachments are not accepted. The server validates file contents and dimensions and stores randomly named files on the existing public storage disk. These are public advertising images: do not upload patient information or confidential material.

Display dates are entered in the administrator's device timezone and submitted in UTC. An empty start means no lower date bound; an empty end means no expiry. The first ten active banners are offered in ascending display-order, then newest-ID order. One card is shown at a time with manual previous/next controls; there is no autoplay or disruptive popup. All active banners remain available through these controls.

## Responsive behavior

The image uses the available width with containment rather than cropping/distortion. Below 640 px, the optional mobile image is used, with the main image as fallback. Title/details are real wrapping text rather than baked into the uploaded artwork. Content and CTA stack on narrow screens; the CTA uses the available width. HTTP/HTTPS links open in a new tab with `noopener noreferrer`. The preview offers desktop/mobile layouts. The region is excluded from printing.

The feature checks on homepage entry, focus and reconnection, then every 60 seconds after the previous request completes. It is not instantaneous push. Visibility, authentication and unmount cleanup prevent background requests outside the applicable workspace. A failed banner request hides the optional advertisement without showing a full-screen loader, redirecting or blocking lab work. Locally expired cards are hidden even before the next refresh. No active banner means no empty reserved advertising space.

## Persistence and safe changes

Two additive tables: `lab_banners` and `lab_banner_targets`. No existing table is reset. Only administrators can mutate banners. Create requests retain a per-administrator UUID and payload hash for safe retries after interrupted responses. Updates and deletion use version checks to prevent silently overwriting another administrator's edits. Image and target changes are transactional; newly stored images are removed if the database change fails. Superseded images are removed after a successful commit. Soft-deleted banners retain their last assets for recovery; a separate reviewed retention policy can clean these later.

API routes are registered by `LabBannerServiceProvider`, independently of existing announcement APIs. No npm/Composer dependency change is required.

## Deployment

Review and merge the feature PR, back up the live database and public uploads, and preserve `.env`, APP_KEY and hosting-specific files. With a clean working tree in the existing checkout:

```sh
git status --short --branch
git pull --ff-only origin main
cd backend
php artisan migrate --force
php artisan optimize:clear
php artisan route:list --path=lab-banners
cd ../frontend
npm ci
npm run build
```

Publish backend and generated frontend assets using the current hosting mapping. Building alone does not copy `dist` into a separate `public_html`. Run migrations before exposing the new frontend. Confirm that existing `/storage` URLs are publicly served (the standard setup uses `php artisan storage:link`) and that PHP/web-server upload limits allow the combined multipart request (two 5 MB files plus form overhead). Never run `migrate:fresh`, `db:wipe`, `erp:install` or regenerate the APP_KEY for this update.

Smoke test with a designated test lab before selecting all labs. Verify an uploaded image and CTA on the real Hostinger domain, including any lab subdomains. No production database access, actual advertisements or live deployment is performed by the PR.

For a rollback, revert code/build and retain these new tables to preserve banner records. Running the migration down drops only these two new tables and destroys their records; do not do so casually.

## Verification

Run `php artisan test --filter='LabBannerTest|LabAnnouncementTest'` only on a disposable test database. `DatabaseMigrations` rebuilds tables; never use production credentials. The focused workflow provisions isolated MariaDB, checks PHP syntax and route registration, and tests targeting, authorization, file validation, idempotency, schedules, ordering, asset replacement and deletion, plus existing announcement regression tests.

The real frontend is built from its existing lockfile. The Playwright harness tests real Vue components against synthetic/mocked API responses: multipart upload, mobile artwork, selected/all audiences, interrupted-request retry, editing/deletion, escaped text, responsive widths 1440/768/390/320, print exclusion, lifecycle cleanup and nonblocking failures. Browser tooling is installed separately from application dependencies. Screenshots are CI artifacts; these tests do not replace a real-host end-to-end check after deployment.
