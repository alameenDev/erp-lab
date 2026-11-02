# Experimental fast report print — 2026-11-02

Adds **طباعة سريعة — تجريبية** beside preview/execute in the existing report selection dialog, in both the report list and result editor. It is opt-in; the default remains the existing print action. No backend migrations or settings changes.

## Implementation

- The new action uses the existing selected-item filter, report renderer, scale, margins, page slicing, repeated headers, background, monochrome processing and inline attachments.
- It skips PDF encoding and the old action's 200 ms delay. Vue's next tick, renderer asset loading and final image decoding still complete before printing.
- The new cache is keyed by the entire report/settings/form/QR context and separate from the legacy PDF cache. Changed results, selections or attachments invalidate it. Already prepared identical legacy sheets may be reused.
- An offscreen iframe with the existing A4 sheet CSS replaces the extra popup for this button only. Failed image decoding blocks printing. `afterprint`, scope disposal and page navigation clean the iframe; there is no timer that can remove it while a dialog is open.
- The configured report printer/copies and helper's uncertain-job/no-auto-retry behavior are retained. Successful output records the existing `print` audit/status action, never `download`.
- The same modal debt check, disabled/busy guards and entry-point permissions apply. Existing download, preview, WhatsApp and print buttons still use the original PDF/popup path.

## Verification

- Production frontend build.
- 42 Node regression tests: page geometry/slicing, selected analyses, PDF output, monochrome, print lifecycle and local printer settings.
- New Chromium browser test mounts the **actual report list and result editor**. It clicks the experimental and original buttons and compares every generated PNG byte, plus the final sheet CSS. It verifies one/multiple pages, classic/modern, with/without form, monochrome, a two-page PDF attachment and a photo, subset selection, full-record restoration, changed-result invalidation, debt loading/warning, double clicks, print/status failures, cleanup/cancellation and the mobile dialog.
- The same test exercises helper submission with saved printer/copies and an uncertain response, confirming no automatic duplicate or browser fallback. Network APIs and the native print dialog are simulated; report rendering and images are real.
- Existing report-action browser regression covers download, print, print+save, WhatsApp and combined actions, status errors and modal permissions.
- Existing unified-output browser regression covers 12 template/entry/form combinations, PDF upload/download byte equality and readable QR codes on every printed page.
- CI runs the new browser test and collects screenshots/timing output alongside report regressions.

## Observed preparation time

One local Chromium 140 / Vite run, synthetic data, fast first then legacy. These are `prepareReport` durations, **not** physical-printer speed or end-to-end production benchmarks. The legacy action's additional 200 ms and both routes' final print delivery are excluded. Import/attachment caches are not equalized; repeat results will vary.

| Fixture | Sheets | Experimental | Legacy |
| --- | ---: | ---: | ---: |
| List, short report | 1 | 249 ms | 741 ms |
| Editor, long report + form | 4 | 584 ms | 2462 ms |
| Classic, monochrome + form | 3 | 417 ms | 1812 ms |
| Editor, PDF + photo + form | 4 | 1869 ms | 3541 ms |

## Lab acceptance after deploying this branch

1. On the target Windows browser/printer, print the same selected patient items with each button; compare page count, margins, header, last result and attachment pages.
2. Compare colour/monochrome and with/without letterhead. Try the saved-printer helper with the configured copy count and browser-dialog mode.
3. Cancel the dialog, retry, and verify status behavior remains the same as legacy. Browsers do not report physical print completion; the status remains an output-action record.
4. Time repeated realistic reports. Until accepted, staff can use all original buttons unchanged.

No live patient data was used, and no deployment/merge or physical Windows printer verification was performed as part of local testing. The existing editor header has horizontal overflow at phone width; the experimental modal/buttons fit their viewport. This change does not modify that unrelated header.
