# Ibn Sina patient conversation journal

Lab Settings → **محادثات ابن سينا** lists consented patient conversations, patient name/code, selected report, last message, timestamps in Baghdad, and patient-message count. Open a conversation to read patient messages and assistant replies in chronological pages. Search name/code and filter by the date of last activity. Failed or interrupted replies retain the submitted patient message.

## Deployment

Deploy the backend, run `php artisan migrate --force`, and deploy the frontend from the same revision. No API key change is needed. Old cached clients are explicitly asked to refresh and accept the new disclosure; the old AI-provider consent does not authorize staff transcript review. Do not deploy frontend alone. Existing lab AI enablement/provider configuration still applies.

Only messages sent after this update and the patient's affirmative consent are recorded. No old transcript can be recovered; old chat lived only in page memory. Unsent drafts, local welcome/selection prompts and arbitrary client-supplied history are not recorded as patient messages. No new patient-facing history retrieval endpoint is added.

Lab accounts (role 2) can read their own lab journal. Staff need `patient ai conversations view`; to navigate Lab Settings they also need the existing `lab settings edit` permission. Existing staff settings permission alone does not grant transcript access. The new permission is available in the role editor without granting it to existing staff. Every list/detail request resolves the authenticated tenant; request parameters cannot select another lab. Portal OTP/expiry and patient/report boundaries remain enforced before journaling.

## Storage and operation

New tables: `patient_ai_conversations`, `patient_ai_turns`. Message text, assistant response envelopes, and selected analysis names use Laravel encrypted casts. Preserve the server's existing APP_KEY and protect its backups; changing it without key-rotation support makes old encrypted records unreadable. Names/codes are resolved from the existing patient record. No portal bearer tokens, API keys, full report copies, or arbitrary history are stored in these tables.

A UUID per conversation and per submitted message prevents duplicate rows or provider requests on completed retries. Failed attempts can retry the same message. An in-flight request is not restarted for two minutes; older interrupted attempts may retry with a new attempt number, and late old responses cannot overwrite the latest attempt. Report/context changes require a new conversation before reusing cached replies.

The transcript is retained until removed under the lab's data-retention process; this update does not silently erase records on chat close or impose an automatic deletion interval. The patient disclosure states that closing the page does not remove stored conversations. Viewing details writes a metadata-only activity entry. Transcript models are excluded from generic change auditing; responses use `private, no-store`, and UI text is escaped rather than rendered as HTML. No export, edit, or delete endpoint is introduced.

## Verification

MariaDB feature tests cover updated consent, OTP/expiry, encryption, tenant and staff authorization/revocation, failed sends, idempotent retries, stale-attempt exclusion, Baghdad dates and pagination. Existing AI report-publication, selected-analysis, doctor-booking and provider-error tests continue to run. Browser tests cover transcript reading, filters, pagination, safe literal HTML display, failed replies, permission errors, stale cancellation and 390/1280 px layouts using synthetic data only.
