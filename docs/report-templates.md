# Medical report templates

Lab owners select **Original template** or **New template** in **Lab Settings → Print → Paper & visibility → Medical report template**, then save. Existing staff permissions apply: staff inherit the lab's selection but cannot change its settings.

`lab_settings.report_template` accepts `classic` or `modern`; the migration defaults existing and new rows to `classic`. Print settings reset restores `classic`, while branding reset and partial updates preserve the selection.

The modern layout adds a patient information card and a Flag column immediately after Result. It uses the same invoice results, reference ranges, formulas and custom test content as the original. It does not reinterpret medical results. Existing status IDs supply badges (1 high, 2 normal, 4 low); other stored labels are displayed neutrally and unknown status is a dash.

Both layouts use `medicalReportPages.js` for the A4 sheets. `getReportTemplateCss` is included by the report list, result editor and public/referral report page, so their preview, PDF, printing and WhatsApp attachments share the chosen style. Letterhead remains fixed to the full A4 sheet; margins affect content only.

## Deployment

Update the backend and the compiled frontend together, then run:

```sh
php artisan migrate --force
```

The new migration is `2026_09_30_000003_add_report_template_to_lab_settings.php`. Select the new template after deployment and migration; no automatic template change is made for existing labs.

## Verification

Backend: `php artisan test --filter=LabSettingsTest`.

Frontend: `node --test tests/medical-report-pages.test.mjs tests/print-lifecycle.test.mjs` and `npm run build`.

The Report page rendering workflow runs the original background/margin regression plus `tests/report-template.browser.mjs`, which exercises the real report component against synthetic fixtures and stores screenshots with the run artifacts. See `design-qa.md` for the visual review.
