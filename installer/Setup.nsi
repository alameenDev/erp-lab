# -*- coding: utf-8 -*-
Unicode true
!include "MUI2.nsh"
!include "LogicLib.nsh"
!include "x64.nsh"

!ifndef DESKTOP_SETUP
  !error "DESKTOP_SETUP must point to the original verified installer"
!endif
!ifndef PAYLOAD_INCLUDE
  !error "PAYLOAD_INCLUDE must point to the generated managed-file list"
!endif

Name "Digital Lab - Offline Setup and All Labs Transfer"
OutFile "${OUTPUT_FILE}"
InstallDir "$LOCALAPPDATA\Programs\DigitalLabOffline"
InstallDirRegKey HKCU "Software\DigitalLab\OfflineBundle" "InstallLocation"
RequestExecutionLevel user
SetCompressor zlib
SetCompress off
CRCCheck force
ShowInstDetails show
ShowUninstDetails show
BrandingText "Digital Lab"
VIProductVersion "1.0.0.0"
VIAddVersionKey /LANG=1025 "ProductName" "Digital Lab Offline Setup"
VIAddVersionKey /LANG=1025 "FileDescription" "Offline Desktop and all-laboratories data transfer setup"
VIAddVersionKey /LANG=1025 "FileVersion" "1.0.0"
VIAddVersionKey /LANG=1025 "CompanyName" "Digital Lab"
Icon "${ICON_FILE}"
UninstallIcon "${ICON_FILE}"

Var PowerShell
Var DesktopDir
Var ExitCode
Var ResultCode
Var MutexHandle
Var MarkerValue
Var ExistingInstall

!define MUI_ABORTWARNING
!define MUI_ICON "${ICON_FILE}"
!define MUI_UNICON "${ICON_FILE}"
!define MUI_WELCOMEPAGE_TITLE "تثبيت المختبر الرقمي ونقل جميع المختبرات"
!define MUI_WELCOMEPAGE_TEXT "تثبت هذه الحزمة برنامج Digital Lab Desktop V1 وأداة نقل بيانات جميع المختبرات.$\r$\n$\r$\nبعد التثبيت، يفتح معالج النقل لإدخال مفتاح APP_KEY الخاص بالموقع واختيار المرفقات المتوفرة على حاسبتك.$\r$\n$\r$\nجهز نحو 8 غيغابايت مساحة فارغة أو أكثر. اختر مسار تثبيت بحروف إنكليزية.$\r$\n$\r$\nاضغط التالي للمتابعة."
!insertmacro MUI_PAGE_WELCOME
!define MUI_DIRECTORYPAGE_TEXT_TOP "اختر مجلداً محلياً بحروف إنكليزية يمكن لحسابك الكتابة فيه. يحفظ المثبت أداة النقل هنا ويثبت Desktop إذا لم يجد نسخة V1 متوافقة."
!insertmacro MUI_PAGE_DIRECTORY
!insertmacro MUI_PAGE_INSTFILES
!define MUI_FINISHPAGE_TITLE "اكتمل التثبيت"
!define MUI_FINISHPAGE_TEXT "أصبحت أداة نقل جميع المختبرات جاهزة.$\r$\n$\r$\nأدخل APP_KEY في المعالج على حاسبتك، واختر مجلد storage/app إذا متوفر. تنشئ الأداة ملف .dlbackup وتختبر استعادته.$\r$\n$\r$\nبعدها استعد الملف من إعداد النسخة والنسخ الاحتياطي داخل البرنامج. التعليمات مثبتة مع الأداة."
!define MUI_FINISHPAGE_RUN
!define MUI_FINISHPAGE_RUN_TEXT "فتح معالج نقل جميع المختبرات الآن"
!define MUI_FINISHPAGE_RUN_FUNCTION LaunchTransfer
!insertmacro MUI_PAGE_FINISH
!insertmacro MUI_UNPAGE_CONFIRM
!insertmacro MUI_UNPAGE_INSTFILES
!insertmacro MUI_LANGUAGE "Arabic"

!include "${PAYLOAD_INCLUDE}"

Function SelectPowerShell
  StrCpy $PowerShell "$WINDIR\Sysnative\WindowsPowerShell\v1.0\powershell.exe"
  IfFileExists "$PowerShell" selected_ps
  StrCpy $PowerShell "$SYSDIR\WindowsPowerShell\v1.0\powershell.exe"
  selected_ps:
