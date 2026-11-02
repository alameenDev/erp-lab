# The bootstrap never opens, replaces, or deletes the Desktop live database.
[CmdletBinding()]
param(
    [ValidateSet('Install', 'CheckUninstall')][string]$Mode = 'Install',
    [Parameter(Mandatory = $true)][string]$InstallRoot,
    [string]$DesktopSetup,
    [string]$ExpectedSetupHash,
    [Parameter(Mandatory = $true)][string]$ResultFile
)
$ErrorActionPreference = 'Stop'
$script:ResultPath = $null
$script:DesktopDir = ''
$script:Reused = 0
$script:Codes = @{
    UNSUPPORTED_WINDOWS = 40; INVALID_PATH = 41; ROOT_NOT_OWNED = 42
    INCOMPATIBLE_DESKTOP = 43; MULTIPLE_DESKTOP_INSTALLATIONS = 44
    DESKTOP_RUNNING = 45; TRANSFER_RUNNING = 46; INSTALLER_HASH_MISMATCH = 47
    CHILD_INSTALL_FAILED = 48; DESKTOP_VALIDATION_FAILED = 49
    FILESYSTEM_FAILED = 50; PROCESS_CHECK_FAILED = 51
    DESKTOP_FOLDER_CONFLICT = 52; NON_ASCII_PATH = 53
}
function Stop-Bootstrap([string]$Code) { throw $Code }
function Test-Within([string]$Path, [string]$Parent) {
    return $Path.Equals($Parent, [StringComparison]::OrdinalIgnoreCase) -or
        $Path.StartsWith($Parent.TrimEnd('\') + '\', [StringComparison]::OrdinalIgnoreCase)
}
function Resolve-LocalPath([string]$Path) {
    if ([string]::IsNullOrWhiteSpace($Path)) { Stop-Bootstrap 'INVALID_PATH' }
    $value = $Path.Replace('/', '\')
    if ($value -notmatch '^[A-Za-z]:\\' -or $value.Substring(2) -match '[:<>"|?*\x00-\x1F]') {
        Stop-Bootstrap 'INVALID_PATH'
    }
    foreach ($part in $value.Substring(3).Split('\')) {
        if ($part.EndsWith('.') -or $part.EndsWith(' ')) { Stop-Bootstrap 'INVALID_PATH' }
    }
    $full = [IO.Path]::GetFullPath($value).TrimEnd('\')
    if ($full.Length -eq 2) { $full += '\' }
    $drive = New-Object IO.DriveInfo ([IO.Path]::GetPathRoot($full))
    $device = New-Object Text.StringBuilder 32768
    if ($drive.DriveType -notin @([IO.DriveType]::Fixed, [IO.DriveType]::Removable) -or
        [DigitalLabBootstrapNative]::QueryDosDevice($full.Substring(0, 2), $device, $device.Capacity) -eq 0 -or
        $device.ToString().StartsWith('\??\')) { Stop-Bootstrap 'INVALID_PATH' }
    $ancestor = $full
    $missing = New-Object 'Collections.Generic.List[string]'
    while (-not [IO.Directory]::Exists($ancestor) -and -not [IO.File]::Exists($ancestor)) {
        $missing.Insert(0, [IO.Path]::GetFileName($ancestor))
        $ancestor = [IO.Path]::GetDirectoryName($ancestor)
        if ([string]::IsNullOrEmpty($ancestor)) { Stop-Bootstrap 'INVALID_PATH' }
    }
    $cursor = $ancestor
    while ($cursor) {
        if (([IO.File]::GetAttributes($cursor) -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
            Stop-Bootstrap 'INVALID_PATH'
        }
        $cursor = [IO.Path]::GetDirectoryName($cursor)
    }
    $buffer = New-Object Text.StringBuilder 32768
    $length = [DigitalLabBootstrapNative]::GetLongPathName($ancestor, $buffer, $buffer.Capacity)
    if ($length -eq 0 -or $length -ge $buffer.Capacity) { Stop-Bootstrap 'INVALID_PATH' }
    $full = $buffer.ToString()
    foreach ($part in $missing) { $full = [IO.Path]::Combine($full, $part) }
    return $full.TrimEnd('\') + $(if ($full.TrimEnd('\').Length -eq 2) { '\' } else { '' })
}
function Assert-ManagedTree([string]$Path) {
    $null = Resolve-LocalPath $Path
    if (-not [IO.Directory]::Exists($Path)) { return }
    $queue = New-Object 'Collections.Generic.Queue[string]'
    $queue.Enqueue($Path)
    while ($queue.Count) {
        foreach ($item in Get-ChildItem -LiteralPath $queue.Dequeue() -Force) {
            if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
                Stop-Bootstrap 'INVALID_PATH'
            }
            if ($item.PSIsContainer) { $queue.Enqueue($item.FullName) }
        }
    }
}
function Write-Result([string]$Code) {
    if ($script:ResultPath) {
        $body = "[Result]`r`nCode=$Code`r`nDesktopDir=$script:DesktopDir`r`nReused=$script:Reused`r`n"
        [IO.File]::WriteAllText($script:ResultPath, $body, [Text.Encoding]::Unicode)
    }
}
function Test-Desktop([string]$Path) {
    $required = @('Digital Lab Desktop.exe', 'resources\app.asar', 'resources\bundle\build.json',
        'resources\bundle\php\php.exe', 'resources\bundle\php\php-cgi.exe',
        'resources\bundle\mariadb\bin\mariadbd.exe', 'resources\bundle\mariadb\bin\mariadb.exe',
        'resources\bundle\desktop-php\bootstrap.php', 'resources\bundle\desktop-php\status.php',
        'resources\bundle\desktop-php\console.php', 'resources\bundle\backend\vendor\autoload.php')
    foreach ($relative in $required) {
        $file = Join-Path $Path $relative
        if (-not [IO.File]::Exists($file)) { return $false }
        $null = Resolve-LocalPath $file
    }
    try { $build = [IO.File]::ReadAllText((Join-Path $Path 'resources\bundle\build.json')) | ConvertFrom-Json }
    catch { return $false }
    return $build.version -eq '1.0.0' -and $build.php -eq '8.4.26' -and
        $build.mariadb -eq '10.11.19' -and $build.origin -eq 'http://127.0.0.1:17892'
}
function Get-DesktopCandidates([string]$Target) {
    $paths = New-Object 'Collections.Generic.HashSet[string]' ([StringComparer]::OrdinalIgnoreCase)
    foreach ($hive in @([Microsoft.Win32.RegistryHive]::CurrentUser, [Microsoft.Win32.RegistryHive]::LocalMachine)) {
        foreach ($view in @([Microsoft.Win32.RegistryView]::Registry64, [Microsoft.Win32.RegistryView]::Registry32)) {
            $base = [Microsoft.Win32.RegistryKey]::OpenBaseKey($hive, $view)
            $uninstall = $null
            try {
                $uninstall = $base.OpenSubKey('Software\Microsoft\Windows\CurrentVersion\Uninstall')
                if (-not $uninstall) { continue }
                foreach ($name in $uninstall.GetSubKeyNames()) {
                    $key = $uninstall.OpenSubKey($name)
                    if (-not $key) { continue }
                    try {
                        if ([string]$key.GetValue('DisplayName') -notlike 'Digital Lab Desktop*') { continue }
                        $location = [string]$key.GetValue('InstallLocation')
                        if (-not $location) {
                            $icon = [string]$key.GetValue('DisplayIcon')
                            if ($icon -match '^\s*"?(.+?\.exe)"?(?:,\s*-?\d+)?\s*$') {
                                $location = [IO.Path]::GetDirectoryName($Matches[1])
                            }
                        }
                        if ($location -and [IO.Directory]::Exists($location)) {
                            $null = $paths.Add((Resolve-LocalPath $location))
                        }
                    } finally { $key.Dispose() }
                }
            } finally { if ($uninstall) { $uninstall.Dispose() }; $base.Dispose() }
        }
    }
    foreach ($location in @($Target, (Join-Path $env:LOCALAPPDATA 'Programs\Digital Lab Desktop'),
        (Join-Path $env:LOCALAPPDATA 'Programs\digital-lab-desktop'),
        (Join-Path $env:LOCALAPPDATA 'Programs\DigitalLabDesktop'))) {
        if ([IO.Directory]::Exists($location)) { $null = $paths.Add((Resolve-LocalPath $location)) }
    }
    return @($paths)
}
function Get-SafeProcesses {
    try { return @(Get-CimInstance -ClassName Win32_Process -ErrorAction Stop) }
    catch { Stop-Bootstrap 'PROCESS_CHECK_FAILED' }
}
function Assert-TransferClosed($Processes, [string]$Root, [string]$OriginalRoot) {
    $transfer = Join-Path $Root 'Transfer'
    $original = Join-Path $OriginalRoot 'Transfer'
    foreach ($process in $Processes) {
        if ($process.ProcessId -eq $PID) { continue }
        $command = [string]$process.CommandLine
        $exe = [string]$process.ExecutablePath
        if (($exe -and (Test-Within $exe $transfer)) -or
            ($command -match '(?i)(Transfer-Wizard\.ps1|migrate\.cjs)' -and
            ($command.IndexOf($transfer, [StringComparison]::OrdinalIgnoreCase) -ge 0 -or
             $command.IndexOf($original, [StringComparison]::OrdinalIgnoreCase) -ge 0))) {
            Stop-Bootstrap 'TRANSFER_RUNNING'
        }
    }
}
try {
    $architecture = if ($env:PROCESSOR_ARCHITEW6432) { $env:PROCESSOR_ARCHITEW6432 } else { $env:PROCESSOR_ARCHITECTURE }
    if ($env:OS -ne 'Windows_NT' -or -not [Environment]::Is64BitOperatingSystem -or
        $architecture -ne 'AMD64' -or [Environment]::OSVersion.Version.Major -lt 10) {
        Stop-Bootstrap 'UNSUPPORTED_WINDOWS'
    }
    Add-Type -TypeDefinition @'
using System;
using System.Text;
using System.Runtime.InteropServices;
public static class DigitalLabBootstrapNative {
    [DllImport("kernel32.dll", CharSet = CharSet.Unicode, SetLastError = true)]
    public static extern uint GetLongPathName(string path, StringBuilder buffer, int size);
    [DllImport("kernel32.dll", CharSet = CharSet.Unicode, SetLastError = true)]
    public static extern uint QueryDosDevice(string name, StringBuilder buffer, int size);
}
'@
    $result = Resolve-LocalPath $ResultFile
    $temporary = Resolve-LocalPath ([IO.Path]::GetTempPath())
    if (-not (Test-Within $result $temporary) -or -not [IO.Directory]::Exists([IO.Path]::GetDirectoryName($result)) -or
        [IO.Path]::GetExtension($result) -ne '.ini' -or [IO.Directory]::Exists($result)) { Stop-Bootstrap 'INVALID_PATH' }
    $script:ResultPath = $result
    $originalRoot = $InstallRoot
    $root = Resolve-LocalPath $InstallRoot
    if ($root -match '[^\x20-\x7E]') { Stop-Bootstrap 'NON_ASCII_PATH' }
    if ($root.Contains("'") -or [IO.File]::Exists($root)) { Stop-Bootstrap 'INVALID_PATH' }
    $liveRoot = Join-Path $env:APPDATA 'DigitalLabDesktopV1'
    foreach ($protected in @($env:WINDIR, [Environment]::SystemDirectory, $env:ProgramData,
        $env:ProgramFiles, ${env:ProgramFiles(x86)}, $liveRoot)) {
        if (-not $protected) { continue }
        $protected = Resolve-LocalPath $protected
        if ((Test-Within $root $protected) -or (Test-Within $protected $root)) { Stop-Bootstrap 'INVALID_PATH' }
    }
    $marker = Join-Path $root '.digitallab-offline-bundle'
    $null = Resolve-LocalPath $marker
    $owned = [IO.File]::Exists($marker) -and [IO.File]::ReadAllText($marker).TrimEnd("`r", "`n") -eq 'DigitalLabOfflineBundleV1'
    if (-not $owned -and (($Mode -eq 'CheckUninstall') -or
        ([IO.Directory]::Exists($root) -and @(Get-ChildItem -LiteralPath $root -Force).Count -gt 0))) {
        Stop-Bootstrap 'ROOT_NOT_OWNED'
    }
    foreach ($managed in @('Transfer', 'InstallerSupport', 'DigitalLab.ico', 'Uninstall-Data-Transfer.exe', '.desktop-install-pending')) {
        Assert-ManagedTree (Join-Path $root $managed)
    }
    $processes = Get-SafeProcesses
    Assert-TransferClosed $processes $root $originalRoot
    if ($Mode -eq 'CheckUninstall') { Write-Result 'READY_TO_REMOVE'; exit 0 }
    if ([string]::IsNullOrWhiteSpace($DesktopSetup) -or $ExpectedSetupHash -notmatch '^[0-9A-Fa-f]{64}$') {
        Stop-Bootstrap 'INSTALLER_HASH_MISMATCH'
    }
    $target = Resolve-LocalPath (Join-Path $root 'Desktop')
    $pending = Join-Path $root '.desktop-install-pending'
    $pendingValue = $target + "`r`n" + $ExpectedSetupHash.ToLowerInvariant()
    $retry = $owned -and [IO.File]::Exists($pending) -and [IO.File]::ReadAllText($pending) -eq $pendingValue
    $compatible = @()
    foreach ($candidate in @(Get-DesktopCandidates $target)) {
        if (Test-Desktop $candidate) { $compatible += $candidate; continue }
        if ($candidate -eq $target -and $retry) {
            $buildFile = Join-Path $target 'resources\bundle\build.json'
            $null = Resolve-LocalPath $buildFile
            $partialBuild = $null
            try { if ([IO.File]::Exists($buildFile)) { $partialBuild = [IO.File]::ReadAllText($buildFile) | ConvertFrom-Json } } catch { }
            if ($partialBuild -and ($partialBuild.version -ne '1.0.0' -or $partialBuild.php -ne '8.4.26' -or
                $partialBuild.mariadb -ne '10.11.19' -or $partialBuild.origin -ne 'http://127.0.0.1:17892')) {
                Stop-Bootstrap 'INCOMPATIBLE_DESKTOP'
            }
            continue
        }
        if ($candidate -eq $target -and @(Get-ChildItem -LiteralPath $target -Force).Count -eq 0) { continue }
        Stop-Bootstrap 'INCOMPATIBLE_DESKTOP'
    }
    if ($compatible.Count -gt 1) { Stop-Bootstrap 'MULTIPLE_DESKTOP_INSTALLATIONS' }
    if ($compatible.Count -eq 1) {
        $script:DesktopDir = $compatible[0]
        if ($script:DesktopDir -match '[^\x20-\x7E]') { Stop-Bootstrap 'NON_ASCII_PATH' }
        $script:Reused = 1
    } else {
        if ([IO.File]::Exists($target) -or ([IO.Directory]::Exists($target) -and
            @(Get-ChildItem -LiteralPath $target -Force).Count -gt 0 -and -not $retry)) { Stop-Bootstrap 'DESKTOP_FOLDER_CONFLICT' }
        if ($retry) { Assert-ManagedTree $target }
        foreach ($process in $processes) {
            $exe = [string]$process.ExecutablePath
            if ($process.Name -ieq 'Digital Lab Desktop.exe' -or ($exe -and (Test-Within $exe $target)) -or
                ($exe -match '(?i)\\resources\\bundle\\(?:php\\php-cgi\.exe|mariadb\\bin\\(?:mariadbd|mysqld)\.exe)$')) {
                Stop-Bootstrap 'DESKTOP_RUNNING'
            }
        }
        $setup = Resolve-LocalPath $DesktopSetup
        if (-not [IO.File]::Exists($setup) -or (Get-FileHash -LiteralPath $setup -Algorithm SHA256).Hash -ine $ExpectedSetupHash) {
            Stop-Bootstrap 'INSTALLER_HASH_MISMATCH'
        }
        $null = [IO.Directory]::CreateDirectory($root)
        if (-not $owned) { [IO.File]::WriteAllText($marker, 'DigitalLabOfflineBundleV1', [Text.Encoding]::ASCII) }
        [IO.File]::WriteAllText($pending, $pendingValue, [Text.Encoding]::UTF8)
        # NSIS requires /D last and WITHOUT quotes, even when its value contains spaces.
        try { $child = Start-Process -FilePath $setup -ArgumentList ('/S /currentuser /D=' + $target) -Wait -PassThru }
        catch { Stop-Bootstrap 'CHILD_INSTALL_FAILED' }
        if ($child.ExitCode -ne 0) { Stop-Bootstrap 'CHILD_INSTALL_FAILED' }
        if (-not (Test-Desktop $target)) { Stop-Bootstrap 'DESKTOP_VALIDATION_FAILED' }
        $script:DesktopDir = $target
    }
    $null = [IO.Directory]::CreateDirectory($root)
    if (-not $owned) { [IO.File]::WriteAllText($marker, 'DigitalLabOfflineBundleV1', [Text.Encoding]::ASCII) }
    if ([IO.File]::Exists($pending)) { [IO.File]::Delete($pending) }
    Write-Result 'OK'
    exit 0
} catch {
    $code = [string]$_.Exception.Message
    if (-not $script:Codes.ContainsKey($code)) { $code = 'FILESYSTEM_FAILED' }
    try { Write-Result $code } catch { }
    exit $script:Codes[$code]
}
