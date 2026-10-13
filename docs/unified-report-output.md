# Unified medical report output

Medical report print layout is the source for preview, PDF download, saved/uploaded PDF and the file prepared for WhatsApp. `print_Result.vue.prepareReport()` prepares the full A4 sheets and PDF once for a given record, settings, form selection and report URL. Staff report actions and direct report links consume that output. It includes the saved classic/modern template, margins, patient header, table styling, background, and group page boundaries.

## Lab form selection

The report action dialog preserves the selected form mode when changing actions or reopening. Direct reports load the invoice lab settings, even when a staff browser has stale settings from a different lab. By default a configured lab form appears; `form=0` explicitly disables it and `form=1` enables it. QR links carry the selected mode. WhatsApp portal links carry `form` and `report` so the choice applies to the shared invoice, without changing other reports in the portal. Signed referral report URLs carry the mode in the fragment to preserve their signed query.

The existing WhatsApp flow downloads the same PDF and opens the registered phone conversation with the patient portal link. The operator attaches the downloaded PDF before sending. No Meta API integration is introduced.

## Save integrity

The invoice PDF upload accepts only actual PDF blobs, keeps the original bytes, and explicitly posts multipart form data despite the API client's JSON default. HTML relabelled as a PDF is rejected. The legacy medical WhatsApp modal also uses the shared report renderer.

## Verification

- Unit tests cover form parsing, ordinary/signed URLs, portal invoice scoping and PDF validation.
- `report-output.browser.mjs` covers classic/modern templates, embedded staff reports, public links, and authenticated direct links, with and without the lab form.
- It compares downloaded/uploaded PDF bytes, preview/print sheet images, background pixels on every page, WhatsApp URL presentation, and action dialog selection persistence.
- Existing report tests continue to cover page margins, fixed backgrounds, template rendering and isolated group pages.
- All test data, patient names, phone numbers and API responses are synthetic; no real messages are sent.

![Synthetic three-page report with a fixed lab form](qa/unified-report-output.jpg)

GitHub's Hostinger build produces deployment artifacts. The production site only changes after its normal Hostinger deployment step.

## Selecting report contents

The staff report action dialog opens with all analyses selected, including pending results, cultures, and members of groups/packages. A parent checkbox toggles all its members; an indeterminate checkbox means only some are selected. Search does not reset hidden selections. Every new opening starts with all items checked. Compound/template analyses remain one complete selectable analysis.

`reportItemSelection.js` supplies the same output-only projection to the report list and result editor. It handles flattened package results and nested group membership without printing catalog duplicates. Excluded formula targets are removed from formula rows as well. Selected calculated results retain their full calculation context when their input analyses are hidden. The original invoice data is restored after preview/export and is never edited by selection.

Preview, print, PDF download and WhatsApp use the same selected PDF. Partial reports suppress the full-report QR and omit the portal link from the WhatsApp message, so that sharing selected results does not also link to excluded results. Full selections keep the existing patient portal message. This is an output selection, not a change to what an already-authorized patient can see in their portal.
