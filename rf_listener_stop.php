<?php
require_once __DIR__ . '/lib.php';

// Persist the intent so postStart.sh won't relaunch on next boot
$config = rf_load_config();
$config['listenerEnabled'] = false;
rf_save_config($config);

$pid = rf_find_listener_pid();
if ($pid !== null) {
    @shell_exec('kill ' . escapeshellarg((string) $pid) . ' 2>&1');
}

rf_log('Listener stopped via admin');
rf_send_json(['ok' => true]);
