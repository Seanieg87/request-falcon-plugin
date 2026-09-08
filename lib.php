<?php
/**
 * Shared helpers for the Request Falcon plugin.
 *
 * Everything in here is reusable across the config page, AJAX API, and
 * the background listener. Keep it small and side-effect-free where
 * possible.
 */

// FPP provides a global $settings array pointing at the media root and
// plugin directories. When this file is loaded standalone (e.g. by the
// listener from cron/systemd), FPP's setup hasn't run — so we fall back
// to the hardcoded conventional path.
if (!defined('RF_PLUGIN_ROOT')) {
    define('RF_PLUGIN_ROOT', __DIR__);
}
if (!defined('RF_MEDIA_DIR')) {
    define('RF_MEDIA_DIR', '/home/fpp/media');
}
if (!defined('RF_CONFIG_FILE')) {
    define('RF_CONFIG_FILE', RF_MEDIA_DIR . '/config/plugin.request-falcon.json');
}
if (!defined('RF_LOG_FILE')) {
    define('RF_LOG_FILE', RF_MEDIA_DIR . '/logs/request-falcon.log');
}

/**
 * Load plugin config from disk. Returns an array with defaults filled in
 * for any missing keys. Never throws — a missing/malformed file just
 * returns defaults.
 */
function rf_load_config(): array
{
    $defaults = [
        'apiPath'           => 'https://requestfalcon.com/api/plugin',
        'token'             => '',
        'remotePlaylist'    => '',
        'interruptSchedule' => false,
        'fetchIntervalSec'  => 3,
        'statusCheckIntervalSec' => 1,
        'verboseLogging'    => false,
        'listenerEnabled'   => true,
    ];

    if (!is_readable(RF_CONFIG_FILE)) {
        return $defaults;
    }
    $raw = @file_get_contents(RF_CONFIG_FILE);
    if ($raw === false || $raw === '') {
        return $defaults;
    }
    $parsed = json_decode($raw, true);
    if (!is_array($parsed)) {
        return $defaults;
    }

    // Merge parsed values over defaults so any newly-added config key
    // (from a plugin upgrade) still has a sensible fallback.
    return array_replace($defaults, $parsed);
}

/**
 * Save plugin config to disk atomically. Writes to a temp file first,
 * then renames — so a crash mid-write can't leave a partial file.
 * Returns true on success, false on failure.
 */
function rf_save_config(array $config): bool
{
    // Ensure the parent directory exists (fresh installs won't have it)
    $dir = dirname(RF_CONFIG_FILE);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true)) {
            return false;
        }
    }

    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    $tmp = RF_CONFIG_FILE . '.tmp';
    if (@file_put_contents($tmp, $json) === false) {
        return false;
    }
    return @rename($tmp, RF_CONFIG_FILE);
}

/**
 * Write a line to the plugin log. Always appends a timestamp. Failures
 * are silent — if the log can't be written, we don't want to break the
 * caller.
 */
function rf_log(string $message): void
{
    $dir = dirname(RF_LOG_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    @file_put_contents(RF_LOG_FILE, $line, FILE_APPEND);
}

/**
 * Read the last N lines from the plugin log. Used by the config page's
 * "Tail Log" button. Returns an array of lines (newest last), possibly
 * empty.
 */
function rf_tail_log(int $lines = 50): array
{
    if (!is_readable(RF_LOG_FILE)) {
        return [];
    }
    // For small logs this is fine; if the log grows unbounded we'd want
    // to seek from the end. Log rotation is out of scope for v1 — the
    // owner can truncate manually if needed.
    $all = @file(RF_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($all)) {
        return [];
    }
    return array_slice($all, -max(1, $lines));
}

/**
 * Make an HTTP request to the Request Falcon server. Returns an
 * associative array:
 *   [ 'ok' => bool, 'status' => int, 'body' => mixed, 'error' => string|null ]
 *
 * The 'body' is JSON-decoded if the response looks like JSON, otherwise
 * the raw string.
 */
function rf_api_request(string $method, string $endpoint, ?array $body = null, ?string $token = null): array
{
    $config = rf_load_config();
    $token = $token ?? $config['token'];
    $url = rtrim($config['apiPath'], '/') . '/' . ltrim($endpoint, '/');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'remotetoken: ' . ($token ?? ''),
        ],
        CURLOPT_USERAGENT => 'RequestFalcon-FPP-Plugin/1.0',
    ]);

    if ($body !== null) {
        $encoded = json_encode($body);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
    }

    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return [
            'ok'     => false,
            'status' => 0,
            'body'   => null,
            'error'  => $err ?: 'Request failed',
        ];
    }

    $decoded = json_decode((string) $raw, true);
    $body = json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;

    return [
        'ok'     => $status >= 200 && $status < 300,
        'status' => (int) $status,
        'body'   => $body,
        'error'  => null,
    ];
}

/**
 * Call FPP's local API. FPP listens on localhost:80 for its own admin
 * API. Returns the parsed response or null on any failure. Used by the
 * listener and the config page's playlist picker.
 */
function rf_fpp_request(string $method, string $endpoint, ?array $body = null): ?array
{
    $url = 'http://localhost/' . ltrim($endpoint, '/');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_TIMEOUT        => 5,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $status < 200 || $status >= 300) {
        return null;
    }
    $decoded = json_decode((string) $raw, true);
    return is_array($decoded) ? $decoded : null;
}
