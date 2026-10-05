import json
import socket
import tempfile
import unittest
from pathlib import Path
import sys
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from bm850 import parse_bm850, identity
from core import Store, Bridge, Api, DeliveryError
from test_bridge import CONFIG, FakeApi
from test_np21h import FakeConnection

def sample(): return Path(__file__).with_name('bm850_message.hl7').read_bytes()

class BM850Tests(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory();self.store=Store(Path(self.tmp.name)/'db.sqlite3')
        self.cfg=dict(CONFIG,adapter='bm850',port=5600)
    def tearDown(self): self.tmp.cleanup()
    def test_barcode_exact_obr4_all_values_and_histograms(self):
        raw=sample().replace(b'TEST-SAMPLE',b'000000000042')
        p=parse_bm850(raw)
        self.assertEqual(p['orders'][0]['accession'],'000000000042')
        self.assertEqual(p['orders'][0]['instrument_sequence'],'TEST-SEQ')
        self.assertEqual(len(p['observations']),22)
        self.assertTrue(all(o['status']=='P' for o in p['observations']))
        self.assertTrue(p['warnings'])
        for name,hist in p['orders'][0]['histograms'].items(): self.assertEqual(len(hist['values'][name]),80)
        self.store.capture(raw,self.cfg)
        payload=json.loads(self.store.next(self.cfg)['payload'])
        self.assertEqual(payload['specimen_barcode'],'000000000042')
        self.assertEqual(payload['raw_message'],raw.decode())
    def test_fragmented_frames_idle_durable_ack_and_repeat(self):
        frame=b'\x0b'+sample()+b'\x1c\r'
        conn=FakeConnection([socket.timeout(),frame[:30],frame[30:]+frame],self.store)
        Bridge(self.cfg,self.store,api=FakeApi()).receive_hl7(conn)
        self.assertEqual(len(conn.sent),2)
        self.assertTrue(all(b'MSA|AA|BM-TEST-1|' in msg and b'|2.7\r' in msg for msg in conn.sent))
        later=sample().replace(b'20261005175539',b'20261005185539')
        self.assertEqual(identity(sample()),identity(later))
        self.store.capture(later,self.cfg)
        self.assertEqual(self.store.counts(),{'pending':1})
        self.assertNotEqual(identity(sample()),identity(sample().replace(b'||5.5|',b'||5.6|')))
    def test_incomplete_duplicate_unknown_and_no_barcode_fail_closed(self):
        for raw in [sample().replace(b'|NM|WBC|',b'|NM|UNKNOWN|'),
                    sample().replace(b'|NM|WBC|',b'|NM|RBC|'),
                    sample().replace(b'TEST-SAMPLE',b''),
                    sample().replace(b'OBX|26|',b'BAD|26|')]:
            with self.assertRaises(ValueError):parse_bm850(raw)
            conn=FakeConnection([b'\x0b'+raw+b'\x1c\r'],self.store)
            Bridge(self.cfg,self.store,api=FakeApi()).receive_hl7(conn)
            self.assertIn(b'MSA|AR|BM-TEST-1|',conn.sent[0])
        self.assertIsNone(self.store.next(self.cfg))
    def test_old_server_blocked(self):
        api=Api(self.cfg)
        api.post=lambda *_:{'protocol':'labbridge-v1','idempotency':True,'device_id':CONFIG['device_id'],'adapters':['np21h']}
        with self.assertRaises(DeliveryError):api.check()
    def test_disk_failure_never_acknowledged(self):
        conn=FakeConnection([b'\x0b'+sample()+b'\x1c\r'],self.store)
        self.store.capture=lambda *_: (_ for _ in ()).throw(OSError('disk full'))
        with self.assertRaises(OSError):Bridge(self.cfg,self.store,api=FakeApi()).receive_hl7(conn)
        self.assertFalse(conn.sent)
