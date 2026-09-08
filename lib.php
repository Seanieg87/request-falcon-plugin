<?php
/**
 * Shared helpers for the Request Falcon plugin.
 *
 * Included by config.php, all rf_*.php AJAX endpoints, and listener.php.
 * Everything here is safe to include multiple times (guarded with
 * function_exists / defined).
 */

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

if (!function_exists('rf_load_config')) {
    /**
     * Load plugin config from disk. Returns array with defaults for any
     * missing keys. Never throws — a missing/malformed file returns defaults.
     */
    function rf_load_config(): array
    {
        $defaults = [
            'apiPath'                => 'https://requestfalcon.com/api/plugin',
            'token'                  => '',
            'remotePlaylist'         => '',
            'interruptSchedule'      => false,
            'fetchIntervalSec'       => 3,
            'statusCheckIntervalSec' => 1,
            'verboseLogging'         => false,
            'listenerEnabled'        => true,
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
        return array_replace($defaults, $parsed);
    }
}

if (!function_exists('rf_save_config')) {
    /**
     * Save plugin config atomically. Returns true on success.
     */
    function rf_save_config(array $config): bool
    {
        $dir = dirname(RF_CONFIG_FILE);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return false;
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
}

if (!function_exists('rf_log')) {
    /**
     * Append a line to the plugin log. Silent on failure.
     */
    function rf_log(string $message): void
    {
        $dir = dirname(RF_LOG_FILE);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents(
            RF_LOG_FILE,
            '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n",
            FILE_APPEND
        );
    }
}

if (!function_exists('rf_tail_log')) {
    /**
     * Return last N lines from the log (newest last). Empty array if unavailable.
     */
    function rf_tail_log(int $lines = 50): array
    {
        if (!is_readable(RF_LOG_FILE)) {
            return [];
        }
        $all = @file(RF_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($all)) {
            return [];
        }
        return array_slice($all, -max(1, $lines));
    }
}

if (!function_exists('rf_api_request')) {
    /**
     * Call Request Falcon server. Returns:
     *   ['ok' => bool, 'status' => int, 'body' => mixed, 'error' => string|null]
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
            CURLOPT_USERAGENT => 'RequestFalcon-FPP-Plugin/1.1',
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $raw = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return [
                'ok' => false, 'status' => 0, 'body' => null,
                'error' => $err ?: 'Request failed',
            ];
        }
        $decoded = json_decode((string) $raw, true);
        $body_out = json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => (int) $status,
            'body' => $body_out,
            'error' => null,
        ];
    }
}

if (!function_exists('rf_fpp_request')) {
    /**
     * Call FPP's local API. Returns parsed response or null on any failure.
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
}

if (!function_exists('rf_find_listener_pid')) {
    /**
     * Find the running listener PID via pgrep on the listener path.
     * Returns int PID or null.
     */
    function rf_find_listener_pid(): ?int
    {
        $listener = RF_PLUGIN_ROOT . '/listener.php';
        $out = @shell_exec('pgrep -f ' . escapeshellarg($listener) . ' 2>/dev/null');
        if ($out === null) return null;
        $lines = array_filter(array_map('trim', explode("\n", $out)));
        foreach ($lines as $line) {
            $pid = (int) $line;
            if ($pid > 0 && $pid !== getmypid()) {
                return $pid;
            }
        }
        return null;
    }
}

if (!function_exists('rf_json_body')) {
    /**
     * Read the request body as JSON. Returns array or null.
     */
    function rf_json_body(): ?array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return null;
        }
        $parsed = json_decode($raw, true);
        return is_array($parsed) ? $parsed : null;
    }
}

if (!function_exists('rf_send_json')) {
    /**
     * Send a JSON response and exit. Sets Content-Type. Optional status code.
     */
    function rf_send_json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
