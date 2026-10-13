# Public client laboratory map

## Purpose

The public Digital Lab homepage now has a client section that can show laboratories on an interactive map. Each laboratory is represented by a branded map pin containing its uploaded logo. Selecting a pin or a laboratory card shows the laboratory name and address and provides a link to the same coordinates in Google Maps.

The section is hidden automatically when there are no enabled laboratories. It is public marketing content only and is not connected to patient, invoice, result or laboratory operational data.

## Administrator workflow

On the Super Admin dashboard open **Client Laboratory Map / خريطة مختبرات العملاء** and choose **Manage Map / إدارة الخريطة**.

Each entry contains:

- Laboratory name.
- Public laboratory address.
- Latitude and longitude.
- Laboratory logo.
- Display order.
- Visible/hidden state.

Coordinates can be copied from Google Maps. Latitude must be between -90 and 90 and longitude between -180 and 180. The editor provides a preview link when the coordinates are valid.

Logo uploads accept JPEG, PNG and WebP up to 5 MB. The server validates the image MIME type, extension and dimensions before storing it on the existing public storage disk. Do not upload patient data or confidential documents because map logos are intentionally public.

## Public map behavior

The homepage fetches only enabled entries from `/api/public/client-labs`. Internal request IDs, storage paths, payload hashes, administrator IDs and version metadata are not exposed.

The interactive map uses Leaflet 1.9.4 loaded from jsDelivr and OpenStreetMap raster tiles. No Google Maps API key is required. Google Maps is used only for the external "View on Google Maps" link based on the saved coordinates.

When several laboratories exist, the map fits all markers automatically. With one laboratory it centers on that location. The map does not request the visitor's geolocation. If Leaflet or the external tile service is temporarily unavailable, the laboratory list and selected laboratory details remain usable.

The marker uses the laboratory logo inside a custom pointer. Names and addresses are rendered as text rather than trusted HTML. The component supports Arabic/RTL and English/LTR and reflows on tablet and mobile screens.

## Persistence and safety

The additive `client_map_locations` table stores map metadata. Creates use a per-administrator UUID and payload hash so a retried upload cannot create the same entry twice. Edits and deletes use a version number to prevent silently overwriting a concurrent administrator change. Deletion is soft-delete; deleted entries no longer appear publicly.

Only role 1 administrators can list, create, edit or delete map entries. Public users can only read the enabled public projection.

## Deployment

Review and merge the feature pull request, back up the production database and public uploads, then deploy from a clean working tree:

```sh
git status --short --branch
git pull --ff-only origin main

cd backend
php artisan migrate --force
php artisan optimize:clear
php artisan route:list --path=client-labs

cd ../frontend
npm ci
npm run build
```

Publish the generated frontend assets using the existing Hostinger mapping. Building alone does not copy `dist` into a separate `public_html` directory.

Keep the production `.env`, `APP_KEY`, uploads and existing data. Do not run `migrate:fresh`, `db:wipe`, `erp:install` or regenerate the application key.

After deployment, perform one real-host check with a test laboratory: upload the logo, save coordinates, verify the public marker, open the Google Maps link, and test desktop and mobile widths. Also confirm the hosting/network can reach jsDelivr and OpenStreetMap tile servers.
