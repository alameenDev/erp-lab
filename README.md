# ERP Lab

Medical laboratory management project imported from the supplied frontend and backend archives.

## Structure

- `frontend/`: Vue 3.5, Vite 7, PrimeVue 4 and Tailwind CSS 4 application.
- `backend/`: Laravel 12 API, database migrations, seeders and import templates.

## Local setup

Backend requires PHP 8.4+, Composer and MariaDB/MySQL (PDO MySQL).

For Hostinger installation, use [backend/HOSTINGER_SETUP.md](backend/HOSTINGER_SETUP.md). The `erp:install` command provisions reference data and a private administrator account without demo records.

```sh
cd backend
composer install
cp .env.example .env
# Configure your database credentials and APP_URL in .env.
php artisan key:generate
php artisan erp:install
php artisan storage:link
php artisan serve
```

In another terminal:

```sh
cd frontend
npm ci --legacy-peer-deps
cp .env.example .env
# Set the API and image URLs for your backend.
npm run dev
```

Build the frontend using `npm run build` inside `frontend/`.
Set backend CORS_ALLOWED_ORIGINS and FRONTEND_URL to the frontend origin.
Existing seeders include demo users and passwords; review them before using seeding outside local development.

## Configuration

Private `.env` files, credentials, dependencies and runtime files are excluded from version control. Supply deployment credentials separately. MariaDB is the default for new installations. Existing .env settings must be updated explicitly; no live data is transferred automatically.

## Corrected source replacement (2026-09-14)

The current frontend is imported from `lab-site-main (1)(2).zip`. The backend
archive `medical_lab_backend-main(1).zip` is byte-for-byte identical in file
contents to the originally supplied backend; its reviewed MySQL compatibility,
installer and Hostinger deployment patches are retained. See
[SOURCE_REPLACEMENT.md](SOURCE_REPLACEMENT.md) for provenance and update steps.

The existing database, administrator, `APP_KEY` and uploaded files are reused.
Do not rerun installation, regenerate the app key or reset migrations for this update.
