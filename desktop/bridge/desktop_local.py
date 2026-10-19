"""Independent device inbox bridge. Only the V1 loopback API is permitted."""
import os
import sys
from pathlib import Path
import core
import desktop

LOCAL_API = 'http://127.0.0.1:17892/api'
original_validate = core.validate_config

def local_validate(config, require_destination=True):
    if str(config.get('api_url', '')).strip().rstrip('/') != LOCAL_API:
        raise ValueError('Desktop V1 bridge only connects to http://127.0.0.1:17892/api')
    checked = dict(config, api_url='https://127.0.0.1:17892/api')
    checked = original_validate(checked, require_destination)
    checked['api_url'] = LOCAL_API
    return checked

core.validate_config = local_validate
desktop.validate_config = local_validate

name = Path(sys.executable if getattr(sys, 'frozen', False) else sys.argv[0]).stem.lower()
adapter = 'np21h' if 'np21h' in name else 'bm850' if 'bm850' in name else 'dxh500'
desktop.HOME_DIR = Path(os.environ.get('LOCALAPPDATA', str(Path.home()))) / 'DigitalLabDesktopV1-Bridges' / adapter
desktop.HOME_DIR.mkdir(parents=True, exist_ok=True)
desktop.SETTINGS = desktop.HOME_DIR / 'settings.json'
desktop.DEFAULTS.update(api_url=LOCAL_API, adapter=adapter, port=5001 if adapter=='dxh500' else 5600, sample_field=3 if adapter=='np21h' else 2)
if __name__ == '__main__':
    window = desktop.Window()
    if '--smoke-test' in sys.argv:
        window.root.update()
        window.root.destroy()
    else:
        window.root.mainloop()
