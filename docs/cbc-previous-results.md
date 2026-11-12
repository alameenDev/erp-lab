# Device CBC previous results

In the result editor, open the device-imported CBC and use **إظهار النتائج السابقة لهذا CBC في التعديل والتقرير**. This works for standalone tests, groups and packages, and is saved by the existing result save/autosave. An explicit per-CBC on/off choice overrides the laboratory default; an unset choice inherits **إظهار النتائج السابقة في التقرير**.

In **إعدادات المختبر → إعدادات الطباعة → النتائج السابقة**, choose:

- **عمود Last Result بجانب Result**: the most recent available completed result for each exact component, alongside its original unit and date. Missing components remain blank/dashed. Different components may have different previous dates. No unit conversion or guessed analyzer aliases are applied. Ambiguous values within the latest prior visit are flagged instead of silently selecting one.
- **ورقة منفصلة بعد ورقة CBC الحالية**: previous completed CBC examinations follow the current CBC on separate sheets. The existing previous-examination count (1–10) applies. There is no extra sheet when history is empty. Large histories continue on additional sheets with the existing patient header and page boundaries.

The same renderer serves normal print, fast print, PDF and WhatsApp output. Current CBC values, range/high-result formatting, comments, hidden-unit settings, report templates and letterhead rules are retained. Showing/hiding history does not change clinical values or completion state. The stored CBC HTML stays current-only; previous results are added only when rendering.

History uses the existing patient/laboratory-scoped completed-invoice history and saved test identity. It is prior patient examinations, not earlier transmissions/replacements within the current invoice. Original device transmissions remain in the device-results inbox.

Deploy the backend validation change and the rebuilt frontend together. No new database migration is required; the layout lives in the existing print_table_config JSON. Existing stored PDFs must be regenerated to reflect a new display setting. Resetting print settings restores the laboratory history default to off and layout to inline; explicit saved per-CBC selections remain on their invoices.

Verification: CBC identity/visibility/history unit tests; API settings validation/isolation/reset and result-save round trips; browser editor/autosave checks in standalone/group/package contexts, setting save/reload, raster-page separation, PDF/fast output parity, classic/modern/monochrome layouts, missing-history pages, complete 21-component panel and unit visibility. Existing report regression tests run alongside these checks.
