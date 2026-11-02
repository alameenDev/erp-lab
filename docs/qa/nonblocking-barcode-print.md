# Barcode layout warnings no longer block printing

The operator explicitly requested printing to continue despite the clipped-text/layout warning. Both shared iframe print paths now treat barcode layout diagnostics as advisory; they no longer alert, throw, or return before sending the print job for long text, overlap, outside bounds or narrow barcode boxes. The designer's test-print button stays enabled while layout notes are displayed.

BarcodeLabel now renders valid encoded bars even below the recommended width, instead of substituting an "Increase barcode width" message. It preserves the complete encoded value, leading zeroes and quiet zones, uses whole printer dots where possible, and fits all bars to the selected box when it is smaller than one dot per module. Standard-width geometry remains unchanged. Designer diagnostics still report the recommended minimum width; they are explicitly labelled as nonblocking notes.

This is best-effort output, not automatic layout redesign: an undersized/overlapping saved layout can still clip text or make the printed symbol hard to scan. Invalid barcode values are not silently changed. Missing invoice data, failed register-number refresh, device failures and uncertain helper submissions keep their existing handling; no duplicate automatic print retries were added.

Validation performed locally:

- 20 Node tests: barcode encoding/quiet zones/narrow widths, sample copies, register numbering, both iframe paths and lifecycle/actual print errors.
- Sample-picker browser regression: dialog and direct print continue with clipped text, overlap and a narrow code; every label retains the original sample ID and expected copies. Failed API/register refresh handling remains intact.
- Barcode designer browser regression: long-name notes no longer disable or block test printing; presets, keyboard/drag placement and mobile layout pass.
- Generated two 50 x 30 mm PDF labels, rasterized at 203 DPI, and decoded both with ZXing to the exact original sample value.
- Local print helper browser regression covers all eight document routes and uncertain-response handling.
- Production frontend build and git diff whitespace check pass.

Synthetic data only. A physical Windows printer was not available for testing. No production deployment was performed.
