# Selectable medical report template — design QA

final result: passed

Reviewed on 2026-09-30 against the user-supplied patient-information / lipid-profile reference image. All browser records are synthetic; no production patient data was loaded or sent.

## Browser evidence

![Modern report and template picker](docs/qa/report-template-preview.jpg)

Cloud Chrome viewport: 1363 × 936 CSS pixels. The screenshot shows the real `print_Result.vue` output produced by the shared A4 renderer, alongside the same picker used in Lab Settings. The reference was inspected alongside the rendered report at comparable content widths.

Source visual: user attachment `ChatGPT Image Sep 30, 2026, 03_46_01 PM.png`, inspected locally at `/workspace/scratch/e7105540ae61/attachments/d0fffaf6-3218-4ee5-bdc4-a9efe2c31a64/ChatGPT Image Sep 30, 2026, 03_46_01 PM.png` (1055 × 1491 pixels). It is not copied into the repository because the supplied example contains patient information.

Implementation image: `docs/qa/report-template-preview.jpg` (1353 × 929 pixels, browser capture of the 1363 × 936 CSS viewport). The rendered report has an approximately 675 CSS-pixel content width inside a 794 CSS-pixel A4 sheet. Comparison normalized the reference's approximately 981-pixel content width to that report region, excluding the fixture controls and browser canvas. State: New template, colored output, status visible, synthetic lipid profile.

Full-view comparison checked patient-card / results-table order, content boundaries, visual hierarchy and whitespace. Focused inspection checked the patient columns, real QR, labels, result/flag alignment and row tint directly in the full-resolution captures; separate crops were unnecessary.

## Fidelity and integration

- Preserves the reference's pale teal patient card, three information columns, report QR, section bands, alternating rows, and result-adjacent status badges.
- Uses the installed PrimeIcons library and an actual generated QR for the existing report URL. The label says “Scan to view patient report”; it does not claim cryptographic verification.
- Uses existing laboratory font sizes, cell padding, table colors, margins and visibility settings. Density therefore follows the lab's settings instead of hard-coding the reference image's scale. Custom test templates retain their own result layout.
- The original template stays the default; switching back was inspected in the browser.

## Interactions and regression checks

- Switched Original → New → Original and inspected the actual generated report.
- Verified standalone, grouped, packaged, calculated and culture rows; zero results stay visible; optional status columns stay aligned.
- Inspected monochrome output and fixed residual stripe tint. CI also checks that the generated monochrome pixels have equal RGB channels.
- Inspected a 65-row report over five pages, including repeated patient details on a continuation sheet.
- PDF page count matches the preview sheets. Existing native-print and fixed-background regression passes with asymmetric margins and five-page reports.
- Production Vite build and the 11 print/pagination unit tests pass. GitHub Actions supplies MariaDB validation for per-lab persistence, invalid values, staff read-only access, resets and isolation.
- App console errors: none. The cloud browser reported extension metadata errors from a `chrome-extension://` URL; these were unrelated to the app. CI's clean browser checks app console and page errors.

## Comparison history

1. Initial modern capture matched the reference's structure. Mixed-result review exposed a decorative heading for a missing category; the modern branch now suppresses that heading. Re-rendered mixed results and the browser regression passed.
2. Monochrome inspection exposed residual alternating-row tint. Added a more specific monochrome override and a rendered-pixel regression; the updated browser workflow passed.
3. Settings thumbnail was adapted to scale to its available width. A separate browser check of the real preview component in an RTL 280-pixel panel confirmed that its rendered bounds stay inside the panel, without application console errors.
4. Final reference-style capture and a continuation page were inspected. Patient identity repeats correctly; no outstanding blocking visual findings remain. Differences in text size / row density are intentional consequences of retaining configurable lab settings.

No outstanding P0, P1 or P2 design findings. The preview is a development fixture, not evidence of deployment to the live Hostinger installation.
