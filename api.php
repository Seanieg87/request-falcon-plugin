<?php
/**
 * AJAX endpoints called by the admin config page.
 *
 * Dispatch is by ?action=... query param. All responses are JSON.
 *
 * Actions:
 *   saveConfig     POST — replace the plugin config file
 *   testConnect    GET  — hit the RF server's health endpoint, return status
 *   syncPlaylist   POST — read the FPP remote playlist, upload to RF
 *   listenerStatus GET  — is the listener running?
 *   restartListener POST — kill any listener process, start a fresh one
 *   stopListener   POST — kill the listener, don't restart
 *   tailLog        GET  — return last 50 lines of the log
 */

require_once __DIR__ . '/lib.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'saveConfig':
            handle_save_config();
            break;
        case 'testConnect':
            handle_test_connect();
            break;
        case 'syncPlaylist':
            handle_sync_playlist();
            break;
        case 'listenerStatus':
            handle_listener_status();
            break;
        case 'restartListener':
            handle_restart_listener();
            break;
        case 'stopListener':
            handle_stop_listener();
            break;
        case 'tailLog':
            handle_tail_log();
            break;
        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Internal error: ' . $e->getMessage()]);
    rf_log('api.php exception: ' . $e->getMessage());
}
exit;


// ─────────────────────────────────────────────────────────────────────
// Handlers
// ─────────────────────────────────────────────────────────────────────

function handle_save_config(): void
{
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        return;
    }

    $existing = rf_load_config();

    // Whitelist accepted keys — don't let arbitrary JSON contaminate the
    // config with unknown fields.
    $updated = $existing;
    if (isset($body['token']))                  $updated['token']              = (string) $body['token'];
    if (isset($body['remotePlaylist']))         $updated['remotePlaylist']     = (string) $body['remotePlaylist'];
    if (isset($body['apiPath']))                $updated['apiPath']            = (string) $body['apiPath'];
    if (isset($body['interruptSchedule']))      $updated['interruptSchedule']  = (bool)   $body['interruptSchedule'];
    if (isset($body['fetchIntervalSec']))       $updated['fetchIntervalSec']   = max(1, min(10, (int) $body['fetchIntervalSec']));
    if (isset($body['statusCheckIntervalSec'])) $updated['statusCheckIntervalSec'] = max(1, min(10, (int) $body['statusCheckIntervalSec']));
    if (isset($body['verboseLogging']))         $updated['verboseLogging']     = (bool)   $body['verboseLogging'];

    if (!rf_save_config($updated)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not write config file. Check permissions on ' . RF_CONFIG_FILE]);
        return;
    }

    rf_log('Config saved via admin page');
    echo json_encode(['ok' => true]);
}


function handle_test_connect(): void
{
    $config = rf_load_config();
    if (empty($config['token'])) {
        echo json_encode(['ok' => false, 'error' => 'No token configured. Paste your show token first.']);
        return;
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
        echo json_encode(['ok' => false, 'error' => $msg, 'latencyMs' => $latencyMs]);
        return;
    }

    $showName = is_array($res['body']) && isset($res['body']['showName']) ? $res['body']['showName'] : null;
    echo json_encode([
        'ok' => true,
        'latencyMs' => $latencyMs,
        'showName' => $showName,
    ]);
}


