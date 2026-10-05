"""Single-instrument durable DxH-to-ERP bridge. Python standard library only."""
import hashlib
import ipaddress
import json
import re
import socket
import sqlite3
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
from contextlib import contextmanager
from pathlib import Path
from dxh500 import parse_dxh500
from np21h import parse_np21h, identity as np21_identity, ack as np21_ack
from bm850 import parse_bm850, identity as bm850_identity, ack as bm850_ack
from protocols import MLLPStream
from protocols import ACK, NAK, ENQ, EOT, STX, ETX, CR, LF, validate_astm_frame

LIMIT = 65535
PROTOCOL = 'labbridge-v1'


def validate_config(config, require_destination=True):
    cfg = dict(config)
    cfg['adapter'] = cfg.get('adapter', 'dxh500')
    if cfg['adapter'] not in ('dxh500','np21h','bm850'): raise ValueError('Unsupported analyzer adapter')
    url = urllib.parse.urlsplit(str(cfg.get('api_url', '')).strip().rstrip('/'))
    if url.scheme != 'https' or not url.hostname or url.username or url.password or url.query or url.fragment:
        raise ValueError('Server must be an HTTPS API URL without credentials or query parameters')
    cfg['api_url'] = urllib.parse.urlunsplit(url)
    if not re.fullmatch('[a-fA-F0-9]{64}', str(cfg.get('token', '')).strip()):
        raise ValueError('Enter the 64-character device API token from the Devices page')
    cfg['token'] = cfg['token'].strip()
    for key in ('listen_ip', 'analyzer_ip'):
        value = ipaddress.IPv4Address(cfg.get(key, ''))
        if value.is_multicast or (key == 'analyzer_ip' and value.is_unspecified):
            raise ValueError('Invalid '+key)
        cfg[key] = str(value)
    cfg['port'] = int(cfg.get('port', 5001))
    if not 1 <= cfg['port'] <= 65535: raise ValueError('Port must be between 1 and 65535')
    cfg['sample_field'] = int(cfg.get('sample_field', 2))
    if cfg['sample_field'] not in (2, 3): raise ValueError('Sample field must be 2 or 3')
    if require_destination and (type(cfg.get('device_id')) is not int or cfg['device_id'] < 1):
        raise ValueError('Test and save the server connection first')
    return cfg


class DeliveryError(Exception):
    def __init__(self, message, permanent=False):
        super().__init__(message)
        self.permanent = permanent


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        # Never forward a device token or patient results to a redirect destination.
        return None


class Api:
    def __init__(self, config):
        self.config = config
        self.opener = urllib.request.build_opener(NoRedirect())

    def post(self, endpoint, payload):
        body = json.dumps(payload, ensure_ascii=False, separators=(',', ':')).encode('utf-8')
        req = urllib.request.Request(self.config['api_url']+'/device/bridge/'+endpoint, data=body,
            headers={'Content-Type': 'application/json', 'Accept': 'application/json',
                     'X-Device-Token': self.config['token']}, method='POST')
        try:
            with self.opener.open(req, timeout=8) as response:
                if response.status not in (200, 201): raise DeliveryError('Unexpected HTTP response')
                data = response.read(65537)
                if len(data) > 65536: raise DeliveryError('Response exceeds limit')
                return json.loads(data)
        except urllib.error.HTTPError as exc:
            # Do not log response bodies: they may contain patient data or credentials.
            raise DeliveryError('HTTP '+str(exc.code), exc.code in (301,302,303,307,308,400,401,403,404,405,409,413,422)) from None
        except (urllib.error.URLError, TimeoutError, OSError, ValueError):
            raise DeliveryError('Server unavailable, TLS failure, or invalid JSON response') from None

    def check(self):
        result = self.post('heartbeat', {})
        if not isinstance(result, dict) or result.get('protocol') != PROTOCOL or result.get('idempotency') is not True:
            raise DeliveryError('Server bridge update is not installed', True)
        if type(result.get('device_id')) is not int or result['device_id'] < 1:
            raise DeliveryError('Invalid server device identity', True)
        if self.config.get('device_id') and result['device_id'] != self.config['device_id']:
            raise DeliveryError('Token belongs to a different device; queued records were not sent', True)
        if self.config.get('adapter') == 'bm850' and 'bm850' not in result.get('adapters', []):
            raise DeliveryError('Install the BM850 server update and select Boule BM850 for the Izmir device; use its own API token', True)
        if self.config.get('adapter') == 'np21h' and 'np21h' not in result.get('adapters', []):
            if result.get('lab_id'):
                raise DeliveryError('Select Nipigon NP-21H in this laboratory device settings, then use its own API token', True)
            raise DeliveryError('Install the NP-21H server update before saving settings', True)
        return result

    def deliver(self, payload):
        result = self.post('results', payload)
        if not isinstance(result, dict) or result.get('protocol') != PROTOCOL or result.get('stored') is not True or \
           result.get('delivery_id') != payload['delivery_id'] or result.get('device_id') != payload['device_id'] or \
           type(result.get('id')) is not int or result['id'] < 1:
            raise DeliveryError('Server did not confirm storage of this exact message')
        return result


