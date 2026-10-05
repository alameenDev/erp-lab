"""Boule BM850 HL7MW profile from Izmir's supplied HL7 2.7 capture.
OBR-4 is the site's barcode; OBR-3 is the instrument sequence.
"""
import base64
import hashlib
import json
import math
import re
from datetime import datetime
from uuid import uuid4

CODES = ('WBC','LYMR','MIDR','GRNR','LYMA','MIDA','GRNA','RBC','HGB','HCT',
         'MCV','MCH','MCHC','RDWR','RDWA','PLT','MPV','PCT','PDW','PDWR','P-LCR','P-LCC')
META = {'ID2','PROF','METH','OPID'}

def header(raw):
    h = raw.decode('utf-8', 'strict').split('\r')[0].split('|')
    if len(h)<12 or h[:3]!=['MSH','^~\\&','BM850^HL7MW'] or h[8]!='ORU^R01' or h[11]!='2.7' or not h[9]:
        raise ValueError('Expected BM850 HL7MW ORU^R01 / HL7 2.7')
    return h

def identity(raw):
    h = header(raw)
    rows = raw.decode('utf-8').split('\r')
    # This capture puts its transport timestamp in MSH-8 (index 7).
    # Support standard MSH-7 too, but never erase non-timestamp security data.
    for i in (6,7):
        if re.fullmatch(r'\d{14}', h[i]): h[i]=''
    rows[0]='|'.join(h)
    return hashlib.sha256('\r'.join(rows).encode('utf-8')).hexdigest()

def parse_bm850(raw):
    h=header(raw)
    text=raw.decode('utf-8')
    if not text.endswith('\r') or '\x00' in text: raise ValueError('Incomplete HL7 message')
    rows=[r.split('|') for r in text.split('\r') if r]
    orders=[r for r in rows if r[0]=='OBR']
    if len(orders)!=1 or len(orders[0])<5: raise ValueError('Exactly one OBR required')
    order=orders[0]; accession=order[4].strip()
    if not accession or accession=='""' or len(accession)>255 or any(c in accession for c in '^~\\&'):
        raise ValueError('Missing or composite barcode in OBR-4')
    observations=[]; comments=[]; details={}; histograms={}; seen=set()
    for r in rows:
        if r[0]=='NTE' and len(r)>3 and r[3] not in ('','""'):
            comments.append({'scope':'sample','code':'NTE','text':r[3]})
        if r[0]!='OBX': continue
        if len(r)<12: raise ValueError('Truncated OBX')
        code=r[3]
        if code in META and r[2]=='ST':
            details[code]=r[5];continue
        if r[2]=='ED' and not code and r[4]=='HGRAM':
            if histograms: raise ValueError('Duplicate histogram block')
            try:
                histograms=json.loads(base64.b64decode(r[5],validate=True))
                if not isinstance(histograms,dict): raise ValueError('Histogram object required')
                for name, data in histograms.items():
                    values=data['values'][name]
                    if name not in ('WBC','RBC','PLT') or type(data['count']) is not int or not 1<=data['count']<=4096 or len(values)!=data['count']:
                        raise ValueError('Invalid histogram count')
                    if not all(type(v) in (int,float) and math.isfinite(v) and v>=0 for v in values):
                        raise ValueError('Invalid histogram values')
            except (ValueError,KeyError,TypeError) as exc:
                raise ValueError('Invalid histogram payload') from exc
            continue
        if code not in CODES or code in seen or r[2]!='NM' or r[11] not in ('P','F'):
            raise ValueError('Unknown, duplicate or unsupported observation: '+code)
        if not re.fullmatch(r'[+-]?(?:\d+(?:\.\d*)?|\.\d+)',r[5]): raise ValueError('Non-numeric result: '+code)
        seen.add(code)
        observations.append({'code':code,'name':code,'value':r[5],'raw_value':r[5],
            'unit':r[6],'reference':r[7],'flag':'' if r[8]=='""' else r[8],
            'status':r[11],'research_only':False,'accession':accession})
    if seen!=set(CODES): raise ValueError('Expected all 22 BM850 numeric results')
    warnings=[]
    if any(o['status']=='P' for o in observations):
        warnings.append('Instrument marked results P (preliminary); review before report approval.')
    observations.sort(key=lambda o:CODES.index(o['code']))
    return {'adapter':'bm850','observations':observations,'comments':comments,'warnings':warnings,
        'orders':[{'accession':accession,'barcode_source':'OBR-4','instrument_sequence':order[3],
                   'control_id':h[9],'result_time':order[22] if len(order)>22 else '',
                   'details':details,'histograms':histograms}]}

def ack(raw,code,explanation):
    h=header(raw);now=datetime.now().astimezone().strftime('%Y%m%d%H%M%S%z')
    body=(f'MSH|^~\\&|DigitalLabBridge|Izmir|{h[2]}|{h[3]}|{now}||ACK^R01|{uuid4().hex}|P|2.7\r'
          f'MSA|{code}|{h[9]}|{explanation}\r')
    return b'\x0b'+body.encode('utf-8')+b'\x1c\r'
