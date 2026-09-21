<?php

return [
    // Transport staging only. No clinical adapter is enabled by this flag.
    'enabled' => env('LAB_SYNC_ENABLED', false),
    'max_event_bytes' => 65536,
    'batch_size' => 25,
];
