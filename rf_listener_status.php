<?php
require_once __DIR__ . '/lib.php';

$pid = rf_find_listener_pid();
rf_send_json([
    'ok' => true,
    'running' => $pid !== null,
    'pid' => $pid,
]);
