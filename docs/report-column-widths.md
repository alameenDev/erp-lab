# Medical report column widths

Open **إعدادات المختبر → إعدادات الطباعة → جداول النتائج → عرض أعمدة التقرير**.
Enable **تخصيص العرض**, adjust the slider or percentage for any visible column, then **حفظ**.
The remaining columns share the remaining space automatically; the visible total stays 100%.
Optional Status/Flag and Last Result columns take no space when hidden. Template switching
preserves the width of each semantic column even though Flag changes position.

**إعادة ضبط القياسات** restores the suggested proportions. Disable **تخصيص العرض** to return
to the selected template's original sizing. Existing labs start with custom sizing disabled.
The same settings apply to classic and modern standard result tables: standalone analyses,
groups, packages, calculated results and cultures, including merged tables. Custom HTML test
templates and the separate three-column patient-history table retain their own layouts.

Widths live in the existing `print_table_config` JSON (`custom_column_widths`, `column_widths`).
No schema migration is needed. Partial updates retain existing typography and other widths;
server validation permits only the six named columns, integer widths 5–85 and a boolean switch.
The current owner permission and referral settings isolation remain in effect.

Semantic `<colgroup>` widths and wrapping styles are included in the canonical report source.
The same source produces preview, print, downloaded PDF and the WhatsApp attachment; changing
widths invalidates the existing prepared-report cache through the settings key. Backgrounds,
margins and whole-section pagination continue to use the shared renderer.

## Verification

- `node --test tests/medical-report-columns.test.mjs` checks totals, reload stability, both
  column orders, optional columns, exact resizing, bounds and malformed historical data.
- `php artisan test --filter=LabSettingsTest` checks persistence, public report settings,
  partial saves, tenant isolation, staff permissions and rejected dimensions.
- `tests/report-columns.browser.mjs` exercises the real settings page, multipart saving,
  reload, both templates, all standard table types, measured cell widths, wrapping, optional
  and merged columns, mobile controls, original sizing and canonical PDF preparation.
- The existing report-page CI suite covers form backgrounds, pagination, isolated groups,
  keeping blocks together and byte-identical shared output for print/save/WhatsApp.

Browser fixture: `tests/report-columns-browser.html`. It uses synthetic records and intercepts
every API call. No patient data or real WhatsApp message is involved.

## Hostinger deployment

After merging, wait for `Build Hostinger frontend` and verify `hostinger-build/SOURCE_COMMIT`
matches main. Then run the existing deployment script on the hosting server:

```sh
cd /home/u859215520/erp-lab
git pull --ff-only origin main
bash scripts/deploy-hostinger.sh /home/u859215520/domains/lightpink-badger-650079.hostingersite.com/public_html https://lightpink-badger-650079.hostingersite.com
```

GitHub merge/build does not itself deploy the PHP application to Hostinger.