FunctionEnd

Function .onInit
  SetShellVarContext current
  SetRegView 64
  ${IfNot} ${RunningX64}
    MessageBox MB_OK|MB_ICONSTOP "هذه النسخة تحتاج Windows 10 أو 11 بنظام 64 بت." /SD IDOK
    SetErrorLevel 10
    Quit
  ${EndIf}
  System::Call 'kernel32::CreateMutexW(p 0, i 0, w "Local\DigitalLabOfflineBundleSetupV1") p .r0 ?e'
  Pop $1
  StrCpy $MutexHandle $0
  IntCmp $1 183 already_running
  Call SelectPowerShell
  IfFileExists "$PowerShell" init_done
    MessageBox MB_OK|MB_ICONSTOP "Windows PowerShell غير متوفر على هذه الحاسبة." /SD IDOK
    SetErrorLevel 11
    Quit
  already_running:
    MessageBox MB_OK|MB_ICONSTOP "يوجد مثبت آخر لهذه الحزمة يعمل الآن. أكمله أولاً." /SD IDOK
    SetErrorLevel 12
    Quit
  init_done:
FunctionEnd

Function ShowInstallFailure
  StrCpy $0 "لم يكتمل التثبيت. رمز الحالة: $ResultCode.$\r$\nأغلق أداة النقل إذا كانت مفتوحة، وتحقق من مسار التثبيت ومساحة القرص، ثم أعد المحاولة."
  StrCmp $ResultCode "DESKTOP_RUNNING" 0 +2
    StrCpy $0 "أغلق Digital Lab Desktop من قائمة خروج، ثم أعد التثبيت. لم يغلق المثبت البرنامج بالقوة."
  StrCmp $ResultCode "TRANSFER_RUNNING" 0 +2
    StrCpy $0 "أداة النقل تعمل الآن. انتظر اكتمالها وأغلق نافذتها، ثم أعد التثبيت."
  StrCmp $ResultCode "INCOMPATIBLE_DESKTOP" 0 +2
    StrCpy $0 "توجد نسخة Desktop مختلفة عن V1 المطلوبة لهذه الحزمة. لم يتم استبدالها."
  StrCmp $ResultCode "MULTIPLE_DESKTOP_INSTALLATIONS" 0 +2
    StrCpy $0 "توجد عدة نسخ Desktop ولا يمكن تحديد النسخة المطلوبة تلقائياً. احتفظ برمز الحالة للمتابعة."
  StrCmp $ResultCode "ROOT_NOT_OWNED" 0 +2
    StrCpy $0 "المجلد المحدد يحتوي ملفات لا تخص هذه الحزمة. اختر مجلداً جديداً فارغاً."
  StrCmp $ResultCode "NON_ASCII_PATH" 0 +2
    StrCpy $0 "نسخة V1 تحتاج مسار تثبيت بحروف إنكليزية. اختر مجلداً إنكليزياً يمكن لحسابك الكتابة فيه."
  MessageBox MB_OK|MB_ICONSTOP "$0$\r$\n$\r$\nالرمز: $ResultCode" /SD IDOK
FunctionEnd

