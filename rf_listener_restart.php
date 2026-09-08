<?php
require_once __DIR__ . '/lib.php';

// Re-enable in case it was disabled via stop
$config = rf_load_config();
$config['listenerEnabled'] = true;
rf_save_config($config);

// Kill any existing listener
$existingPid = rf_find_listener_pid();
if ($existingPid !== null) {
    @shell_exec('kill ' . escapeshellarg((string) $existingPid) . ' 2>&1');
    sleep(1);
}

// Launch a fresh one in the background
$listener = escapeshellarg(RF_PLUGIN_ROOT . '/listener.php');
@shell_exec('nohup /usr/bin/php ' . $listener . ' > /dev/null 2>&1 &');
sleep(1);

$newPid = rf_find_listener_pid();
rf_log('Listener restarted via admin' . ($newPid ? ' (PID ' . $newPid . ')' : ' (failed to start)'));

rf_send_json([
    'ok' => $newPid !== null,
    'pid' => $newPid,
    'error' => $newPid === null ? 'Listener failed to start. Check the log.' : null,
]);
