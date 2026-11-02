[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][string]$SuccessInstaller,
    [Parameter(Mandatory = $true)][string]$FailInstaller,
    [Parameter(Mandatory = $true)][string]$FalseSuccessInstaller,
    [Parameter(Mandatory = $true)][string]$PayloadDirectory,
    [Parameter(Mandatory = $true)][string]$PayloadHashes,
    [Parameter(Mandatory = $true)][string]$OutputDirectory
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

function Assert-True([bool]$Condition, [string]$Message) {
    if (-not $Condition) { throw $Message }
}

function File-Hash([string]$Path) {
    return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant()
}

function Run-Executable([string]$File, [string]$Arguments, [int]$TimeoutSeconds = 360) {
    $info = New-Object System.Diagnostics.ProcessStartInfo
    $info.FileName = [IO.Path]::GetFullPath($File)
    $info.Arguments = $Arguments
    $info.UseShellExecute = $false
    $info.CreateNoWindow = $true
    $process = New-Object System.Diagnostics.Process
    $process.StartInfo = $info
    if (-not $process.Start()) { throw 'Test process did not start' }
    try {
        if (-not $process.WaitForExit($TimeoutSeconds * 1000)) {
            # The process is this synthetic CI test's exact installer only.
            try { $process.Kill() } catch {}
            throw 'Synthetic installer process timed out'
        }
        return $process.ExitCode
    } finally { $process.Dispose() }
}

function Show-SyntheticBootstrapDiagnostic([string]$FailWrapper, [string]$TestRoot) {
    # Used only when the first generated child fixture was never reached. Run
    # the same guard directly in a separate fresh destination to expose its
    # numeric status and deliberately limited result fields, never raw errors.
    $previousLog = $env:DLM_TEST_CHILD_LOG
    try {
        $bootstrap = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\installer\Bootstrap.ps1'))
        $child = Join-Path ([IO.Path]::GetDirectoryName([IO.Path]::GetFullPath($FailWrapper))) 'child-failure-42.exe'
        $diagnosticRoot = Join-Path $TestRoot ('Bootstrap Diagnostic ' + [Guid]::NewGuid().ToString('N'))
        $resultFile = Join-Path ([IO.Path]::GetTempPath()) ('DLM-Installer-Diagnostic-' + [Guid]::NewGuid().ToString('N') + '.ini')
        $env:DLM_TEST_CHILD_LOG = Join-Path $TestRoot 'bootstrap-diagnostic-child.log'
        $powershell = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
        $arguments = '-NoLogo -NoProfile -NonInteractive -ExecutionPolicy Bypass -File "' + $bootstrap + '" -Mode Install -InstallRoot "' + $diagnosticRoot + '" -DesktopSetup "' + $child + '" -ExpectedSetupHash "' + (File-Hash $child) + '" -ResultFile "' + $resultFile + '"'
        $diagnosticExit = Run-Executable $powershell $arguments 90
        Write-Host ('Synthetic Bootstrap diagnostic exit: ' + $diagnosticExit)
        if ([IO.File]::Exists($resultFile)) {
            foreach ($line in [IO.File]::ReadAllLines($resultFile)) {
                if ($line -match '^\[Result\]$|^(Code|Reused|FailureLine|ErrorType)=[A-Za-z0-9_.-]*$|^DesktopDir=$') {
                    Write-Host $line
                }
            }
        } else {
            Write-Host 'Synthetic Bootstrap diagnostic result file absent.'
        }
    } catch {
        Write-Host ('Synthetic Bootstrap diagnostic unavailable: ' + $_.Exception.GetType().Name)
    } finally { $env:DLM_TEST_CHILD_LOG = $previousLog }
}

