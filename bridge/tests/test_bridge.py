import io
import json
import socket
import tempfile
import threading
import unittest
from pathlib import Path
import sys
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from core import Api,Bridge,Store,DeliveryError,NoRedirect,validate_config
from dxh_sample import sample
from protocols import astm_frame,ENQ,EOT,ACK,NAK

CONFIG={'api_url':'https://lab.example.test/api','token':'a'*64,'listen_ip':'127.0.0.1',
        'analyzer_ip':'127.0.0.1','port':5001,'sample_field':2,'device_id':7}

class FakeApi:
    def __init__(self):self.calls=[];self.fail=False
    def check(self):return {'device_id':7}
    def deliver(self,p):
        self.calls.append(p)
        if self.fail:raise DeliveryError('offline')
        return {'id':101,'device_id':7,'delivery_id':p['delivery_id'],'stored':True,'protocol':'labbridge-v1'}

class Tests(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory();self.path=Path(self.tmp.name)/'db.sqlite3'
        self.store=Store(self.path);self.cfg=dict(CONFIG)
    def tearDown(self):self.tmp.cleanup()
    def test_persist_parse_metadata_and_no_duplicate_after_restart(self):
        self.store.capture(sample(),self.cfg)
        row=self.store.next(self.cfg);p=json.loads(row['payload'])
        self.assertEqual(len(p['parsed_results']),27)
        self.assertEqual(p['parsed_results'][2]['value'],'11.57')
        self.assertEqual(p['parsed_results'][2]['flags'],'Rl')
        self.assertEqual(p['instrument_metadata']['comments'][0]['text'],'Lyse Expired')
        self.assertEqual(sum(x['research_only'] for x in p['parsed_results']),6)
        self.assertTrue(p['raw_message'].endswith('\r'))
        store=Store(self.path);store.capture(sample(),self.cfg)
        self.assertEqual(store.counts(),{'pending':1})
    def test_offline_retry_only_marks_sent_after_receipt(self):
        self.store.capture(sample(),self.cfg);api=FakeApi();api.fail=True
        bridge=Bridge(self.cfg,self.store,api=api);bridge.upload_once()
        self.assertEqual(self.store.counts(),{'pending':1})
        self.store.retry();api.fail=False;bridge.upload_once()
        self.assertEqual(self.store.counts(),{'sent':1})
        self.assertEqual(api.calls[0],api.calls[1])
    def test_quarantine_incomplete_and_multi_order(self):
        self.store.capture(sample().replace(b'L|1|N\r',b''),self.cfg)
        self.store.capture(sample().replace(b'L|1|N\r',b'O|2|OTHER!|OTHER!OV\rR|1|!!!WBC|9!R|u||1 to 10|A\rL|1|N\r'),self.cfg)
        self.assertEqual(self.store.counts(),{'review':2})
        self.assertIsNone(self.store.next(self.cfg))
    def test_destination_cannot_change_with_pending_data(self):
        self.store.capture(sample(),self.cfg)
        self.assertFalse(self.store.can_change_destination(dict(self.cfg,device_id=8)))
        self.assertFalse(self.store.can_change_destination(dict(self.cfg,api_url='https://other.test/api')))
        self.assertIsNone(self.store.next(dict(self.cfg,device_id=8)))
    def test_ack_requires_storage_and_invalid_checksum_nak(self):
        a,b=socket.socketpair();a.settimeout(.1);b.settimeout(2)
        bridge=Bridge(self.cfg,self.store,api=FakeApi())
        thread=threading.Thread(target=bridge.receive,args=(a,));thread.start()
        try:
            b.sendall(ENQ);self.assertEqual(b.recv(1),ACK)
            for i,record in enumerate(sample().rstrip(b'\r').split(b'\r'),1):
                frame=astm_frame(i,record+b'\r')
                if i==2:
                    b.sendall(frame[:-4]+b'ZZ'+frame[-2:]);self.assertEqual(b.recv(1),NAK)
                b.sendall(frame[:4]);b.sendall(frame[4:]);self.assertEqual(b.recv(1),ACK)
                b.sendall(frame);self.assertEqual(b.recv(1),ACK)
            self.assertEqual(self.store.counts(),{'pending':1})
            b.sendall(EOT+ENQ);self.assertEqual(b.recv(1),ACK)
            b.sendall(EOT)
        finally:b.close();thread.join(3);a.close()
        self.assertFalse(thread.is_alive())
    def test_invalid_response_never_confirms_delivery(self):
        self.store.capture(sample(),self.cfg);p=json.loads(self.store.next(self.cfg)['payload'])
        api=Api(self.cfg)
        for reply in ({'stored':True}, {'protocol':'labbridge-v1','stored':True,'delivery_id':p['delivery_id'],'device_id':8,'id':1}, '<html>', None):
            api.post=lambda *_,r=reply:r
            with self.assertRaises(DeliveryError):api.deliver(p)
    def test_https_and_no_redirect(self):
        for url in ('http://lab.test/api','https://user:pw@lab.test/api','https://lab.test/api?secret=a'):
            with self.assertRaises(ValueError):validate_config(dict(self.cfg,api_url=url))
        self.assertIsNone(NoRedirect().redirect_request(None,None,302,'',{},'https://other.test'))
    def test_permanent_failure_is_retained_for_explicit_retry(self):
        self.store.capture(sample(),self.cfg);row=self.store.next(self.cfg)
        self.store.failed(row,DeliveryError('HTTP 422',True));self.assertEqual(self.store.counts(),{'blocked':1})
        self.assertIsNone(self.store.next(self.cfg));self.store.retry();self.assertEqual(self.store.counts(),{'pending':1})

if __name__=='__main__':unittest.main()
