# Supabase setup for ERP Lab

Project: `yjpjyfcvnebrukicwcau` (Frankfurt).
Dashboard: https://supabase.com/dashboard/project/yjpjyfcvnebrukicwcau

The private `laravel` schema has been created. Application tables have NOT yet been migrated. Keep this schema out of the Supabase Data API exposed schemas. Laravel owns authentication and authorization; this setup does not migrate users to Supabase Auth or files to Storage.

## Server requirements

PHP 8.4+, Composer, PDO PostgreSQL (`pdo_pgsql`), the extensions required by composer.lock, and outbound PostgreSQL connectivity. Confirm these before deploying on Hostinger. The web document root must be `backend/public`; never expose the backend root or .env.

## Configure on the server

For a new deployment, copy `supabase.env.example` to `.env`. For an existing deployment, merge its DB settings into the existing .env and preserve APP_KEY. Never overwrite an existing key or publish credentials.

In Supabase, open Connect > Session pooler and copy the exact host, port (5432), database and username. Set DB_PASSWORD to the database password, not an API key. If it is unavailable, set/reset it in project Database Settings. Keep DB_SCHEMA=laravel and DB_SSLMODE=require. Remove any old DB_URL that would override the individual DB settings.

Set APP_URL to the HTTPS backend URL and FRONTEND_URL/CORS_ALLOWED_ORIGINS to the real frontend origin. The checked-in localhost values are placeholders.

## Install and migrate

Run inside backend on the configured server:

```sh
composer install --no-dev --optimize-autoloader
php artisan config:clear
# Only for a fresh installation with an empty APP_KEY:
php artisan key:generate
php artisan migrate:status
php artisan migrate --force
php artisan migrate:status
php artisan storage:link
php artisan config:cache
```

A fresh database may initially report that the migrations table does not exist; `migrate` creates it. Use the existing Laravel migrations so its migration history remains authoritative. Do not use migrate:fresh on a database with data. The demo seeders create sample users with fixed passwords; do not run them on production without reviewing and replacing demo credentials. Required reference data and a real administrator still need provisioning after migration.

## Frontend and verification

Set frontend VITE_BASE_URL to the deployed backend API URL before npm run build. Keep database credentials exclusively in backend configuration; never put them in VITE_* variables.

Verify all migrations succeeded, then test real login, patient creation, invoices and result entry against the deployed API. Neither the Laravel migrations nor these application flows have been executed from the current workspace (PHP and Composer are unavailable). No existing business data has been transferred.