class Store:
    def __init__(self, path):
        self.path = str(path)
        Path(path).parent.mkdir(parents=True, exist_ok=True)
        self.lock = threading.RLock()
        with self.db() as db:
            db.execute('PRAGMA journal_mode=WAL')
            db.executescript('''
                CREATE TABLE IF NOT EXISTS outbox (
                    id INTEGER PRIMARY KEY, api_url TEXT NOT NULL, device_id INTEGER NOT NULL,
                    digest TEXT NOT NULL, raw BLOB NOT NULL, payload TEXT,
                    status TEXT NOT NULL, error TEXT NOT NULL DEFAULT '', attempts INTEGER NOT NULL DEFAULT 0,
                    retry_at REAL NOT NULL DEFAULT 0, server_id INTEGER, created_at REAL NOT NULL,
                    UNIQUE(api_url, device_id, digest));
                CREATE TABLE IF NOT EXISTS np21_identity (
                    api_url TEXT NOT NULL, device_id INTEGER NOT NULL, fingerprint TEXT NOT NULL,
                    outbox_id INTEGER NOT NULL, UNIQUE(api_url,device_id,fingerprint));
                CREATE TABLE IF NOT EXISTS wire (
                    id INTEGER PRIMARY KEY, at REAL NOT NULL, direction TEXT NOT NULL, data BLOB NOT NULL);
            ''')

    @contextmanager
    def db(self):
        with self.lock:
            conn = sqlite3.connect(self.path, timeout=15)
            conn.row_factory = sqlite3.Row
            conn.execute('PRAGMA synchronous=FULL')
            try:
                with conn: yield conn
            finally: conn.close()

    def wire(self, direction, data):
        with self.db() as db:
            db.execute('INSERT INTO wire(at,direction,data) VALUES(?,?,?)', (time.time(), direction, data))

    def capture(self, raw, cfg):
        digest = hashlib.sha256(raw).hexdigest()
        payload, status, error = None, 'pending', ''
        try:
            if len(raw) > LIMIT: raise ValueError('Message exceeds server limit')
            parsed = parse_bm850(raw) if cfg.get('adapter') == 'bm850' else parse_np21h(raw) if cfg.get('adapter') == 'np21h' else parse_dxh500(raw, options={'astm_sample_field': cfg['sample_field']})
            accessions = {o['accession'] for o in parsed['observations']}
            if len(accessions) != 1 or len(parsed['orders']) != 1:
                raise ValueError('Multiple orders require manual review; original retained locally')
            payload = {'delivery_id': digest, 'device_id': cfg['device_id'],
                'specimen_barcode': next(iter(accessions)), 'raw_message': raw.decode('utf-8'),
                'parsed_results': [{'test_code': o['code'], 'test_name': o['name'], 'value': o['value'],
                    'unit': o['unit'], 'flags': o['flag'], 'reference_range': o['reference'],
                    'result_status': o['status'], 'research_only': o['research_only'], 'raw_value': o['raw_value']}
                    for o in parsed['observations']],
                'instrument_metadata': {k: parsed[k] for k in ('adapter','warnings','comments','orders')}}
        except (ValueError, UnicodeError) as exc:
            status, error = 'review', str(exc)
        with self.db() as db:
            fingerprint = bm850_identity(raw) if cfg.get('adapter') == 'bm850' and payload else np21_identity(raw) if cfg.get('adapter') == 'np21h' and payload else None
            if fingerprint:
                prior = db.execute('SELECT o.id,o.status FROM np21_identity n JOIN outbox o ON o.id=n.outbox_id WHERE n.api_url=? AND n.device_id=? AND n.fingerprint=?', (cfg['api_url'],cfg['device_id'],fingerprint)).fetchone()
                if prior: return dict(prior)
            db.execute('''INSERT OR IGNORE INTO outbox(api_url,device_id,digest,raw,payload,status,error,created_at)
                VALUES(?,?,?,?,?,?,?,?)''', (cfg['api_url'], cfg['device_id'], digest, raw,
                json.dumps(payload, ensure_ascii=False) if payload else None, status, error, time.time()))
            row = db.execute('SELECT id,status FROM outbox WHERE api_url=? AND device_id=? AND digest=?',
                (cfg['api_url'],cfg['device_id'],digest)).fetchone()
            if fingerprint:
                db.execute('INSERT OR IGNORE INTO np21_identity VALUES(?,?,?,?)',(cfg['api_url'],cfg['device_id'],fingerprint,row['id']))
            return dict(row)

    def next(self, cfg):
        with self.db() as db:
            row=db.execute("SELECT * FROM outbox WHERE status='pending' AND api_url=? AND device_id=? AND retry_at<=? ORDER BY id LIMIT 1",
                           (cfg['api_url'],cfg['device_id'],time.time())).fetchone()
            return dict(row) if row else None

    def sent(self, row, receipt):
        with self.db() as db:
            db.execute("UPDATE outbox SET status='sent',server_id=?,error='' WHERE id=?", (receipt['id'],row['id']))

    def failed(self, row, error):
        with self.db() as db:
            db.execute('UPDATE outbox SET status=?,error=?,attempts=attempts+1,retry_at=? WHERE id=?',
                ('blocked' if error.permanent else 'pending',str(error),time.time()+min(300,5*2**min(row['attempts'],6)),row['id']))

    def counts(self):
        with self.db() as db: return {r['status']:r['n'] for r in db.execute('SELECT status,COUNT(*) n FROM outbox GROUP BY status')}

    def retry(self):
        with self.db() as db: db.execute("UPDATE outbox SET status='pending',retry_at=0 WHERE status IN ('blocked','pending')")

    def can_change_destination(self, cfg):
        with self.db() as db:
            return not db.execute("SELECT id FROM outbox WHERE status!='sent' AND (api_url!=? OR device_id!=?) LIMIT 1",
                                  (cfg['api_url'],cfg['device_id'])).fetchone()

    def recent(self):
        with self.db() as db:
            return [dict(r) for r in db.execute('SELECT id,status,error,server_id FROM outbox ORDER BY id DESC LIMIT 10')]


