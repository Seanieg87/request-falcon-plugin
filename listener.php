<?php
/**
 * Request Falcon listener daemon.
 *
 * Reads plugin settings from FPP's INI file at
 *   /home/fpp/media/config/plugin.request-falcon-plugin
 * (FPP maintains this file automatically as the browser POSTs values
 * via /api/plugin/request-falcon-plugin/settings/<key>).
 *
 * Loop:
 *   1. Re-read settings each cycle (cheap — small INI file)
 *   2. If listenerEnabled=false, exit cleanly
 *   3. If listenerRestarting=true, clear the flag and exit (postStart
 *      relaunches us)
 *   4. Otherwise: report FPP status to RF, fetch next request, inject
 */

$PLUGIN_NAME = 'request-falcon-plugin';
$PLUGIN_VERSION = '1.2.0';
$CONFIG_FILE = '/home/fpp/media/config/plugin.' . $PLUGIN_NAME;
$LOG_FILE = '/home/fpp/media/logs/request-falcon.log';

function rf_log($msg) {
    global $LOG_FILE;
    $dir = dirname($LOG_FILE);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents($LOG_FILE, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

function rf_load_settings() {
    global $CONFIG_FILE;
    if (!is_readable($CONFIG_FILE)) {
        return [];
    }
    $raw = @parse_ini_file($CONFIG_FILE);
    return is_array($raw) ? $raw : [];
}

function rf_get($settings, $key, $default) {
    if (!isset($settings[$key])) return $default;
    // FPP URL-encodes values before writing to INI
    return urldecode($settings[$key]);
}

// Prevent multiple listeners from running
$selfPid = getmypid();
$listenerPath = __FILE__;
$existing = trim((string) @shell_exec('pgrep -f ' . escapeshellarg($listenerPath) . ' 2>/dev/null'));
$otherPids = array_filter(
    array_map('intval', explode("\n", $existing)),
    function ($p) use ($selfPid) { return $p > 0 && $p !== $selfPid; }
);
if (!empty($otherPids)) {
    rf_log('Startup aborted — another listener is running (PID ' . implode(',', $otherPids) . ')');
    exit(0);
}

// Signal handling for clean shutdown
$running = true;
if (function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, function () use (&$running) { $running = false; });
    pcntl_signal(SIGINT,  function () use (&$running) { $running = false; });
}

rf_log('Listener starting (PID ' . $selfPid . ', v' . $PLUGIN_VERSION . ')');

// Report plugin version to Request Falcon once at startup
function rf_api_post($apiPath, $endpoint, $token, $body) {
    $url = rtrim($apiPath, '/') . '/' . ltrim($endpoint, '/');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'remotetoken: ' . $token,
        ],
        CURLOPT_POSTFIELDS => json_encode($body),
    ]);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => (int) $status, 'body' => $raw];
}

function rf_api_get($apiPath, $endpoint, $token) {
    $url = rtrim($apiPath, '/') . '/' . ltrim($endpoint, '/');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => [
            'remotetoken: ' . $token,
        ],
    ]);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode((string) $raw, true);
    return ['status' => (int) $status, 'body' => is_array($decoded) ? $decoded : null];
}

function rf_fpp_get($endpoint) {
    $ch = curl_init('http://localhost/' . ltrim($endpoint, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
    ]);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status < 200 || $status >= 300) return null;
    $decoded = json_decode((string) $raw, true);
    return is_array($decoded) ? $decoded : null;
}

function rf_fpp_post($endpoint, $body) {
    $ch = curl_init('http://localhost/' . ltrim($endpoint, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($body),
    ]);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $status >= 200 && $status < 300;
}

// Loop state
$lastReportedPlaying = null;
$lastFetchAt = 0;
$lastHeartbeatAt = 0;
$lastStatusCheckAt = 0;
$startupReported = false;

