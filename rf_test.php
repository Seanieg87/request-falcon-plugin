<?php
require_once __DIR__ . '/lib.php';

$config = rf_load_config();
if (empty($config['token'])) {
    rf_send_json([
        'ok' => false,
        'error' => 'No token configured. Paste your show token first.',
    ]);
}

$startMs = (int) (microtime(true) * 1000);
$res = rf_api_request('GET', '/q/health');
$latencyMs = (int) (microtime(true) * 1000) - $startMs;

if (!$res['ok']) {
    $msg = $res['status'] === 401
        ? 'Token was rejected. Double-check you copied the full token.'
        : ($res['status'] === 0
            ? 'Could not reach Request Falcon: ' . ($res['error'] ?? 'network error')
            : 'Request Falcon returned HTTP ' . $res['status']);
    rf_send_json(['ok' => false, 'error' => $msg, 'latencyMs' => $latencyMs]);
}

$showName = is_array($res['body']) && isset($res['body']['showName']) ? $res['body']['showName'] : null;
rf_send_json([
    'ok' => true,
    'latencyMs' => $latencyMs,
    'showName' => $showName,
]);