function Get-Registry64([string]$Subkey) {
    $base = [Microsoft.Win32.RegistryKey]::OpenBaseKey(
        [Microsoft.Win32.RegistryHive]::CurrentUser,
        [Microsoft.Win32.RegistryView]::Registry64)
    try {
        $key = $base.OpenSubKey($Subkey)
        if ($null -eq $key) { return $null }
        try {
            $result = @{}
            foreach ($name in $key.GetValueNames()) { $result[$name] = $key.GetValue($name) }
            return $result
        } finally { $key.Dispose() }
    } finally { $base.Dispose() }
}

function Desktop-Registrations {
    $items = @()
    foreach ($view in @([Microsoft.Win32.RegistryView]::Registry64, [Microsoft.Win32.RegistryView]::Registry32)) {
        $base = [Microsoft.Win32.RegistryKey]::OpenBaseKey([Microsoft.Win32.RegistryHive]::CurrentUser, $view)
        try {
            $parent = $base.OpenSubKey('Software\Microsoft\Windows\CurrentVersion\Uninstall')
            if ($null -eq $parent) { continue }
            try {
                foreach ($childName in $parent.GetSubKeyNames()) {
                    $child = $parent.OpenSubKey($childName)
                    if ($null -eq $child) { continue }
                    try {
                        if ([string]$child.GetValue('DisplayName') -eq 'Digital Lab Desktop') {
                            $items += [pscustomobject]@{
                                Key = $childName
                                Version = [string]$child.GetValue('DisplayVersion')
                                Location = [string]$child.GetValue('InstallLocation')
                                Uninstall = [string]$child.GetValue('UninstallString')
                            }
                        }
                    } finally { $child.Dispose() }
                }
            } finally { $parent.Dispose() }
        } finally { $base.Dispose() }
    }
    return @($items)
}

function Assert-Payload([string]$InstalledTransfer, $Hashes) {
    foreach ($property in $Hashes.files.PSObject.Properties) {
        $relative = $property.Name.Replace('/', [IO.Path]::DirectorySeparatorChar)
        $installed = Join-Path $InstalledTransfer $relative
        Assert-True ([IO.File]::Exists($installed)) ('Missing installed payload file: ' + $property.Name)
        Assert-True ((File-Hash $installed) -eq $property.Value) ('Installed payload changed: ' + $property.Name)
    }
}

function Capture-InstallerWelcome([string]$File, [string]$Destination, [string]$Png) {
    Add-Type -AssemblyName System.Drawing
    if (-not ('DlmInstallerWindow' -as [type])) {
        Add-Type -TypeDefinition @'
using System;
using System.Runtime.InteropServices;
public static class DlmInstallerWindow {
  [StructLayout(LayoutKind.Sequential)] public struct Rect { public int Left, Top, Right, Bottom; }
  [DllImport("user32.dll")] public static extern bool GetWindowRect(IntPtr hWnd, out Rect rect);
  [DllImport("user32.dll")] public static extern bool PrintWindow(IntPtr hWnd, IntPtr hdc, uint flags);
}
'@
    }
    $process = Start-Process -FilePath $File -ArgumentList ('/D=' + $Destination) -PassThru
    try {
        $until = [DateTime]::UtcNow.AddSeconds(45)
        do {
            Start-Sleep -Milliseconds 250
            $process.Refresh()
            if ($process.HasExited) { throw 'Installer exited before its welcome screen' }
        } while ($process.MainWindowHandle -eq [IntPtr]::Zero -and [DateTime]::UtcNow -lt $until)
        Assert-True ($process.MainWindowHandle -ne [IntPtr]::Zero) 'Installer welcome window did not appear'
        Start-Sleep -Milliseconds 500
        $rect = New-Object DlmInstallerWindow+Rect
        Assert-True ([DlmInstallerWindow]::GetWindowRect($process.MainWindowHandle, [ref]$rect)) 'Could not locate installer window'
        $bitmap = New-Object System.Drawing.Bitmap(($rect.Right - $rect.Left), ($rect.Bottom - $rect.Top))
        try {
            $graphics = [Drawing.Graphics]::FromImage($bitmap)
            try {
                $dc = $graphics.GetHdc()
                try { Assert-True ([DlmInstallerWindow]::PrintWindow($process.MainWindowHandle, $dc, 2)) 'Could not capture installer window' }
                finally { $graphics.ReleaseHdc($dc) }
            } finally { $graphics.Dispose() }
            $bitmap.Save($Png, [Drawing.Imaging.ImageFormat]::Png)
        } finally { $bitmap.Dispose() }
    } finally {
        # No installation button was pressed: close only this synthetic UI probe.
        if (-not $process.HasExited) { $process.Kill(); [void]$process.WaitForExit(10000) }
        $process.Dispose()
    }
}

