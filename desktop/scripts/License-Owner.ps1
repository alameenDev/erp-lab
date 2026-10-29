$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Windows.Forms
function Choose-File($title, $save = $false) {
    $dialog = if ($save) { New-Object System.Windows.Forms.SaveFileDialog } else { New-Object System.Windows.Forms.OpenFileDialog }
    $dialog.Title = $title
    if ($dialog.ShowDialog() -ne [System.Windows.Forms.DialogResult]::OK) { throw 'Cancelled' }
    return $dialog.FileName
}
Write-Host 'Digital Lab - Owner License Tool (keep on your own computer only)'
Write-Host '1. Create issuer keys (once)'
Write-Host '2. Issue annual license'
Write-Host '3. Issue lifetime license'
$choice = Read-Host 'Choose 1, 2 or 3'
if ($choice -notin @('1','2','3')) { throw 'Invalid choice' }
$secure = Read-Host 'Private key password (minimum 16 characters for new keys)' -AsSecureString
$ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
$previous = $env:DL_LICENSE_KEY_PASSWORD
try {
    $env:DL_LICENSE_KEY_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr)
    if ($choice -eq '1') {
        $private = Choose-File 'Save encrypted PRIVATE issuer key (.pem)' $true
        $public = Choose-File 'Save PUBLIC issuer key (.pem)' $true
        & node "$PSScriptRoot/license-admin.cjs" keygen --private $private --public $public
    } else {
        $private = Choose-File 'Select your encrypted PRIVATE issuer key'
        $request = Choose-File 'Select device activation request (.json)'
        $lab = Read-Host 'Laboratory name'
        $output = Choose-File 'Save license with .dllicense extension' $true
        $type = if ($choice -eq '2') { 'annual' } else { 'lifetime' }
        $extra = @()
        $data = Get-Content -Raw $request | ConvertFrom-Json
        if ($data.binding -eq 'software') {
            if ((Read-Host 'This device requests WEAKER SOFTWARE binding. Type YES to approve') -cne 'YES') { throw 'Not approved' }
            $extra = @('--allow-software','yes')
        }
        & node "$PSScriptRoot/license-admin.cjs" issue --private $private --request $request --lab $lab --type $type --output $output @extra
    }
    if ($LASTEXITCODE -ne 0) { throw 'License operation failed' }
} finally {
    $env:DL_LICENSE_KEY_PASSWORD = $previous
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr)
}
