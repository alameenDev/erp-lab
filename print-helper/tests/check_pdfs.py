import json, sys
from pathlib import Path
from pypdf import PdfReader
import pymupdf
from PIL import Image
import zxingcpp

root = Path(sys.argv[1])
manifest = json.loads((root / 'manifest.json').read_text())
assert len(manifest) == 8, manifest
for row in manifest:
    file = root / (row['kind'] + '.pdf')
    pdf = PdfReader(file)
    expected_pages = 2 if row['kind'] == 'report' else 1
    assert len(pdf.pages) == expected_pages, (row['kind'], len(pdf.pages))
    for page in pdf.pages:
        assert abs(float(page.mediabox.width) * 25.4 / 72 - row['paper']['width']) < .5, row
        assert abs(float(page.mediabox.height) * 25.4 / 72 - row['paper']['height']) < .5, row
    with pymupdf.open(file) as document:
        pixels = document[0].get_pixmap(dpi=203)
        image = Image.frombytes('RGB', [pixels.width, pixels.height], pixels.samples)
        image.save(root / (row['kind'] + '.png'))
        if row['kind'] == 'barcode':
            result = zxingcpp.read_barcode(image)
            assert result and result.text == '1234567890', result
            assert '#12' in pdf.pages[0].extract_text(), pdf.pages[0].extract_text()
print('PASS: all 8 native PDFs preserve media dimensions/page count; sample barcode decodes at 203 DPI with separate #12 register number.')
