# Result comments

Per-test patient comments now render below the corresponding result, including group/package members, formula targets, cultures and custom/CBC templates. Template comment slots are respected without duplicate fallback text. Comments are escaped plain text with multiline wrapping. Short comments travel with their result; oversized comments continue at text-line boundaries. Existing margins, isolated pages, background, columns, history and output routes remain in use.

The test catalog's existing `result_comments` now provides choices for that test in the result editor. Choosing a preset appends it without duplicates; free text stays editable and is saved only on the patient result. Catalog updates refresh choices in existing group/package snapshots by test identity without overwriting patient comments. Clearing all presets sends an explicit empty array.

Print settings → Paper & visibility → Show result comments controls classic/modern reports, PDF, shared reports and normal/experimental print. It defaults to visible for existing settings and does not delete saved comments. General section comments appear separately; partial selections omit general comments that may describe excluded analyses.

Deployment requires the backend migration `2026_11_04_000001_expand_result_comments` as well as the frontend build. The previous VARCHAR(255) field cannot store multiline presets reliably, so this migration widens it to LONGTEXT without changing data. Rollback refuses to narrow the column when long comments exist, rather than truncating patient data. No new permissions or print route are introduced.

Verification:
- Node tests cover legacy comment values, normalization, preset selection, visibility, escaping, custom slots and partial report selection.
- `report-comments.browser.mjs` mounts the real catalog, patient editor, lab settings and report. Covers both templates/named and merged modes, changing columns, unbroken text, a 70-line Arabic/English comment, custom slots, hiding/cache invalidation, autosave and independent package comments. The PDF and fast preparation produce identical A4 images.
- Existing report group-page, keep-together, fast-print and action suites protect previous printing behavior. Browser fixtures use synthetic patients and mocked APIs/native dialogs; they do not replace physical printer checks.
- `ResultCommentsTest` covers catalog creation/clearing/validation, >255-character patient comments, group/package round trips and live choices, public report reads, settings validation and lab/staff boundaries. Runs against MariaDB/PHP in the database workflow.

Verified on 2026-11-04:
- Local production build passed; 25 focused Node tests passed.
- Both report templates and merged/named modes passed the new rendering tests; real catalog/editor/settings flows passed, including formula comments and the settings preview.
- 88 existing browser assertions/scenarios passed across group pagination, keep-together, experimental printing and report actions. The test-filter browser check also passed locally; its first CI attempt hit an intermittent mobile selection timeout.
- GitHub's database job passed all 42 tests / 388 assertions, including every ResultCommentsTest case and the >255-character round trip. CI frontend build, barcode, monochrome, print-helper and permission checks passed on the implementation commit.
