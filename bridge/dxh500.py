"""DxH 500 capture-derived commissioning adapter; not a certified host driver."""
import re
from protocols import field


def parse_dxh500(raw, mapping=None, options=None):
    options = options or {}
    records = [r for r in re.split(r'\r\n|\r|\n', raw.decode('utf-8')) if r]
    if not records or not records[0].startswith('H|\\!~|'):
        raise ValueError('DxH 500 requires the observed H|\\!~ header')
    header = records[0].split('|')
    if field(header, 4).split('!')[0].strip().casefold() != 'dxh 500':
        raise ValueError('Header does not identify DxH 500')
    if not records[-1].startswith('L|') or field(records[-1].split('|'), 2) != 'N':
        raise ValueError('Missing normal DxH 500 termination record')
    patient = accession = instrument_id = ''
    observations, comments, orders = [], [], []
    target = None
    warnings = ['نسخة اختبار DxH 500: طابق هوية العينة والنتائج مع الجهاز قبل الاعتماد.']
    for line in records[1:-1]:
        parts = line.split('|')
        kind = parts[0]
        if kind == 'P':
            patient = field(parts, options.get('astm_patient_field', 3)).split('!')[0].strip()
            accession = instrument_id = ''
            target = None
        elif kind == 'O':
            accession = field(parts, options.get('astm_sample_field', 2)).split('!')[0].strip()
            instrument_id = field(parts, 3)
            orders.append({'sample_field_2': field(parts, 2), 'instrument_field_3': instrument_id,
                           'selected_accession': accession, 'raw_record': line})
            target = None
        elif kind == 'R':
            if not accession: raise ValueError('Result without sample identifier')
            code = field(parts, 2).split('!')[-1].strip()
            if not code: raise ValueError('Result without test code')
            values = field(parts, 3).split('!')
            value = values[0].strip()
            flags = '!'.join(values[1:]).strip()
            item = (mapping or {}).get(code, {})
            # Units stay exactly as sent. No implicit unit conversion.
            target = {'code': code, 'name': item.get('name') or code, 'value': value,
                      'unit': field(parts, 4), 'reference': field(parts, 6),
                      'flag': flags, 'status': field(parts, 7), 'patient_id': patient,
                      'accession': accession, 'specimen': '', 'raw_value': field(parts, 3),
                      'raw_record': line, 'instrument_id': instrument_id,
                      'instrument_timestamp': field(parts, 13), 'device_id': field(parts, 14),
                      'research_only': code.startswith('@'), 'comments': []}
            observations.append(target)
        elif kind == 'C':
            comment = {'text': field(parts, 3), 'type': field(parts, 4),
                       'scope': 'result' if target is not None else 'sample',
                       'code': target['code'] if target else '', 'accession': accession,
                       'raw_record': line}
            comments.append(comment)
            if target is not None: target['comments'].append(comment)
        else:
            warnings.append('سجل غير محلل محفوظ بالنص الأصلي: ' + kind)
    if not observations: raise ValueError('No DxH 500 result records')
    if any(not o['patient_id'] for o in observations):
        warnings.append('معرّف المريض غير متوفر؛ لا تربط النتائج تلقائياً بمريض.')
    for c in comments:
        if c['scope'] == 'sample' and c['text'] not in warnings: warnings.append(c['text'])
    if any(o['research_only'] for o in observations):
        warnings.append('الفحوصات التي تبدأ بـ @ بحثية فقط حسب رسالة الجهاز.')
    return {'format': 'ASTM', 'adapter': 'dxh500', 'message_type': 'RESULT', 'control_id': '',
            'patient_id': patient, 'accession': accession, 'version': field(header, 12),
            'observations': observations, 'orders': orders, 'comments': comments,
            'warnings': warnings, 'requires_review': True}
