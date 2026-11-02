Unicode true
RequestExecutionLevel user
SilentInstall silent
Name "Digital Lab synthetic child failure"
!ifndef OUTPUT_FILE
  !error "OUTPUT_FILE is required"
!endif
!ifndef EXIT_CODE
  !define EXIT_CODE 42
!endif
OutFile "${OUTPUT_FILE}"
InstallDir "$LOCALAPPDATA\DigitalLabSyntheticChild"
!include "LogicLib.nsh"

Section
  ; This fixture intentionally installs no desktop files. EXIT_CODE=0 covers
  ; a child installer that quits without doing its work but reports success.
  ReadEnvStr $0 "DLM_TEST_CHILD_LOG"
  ${If} $0 != ""
    FileOpen $1 "$0" w
    FileWrite $1 "synthetic-child-invoked$\r$\n"
    FileWrite $1 "$CMDLINE$\r$\n"
    FileWrite $1 "$INSTDIR$\r$\n"
    FileClose $1
  ${EndIf}
  SetErrorLevel ${EXIT_CODE}
  Quit
SectionEnd
