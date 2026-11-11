# Educational demo at /demo

This is a separate build of the same Vue application and a separate installation of the same Laravel backend. The live application does not provision demo accounts. Never point the demo API at the live database.

## Included

- Arabic welcome page, 35 documented subjects, searchable lessons, contextual chat from the curated feature guide, explanations, guided actions, and links to real seeded examples.
- Actual application screens and permissions. Independent synthetic lab for each session; patient, invoice, payment, test, group, package, device inbox, owner, doctor, referring lab and collector examples.
- Browser storage is namespaced **at build time** before app imports run; existing live login, branding, printer pairing, and service workers are untouched. Production build does not mount the trainer.
- Save steps complete only after the expected successful API operation. Read/review steps are explicitly manual. Skipped lessons are not marked completed. Progress and chat stay in this browser.
- Sessions last 24 hours. Resume/role switch rotates that identity's demo token. Reset provisions a fresh workspace and expires the previous one atomically. Expired workspaces are inaccessible; records remain in the **demo database** until its separate retention process removes them.
- Printing/PDF use existing report code. External messages, AI, hardware bridges, mobile app linking, push and local print helper connections are disabled. Returned simulation notices do not claim delivery.
- Chat is curated Arabic keyword retrieval, **not an LLM**: unsupported questions get an explicit fallback. It does not answer medical questions or promise undocumented features. Extend `frontend/src/demo/guide.js` alongside future feature changes.

## Build and deploy on Hostinger

1. Create a new MySQL database **and a user restricted to it**. Use a different DB name, username, password and APP_KEY. The installer checks the demo user cannot read the live `users` table. The database must initially be empty; do not import live patients.
2. Create an empty private runtime directory outside `public_html` and outside the live checkout. Create a private configuration file from `backend/demo.env.example`. Do not put credentials in the repo or public directory.
3. In `frontend`, run `npm ci` then `npm run build:demo`. Live deployment still uses `npm run build`. Composer dependencies must exist in the source backend.
4. Run from the checkout:

```sh
php scripts/publish-learning-demo.php /private/erp-demo /private/demo.env /site/public_html https://your-domain.example
```

The installer copies backend code/dependencies without live `.env`, storage, cached config, or tests. It creates private demo storage, applies migrations to the dedicated DB, seeds only permissions/reference data, publishes `dist-demo` to `public_html/demo`, and delegates `/demo` before the root SPA fallback. Later live deployments preserve this delegation. It never edits the live environment or migrates the live database.

5. Open `/demo/`, create two independent browser sessions and verify the full acceptance checklist below before distributing the URL. Re-run the same installer to upgrade the demo when new features ship. A maintenance marker blocks API requests while upgrading.
6. Configure independent hosting quotas, backups and retention for the demo DB/storage. Default maximum simultaneously active sessions is 500; session creation is rate limited. Reset does not delete expired records or uploaded files. Do not schedule live `deploy-hostinger.sh` against this private runtime. Don't enable real messaging, printer, push, mobile DB or device credentials there.

If unavailable/misconfigured, demo endpoints fail closed (404/503); no production API fallback exists. Deployment is separate and not executed by opening a PR.

## Acceptance / tests

- MariaDB `LearningDemoTest`: production disabled; missing/shared configuration denied; two sessions isolated; actual patient/invoice/portal/owner reads; foreign tokens and public reports rejected; reset and expiry; forbidden external actions.
- `node --test frontend/tests/learning-demo.test.mjs`: storage isolation/clear; Arabic retrieval/fallback; unique curriculum routes/IDs and save completion definitions.
- `Isolated learning demo` CI builds the actual demo and runs a browser against Laravel/MySQL: start, Arabic help, patient save gating, reload, roles, mobile layout, reset and live storage/API isolation.
- Build **both** frontend modes. In browser verify `/demo` landing, reload and deep links, start/resume, role switching, mobile drawer, real patient save, invoice→result→PDF, simulated outgoing actions and preserved live token/printer settings.
- All patient records, names and results must remain clearly labelled as examples. Do not enter real patient data. Training content is an operational guide, not clinical interpretation.

## Notes for maintenance

The guide does not reimplement invoice/printing logic. The `data-demo` hooks are inert in the live app. Add precise hooks and API assertions when expanding step-by-step lessons; do not complete save steps with a timer or a click alone. The caller must distinguish “I reviewed this screen” from a successful saved operation.
