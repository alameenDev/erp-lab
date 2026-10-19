$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$bundle = Join-Path $root 'bundle'
$cache = Join-Path $root '.downloads'
New-Item -ItemType Directory -Force $bundle,$cache | Out-Null
function Get-VerifiedZip($url, $sha, $name) {
  $file = Join-Path $cache $name
  if (!(Test-Path $file)) { Invoke-WebRequest $url -OutFile $file }
  if ((Get-FileHash $file -Algorithm SHA256).Hash.ToLower() -ne $sha) { throw "Checksum mismatch: $name" }
  return $file
}
$php = Get-VerifiedZip 'https://downloads.php.net/~windows/releases/php-8.4.26-nts-Win32-vs17-x64.zip' 'da68394f9193b7f6b89d0c76861a4034ae10efee7fd55a7255d8118c2acf70d7' 'php.zip'
$maria = Get-VerifiedZip 'https://archive.mariadb.org/mariadb-10.11.19/winx64-packages/mariadb-10.11.19-winx64.zip' '398ea30e5036010bbebe01d2b1804280424dcc2626e36d8e95155c04d25a0490' 'mariadb.zip'
Expand-Archive $php -DestinationPath "$bundle/php" -Force
Expand-Archive $maria -DestinationPath "$cache/mariadb" -Force
$mariaRoot = Get-ChildItem "$cache/mariadb" -Directory | Select-Object -First 1
Copy-Item $mariaRoot.FullName "$bundle/mariadb" -Recurse -Force
# App-local Microsoft runtime DLLs: no system service, machine-wide install,
# or administrator rights are required. Verify publisher before packaging.
$runtimeManifest = @()
foreach ($name in @('vcruntime140.dll','vcruntime140_1.dll','msvcp140.dll','concrt140.dll')) {
  $dll = Join-Path $env:SystemRoot "System32/$name"
  if (!(Test-Path $dll)) { throw "Required Microsoft runtime missing: $name" }
  $signature = Get-AuthenticodeSignature $dll
  if ($signature.Status -ne 'Valid' -or $signature.SignerCertificate.Subject -notmatch 'Microsoft') { throw "Untrusted runtime DLL: $name" }
  Copy-Item $dll "$bundle/php/$name"
  Copy-Item $dll "$bundle/mariadb/bin/$name"
  $runtimeManifest += @{name=$name; sha256=(Get-FileHash $dll -Algorithm SHA256).Hash; publisher=$signature.SignerCertificate.Subject}
}
$runtimeManifest | ConvertTo-Json | Set-Content "$bundle/runtime-provenance.json"
$extensions = @('curl','fileinfo','gd','intl','mbstring','exif','mysqli','openssl','pdo_mysql','sodium','zip')
$ini = @('extension_dir=ext','date.timezone=Asia/Baghdad','memory_limit=512M','max_execution_time=120','upload_max_filesize=64M','post_max_size=128M','max_file_uploads=30','max_input_vars=10000','display_errors=Off','log_errors=On','expose_php=Off','cgi.force_redirect=1','cgi.fix_pathinfo=0','allow_url_fopen=Off','variables_order=EGPCS')
foreach ($extension in $extensions) { $ini += "extension=$extension" }
$ini -join "`n" | Set-Content "$bundle/php/php.ini" -Encoding utf8NoBOM
& "$bundle/php/php.exe" -c "$bundle/php/php.ini" -d "extension_dir=$bundle/php/ext" -v
if ($LASTEXITCODE -ne 0) { throw 'Bundled PHP failed' }
& "$bundle/mariadb/bin/mariadbd.exe" --no-defaults --version
if ($LASTEXITCODE -ne 0) { throw 'Bundled MariaDB failed' }
