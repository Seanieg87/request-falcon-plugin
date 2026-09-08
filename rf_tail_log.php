<?php
require_once __DIR__ . '/lib.php';

rf_send_json([
    'ok' => true,
    'lines' => rf_tail_log(50),
]);