Section "التثبيت ونقل البيانات" MainSection
  SetShellVarContext current
  SetRegView 64
  InitPluginsDir
  SetOutPath "$PLUGINSDIR"
  File /oname=Bootstrap.ps1 "${BOOTSTRAP_SCRIPT}"
  File /oname=DesktopSetup.exe "${DESKTOP_SETUP}"
  Delete "$PLUGINSDIR\result.ini"
  DetailPrint "فحص النسخة الموجودة وتجهيز برنامج المختبر الرقمي..."
  ClearErrors
  ExecWait '"$PowerShell" -NoLogo -NoProfile -NonInteractive -ExecutionPolicy Bypass -File "$PLUGINSDIR\Bootstrap.ps1" -Mode Install -InstallRoot "$INSTDIR" -DesktopSetup "$PLUGINSDIR\DesktopSetup.exe" -ExpectedSetupHash "${DESKTOP_SETUP_SHA256}" -ResultFile "$PLUGINSDIR\result.ini"' $ExitCode
  IfErrors bootstrap_launch_failed
  ReadINIStr $ResultCode "$PLUGINSDIR\result.ini" "Result" "Code"
  StrCmp $ExitCode "0" 0 bootstrap_failed
  StrCmp $ResultCode "OK" 0 bootstrap_failed
  ReadINIStr $DesktopDir "$PLUGINSDIR\result.ini" "Result" "DesktopDir"
  StrCmp $DesktopDir "" bootstrap_failed
  IfFileExists "$DesktopDir\Digital Lab Desktop.exe" desktop_ready bootstrap_failed
  bootstrap_launch_failed:
    StrCpy $ResultCode "BOOTSTRAP_START_FAILED"
  bootstrap_failed:
    Call ShowInstallFailure
    SetErrorLevel 20
    Abort

  desktop_ready:
  DetailPrint "تثبيت أداة نقل بيانات جميع المختبرات..."
  ClearErrors
  !insertmacro InstallPayload
  IfErrors payload_failed
  SetOutPath "$INSTDIR\InstallerSupport"
  File /oname=Bootstrap.ps1 "${BOOTSTRAP_SCRIPT}"
  IfErrors payload_failed
  SetOutPath "$INSTDIR"
  File /oname=DigitalLab.ico "${ICON_FILE}"
  WriteUninstaller "$INSTDIR\Uninstall-Data-Transfer.exe"
  IfErrors payload_failed
  WriteRegStr HKCU "Software\DigitalLab\OfflineBundle" "InstallLocation" "$INSTDIR"
  WriteRegStr HKCU "Software\DigitalLab\OfflineBundle" "DesktopLocation" "$DesktopDir"
  WriteRegStr HKCU "Software\DigitalLab\OfflineBundle" "Version" "1.0.0"
  WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\DigitalLabAllLabsTransfer" "DisplayName" "Digital Lab - All Labs Data Transfer"
  WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\DigitalLabAllLabsTransfer" "DisplayVersion" "1.0.0"
  WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\DigitalLabAllLabsTransfer" "Publisher" "Digital Lab"
  WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\DigitalLabAllLabsTransfer" "InstallLocation" "$INSTDIR"
  WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\DigitalLabAllLabsTransfer" "DisplayIcon" "$INSTDIR\DigitalLab.ico"
  WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\DigitalLabAllLabsTransfer" "UninstallString" '"$INSTDIR\Uninstall-Data-Transfer.exe"'
  WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\DigitalLabAllLabsTransfer" "QuietUninstallString" '"$INSTDIR\Uninstall-Data-Transfer.exe" /S'
  WriteRegDWORD HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\DigitalLabAllLabsTransfer" "NoModify" 1
  WriteRegDWORD HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\DigitalLabAllLabsTransfer" "NoRepair" 1
  CreateDirectory "$SMPROGRAMS\Digital Lab Offline"
  SetOutPath "$INSTDIR\Transfer"
  CreateShortCut "$DESKTOP\Digital Lab - All Labs Transfer.lnk" "$WINDIR\System32\WindowsPowerShell\v1.0\powershell.exe" '-NoLogo -NoProfile -STA -ExecutionPolicy Bypass -File "$INSTDIR\Transfer\Transfer-Wizard.ps1" -InstallationPath "$DesktopDir"' "$INSTDIR\DigitalLab.ico" 0 SW_SHOWNORMAL
  CreateShortCut "$SMPROGRAMS\Digital Lab Offline\Transfer all labs.lnk" "$WINDIR\System32\WindowsPowerShell\v1.0\powershell.exe" '-NoLogo -NoProfile -STA -ExecutionPolicy Bypass -File "$INSTDIR\Transfer\Transfer-Wizard.ps1" -InstallationPath "$DesktopDir"' "$INSTDIR\DigitalLab.ico" 0 SW_SHOWNORMAL
  CreateShortCut "$SMPROGRAMS\Digital Lab Offline\Open Digital Lab.lnk" "$DesktopDir\Digital Lab Desktop.exe" "" "$DesktopDir\Digital Lab Desktop.exe" 0 SW_SHOWNORMAL
  CreateShortCut "$SMPROGRAMS\Digital Lab Offline\Arabic instructions.lnk" "$INSTDIR\Transfer\README_AR.txt"
  IfErrors payload_failed
  DetailPrint "اكتمل التثبيت. افتح أداة النقل لإكمال إنشاء ملف الاستعادة."
  SetErrorLevel 0
  Goto section_done
  payload_failed:
    StrCpy $ResultCode "TRANSFER_INSTALL_FAILED"
    Call ShowInstallFailure
    SetErrorLevel 21
    Abort
  section_done:
SectionEnd

