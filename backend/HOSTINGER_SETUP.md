# Hostinger / MariaDB installation

Use a dedicated NEW empty MariaDB database from hPanel. Existing PostgreSQL business data is not migrated by this installer. PHP 8.4+, Composer 2 and pdo_mysql are required; retain utf8mb4_unicode_ci for Arabic and case-insensitive search. Supabase is not required for this deployment.

## Update the existing checkout

Run each command separately inside SSH:

```sh
cd /home/u859215520/erp-lab
git pull --ff-only origin main
cd backend
/opt/alt/php84/usr/bin/php /usr/local/bin/composer2 install --no-dev --optimize-autoloader
```

## Configure the database

Create the database AND its user in hPanel. Use its exact hostname and full prefixed database/user names. Passwords stay on the server.

For a fresh checkout without .env:

```sh
cp -n hostinger.env.example .env
chmod 600 .env
nano .env
```

If .env already exists (including from the Supabase setup), edit it instead of replacing it. Preserve APP_KEY and other application settings. Set:

```dotenv
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=YOUR_FULL_HOSTINGER_DATABASE_NAME
DB_USERNAME=YOUR_FULL_HOSTINGER_DATABASE_USER
DB_PASSWORD="YOUR_DATABASE_PASSWORD"
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
```

Replace localhost if hPanel supplies another host. Remove any old DB_URL because it overrides individual connection values. DB_SCHEMA/DB_SSLMODE apply only to PostgreSQL and may be removed for clarity. Set APP_URL to the backend HTTPS URL and FRONTEND_URL/CORS_ALLOWED_ORIGINS to the frontend HTTPS origin.

## Initialize

```sh
/opt/alt/php84/usr/bin/php artisan config:clear
```

Only if APP_KEY is empty in this NEW installation:

```sh
/opt/alt/php84/usr/bin/php artisan key:generate
```

Then:

```sh
/opt/alt/php84/usr/bin/php artisan erp:install
/opt/alt/php84/usr/bin/php artisan migrate:status
```

The installer shows the selected database, applies pending migrations, initializes reference data and permissions, and asks privately for a new administrator password. It adds no demo patients, invoices or contracts and does not replace an existing administrator. The default demo DatabaseSeeder is blocked in production. A system administrator manages labs; create real lab accounts and catalog data for operations after login.

## Web serving and frontend

Keep the checkout outside public_html. Configure the backend website to serve only backend/public; when Hostinger fixes the webroot to public_html, its public entrypoint and assets need deployment with paths adjusted to the private backend. Do not copy .env or the whole backend to public_html. This step must be adapted to the chosen backend domain and existing files, and is not automated by erp:install.

Set VITE_BASE_URL in the frontend build environment to the backend /api URL and VITE_ImageURL to its storage URL, then build frontend/ with npm ci and npm run build. Publish dist with SPA fallback. Give Laravel write access to storage and bootstrap/cache without world-writable permissions. Configure the public storage link for the actual document root.

## Acceptance and backups

Before real use: verify login, lab permissions, patient creation, invoice totals and payments, result entry, printing and uploads. The GitHub Actions MariaDB suite validates migration compatibility and targeted regressions; it is not full application UAT. Verify live server connectivity separately.

Before upgrades, export the database in phpMyAdmin and download uploaded files. Keep an independent protected backup outside the hosting account and test restoration to a separate database. Do not run migrate:fresh or demo seeders on business data. Changing a default env template does not change an already deployed database.