while ($running) {
    $settings = rf_load_settings();

    $enabled = rf_get($settings, 'listenerEnabled', 'true');
    if ($enabled !== 'true') {
        rf_log('Listener stopping — disabled in settings');
        break;
    }

    $restarting = rf_get($settings, 'listenerRestarting', 'false');
    if ($restarting === 'true') {
        // Clear the flag by asking FPP to reset it (we could write the
        // INI directly but it's safer to go through FPP's API to keep
        // the encoding consistent).
        rf_log('Restart requested — exiting so postStart can relaunch');
        // Best-effort clear via HTTP — even if this fails, next startup
        // won't see the flag long since postStart will run and we'll
        // see 'true' → exit again → infinite loop. Guard against that
        // by clearing right here via file write if HTTP fails.
        $curl = curl_init('http://localhost/api/plugin/' . urlencode($PLUGIN_NAME) . '/settings/listenerRestarting');
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => 'false',
            CURLOPT_TIMEOUT        => 5,
        ]);
        curl_exec($curl);
        curl_close($curl);
        break;
    }

    $token = rf_get($settings, 'token', '');
    $apiPath = rf_get($settings, 'apiPath', 'https://requestfalcon.com/api/plugin');
    $remotePlaylist = rf_get($settings, 'remotePlaylist', '');
    $interruptSchedule = rf_get($settings, 'interruptSchedule', 'false') === 'true';
    $fetchInterval = max(1, (int) rf_get($settings, 'fetchIntervalSec', '3'));
    $statusInterval = max(1, (int) rf_get($settings, 'statusCheckIntervalSec', '1'));
    $verbose = rf_get($settings, 'verboseLogging', 'false') === 'true';

    if (empty($token)) {
        sleep(5);
        continue;
    }

    // Report plugin version once, after we have a token
    if (!$startupReported) {
        global $PLUGIN_VERSION;
        rf_api_post($apiPath, 'pluginVersion', $token, ['version' => $PLUGIN_VERSION]);
        $startupReported = true;
    }

    $now = time();

    // Check FPP status, report currently_playing
    if ($now - $lastStatusCheckAt >= $statusInterval) {
        $lastStatusCheckAt = $now;
        $fppStatus = rf_fpp_get('api/fppd/status');
        if (is_array($fppStatus)) {
            $current = null;
            if (!empty($fppStatus['current_sequence'])) {
                $current = preg_replace('/\.fseq$/i', '', $fppStatus['current_sequence']);
            }
            if ($current !== $lastReportedPlaying) {
                if ($verbose) rf_log('Now playing: ' . ($current ?? '(nothing)'));
                rf_api_post($apiPath, 'updateWhatsPlaying', $token, ['currentlyPlaying' => $current]);
                $lastReportedPlaying = $current;
            }
        }
    }

    // Fetch next request from RF
    if ($now - $lastFetchAt >= $fetchInterval) {
        $lastFetchAt = $now;
        $prefs = rf_api_get($apiPath, 'remotePreferences', $token);
        if ($prefs['status'] >= 200 && $prefs['status'] < 300 && is_array($prefs['body'])) {
            $mode = strtolower((string) ($prefs['body']['viewerControlMode'] ?? 'disabled'));
            if ($mode === 'jukebox' || $mode === 'voting') {
                $endpoint = $mode === 'jukebox'
                    ? 'nextPlaylistInQueue?updateQueue=true'
                    : 'highestVotedPlaylist';
                $nextRes = rf_api_get($apiPath, $endpoint, $token);
                if ($nextRes['status'] >= 200 && $nextRes['status'] < 300 && is_array($nextRes['body'])) {
                    $sequence = $nextRes['body']['nextSequence'] ?? null;
                    if (is_string($sequence) && $sequence !== '' && $remotePlaylist !== '') {
                        $seqWithExt = preg_match('/\.fseq$/i', $sequence) ? $sequence : $sequence . '.fseq';
                        if ($verbose) rf_log("Injecting '$seqWithExt' into '$remotePlaylist'");
                        rf_fpp_post('api/playlist/' . rawurlencode($remotePlaylist) . '/nextItem', [
                            'sequenceName' => $seqWithExt,
                        ]);
                    }
                }
            }
        }
    }

    // Heartbeat every 30s
    if ($now - $lastHeartbeatAt >= 30) {
        $lastHeartbeatAt = $now;
        rf_api_post($apiPath, 'fppHeartbeat', $token, []);
    }

    usleep(500 * 1000);
}

rf_log('Listener exited (PID ' . $selfPid . ')');
exit(0);