Function LaunchTransfer
  SetOutPath "$INSTDIR\Transfer"
  Exec '"$PowerShell" -NoLogo -NoProfile -STA -ExecutionPolicy Bypass -File "$INSTDIR\Transfer\Transfer-Wizard.ps1" -InstallationPath "$DesktopDir"'
FunctionEnd

Function un.onInit
  SetShellVarContext current
  SetRegView 64
  ReadRegStr $ExistingInstall HKCU "Software\DigitalLab\OfflineBundle" "InstallLocation"
  StrCmp $ExistingInstall $INSTDIR 0 un_invalid
  FileOpen $0 "$INSTDIR\.digitallab-offline-bundle" r
  IfErrors un_invalid
  FileRead $0 $MarkerValue
  FileClose $0
  StrCmp $MarkerValue "DigitalLabOfflineBundleV1" un_owned
  StrCmp $MarkerValue "DigitalLabOfflineBundleV1$\r$\n" un_owned un_invalid
  un_owned:
    StrCpy $PowerShell "$WINDIR\Sysnative\WindowsPowerShell\v1.0\powershell.exe"
    IfFileExists "$PowerShell" un_guard
    StrCpy $PowerShell "$SYSDIR\WindowsPowerShell\v1.0\powershell.exe"
  un_guard:
    InitPluginsDir
    Delete "$PLUGINSDIR\result.ini"
    ClearErrors
    ExecWait '"$PowerShell" -NoLogo -NoProfile -NonInteractive -ExecutionPolicy Bypass -File "$INSTDIR\InstallerSupport\Bootstrap.ps1" -Mode CheckUninstall -InstallRoot "$INSTDIR" -ResultFile "$PLUGINSDIR\result.ini"' $ExitCode
    IfErrors un_guard_failed
    StrCmp $ExitCode "0" 0 un_guard_failed
    ReadINIStr $ResultCode "$PLUGINSDIR\result.ini" "Result" "Code"
    StrCmp $ResultCode "READY_TO_REMOVE" un_ready
  un_guard_failed:
    MessageBox MB_OK|MB_ICONSTOP "تعذر إزالة أداة النقل الآن. أغلق نافذتها وانتظر انتهاء أي عملية نقل ثم أعد المحاولة." /SD IDOK
    SetErrorLevel 30
    Abort
  un_invalid:
    MessageBox MB_OK|MB_ICONSTOP "تعذر التحقق من مجلد أداة النقل. لم تتم إزالة أي ملفات." /SD IDOK
    SetErrorLevel 31
    Abort
  un_ready:
FunctionEnd

Section "Uninstall"
  SetShellVarContext current
  SetRegView 64
  !insertmacro UninstallPayload
  Delete "$INSTDIR\InstallerSupport\Bootstrap.ps1"
  RMDir "$INSTDIR\InstallerSupport"
  Delete "$INSTDIR\DigitalLab.ico"
  Delete "$INSTDIR\.desktop-install-pending"
  Delete "$DESKTOP\Digital Lab - All Labs Transfer.lnk"
  Delete "$SMPROGRAMS\Digital Lab Offline\Transfer all labs.lnk"
  Delete "$SMPROGRAMS\Digital Lab Offline\Open Digital Lab.lnk"
  Delete "$SMPROGRAMS\Digital Lab Offline\Arabic instructions.lnk"
  RMDir "$SMPROGRAMS\Digital Lab Offline"
  DeleteRegKey HKCU "Software\DigitalLab\OfflineBundle"
  DeleteRegKey HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\DigitalLabAllLabsTransfer"
  Delete "$INSTDIR\Uninstall-Data-Transfer.exe"
  ; Retain ownership when Desktop or user files remain, so reinstall can reuse it.
  ; Remove the marker and root only when no other entries are present.
  FindFirst $0 $1 "$INSTDIR\*.*"
  un_check_remaining:
    StrCmp $1 "" un_root_empty
    StrCmp $1 "." un_next_entry
    StrCmp $1 ".." un_next_entry
    StrCmp $1 ".digitallab-offline-bundle" un_next_entry un_root_preserved
  un_next_entry:
    FindNext $0 $1
    Goto un_check_remaining
  un_root_empty:
    FindClose $0
    Delete "$INSTDIR\.digitallab-offline-bundle"
    RMDir "$INSTDIR"
    Goto un_finish
  un_root_preserved:
    FindClose $0
  un_finish:
  SetErrorLevel 0
SectionEnd