function handle_sync_playlist(): void
{
    $body = json_decode(file_get_contents('php://input'), true);
    $playlistName = is_array($body) && isset($body['playlist']) ? (string) $body['playlist'] : '';
    if ($playlistName === '') {
        echo json_encode(['ok' => false, 'error' => 'No playlist selected']);
        return;
    }

    // Fetch the playlist details from FPP
    $playlist = rf_fpp_request('GET', 'api/playlist/' . rawurlencode($playlistName));
    if (!is_array($playlist)) {
        echo json_encode(['ok' => false, 'error' => 'Could not load playlist "' . $playlistName . '" from FPP']);
        return;
    }

    $items = is_array($playlist['mainPlaylist'] ?? null) ? $playlist['mainPlaylist'] : [];

    // Build the payload for our server. Match the exact field names the
    // server expects (sequence, displayName, artist, duration).
    // Only include items that are actual sequences or media — skip
    // FPP "command" items that don't play music.
    $sequences = [];
    $index = 0;
    foreach ($items as $item) {
        $type = $item['type'] ?? '';
        $sequenceName = null;
        $duration = null;

        if ($type === 'both' || $type === 'sequence') {
            // Both/sequence items carry .fseq filenames — strip the extension
            $raw = $item['sequenceName'] ?? null;
            if (is_string($raw) && $raw !== '') {
                $sequenceName = preg_replace('/\.fseq$/i', '', $raw);
            }
            $duration = isset($item['duration']) ? (float) $item['duration'] : null;
        } elseif ($type === 'media') {
            $raw = $item['mediaName'] ?? null;
            if (is_string($raw) && $raw !== '') {
                // Strip common media extensions
                $sequenceName = preg_replace('/\.(mp3|mp4|wav|ogg|flac|m4a)$/i', '', $raw);
            }
        } else {
            // Skip command/other item types
            continue;
        }

        if (!$sequenceName) {
            continue;
        }

        $sequences[] = [
            'sequence' => $sequenceName,
            'index'    => $index,
            'duration' => $duration,
        ];
        $index++;
    }

    if (empty($sequences)) {
        echo json_encode(['ok' => false, 'error' => 'Playlist has no requestable sequences']);
        return;
    }

    // Send to Request Falcon
    $res = rf_api_request('POST', '/syncPlaylists', ['playlists' => $sequences]);
    if (!$res['ok']) {
        $msg = $res['status'] === 401
            ? 'Token rejected'
            : ($res['status'] === 0 ? ($res['error'] ?? 'network error') : 'HTTP ' . $res['status']);
        echo json_encode(['ok' => false, 'error' => 'Sync failed: ' . $msg]);
        return;
    }

    // Save the remote playlist name so the listener knows what to poll
    $config = rf_load_config();
    $config['remotePlaylist'] = $playlistName;
    rf_save_config($config);

    rf_log('Synced playlist "' . $playlistName . '" (' . count($sequences) . ' sequences)');

    $serverStats = is_array($res['body']) ? $res['body'] : [];
    echo json_encode([
        'ok' => true,
        'total'    => $serverStats['total']    ?? count($sequences),
        'inserted' => $serverStats['inserted'] ?? 0,
        'updated'  => $serverStats['updated']  ?? 0,
    ]);
}


function handle_listener_status(): void
{
    $pid = rf_find_listener_pid();
    echo json_encode([
        'ok' => true,
        'running' => $pid !== null,
        'pid' => $pid,
    ]);
}


function handle_restart_listener(): void
{
    // Kill any existing listener
    $pid = rf_find_listener_pid();
    if ($pid !== null) {
        @shell_exec('kill ' . escapeshellarg((string) $pid) . ' 2>&1');
        sleep(1);
    }
    // Start a fresh one
    $listener = escapeshellarg(RF_PLUGIN_ROOT . '/listener.php');
    @shell_exec('nohup /usr/bin/php ' . $listener . ' > /dev/null 2>&1 &');
    sleep(1);
    $newPid = rf_find_listener_pid();

    rf_log('Listener restarted via admin' . ($newPid ? ' (PID ' . $newPid . ')' : ' (failed to start)'));
    echo json_encode(['ok' => $newPid !== null, 'pid' => $newPid]);
}


function handle_stop_listener(): void
{
    // Persist the intention so postStart / restart doesn't bring it back
    $config = rf_load_config();
    $config['listenerEnabled'] = false;
    rf_save_config($config);

    $pid = rf_find_listener_pid();
    if ($pid !== null) {
        @shell_exec('kill ' . escapeshellarg((string) $pid) . ' 2>&1');
    }

    rf_log('Listener stopped via admin');
    echo json_encode(['ok' => true]);
}


function handle_tail_log(): void
{
    $lines = rf_tail_log(50);
    echo json_encode(['ok' => true, 'lines' => $lines]);
}


/**
 * Find the running listener PID by searching the process list for our
 * listener.php path. Returns the PID as an int, or null if not found.
 */
function rf_find_listener_pid(): ?int
{
    $listener = RF_PLUGIN_ROOT . '/listener.php';
    // pgrep is available on FPP by default. -f matches the full command
    // line, so we can find our specific script even though the base name
    // is generic (php).
    $out = @shell_exec('pgrep -f ' . escapeshellarg($listener) . ' 2>/dev/null');
    if ($out === null) return null;
    $line = trim(explode("\n", $out)[0]);
    if ($line === '') return null;
    $pid = (int) $line;
    // pgrep -f will also match its own command line if launched from
    // a shell wrapper — filter self out. On PHP-CLI the PID we see for
    // our own process is easy to identify.
    if ($pid === getmypid()) return null;
    return $pid > 0 ? $pid : null;
}
