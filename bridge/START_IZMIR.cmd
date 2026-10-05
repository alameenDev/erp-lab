@echo off
cd /d "%~dp0"
where py >nul 2>&1
if not errorlevel 1 (
  py -3 desktop_izmir.py
) else (
  python desktop_izmir.py
)
if errorlevel 1 (
  echo Install Python 3.12 with Tcl/Tk and Add Python to PATH enabled.
  pause
)
