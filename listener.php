<?php
/**
 * Request Falcon listener daemon.
 *
 * Runs continuously in the background. In each polling cycle:
 *   1. Ask FPP what's currently playing (via localhost API)
 *   2. If it changed, report the new "currently playing" to Request Falcon
 *   3. If show mode is jukebox/voting AND enough time has passed since the
 *      last request check, ask Request Falcon for the next-up sequence
 *      and inject it into FPP's playlist
 *   4. Send a heartbeat so the dashboard shows this Pi as "connected"
 *
 * Launched by:
 *   - scripts/postStart.sh at FPP boot
 *   - api.php "restartListener" action from the admin page
 *
 * Stops when:
 *   - Config has listenerEnabled=false (checked each cycle)
 *   - Process is killed (kill/pkill)
 */

require_once __DIR__ . '/lib.php';

// Don't run multiple copies. If another listener is running, exit cleanly.
$selfPid = getmypid();
$listenerPath = __FILE__;
$existing = trim((string) @shell_exec('pgrep -f ' . escapeshellarg($listenerPath) . ' 2>/dev/null'));
$otherPids = array_filter(
    array_map('intval', explode("\n", $existing)),
    fn($p) => $p > 0 && $p !== $selfPid
);
if (!empty($otherPids)) {
    rf_log('Listener startup aborted — another listener is already running (PID ' . implode(',', $otherPids) . ')');
    exit(0);
}

// Handle SIGTERM/SIGINT gracefully so kill/restart is clean
$running = true;
if (function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, function () use (&$running) { $running = false; });
    pcntl_signal(SIGINT,  function () use (&$running) { $running = false; });
}

rf_log('Listener starting (PID ' . $selfPid . ')');

// Version reported on startup so the dashboard knows what plugin we are
rf_api_request('POST', '/pluginVersion', [
    'version' => '1.0.0',
]);

// Loop state — track what we last reported to avoid noisy repeats
$lastReportedPlaying = null;
$lastFetchAt = 0;
$lastHeartbeatAt = 0;
$lastStatusCheckAt = 0;

while ($running) {
    // Re-read config each cycle so admin-page changes take effect without
    // a listener restart (cheap — it's just reading a small JSON file).
    $config = rf_load_config();

    // Owner disabled the listener — exit cleanly
    if (empty($config['listenerEnabled'])) {
        rf_log('Listener stopping — disabled in config');
        break;
    }

    // Token not configured — sleep and check again
    if (empty($config['token'])) {
        sleep(5);
        continue;
    }

    $now = time();
    $fetchInterval  = max(1, (int) ($config['fetchIntervalSec']  ?? 3));
    $statusInterval = max(1, (int) ($config['statusCheckIntervalSec'] ?? 1));

    // ─── Step 1: check what FPP is currently playing ───────────────────
    if ($now - $lastStatusCheckAt >= $statusInterval) {
        $lastStatusCheckAt = $now;
        $fppStatus = rf_fpp_request('GET', 'api/fppd/status');
        if (is_array($fppStatus)) {
            $currentSeq = rf_extract_current_sequence($fppStatus);
            $nextSeq    = rf_extract_next_sequence($fppStatus);

            // Report "currently playing" only when it changes, so we
            // don't spam the server with identical updates every second.
            if ($currentSeq !== $lastReportedPlaying) {
                if (!empty($config['verboseLogging'])) {
                    rf_log("Currently playing changed: " . ($currentSeq ?? '(none)'));
                }
                rf_api_request('POST', '/updateWhatsPlaying', [
                    'currentlyPlaying' => $currentSeq,
                ]);
                $lastReportedPlaying = $currentSeq;
            }

            if ($nextSeq !== null) {
                rf_api_request('POST', '/updateNextScheduledSequence', [
                    'nextScheduled' => $nextSeq,
                ]);
            }
        }
    }

    // ─── Step 2: ask Request Falcon for the next request ──────────────
    if ($now - $lastFetchAt >= $fetchInterval) {
        $lastFetchAt = $now;

        // Get show preferences (mode: jukebox / voting / disabled)
        $prefs = rf_api_request('GET', '/remotePreferences');
        if ($prefs['ok'] && is_array($prefs['body'])) {
            $mode = strtolower((string) ($prefs['body']['viewerControlMode'] ?? 'disabled'));

            if ($mode === 'jukebox' || $mode === 'voting') {
                // Jukebox: /nextPlaylistInQueue — first in queue
                // Voting:  /highestVotedPlaylist — top vote-getter
                $endpoint = $mode === 'jukebox'
                    ? '/nextPlaylistInQueue?updateQueue=true'
                    : '/highestVotedPlaylist';

                $nextRes = rf_api_request('GET', $endpoint);
                if ($nextRes['ok'] && is_array($nextRes['body'])) {
                    $sequence = $nextRes['body']['nextSequence'] ?? null;
                    if (is_string($sequence) && $sequence !== '') {
                        rf_inject_sequence_into_fpp($sequence, $config);
                    }
                }
            }
        }
    }

    // ─── Step 3: heartbeat every 30s ──────────────────────────────────
    if ($now - $lastHeartbeatAt >= 30) {
        $lastHeartbeatAt = $now;
        rf_api_request('POST', '/fppHeartbeat', []);
    }

    // Sleep briefly. Actual polling cadence is controlled by the
    // per-action timestamps above; this just keeps the loop from
    // busy-spinning.
    usleep(500 * 1000); // 500ms
}

