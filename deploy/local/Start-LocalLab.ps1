$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot '../..'))
if (-not (Get-Command docker -ErrorAction SilentlyContinue)) { throw 'Docker Desktop is not installed or PowerShell needs to be reopened.' }
& docker info --format '{{.OSType}}'
if ($LASTEXITCODE -ne 0) { throw 'Start Docker Desktop and wait until its engine is running.' }
& docker compose version
if ($LASTEXITCODE -ne 0) { throw 'Docker Compose is required.' }
function New-RandomBase64 {
    $bytes = New-Object byte[] 32
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($bytes) } finally { $rng.Dispose() }
    return [Convert]::ToBase64String($bytes)
}
# Never regenerate keys/passwords for an existing local database.
if (-not (Test-Path '.env.local')) {
    $content = "LOCAL_APP_KEY=base64:$(New-RandomBase64)`nLOCAL_DB_PASSWORD=$(New-RandomBase64)`nLOCAL_DB_ROOT_PASSWORD=$(New-RandomBase64)`n"
    $path = Join-Path (Get-Location) '.env.local'
    [IO.File]::WriteAllText($path, $content, (New-Object Text.UTF8Encoding $false))
    $acl = New-Object System.Security.AccessControl.FileSecurity
    $acl.SetAccessRuleProtection($true, $false)
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent().Name
    $rule = New-Object System.Security.AccessControl.FileSystemAccessRule($identity, 'FullControl', 'Allow')
    $acl.AddAccessRule($rule)
    Set-Acl -LiteralPath $path -AclObject $acl
}
& docker compose --env-file .env.local -f deploy/local/compose.yaml up -d --build --wait --wait-timeout 300
if ($LASTEXITCODE -ne 0) { throw 'Local startup failed. Review: docker compose --env-file .env.local -f deploy/local/compose.yaml logs --tail 60 app' }
Write-Host 'Local trial ready: http://localhost:8080' -ForegroundColor Green
Write-Host 'No cloud data was copied. Cloud synchronization is NOT implemented or enabled.' -ForegroundColor Yellow
Write-Host 'Create a LOCAL TEST administrator using:'
Write-Host 'docker compose --env-file .env.local -f deploy/local/compose.yaml exec app php artisan erp:install'
Start-Process 'http://localhost:8080'
