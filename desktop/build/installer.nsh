!include "FileFunc.nsh"
!macro customInstall
  ; ExecWait keeps activation/account setup inside Setup, before successful completion.
  ${If} ${Silent}
    ${GetParameters} $R0
    ${GetOptions} $R0 "/LICENSE=" $R1
    ${If} $R1 != ""
      ExecWait '"$INSTDIR\${APP_EXECUTABLE_FILENAME}" --installer-check --license-file "$R1"' $R2
    ${Else}
      ExecWait '"$INSTDIR\${APP_EXECUTABLE_FILENAME}" --installer-check' $R2
    ${EndIf}
  ${Else}
    ExecWait '"$INSTDIR\${APP_EXECUTABLE_FILENAME}" --installer-activate' $R2
  ${EndIf}
  ${If} $R2 != 0
    SetErrorLevel 2
    Abort "Digital Lab activation was not completed. Run Setup again. Existing patient data was not changed."
  ${EndIf}
!macroend
