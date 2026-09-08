<?php
require_once __DIR__ . '/lib.php';

$body = rf_json_body();
if ($body === null) {
    rf_send_json(['ok' => false, 'error' => 'Invalid JSON'], 400);
}

$existing = rf_load_config();
$updated = $existing;

// Whitelist accepted keys — never blindly merge arbitrary JSON
if (isset($body['token']))                  $updated['token']                  = (string) $body['token'];
if (isset($body['remotePlaylist']))         $updated['remotePlaylist']         = (string) $body['remotePlaylist'];
if (isset($body['apiPath']))                $updated['apiPath']                = (string) $body['apiPath'];
if (isset($body['interruptSchedule']))      $updated['interruptSchedule']      = (bool)   $body['interruptSchedule'];
if (isset($body['fetchIntervalSec']))       $updated['fetchIntervalSec']       = max(1, min(10, (int) $body['fetchIntervalSec']));
if (isset($body['statusCheckIntervalSec'])) $updated['statusCheckIntervalSec'] = max(1, min(10, (int) $body['statusCheckIntervalSec']));
if (isset($body['verboseLogging']))         $updated['verboseLogging']         = (bool)   $body['verboseLogging'];

if (!rf_save_config($updated)) {
    rf_send_json([
        'ok' => false,
        'error' => 'Could not write config file. Check permissions on ' . RF_CONFIG_FILE,
    ], 500);
}

rf_log('Config saved via admin page');
rf_send_json(['ok' => true]);
