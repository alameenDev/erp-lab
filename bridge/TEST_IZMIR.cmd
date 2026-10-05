@echo off
cd /d "%~dp0"
where py >nul 2>&1
if not errorlevel 1 (
  py -3 -m unittest discover -s tests -p test_bm850.py -v
) else (
  python -m unittest discover -s tests -p test_bm850.py -v
)
pause