rf_log('Listener exiting cleanly (PID ' . $selfPid . ')');
exit(0);


// ─────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────

/**
 * FPP's status response contains a `current_sequence` field naming the
 * .fseq file currently playing. Strip the extension so it matches how
 * we stored the sequence name during sync.
 */
function rf_extract_current_sequence(array $status): ?string
{
    $raw = $status['current_sequence'] ?? null;
    if (!is_string($raw) || $raw === '') return null;
    return preg_replace('/\.fseq$/i', '', $raw);
}

function rf_extract_next_sequence(array $status): ?string
{
    $raw = $status['next_sequence_in_playlist'] ?? null;
    if (!is_string($raw) || $raw === '') return null;
    return preg_replace('/\.fseq$/i', '', $raw);
}

/**
 * Ask FPP to play the given sequence next.
 *
 * If interruptSchedule is on, we use FPP's insert-at-current-position
 * pattern to break in immediately. Otherwise we queue it to play after
 * the current sequence finishes.
 *
 * The pattern is: POST /api/playlist/<remotePlaylist>/nextItem with the
 * sequence name.
 */
function rf_inject_sequence_into_fpp(string $sequence, array $config): void
{
    $playlist = $config['remotePlaylist'] ?? '';
    if ($playlist === '') {
        if (!empty($config['verboseLogging'])) {
            rf_log("Cannot inject '$sequence' — no remotePlaylist configured");
        }
        return;
    }

    // FPP expects the sequence to have its .fseq extension when inserting
    $seqWithExt = preg_match('/\.fseq$/i', $sequence) ? $sequence : $sequence . '.fseq';

    if (!empty($config['verboseLogging'])) {
        rf_log("Injecting '$seqWithExt' into playlist '$playlist'");
    }

    // Use FPP's insert-playlist-item endpoint. Different FPP versions
    // handle this slightly differently, but the common path is:
    //   POST /api/playlist/<name>/nextItem
    //   Body: { "sequenceName": "..." }
    $res = rf_fpp_request('POST', 'api/playlist/' . rawurlencode($playlist) . '/nextItem', [
        'sequenceName' => $seqWithExt,
    ]);

    if ($res === null && !empty($config['verboseLogging'])) {
        rf_log("FPP rejected inject request for '$seqWithExt'");
    }
}
