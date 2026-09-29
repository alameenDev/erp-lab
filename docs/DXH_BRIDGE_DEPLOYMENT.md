# DxH bridge deployment

This branch adds a one-screen Windows receiver plus an authenticated, idempotent device inbox. It does not change database credentials and does not deploy itself to Hostinger.

## Server update

1. Review/merge the branch through the normal release process. Back up the existing database before migration.
2. Deploy the Laravel changes and rebuilt Vue frontend together. Keep the existing private `.env`, APP_KEY, storage and uploads. Do not run `erp:install`, `migrate:fresh`, seeders, or regenerate APP_KEY on the live site.
3. From the deployed backend directory run:

```sh
php artisan migrate --force
php artisan optimize:clear
```

The new migration adds nullable delivery ID/hash and instrument metadata to `device_results`, plus a unique `(device_id_fk, delivery_id)` index. Existing device records and tokens are retained. SQL credentials remain in the server's existing `.env`; the desktop client never gets direct MySQL access.

4. Build/deploy frontend using the existing Hostinger process (`npm ci --legacy-peer-deps`, `npm run build`, `/api` on the same origin).
5. Create a device in the site's Devices page and enter its 64-character token locally in the bridge. No need to share that token in chat or commit it.
6. Check the connection from the bridge, then transmit one known test sample. Verify the inbox and barcode. No production data is sent by automated tests.

## Contract

- `POST /api/device/bridge/heartbeat`: `X-Device-Token` header; confirms protocol `labbridge-v1`, database columns installed, and authenticated device ID.
- `POST /api/device/bridge/results`: same header, raw message, parsed results, instrument metadata and stable SHA-256 delivery ID. Device ID must match the token. Duplicate ID + identical content returns the existing receipt; changed content returns 409. Results are acknowledged as stored only after the database transaction commits.
- Delivery is scoped to the device's lab. A barcode matching more than one invoice is not automatically matched. No matching invoice still produces a stored pending inbox record.
- `instrument_metadata.review_required` is enforced server-side. New bridge deliveries cannot use the existing invoice apply endpoint in this release. This preserves the requested automatic upload/storage while deferring clinical application until sample identifiers and test mapping are commissioned.
- Raw ASTM control/whitespace is exempted from Laravel string normalization on this one endpoint, so digests and non-numeric results are preserved.

## Build the Windows app

The `Build DxH Windows bridge` workflow creates `DigitalLabBridge-Windows`, containing the standalone exe and Arabic instructions. It runs receiver/outbox tests, opens the Tk window in smoke-test mode on Windows, and starts the packaged exe in smoke-test mode. Build output is an unsigned executable; signing/installer distribution is a separate release task.

## Verification

Local checks: Python receiver/outbox tests; PHP feature tests with an isolated SQLite database; Vue production build. The existing MariaDB CI workflow now includes BridgeResultsTest to check the actual MySQL-family migration and inbox behavior. Do not describe remote CI as passed until its run completes.

Remaining on-site acceptance: correct O-2/O-3 barcode field, exact mapping of CBC components to invoice/package results, warning/research handling, and end-to-end Windows-to-analyzer-to-hosted-site test. No live Hostinger deployment or database access was performed during development.
