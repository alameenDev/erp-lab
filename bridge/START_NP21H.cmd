@echo off
cd /d "%~dp0"
py -3 desktop_np21h.py
if errorlevel 1 pause
