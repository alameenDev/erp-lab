"""NP-21H profile verified against the supplied ORU^R01 / HL7 2.3.1 capture."""
import hashlib
import uuid
from datetime import datetime

# Preserve instrument terminology: GRAN/MID are not a five-part differential.
CODES = {
 '6690-2':'WBC', '736-9':'LYM%', '20482-6':'GRAN%', '32155-4':'MID%',
 '731-0':'LYM#', '19023-1':'GRAN#', '32154-7':'MID#', '789-8':'RBC',
 '718-7':'HGB', '4544-3':'HCT', '787-2':'MCV', '785-6':'MCH', '786-4':'MCHC',
 '788-0':'RDW-CV', '21000-5':'RDW-SD', '777-3':'PLT', '32623-1':'MPV',
 '32207-3':'PDW', '11003':'PCT', '48386-7':'P-LCR', '34167-7':'P-LCC',
}
META = {'02001','02002','02003','30525-0','09001','03001'}

def identity(raw):
    """Only transmission time is ignored; result times, control ID and all values remain."""
    rows = raw.decode('utf-8', 'strict').split('\r')
    h = rows[0].split('|')
    if len(h) < 12 or h[0] != 'MSH':
        raise ValueError('Missing HL7 header')
    h[6] = ''
    rows[0] = '|'.join(h)
    return hashlib.sha256('\r'.join(rows).encode('utf-8')).hexdigest()

def parse_np21h(raw):
    text = raw.decode('utf-8', 'strict')
    if not text.endswith('\r') or '\x00' in text:
        raise ValueError('Incomplete or unsupported HL7 encoding')
    rows = [r.split('|') for r in text.split('\r') if r]
    h = rows[0]
    if len(h)<12 or h[0]!='MSH' or h[1]!='^~\\&' or h[2]!='NP-21H/NP-26H' or h[8]!='ORU^R01' or h[11]!='2.3.1' or not h[9]:
        raise ValueError('Expected NP-21H/NP-26H HL7 2.3.1 ORU^R01')
    orders = [r for r in rows if r[0]=='OBR']
    if len(orders)!=1 or len(orders[0])<8 or not orders[0][3].strip():
        raise ValueError('One OBR with Sample ID in OBR-3 is required')
    order = orders[0]
    accession = order[3].split('^')[0].strip()
    if not accession or len(accession)>255 or any(c in accession for c in '\\~&'):
        raise ValueError('Unsupported Sample ID')
    results, comments, metadata = [], [], {}
    seen = set()
    for r in rows[1:]:
        if r[0]=='NTE':
            if len(r)>3 and r[3]: comments.append({'scope':'sample','code':'NTE','text':r[3]})
            continue
        if r[0]!='OBX': continue
        if len(r)<12: raise ValueError('Truncated OBX record')
        ident=r[3].split('^'); code=ident[0]
        name=ident[1] if len(ident)>1 else ''
        if code in META:
            metadata[name] = r[5]
            if code=='09001' and r[5]: comments.append({'scope':'sample','code':code,'text':r[5]})
            continue
        if code not in CODES or name!=CODES[code] or name in seen:
            raise ValueError('Unknown, inconsistent or duplicate CBC parameter: '+code)
        if r[2]!='NM' or not r[5] or r[11]!='F':
            raise ValueError('Non-final or unsupported observation: '+name)
        seen.add(name)
        results.append({'code':name,'name':name,'value':r[5],'raw_value':r[5],
            'unit':r[6],'reference':r[7],'flag':r[8], 'status':r[11],
            'research_only':False,'accession':accession,'loinc':code})
    if len(results)!=21: raise ValueError('Expected all 21 NP-21H CBC results')
    if metadata.get('Test Mode')!='CBC+3DIFF': raise ValueError('Unsupported test mode')
    warnings=[]
    if h[6][:8]!=order[7][:8]: warnings.append('Result date differs from message date; verify historical sample before release.')
    return {'adapter':'np21h','observations':results,'comments':comments,'warnings':warnings,
        'orders':[{'accession':accession,'sample_time':order[6],'analysis_time':order[7],
                   'message_time':h[6],'control_id':h[9],'details':metadata}]}

def ack(raw, code, explanation):
    h=raw.decode('utf-8','strict').split('\r')[0].split('|')
    if len(h)<12 or h[0]!='MSH' or h[1]!='^~\\&' or not h[9]:
        raise ValueError('Cannot acknowledge an invalid header')
    now=datetime.now().astimezone().strftime('%Y%m%d%H%M%S%z')
    body=(f'MSH|^~\\&|DigitalLabBridge|DigitalLab|{h[2]}|{h[3]}|{now}||ACK^R01|{uuid.uuid4().hex}|P|2.3.1\r'
          f'MSA|{code}|{h[9]}|{explanation}\r')
    return b'\x0b'+body.encode('utf-8')+b'\x1c\r'
