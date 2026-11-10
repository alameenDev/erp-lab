<?php
// Run separately from the live deployment. Never migrate the live database here.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function demoStop(string $message): never { fwrite(STDERR, $message."\n"); exit(1); }
$root = dirname(__DIR__); $source = $root.'/backend';
$private = realpath($argv[1] ?? '') ?: ''; $configFile = realpath($argv[2] ?? '') ?: '';
$public = realpath($argv[3] ?? '') ?: ''; $origin = rtrim($argv[4] ?? '', '/');
$dist = realpath($argv[5] ?? $root.'/frontend/dist-demo') ?: '';
if (!$private || !$public || basename($public) !== 'public_html' || !$configFile || !is_file($dist.'/index.html')) demoStop('Usage: php scripts/publish-learning-demo.php PRIVATE_EMPTY_DIRECTORY DEMO_ENV PUBLIC_HTML HTTPS_ORIGIN [DIST_DEMO]');
if (!preg_match('#^https://[a-z0-9.-]+(?::[0-9]+)?$#i', $origin)) demoStop('An HTTPS origin is required.');
if ($private === $source || str_starts_with($private.'/', $public.'/') || str_starts_with($source.'/', $private.'/') || str_starts_with($configFile, $public.'/')) demoStop('Demo runtime/config must be private and separate from the live source.');
if (!is_file($source.'/vendor/autoload.php') || !is_file($source.'/.env')) demoStop('Install Composer dependencies in the source checkout first.');
require $source.'/vendor/autoload.php';
$live = Dotenv\Dotenv::parse(file_get_contents($source.'/.env'));
$input = Dotenv\Dotenv::parse(file_get_contents($configFile));
foreach (['DB_HOST','DB_DATABASE','DB_USERNAME','DB_PASSWORD','APP_KEY'] as $key) if (empty($input[$key])) demoStop('Missing demo configuration: '.$key);
if (empty($live['DB_DATABASE']) || empty($live['DB_USERNAME']) || !empty($input['DB_URL']) || !empty($live['DB_URL'])) demoStop('Use explicit DB fields; DB_URL is unsupported for this isolated deployment.');
if ($input['DB_DATABASE'] === $live['DB_DATABASE'] || $input['DB_USERNAME'] === $live['DB_USERNAME'] || $input['APP_KEY'] === ($live['APP_KEY'] ?? '')) demoStop('Demo requires a DIFFERENT database, database user, and APP_KEY. No changes made.');
if (count(array_diff(scandir($private), ['.','..'])) && !is_file($private.'/.learning-demo-runtime')) demoStop('Private target must be empty or a previous demo runtime.');
// Check the actual connection and grant isolation before copying or migrating anything.
try {
    $pdo = new PDO('mysql:host='.$input['DB_HOST'].';port='.($input['DB_PORT'] ?? '3306').';dbname='.$input['DB_DATABASE'], $input['DB_USERNAME'], $input['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    try { $pdo->query('SELECT 1 FROM `'.str_replace('`','``',$live['DB_DATABASE']).'`.users LIMIT 1'); demoStop('Demo database user can read the live users table. Remove its live privileges first.'); }
    catch (PDOException $e) { if (!in_array((int)($e->errorInfo[1] ?? 0), [1044,1142], true)) demoStop('Could not verify denial of access to the live database.'); }
} catch (PDOException $e) { demoStop('Could not connect to the dedicated demo database. Check private configuration.'); }
function demoCopy(string $from, string $to, array $skip = []): void {
    if (!is_dir($to)) mkdir($to, 0750, true);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS)) as $file) {
        $rel = substr($file->getPathname(), strlen($from)+1);
        if ($file->isLink() || preg_match('#(^|/)\.env(?:\.|$)#', $rel)) continue;
        foreach ($skip as $prefix) if (str_starts_with($rel, $prefix)) continue 2;
        $target = $to.'/'.$rel;
        if (is_link($target)) demoStop('Refusing a symlink target.');
        if (!is_dir(dirname($target))) mkdir(dirname($target), 0750, true);
        if (!copy($file->getPathname(), $target)) demoStop('Copy failed: '.$rel);
    }
}
$target = $public.'/demo';
if (file_exists($target) && !is_file($target.'/.learning-demo-public')) demoStop('Existing /demo is not managed by this installer.');
// Mark maintenance before updating an existing isolated backend.
if (is_dir($target)) file_put_contents($target.'/.maintenance', 'updating');
demoCopy($source, $private, ['storage/', 'bootstrap/cache/', 'tests/', '.git']);
foreach (['bootstrap/cache','storage/app/public','storage/app/private','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs'] as $dir) if (!is_dir($private.'/'.$dir)) mkdir($private.'/'.$dir, 0750, true);
foreach (glob($private.'/bootstrap/cache/*.php') as $cache) unlink($cache);
$config = [
    'APP_NAME'=>'Digital Lab Demo', 'APP_ENV'=>'production', 'APP_DEBUG'=>'false', 'APP_KEY'=>$input['APP_KEY'],
    'APP_URL'=>$origin.'/demo', 'FRONTEND_URL'=>$origin.'/demo', 'CORS_ALLOWED_ORIGINS'=>$origin,
    'DB_CONNECTION'=>'mysql', 'DB_HOST'=>$input['DB_HOST'], 'DB_PORT'=>$input['DB_PORT'] ?? '3306',
    'DB_DATABASE'=>$input['DB_DATABASE'], 'DB_USERNAME'=>$input['DB_USERNAME'], 'DB_PASSWORD'=>$input['DB_PASSWORD'],
    'DEMO_MODE'=>'true', 'DEMO_DATABASE'=>$input['DB_DATABASE'], 'DEMO_LIVE_DATABASE'=>$live['DB_DATABASE'], 'DEMO_LIVE_USERNAME'=>$live['DB_USERNAME'],
    'CACHE_STORE'=>'file', 'SESSION_DRIVER'=>'file', 'SESSION_COOKIE'=>'digital_lab_demo_session', 'SESSION_PATH'=>'/demo',
    'MAIL_MAILER'=>'log', 'QUEUE_CONNECTION'=>'sync', 'BROADCAST_CONNECTION'=>'null', 'FILESYSTEM_DISK'=>'local',
];
$env = ''; foreach ($config as $key=>$value) $env .= $key.'="'.str_replace(['\\','"','$'], ['\\\\','\\"','\\$'], $value).'"'."\n";
file_put_contents($private.'/.env', $env); chmod($private.'/.env', 0600);
file_put_contents($private.'/.learning-demo-runtime', 'isolated demo');
foreach (['demo:install'] as $command) {
    passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg($private.'/artisan').' '.$command, $code);
    if ($code) demoStop('Demo setup failed; the live application was not changed.');
}
demoCopy($dist, $target);
file_put_contents($target.'/.learning-demo-public', 'isolated demo');
$entry = "<?php\nif (is_file(__DIR__.'/.maintenance')) { http_response_code(503); exit; }\nuse Illuminate\\Http\\Request;\ndefine('LARAVEL_START', microtime(true));\n";
$entry .= '$backend = '.var_export($private, true).";\nrequire \$backend.'/vendor/autoload.php';\n\$app = require \$backend.'/bootstrap/app.php';\n\$app->usePublicPath(__DIR__);\ntry { \$app->make(\\Illuminate\\Contracts\\Http\\Kernel::class)->bootstrap(); \\App\\Demo\\DemoIsolation::assertConfigured(); } catch (\\Throwable \$e) { http_response_code(503); header('Content-Type: application/json'); echo json_encode(['message' => 'Demo environment unavailable']); exit; }\n\$app->handleRequest(Request::capture());\n";
file_put_contents($target.'/index.php', $entry);
file_put_contents($target.'/.htaccess', <<<'APACHE'
DirectoryIndex index.html
Options -Indexes -MultiViews
<IfModule mod_headers.c>
Header always set X-Robots-Tag "noindex, nofollow"
Header always set Referrer-Policy "same-origin"
Header always set Content-Security-Policy "connect-src 'self' blob: data:; form-action 'self'; object-src 'none'; base-uri 'self'"
</IfModule>
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule (^|/)\. - [F,L]
RewriteRule ^storage/.*\.(?:php[0-9]*|phtml|phar)(?:/|$) - [F,L,NC]
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteRule ^(?:api(?:/|$)|sanctum(?:/|$)) index.php [L,QSA]
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]
RewriteRule ^ index.html [L]
</IfModule>
APACHE);
if (!file_exists($target.'/storage') && !is_link($target.'/storage')) symlink($private.'/storage/app/public', $target.'/storage');
// Delegate /demo before the live SPA catch-all. Save the old root rule privately.
$rootHtaccess = $public.'/.htaccess';
$rules = file_get_contents($rootHtaccess);
if (!str_contains($rules, '^demo(?:/|$)')) {
    if (!str_contains($rules, 'RewriteEngine On')) demoStop('No root rewrite engine found. Add the documented /demo delegation rule.');
    copy($rootHtaccess, $private.'/root-htaccess-before-demo-'.date('YmdHis'));
    $rules = str_replace('RewriteEngine On', "RewriteEngine On\nRewriteCond %{DOCUMENT_ROOT}/demo/.htaccess -f\nRewriteRule ^demo(?:/|$) - [L]", $rules);
    file_put_contents($rootHtaccess, $rules);
}
if (is_file($target.'/.maintenance')) unlink($target.'/.maintenance');
echo "Published isolated educational demo at $origin/demo/\n";
