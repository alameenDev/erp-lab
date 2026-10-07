<?php

return [
    // Deployment is opt-in, per laboratory. An empty allowlist enables nobody.
    'enabled' => (bool) env('PATIENT_MOBILE_ENABLED', false),
    'lab_ids' => array_values(array_filter(array_map('intval',
        explode(',', (string) env('PATIENT_MOBILE_LAB_IDS', ''))))),
    'code_minutes' => 10,
    'session_days' => 30,
];
