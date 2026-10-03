"""Isolated NP-21H settings/outbox; does not migrate or reuse the DxH token."""
import os
import sys
from pathlib import Path
import desktop

desktop.HOME_DIR = Path(os.environ.get('LOCALAPPDATA', str(Path.home()))) / 'DigitalLabBridge-NP21H'
desktop.HOME_DIR.mkdir(parents=True, exist_ok=True)
desktop.SETTINGS = desktop.HOME_DIR / 'settings.json'
desktop.DEFAULTS.update(adapter='np21h', analyzer_ip='192.168.1.80', port=5600, sample_field=3)
if __name__ == '__main__':
    window = desktop.Window()
    if '--smoke-test' in sys.argv:
        window.root.update(); window.root.destroy()
    else:
        window.root.mainloop()