if ($env:OS -ne 'Windows_NT') { throw 'This lifecycle test requires Windows' }
foreach ($file in @($SuccessInstaller, $FailInstaller, $FalseSuccessInstaller, $PayloadHashes)) {
    Assert-True ([IO.File]::Exists($file)) ('Test input missing: ' + $file)
}
$PayloadDirectory = [IO.Path]::GetFullPath($PayloadDirectory)
$hashes = [IO.File]::ReadAllText($PayloadHashes) | ConvertFrom-Json
$manifest = [IO.File]::ReadAllText((Join-Path $PayloadDirectory 'data\manifest.json')) | ConvertFrom-Json
Assert-True ($hashes.syntheticDataOnly -eq $true -and $manifest.source.synthetic -eq $true) 'Tests require generated synthetic payload only'
$OutputDirectory = [IO.Path]::GetFullPath($OutputDirectory)
[void][IO.Directory]::CreateDirectory($OutputDirectory)

$bundleKey = 'Software\DigitalLab\OfflineBundle'
$uninstallKey = 'Software\Microsoft\Windows\CurrentVersion\Uninstall\DigitalLabAllLabsTransfer'
Assert-True ($null -eq (Get-Registry64 $bundleKey)) 'This test requires a clean disposable Windows runner'
Assert-True (@(Desktop-Registrations).Count -eq 0) 'An existing desktop must not be modified by this test'

