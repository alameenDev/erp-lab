# DxH 500: CBC by invoice barcode

Set the CBC test's existing interface_code (كود الربط) to the exact string `12345678`.
Enter the **invoice barcode** in the analyzer's Sample ID field. Keep desktop
`sample_field=2` for the previously verified DxH O record. That setting is a
field position (2 or 3), not the actual barcode. Preserve leading zeroes.

New bridge messages are durably saved first within the same database transaction,
then matched against exactly one invoice within the device's lab scope. The
invoice must contain exactly one test with the configured interface code, directly
or in a stored package/group test list (model templates are used for empty lists).
No matching by patient name or the analyzer sequence number.

The CBC draft appears inside the existing invoice result editor and print preview
as a read-only four-column table: test name, result, unit, reference range.
Values and units are copied unchanged; missing units/ranges stay blank. The
reference range is the range transmitted by the analyzer, not a newly calculated
age/sex range. The underlying sub-tests remain structured for result history.

21 supported clinical DxH parameters: WBC, RBC, HGB, HCT, MCV, MCH, MCHC, RDW,
RDW-SD, PLT, MPV, LY, MO, NE, EO, BA, LY#, MO#, NE#, EO#, BA#.
Only received rows are displayed; values are never manufactured. All original
27 observations in the commissioning fixture, including @ research parameters,
flags and comments, remain in the device inbox. Research-only observations do
not enter the patient report. Unknown or repeated clinical codes remain for review.

Import does not mark the test or invoice completed, sign, or send the report.
The staff editor shows device comments and flags outside the four-column report.
Existing populated CBC results and completed/signed/sent invoices are never
overwritten. Ambiguous barcode or panel matches are held with a visible reason.

For messages received before this update, use Apply in Devices > Results.
The server independently checks the barcode and CBC interface code again;
a manual invoice link does not override barcode matching. Reapplying an imported
message is idempotent. Re-sends with a new message timestamp are retained in the
inbox but do not overwrite the populated CBC draft.

Deployment: update backend from the merged branch and deploy the compiled frontend.
No new database migration or Windows EXE is needed for this change (the existing
bridge migration must already be installed). Clear Laravel caches as usual.
Refresh the invoice editor after receipt; an already open editing form is not
automatically refreshed.

Verification uses BridgeResultsTest against MariaDB in GitHub Actions plus the
frontend build. Perform a real instrument test using a test invoice with a unique
barcode and CBC interface code before using the workflow routinely.
