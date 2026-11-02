@echo off
setlocal
cd /d "%~dp0"
if not exist "%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe" (
  echo Windows PowerShell was not found.
  pause
  exit /b 1
)
"%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe" -NoLogo -NoProfile -STA -ExecutionPolicy Bypass -File "%~dp0Transfer-Wizard.ps1"
if errorlevel 1 (
  echo The transfer wizard could not start. Keep this folder complete and try again.
  pause
)
endlocal