$testTemp = if ($env:RUNNER_TEMP) { $env:RUNNER_TEMP } else { [IO.Path]::GetTempPath() }
$root = Join-Path $testTemp ('DLM-Installer-Test-' + [Guid]::NewGuid().ToString('N'))
[void][IO.Directory]::CreateDirectory($root)
[IO.File]::WriteAllText((Join-Path $root '.synthetic-test'), 'Digital Lab installer CI only')
$target = Join-Path $root 'Installed Bundle With Spaces'
$transfer = Join-Path $target 'Transfer'
$desktop = Join-Path $target 'Desktop'
$liveData = Join-Path $env:APPDATA 'DigitalLabDesktopV1\lab-data'
Assert-True (-not [IO.Directory]::Exists($liveData)) 'Unexpected existing desktop data in CI'
[void][IO.Directory]::CreateDirectory($liveData)
$liveSentinel = Join-Path $liveData 'synthetic-preserve-me.txt'
[IO.File]::WriteAllText($liveSentinel, 'Synthetic existing user data must remain unchanged.')
$liveHash = File-Hash $liveSentinel
$previousChildLog = $env:DLM_TEST_CHILD_LOG
$runningProbe = $null
$checks = New-Object System.Collections.Generic.List[string]
try {
    $env:DLM_TEST_CHILD_LOG = Join-Path $root 'failed-child.log'
    $failedTarget = Join-Path $root 'Failed Child Target'
    $exitCode = Run-Executable $FailInstaller ('/S /D=' + $failedTarget)
    if (-not [IO.File]::Exists($env:DLM_TEST_CHILD_LOG)) {
        Show-SyntheticBootstrapDiagnostic $FailInstaller $root
        throw ('Failure fixture was not invoked (wrapper exit ' + $exitCode + ')')
    }
    Assert-True ($exitCode -ne 0) 'A failing child installer was reported as success'
    Assert-True ($null -eq (Get-Registry64 $uninstallKey)) 'Failed setup must not register a completed installation'
    Assert-True ((File-Hash $liveSentinel) -eq $liveHash) 'Failed setup changed live data'
    $checks.Add('child-exit-failure-is-not-success')
    Write-Host 'PASS: child failure is detected without changing live data.'

    $env:DLM_TEST_CHILD_LOG = Join-Path $root 'false-success-child.log'
    $falseTarget = Join-Path $root 'False Success Child Target'
    $exitCode = Run-Executable $FalseSuccessInstaller ('/S /D=' + $falseTarget)
    Assert-True ($exitCode -ne 0) 'A zero-exit child without installed files was reported as success'
    Assert-True ([IO.File]::Exists($env:DLM_TEST_CHILD_LOG)) 'False-success fixture was not invoked'
    Assert-True ($null -eq (Get-Registry64 $uninstallKey)) 'Incomplete setup must not register success'
    $checks.Add('zero-exit-without-desktop-is-rejected')
    Write-Host 'PASS: an incomplete zero-exit child is rejected.'

    $env:DLM_TEST_CHILD_LOG = Join-Path $root 'original-child.log'
    $exitCode = Run-Executable $SuccessInstaller ('/S /D=' + $target)
    Assert-True ($exitCode -eq 0) ('Original desktop setup failed with exit ' + $exitCode)
    Assert-True ([IO.File]::Exists((Join-Path $desktop 'Digital Lab Desktop.exe'))) 'Original desktop executable missing'
    Assert-True (([IO.File]::ReadAllText((Join-Path $target '.digitallab-offline-bundle'))).Trim() -eq 'DigitalLabOfflineBundleV1') 'Installer ownership marker missing'
    Assert-Payload $transfer $hashes
    $registration = Get-Registry64 $bundleKey
    Assert-True ($null -ne $registration) 'Bundle registration missing'
    Assert-True ([IO.Path]::GetFullPath([string]$registration['InstallLocation']) -eq $target) 'Wrong bundle install location'
    Assert-True ([IO.Path]::GetFullPath([string]$registration['DesktopLocation']) -eq $desktop) 'Wrong desktop location'
    $arp = Get-Registry64 $uninstallKey
    Assert-True ($null -ne $arp) 'Bundle uninstall registration missing'
    Assert-True (@(Desktop-Registrations | Where-Object { $_.Version -eq '1.0.0' }).Count -ge 1) 'Original desktop uninstall entry missing'
    $build = [IO.File]::ReadAllText((Join-Path $desktop 'resources\bundle\build.json')) | ConvertFrom-Json
    Assert-True ($build.version -eq '1.0.0' -and $build.mariadb -eq '10.11.19') 'Installed desktop build metadata differs'
    $checks.Add('original-desktop-installed-to-directory-with-spaces')
    $checks.Add('all-synthetic-payload-file-hashes')
    $checks.Add('separate-bundle-and-desktop-registration')
    Write-Host 'PASS: original desktop, exact payload files, marker and registrations installed.'

    $bundle = Join-Path $desktop 'resources\bundle'
    $php = Join-Path $bundle 'php\php.exe'
    $ext = '"' + (Join-Path $bundle 'php\ext').Replace('\', '/') + '"'
    $phpOutput = & $php -c (Join-Path $bundle 'php\php.ini') -d ('extension_dir=' + $ext) -r 'echo PHP_VERSION . "|" . (extension_loaded("pdo_mysql") ? "mysql" : "missing");'
    Assert-True ($LASTEXITCODE -eq 0 -and ($phpOutput -join '') -eq '8.4.26|mysql') 'Installed PHP runtime or driver failed'
    $databaseOutput = & (Join-Path $bundle 'mariadb\bin\mariadbd.exe') --no-defaults --version
    Assert-True ($LASTEXITCODE -eq 0 -and ($databaseOutput -join '') -match '10\.11\.19-MariaDB') 'Installed MariaDB runtime failed'
    $checks.Add('installed-php-and-mariadb-versions')

    $shell = New-Object -ComObject WScript.Shell
    $desktopShortcut = Join-Path ([Environment]::GetFolderPath('Desktop')) 'Digital Lab - All Labs Transfer.lnk'
    $startMenu = Join-Path ([Environment]::GetFolderPath('Programs')) 'Digital Lab Offline'
    foreach ($shortcutPath in @($desktopShortcut, (Join-Path $startMenu 'Transfer all labs.lnk'))) {
        Assert-True ([IO.File]::Exists($shortcutPath)) 'Transfer shortcut missing'
        $shortcut = $shell.CreateShortcut($shortcutPath)
        Assert-True ($shortcut.TargetPath -like '*\powershell.exe') 'Shortcut does not start Windows PowerShell'
        Assert-True ($shortcut.Arguments.Contains((Join-Path $transfer 'Transfer-Wizard.ps1'))) 'Shortcut points to wrong wizard'
        Assert-True ($shortcut.Arguments.Contains($desktop)) 'Shortcut omitted installed desktop path'
    }
    $openShortcut = $shell.CreateShortcut((Join-Path $startMenu 'Open Digital Lab.lnk'))
    Assert-True ($openShortcut.TargetPath -eq (Join-Path $desktop 'Digital Lab Desktop.exe')) 'Desktop launch shortcut points elsewhere'
    $checks.Add('desktop-and-start-menu-shortcuts')

    # Render the installed wizard using only copied synthetic input, never its
    # packaged data/ path, as required by its dedicated UI-test mode.
    $uiFixture = Join-Path $root 'Synthetic UI Fixture'
    [void][IO.Directory]::CreateDirectory($uiFixture)
    Copy-Item -LiteralPath (Join-Path $transfer 'data\manifest.json') -Destination $uiFixture
    Copy-Item -LiteralPath (Join-Path $transfer 'data\online.sql.br') -Destination $uiFixture
    $powershell = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
    $previousSynthetic = $env:DLM_SYNTHETIC_TEST
    $previousInstallation = $env:DLM_TEST_INSTALLATION
    try {
        $env:DLM_SYNTHETIC_TEST = '1'
        $env:DLM_TEST_INSTALLATION = $desktop
        $arguments = '-NoLogo -NoProfile -NonInteractive -STA -ExecutionPolicy Bypass -File "' + (Join-Path $transfer 'Transfer-Wizard.ps1') + '" -UiSmokeTest -SmokeFixtureRoot "' + $uiFixture + '" -SmokeOutputPath "' + (Join-Path $OutputDirectory 'installed-wizard.png') + '"'
        Assert-True ((Run-Executable $powershell $arguments 90) -eq 0) 'Installed wizard could not initialize'
    } finally {
        $env:DLM_SYNTHETIC_TEST = $previousSynthetic
        $env:DLM_TEST_INSTALLATION = $previousInstallation
    }
    $checks.Add('installed-arabic-wizard-initialization')

    $desktopExe = Join-Path $desktop 'Digital Lab Desktop.exe'
    $desktopHash = File-Hash $desktopExe
    $bundleHash = File-Hash (Join-Path $bundle 'build.json')
    $env:DLM_TEST_CHILD_LOG = Join-Path $root 'must-not-invoke-reused-child.log'
    Assert-True ((Run-Executable $FailInstaller ('/S /D=' + $target)) -eq 0) 'Compatible installed desktop was not reused'
    Assert-True (-not [IO.File]::Exists($env:DLM_TEST_CHILD_LOG)) 'Reinstall unnecessarily invoked the child installer'
    Assert-True ((File-Hash $desktopExe) -eq $desktopHash) 'Reinstall changed existing desktop executable'
    Assert-Payload $transfer $hashes
    $checks.Add('compatible-desktop-reused-without-running-child')
    Write-Host 'PASS: compatible desktop is reused rather than reinstalled.'

    $previousNode = $env:ELECTRON_RUN_AS_NODE
    try {
        $env:ELECTRON_RUN_AS_NODE = '1'
        $runningProbe = Start-Process -FilePath $desktopExe -ArgumentList '-e "setInterval(()=>{},1000)"' -PassThru
    } finally { $env:ELECTRON_RUN_AS_NODE = $previousNode }
    Start-Sleep -Milliseconds 500
    Assert-True (-not $runningProbe.HasExited) 'Synthetic running-process probe did not start'
    $runningExit = Run-Executable $SuccessInstaller ('/S /D=' + $target)
    $runningProbe.Refresh()
    Assert-True (-not $runningProbe.HasExited) 'Installer killed a running desktop process'
    Assert-True ((File-Hash $desktopExe) -eq $desktopHash) 'Running-desktop handling changed the executable'
    $runningProbe.Kill(); [void]$runningProbe.WaitForExit(10000); $runningProbe.Dispose(); $runningProbe = $null
    $checks.Add('running-desktop-process-is-never-killed')
    Write-Host ('PASS: running desktop is preserved (installer exit ' + $runningExit + ').')

    Capture-InstallerWelcome $SuccessInstaller (Join-Path $root 'Unstarted UI Target') (Join-Path $OutputDirectory 'installer-welcome.png')
    $checks.Add('installer-welcome-screen')

    $unknownFile = Join-Path $transfer 'user-notes-preserve.txt'
    $export = Join-Path $target 'user-backup-preserve.dlbackup'
    [IO.File]::WriteAllText($unknownFile, 'Synthetic user notes, not an installer-managed file.')
    [IO.File]::WriteAllText($export, 'Synthetic backup placeholder to prove preservation.')
    $unknownHash = File-Hash $unknownFile
    $exportHash = File-Hash $export
    $uninstaller = Join-Path $target 'Uninstall-Data-Transfer.exe'
    Assert-True ([IO.File]::Exists($uninstaller)) 'Transfer uninstaller missing'
    Assert-True ((Run-Executable $uninstaller '/S') -eq 0) 'Transfer uninstall failed'
    $until = [DateTime]::UtcNow.AddSeconds(45)
    do {
        $remaining = @($hashes.files.PSObject.Properties | Where-Object { [IO.File]::Exists((Join-Path $transfer $_.Name)) })
        if ($null -eq (Get-Registry64 $uninstallKey) -and $remaining.Count -eq 0) { break }
        Start-Sleep -Milliseconds 250
    } while ([DateTime]::UtcNow -lt $until)
    Assert-True ($null -eq (Get-Registry64 $uninstallKey)) 'Transfer uninstall registration remains'
    Assert-True ($null -eq (Get-Registry64 $bundleKey)) 'Transfer bundle registration remains'
    foreach ($property in $hashes.files.PSObject.Properties) {
        Assert-True (-not [IO.File]::Exists((Join-Path $transfer $property.Name))) ('Managed payload file remains after uninstall: ' + $property.Name)
    }
    Assert-True ((File-Hash $desktopExe) -eq $desktopHash) 'Uninstall removed or changed Desktop'
    Assert-True ((File-Hash (Join-Path $bundle 'build.json')) -eq $bundleHash) 'Uninstall changed Desktop bundle'
    Assert-True ((File-Hash $liveSentinel) -eq $liveHash) 'Uninstall changed existing user database directory'
    Assert-True ((File-Hash $unknownFile) -eq $unknownHash) 'Uninstall deleted an unknown user file'
    Assert-True ((File-Hash $export) -eq $exportHash) 'Uninstall deleted an exported backup'
    Assert-True (-not [IO.File]::Exists($desktopShortcut)) 'Transfer desktop shortcut remains'
    Assert-True (@(Desktop-Registrations | Where-Object { $_.Version -eq '1.0.0' }).Count -ge 1) 'Transfer uninstall removed the Desktop uninstall entry'
    $checks.Add('uninstall-removes-only-managed-transfer-files')
    $checks.Add('desktop-live-data-exports-and-user-files-preserved')
    Write-Host 'PASS: uninstall preserves Desktop, live data, exports and user files.'

    Assert-True (([IO.File]::ReadAllText((Join-Path $target '.digitallab-offline-bundle'))).Trim() -eq 'DigitalLabOfflineBundleV1') 'Preserved content lost its bundle ownership marker'
    $env:DLM_TEST_CHILD_LOG = Join-Path $root 'must-not-invoke-after-uninstall.log'
    Assert-True ((Run-Executable $FailInstaller ('/S /D=' + $target)) -eq 0) 'Cannot reinstall transfer after preserving Desktop and user files'
    Assert-True (-not [IO.File]::Exists($env:DLM_TEST_CHILD_LOG)) 'Reinstall after uninstall unexpectedly ran the child installer'
    Assert-True ($null -ne (Get-Registry64 $uninstallKey)) 'Reinstall did not restore transfer uninstall registration'
    Assert-True ((File-Hash (Join-Path $transfer 'Transfer-Wizard.ps1')) -eq $hashes.files.'Transfer-Wizard.ps1') 'Reinstall did not restore the transfer wizard'
    Assert-True ((File-Hash $desktopExe) -eq $desktopHash) 'Reinstall after uninstall changed Desktop'
    Assert-True ((File-Hash $liveSentinel) -eq $liveHash -and (File-Hash $unknownFile) -eq $unknownHash -and (File-Hash $export) -eq $exportHash) 'Reinstall after uninstall changed preserved user content'
    Assert-True ((Run-Executable $uninstaller '/S') -eq 0) 'Second transfer uninstall failed'
    $until = [DateTime]::UtcNow.AddSeconds(45)
    do {
        $remaining = @($hashes.files.PSObject.Properties | Where-Object { [IO.File]::Exists((Join-Path $transfer $_.Name)) })
        if ($null -eq (Get-Registry64 $uninstallKey) -and $remaining.Count -eq 0) { break }
        Start-Sleep -Milliseconds 250
    } while ([DateTime]::UtcNow -lt $until)
    Assert-True ($null -eq (Get-Registry64 $uninstallKey) -and $remaining.Count -eq 0) 'Second uninstall did not complete'
    Assert-True ((File-Hash $desktopExe) -eq $desktopHash -and (File-Hash $liveSentinel) -eq $liveHash -and (File-Hash $unknownFile) -eq $unknownHash -and (File-Hash $export) -eq $exportHash) 'Second uninstall changed preserved user content'
    $checks.Add('reinstall-after-uninstall-reuses-preserved-desktop')
    Write-Host 'PASS: transfer can be reinstalled and removed again without changing preserved content.'

    [pscustomobject]@{
        passed = $true
        syntheticDataOnly = $true
        originalDesktopInstaller = $true
        installerHash = (File-Hash $SuccessInstaller)
        php = '8.4.26'
        mariadb = '10.11.19'
        checks = $checks.ToArray()
    } | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath (Join-Path $OutputDirectory 'installer-acceptance.json') -Encoding UTF8
} catch {
    ($_ | Out-String) | Set-Content -LiteralPath (Join-Path $OutputDirectory 'installer-failure.txt') -Encoding UTF8
    throw
} finally {
    $env:DLM_TEST_CHILD_LOG = $previousChildLog
    if ($null -ne $runningProbe) {
        try { if (-not $runningProbe.HasExited) { $runningProbe.Kill(); [void]$runningProbe.WaitForExit(10000) } } catch {}
        $runningProbe.Dispose()
    }
    # Leave the generated installation available to the disposable runner for
    # diagnostics. No task-wide /recursive cleanup is performed by this test.
}
