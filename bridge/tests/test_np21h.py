import hashlib
import json
import socket
import tempfile
import unittest
from pathlib import Path
import sys
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from core import Store, Bridge, Api, DeliveryError
from np21h import parse_np21h, identity, CODES
from protocols import MLLPStream
from test_bridge import CONFIG, FakeApi

VALUES = [
 ('11.10','10*3/uL','3.50-9.50','H~A'),('27.7','%','20.0-50.0','~N'),
 ('66.7','%','40.0-75.0','~N'),('5.6','%','3.0-10.0','~N'),
 ('3.07','10*3/uL','1.10-3.20','~N'),('7.41','10*3/uL','1.80-6.30','H~A'),
 ('0.62','10*3/uL','0.10-0.60','H~A'),('4.79','10*6/uL','3.80-5.10','~N'),
 ('13.3','g/dL','11.5-15.0','~N'),('40.1','%','35.0-45.0','~N'),
 ('83.7','fL','82.0-100.0','~N'),('27.7','pg','27.0-34.0','~N'),
 ('33.1','g/dL','31.6-35.4','~N'),('12.7','%','11.5-14.5','~N'),
 ('38.5','fL','35.0-56.0','~N'),('369','10*3/uL','125-350','H~A'),
 ('8.8','fL','6.5-12.0','~N'),('9.2','fL','9.0-17.0','~N'),
 ('0.326','%','0.108-0.282','H~A'),('18.8','%','11.0-45.0','~N'),('69','10*9/L','30-90','~N')]

def sample():
    rows=['MSH|^~\\&|NP-21H/NP-26H|Nipigon Health|||20261003183702||ORU^R01|TEST-CONTROL-1|P|2.3.1||||||UNICODE',
          'PID|1||||^TEST|||Female','PV1|1',
          'OBR|1||TEST-123|01001^Automated Count^99MRC||20231003171627|20231003171650',
          'OBX|1|IS|02001^Take Mode^99MRC||O||||||F',
          'OBX|2|IS|02002^Blood Mode^99MRC||W||||||F',
          'OBX|3|IS|02003^Test Mode^99MRC||CBC+3DIFF||||||F',
          'OBX|4|NM|30525-0^Age^LN||33|yr|||||F',
          'OBX|5|IS|09001^Remark^99MRC||||||||F',
          'OBX|6|IS|03001^Ref Group^99MRC||Woman||||||F']
    for i,((code,name),(value,unit,ref,flags)) in enumerate(zip(CODES.items(),VALUES),7):
        rows.append(f'OBX|{i}|NM|{code}^{name}^{"99MRC" if name=="PCT" else "LN"}||{value}|{unit}|{ref}|{flags}|||F')
    return ('\r'.join(rows)+'\r').encode()

class FakeConnection:
    def __init__(self,chunks,store): self.chunks=iter(chunks);self.sent=[];self.store=store
    def recv(self,n):
        chunk=next(self.chunks,b'')
        if isinstance(chunk,Exception): raise chunk
        return chunk
    def sendall(self,data):
        # An ACK may only happen after the durable SQLite transaction has committed.
        assert self.store.counts()
        self.sent.append(data)

class NP21Tests(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory();self.store=Store(Path(self.tmp.name)/'db.sqlite3')
        self.cfg=dict(CONFIG,adapter='np21h',port=5600)
    def tearDown(self): self.tmp.cleanup()
    def test_capture_mapping_and_time_only_retransmission(self):
        p=parse_np21h(sample())
        self.assertEqual(len(p['observations']),21)
        self.assertEqual(p['orders'][0]['accession'],'TEST-123')
        self.assertEqual(p['orders'][0]['details']['Age'],'33')
        self.assertEqual(p['observations'][2]['name'],'GRAN%')
        self.assertEqual(p['observations'][-1]['unit'],'10*9/L')
        first=self.store.capture(sample(),self.cfg)
        later=sample().replace(b'20261003183702',b'20261003185702')
        self.assertEqual(self.store.capture(later,self.cfg),first)
        self.assertEqual(self.store.counts(),{'pending':1})
        # A changed measurement must never be suppressed as a duplicate.
        changed=later.replace(b'||11.10|',b'||11.11|')
        self.assertNotEqual(identity(sample()),identity(changed))
        self.store.capture(changed,self.cfg)
        self.assertEqual(self.store.counts(),{'pending':2})
    def test_split_coalesced_frames_idle_and_ack(self):
        raw=sample();frame=b'\x0b'+raw+b'\x1c\r'
        conn=FakeConnection([socket.timeout(),frame[:50],frame[50:]+frame],self.store)
        Bridge(self.cfg,self.store,api=FakeApi()).receive_hl7(conn)
        self.assertEqual(len(conn.sent),2)
        self.assertNotEqual(conn.sent[0],conn.sent[1])
        self.assertTrue(all(b'MSA|AA|TEST-CONTROL-1|' in x for x in conn.sent))
        self.assertEqual(self.store.counts(),{'pending':1})
    def test_partial_unknown_and_nonfinal_never_accepted(self):
        invalids=[sample().replace(b'OBX|27|',b'BAD|27|'),sample().replace(b'6690-2^WBC',b'X^WBC'),sample().replace(b'|H~A|||F',b'|H~A|||P')]
        for raw in invalids:
            conn=FakeConnection([b'\x0b'+raw+b'\x1c\r'],self.store)
            Bridge(self.cfg,self.store,api=FakeApi()).receive_hl7(conn)
            self.assertIn(b'MSA|AR|',conn.sent[0])
        conn=FakeConnection([b'\x0b'+sample()],self.store)
        Bridge(self.cfg,self.store,api=FakeApi()).receive_hl7(conn)
        self.assertFalse(conn.sent)
        self.assertIsNone(self.store.next(self.cfg))
    def test_failed_disk_never_sends_ack(self):
        conn=FakeConnection([b'\x0b'+sample()+b'\x1c\r'],self.store)
        self.store.capture=lambda *_: (_ for _ in ()).throw(OSError('disk full'))
        with self.assertRaises(OSError):Bridge(self.cfg,self.store,api=FakeApi()).receive_hl7(conn)
        self.assertFalse(conn.sent)
    def test_complete_oversize_frame_rejected(self):
        with self.assertRaises(ValueError):MLLPStream(limit=10).feed(b'\x0b'+b'x'*11+b'\x1c\r')
    def test_old_backend_fails_capability_check(self):
        api=Api(self.cfg)
        api.post=lambda *_:{'protocol':'labbridge-v1','idempotency':True,'device_id':7}
        with self.assertRaises(DeliveryError): api.check()

if __name__=='__main__':unittest.main()
