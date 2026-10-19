import importlib.util
import os
from pathlib import Path
import sys
import tempfile
import unittest

root = Path(__file__).resolve().parents[2]
temporary = tempfile.TemporaryDirectory()
os.environ['LOCALAPPDATA'] = temporary.name
sys.path.insert(0, str(root / 'bridge'))
spec = importlib.util.spec_from_file_location('local_bridge', root / 'desktop/bridge/desktop_local.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)

class IndependentBridgeTest(unittest.TestCase):
    def config(self, url):
        return {'api_url': url, 'token': 'a' * 64, 'listen_ip': '0.0.0.0', 'analyzer_ip': '192.168.1.10', 'port': 5001, 'device_id': 1}

    def test_only_v1_local_destination_is_accepted(self):
        self.assertEqual(module.local_validate(self.config(module.LOCAL_API))['api_url'], module.LOCAL_API)
        for url in ['https://lightpink-badger-650079.hostingersite.com/api', 'http://127.0.0.1:17892.evil.test/api', 'http://localhost:17892/api', 'http://127.0.0.1:17891/api']:
            with self.assertRaises(ValueError):
                module.LocalApi(self.config(url))

    def test_old_settings_and_queues_are_not_used(self):
        self.assertIn('DigitalLabDesktopV1-Bridges', str(module.desktop.HOME_DIR))
        self.assertEqual(module.desktop.DEFAULTS['api_url'], module.LOCAL_API)
        api = module.LocalApi(self.config(module.LOCAL_API))
        self.assertFalse(any(isinstance(h, module.urllib.request.ProxyHandler) and h.proxies for h in api.opener.handlers))

if __name__ == '__main__':
    unittest.main()
