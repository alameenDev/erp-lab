<?php

// CLI-only deployment. Credentials and Laravel source remain outside public_html.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function stop(string $message): never { fwrite(STDERR, $message."\n"); exit(1); }
$root = dirname(__DIR__);
$backend = $root.'/backend';
$public = realpath($argv[1] ?? '') ?: '';
$url = rtrim($argv[2] ?? '', '/');
$dist = realpath($argv[3] ?? '') ?: '';
if (!$public || basename($public) !== 'public_html' || !is_writable($public)) {
    stop('The target must be an existing writable public_html directory.');
}
if (str_starts_with($backend.'/', $public.'/') || str_starts_with($public.'/', $root.'/')) {
    stop('Keep the application checkout separate from the web document root.');
}
if (!preg_match('#^https://[a-z0-9.-]+(?::[0-9]+)?$#i', $url)) { stop('Supply the HTTPS origin without a path.'); }
if (!is_file($dist.'/index.html') || !is_file($backend.'/vendor/autoload.php') || !is_file($backend.'/.env')) {
    stop('Missing frontend build, Composer dependencies or backend .env.');
}
if ((file_exists($public.'/storage') || is_link($public.'/storage')) &&
    realpath($public.'/storage') !== realpath($backend.'/storage/app/public')) {
    stop('Existing public storage has a different target. No files were changed.');
}
$backup = $root.'/.deployment-backups/'.date('Ymd-His').'-'.bin2hex(random_bytes(3));
mkdir($backup, 0700, true);
if (!copy($backend.'/.env', $backup.'/backend.env')) { stop('Could not back up configuration.'); }
chmod($backup.'/backend.env', 0600);
function publishFile(string $target, string $content): void {
    global $backup, $public;
    if (is_link($target)) { stop('Refusing to overwrite symlink: '.$target); }
    if (is_file($target)) {
        $saved = $backup.'/public/'.substr($target, strlen($public) + 1);
        if (!is_dir(dirname($saved))) { mkdir(dirname($saved), 0700, true); }
        if (!copy($target, $saved)) { stop('Backup failed: '.$target); }
    }
    if (!is_dir(dirname($target))) { mkdir(dirname($target), 0755, true); }
    $temp = $target.'.deploy-'.bin2hex(random_bytes(3));
    if (file_put_contents($temp, $content) === false) { stop('Write failed: '.$target); }
    chmod($temp, 0644);
    if (!rename($temp, $target)) { stop('Publish failed: '.$target); }
}
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dist, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->isLink()) { stop('Unexpected build entry.'); }
    $relative = substr($file->getPathname(), strlen($dist) + 1);
    if (preg_match('#(^|/)\.|\.(php[0-9]*|phtml|phar)$#i', $relative)) { stop('Unexpected executable or hidden build file.'); }
    // Switch the HTML entrypoint after its assets have been copied.
    if ($relative !== 'index.html') { publishFile($public.'/'.$relative, file_get_contents($file->getPathname())); }
}
$env = file_get_contents($backend.'/.env');
foreach (['APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_URL' => $url,
    'FRONTEND_URL' => $url, 'CORS_ALLOWED_ORIGINS' => $url] as $key => $value) {
    $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
    $env = preg_match($pattern, $env) ? preg_replace($pattern, $key.'='.$value, $env) : rtrim($env)."\n$key=$value\n";
}
if (file_put_contents($backend.'/.env', $env) === false) { stop('Could not update app URLs.'); }
chmod($backend.'/.env', 0600);
$entry = "<?php\nuse Illuminate\\Http\\Request;\ndefine('LARAVEL_START', microtime(true));\n";
$entry .= '$backend = '.var_export($backend, true).";\n";
$entry .= <<<'PHP'
if (file_exists($maintenance = $backend.'/storage/framework/maintenance.php')) { require $maintenance; }
require $backend.'/vendor/autoload.php';
$app = require $backend.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);
$app->handleRequest(Request::capture());
PHP;
publishFile($public.'/index.php', $entry);
publishFile($public.'/.htaccess', <<<'APACHE'
DirectoryIndex index.html
Options -Indexes -MultiViews
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^storage/.*\.(?:php[0-9]*|phtml|phar)(?:/|$) - [F,L,NC]
RewriteRule (^|/)\.(?!well-known/) - [F,L]
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteRule ^(?:api(?:/|$)|sanctum(?:/|$)|up/?$|result/[0-9]+/?$|invoice/[0-9]+/?$) index.php [L,QSA]
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]
RewriteRule ^ index.html [L]
</IfModule>
APACHE);
if (!is_link($public.'/storage') && !file_exists($public.'/storage')) {
    if (!symlink($backend.'/storage/app/public', $public.'/storage')) { stop('Could not create public storage link.'); }
}
publishFile($public.'/index.html', file_get_contents($dist.'/index.html'));
echo "Files published. Previous configuration/files saved privately in $backup\n";
