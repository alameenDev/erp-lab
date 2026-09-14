# Corrected project source

## Source archives

- `medical_lab_backend-main(1).zip`: SHA-256 `87fd8b03af2a014c5da0a0e8040eacdbf3bd005e03d94fef133beb583b3d97bd`
- `lab-site-main (1)(2).zip`: SHA-256 `3566a3578e11cf05f307a7c800df90293059b279cee28f649415cf140a0c8f67`

The uploaded backend has exactly the same 203 files as the original backend
archive (including directory metadata files). No new migration or database
reset is needed. Existing MariaDB patches and production setup tooling remain.
The frontend is replaced completely with the corrected archive; obsolete files
absent from that archive are removed from the current Git tree. Git history is
retained for rollback. Uploaded private environment files are never committed.

## Deployment adaptation

- API calls use `/api`; uploaded images use `/storage/`.
- Shared result and invoice links use the current site origin.
- Canonical/social metadata and sitemap target the Hostinger website.
- Node 22 builds the Vite 7/Tailwind 4 frontend in GitHub Actions.
- PHP 8.4 and the installed MariaDB database continue to serve the Laravel API.
- The publisher checks database connectivity and migrations before switching
  the site, and backs up overwritten public files and the private environment.
- No schema reset, seeding, application key change or administrator replacement
  is included in the update.

## Update the existing Hostinger installation

Wait for both GitHub Actions checks on `main` to pass, then run in the existing
SSH session (the server prompt, not Windows PowerShell):

```bash
cd /home/u859215520/erp-lab
git pull --ff-only origin main
bash scripts/deploy-hostinger.sh /home/u859215520/domains/lightpink-badger-650079.hostingersite.com/public_html https://lightpink-badger-650079.hostingersite.com
```

If `git pull` reports local changes, stop and inspect them; do not discard the
server configuration. The deployment script refuses to publish if the build
branch does not match the checked-out source commit.

After the update, open `/login` and use the existing administrator account.
Administrator role 1 now opens the corrected frontend's `/super-admin` dashboard.
Check `/up`, login, lab settings, and an existing invoice/result if available.
Only compiled assets and the Laravel public entrypoint belong in `public_html`.