class Bridge:
    def __init__(self, config, store, notify=lambda x: None, api=None):
        self.cfg = validate_config(config)
        self.store, self.notify = store, notify
        self.api = api or Api(self.cfg)
        self.stop = threading.Event()
        self.server = None
        self.threads = []

    def start(self):
        if not self.store.can_change_destination(self.cfg):
            raise ValueError('Unsent records belong to another destination. Restore their original server/device first.')
        server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        try:
            if hasattr(socket, 'SO_EXCLUSIVEADDRUSE'):
                server.setsockopt(socket.SOL_SOCKET,socket.SO_EXCLUSIVEADDRUSE,1)
            server.bind((self.cfg['listen_ip'],self.cfg['port']));server.listen(2);server.settimeout(1)
        except Exception:
            server.close();raise
        self.server = server
        self.threads = [threading.Thread(target=f,daemon=True) for f in (self.listen,self.upload)]
        for thread in self.threads: thread.start()
        self.notify('الاستقبال يعمل — بانتظار جهاز التحليل')

    def close(self):
        self.stop.set()
        if self.server: self.server.close()
        for t in self.threads: t.join(10)
        if any(t.is_alive() for t in self.threads): raise RuntimeError('Shutdown still in progress')

    def listen(self):
        while not self.stop.is_set():
            try: conn, address = self.server.accept()
            except socket.timeout: continue
            except OSError: break
            with conn:
                if address[0] != self.cfg['analyzer_ip']:
                    self.notify('تم رفض اتصال من عنوان غير مسموح');continue
                conn.settimeout(1)
                self.notify('الجهاز متصل')
                try:
                    if self.cfg.get('adapter') in ('np21h','bm850'): self.receive_hl7(conn)
                    else: self.receive(conn)
                except Exception as exc: self.notify('توقف اتصال الجهاز: '+type(exc).__name__)
                self.notify('انتهى الاتصال — الاستقبال ينتظر إعادة الاتصال')

    def receive_hl7(self, conn):
        stream = MLLPStream(limit=LIMIT)
        activity = time.monotonic()
        while not self.stop.is_set():
            try: data = conn.recv(8192)
            except socket.timeout:
                if stream.buffer and time.monotonic()-activity > 30:
                    self.notify('Incomplete HL7 frame retained in wire log; reconnect required')
                    return
                continue  # An idle connection is normal; never disconnect merely for silence.
            if not data:
                if stream.buffer: self.notify('Incomplete HL7 frame retained in wire log')
                return
            self.store.wire('RX', data)
            activity = time.monotonic()
            for raw in stream.feed(data):
                row = self.store.capture(raw, self.cfg)
                code = 'AR' if row['status']=='review' else 'AA'
                explanation = 'Retained for review; unsupported result' if code=='AR' else 'Stored in durable local outbox'
                reply = (bm850_ack if self.cfg.get('adapter') == 'bm850' else np21_ack)(raw, code, explanation)
                self.store.wire('TX_PENDING',reply)
                conn.sendall(reply)
                self.store.wire('TX',reply)
                self.notify(self.cfg.get('adapter','').upper()+': local record #'+str(row['id'])+' / '+row['status']+' / ACK '+code)

    def receive(self, conn):
        pending, content = bytearray(), bytearray()
        last_frame = last_number = None
        active = False
        activity = time.monotonic()
        def send(data):
            self.store.wire('TX_PENDING',data);conn.sendall(data);self.store.wire('TX',data)
        try:
            while not self.stop.is_set():
                try: data=conn.recv(8192)
                except socket.timeout:
                    if (pending or content) and time.monotonic()-activity > 30:
                        raise ValueError('Incomplete ASTM timeout')
                    continue
                if not data: break
                self.store.wire('RX',data)
                activity=time.monotonic();pending.extend(data)
                if len(pending)>LIMIT+8192: raise ValueError('Frame limit')
                while pending:
                    if pending.startswith(ENQ):
                        del pending[:1]
                        if content: self.store.capture(bytes(content),self.cfg)
                        content.clear();last_frame=last_number=None;active=True;send(ACK)
                    elif pending.startswith(EOT):
                        del pending[:1]
                        if content: self.store.capture(bytes(content),self.cfg)
                        content.clear();last_frame=last_number=None;active=False
                        self.notify('اكتمل إرسال الجهاز؛ راجع عدّاد الحفظ بالسيرفر')
                    elif pending.startswith(STX):
                        end=pending.find(CR+LF)
                        if end<0: break
                        frame=bytes(pending[:end+2]);del pending[:end+2]
                        try:
                            number,payload,terminal=validate_astm_frame(frame)
                            if not active: raise ValueError('ENQ required')
                            if frame==last_frame: send(ACK);continue
                            if number not in range(8) or (last_number is not None and number!=(last_number+1)%8):
                                raise ValueError('Unexpected frame sequence')
                            if len(content)+len(payload)>LIMIT: raise ValueError('Transaction limit')
                        except ValueError:
                            send(NAK);continue
                        content.extend(payload)
                        if terminal==ETX and any(r.startswith(b'L|') for r in bytes(content).split(CR)):
                            row=self.store.capture(bytes(content),self.cfg)
                            self.notify('حُفظت رسالة محلياً #'+str(row['id'])+' — '+row['status'])
                            content.clear()
                        last_number,last_frame=number,frame
                        send(ACK)
                    else: del pending[:1]
        finally:
            if content: self.store.capture(bytes(content),self.cfg)
            if pending: self.notify('إطار غير مكتمل محفوظ في سجل الاتصال المحلي')

    def upload_once(self):
        row=self.store.next(self.cfg)
        if not row: return False
        try:
            result=self.api.deliver(json.loads(row['payload']))
            self.store.sent(row,result)
            self.notify('تأكد الحفظ بالسيرفر — رسالة #'+str(row['id'])+' / سجل '+str(result['id']))
        except DeliveryError as exc:
            self.store.failed(row,exc)
            self.notify(('الإرسال يحتاج تصحيح إعدادات: ' if exc.permanent else 'الرسالة محفوظة؛ ستتم إعادة المحاولة: ')+str(exc))
        return True

    def upload(self):
        next_check=0
        while not self.stop.is_set():
            try:
                if time.monotonic()>=next_check:
                    self.api.check();next_check=time.monotonic()+60
                self.upload_once()
            except DeliveryError as exc:
                self.notify('فحص السيرفر: '+str(exc));self.stop.wait(15);continue
            except Exception as exc:
                self.notify('الإرسال متوقف مؤقتاً؛ البيانات محفوظة: '+type(exc).__name__)
                self.stop.wait(15);continue
            self.stop.wait(1.2)
