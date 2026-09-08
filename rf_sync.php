<?php
require_once __DIR__ . '/lib.php';

$body = rf_json_body();
$playlistName = is_array($body) && isset($body['playlist']) ? (string) $body['playlist'] : '';
if ($playlistName === '') {
    rf_send_json(['ok' => false, 'error' => 'No playlist selected']);
}

// Fetch playlist details from FPP
$playlist = rf_fpp_request('GET', 'api/playlist/' . rawurlencode($playlistName));
if (!is_array($playlist)) {
    rf_send_json([
        'ok' => false,
        'error' => 'Could not load playlist "' . $playlistName . '" from FPP',
    ]);
}

$items = is_array($playlist['mainPlaylist'] ?? null) ? $playlist['mainPlaylist'] : [];

// Convert FPP playlist items to Request Falcon's sequence format
$sequences = [];
$index = 0;
foreach ($items as $item) {
    $type = $item['type'] ?? '';
    $sequenceName = null;
    $duration = null;

    if ($type === 'both' || $type === 'sequence') {
        $raw = $item['sequenceName'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $sequenceName = preg_replace('/\.fseq$/i', '', $raw);
        }
        $duration = isset($item['duration']) ? (float) $item['duration'] : null;
    } elseif ($type === 'media') {
        $raw = $item['mediaName'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $sequenceName = preg_replace('/\.(mp3|mp4|wav|ogg|flac|m4a)$/i', '', $raw);
        }
    } else {
        continue;  // skip command/other item types
    }

    if (!$sequenceName) continue;

    $sequences[] = [
        'sequence' => $sequenceName,
        'index'    => $index,
        'duration' => $duration,
    ];
    $index++;
}

if (empty($sequences)) {
    rf_send_json(['ok' => false, 'error' => 'Playlist has no requestable sequences']);
}

// Send to Request Falcon
$res = rf_api_request('POST', '/syncPlaylists', ['playlists' => $sequences]);
if (!$res['ok']) {
    $msg = $res['status'] === 401
        ? 'Token rejected'
        : ($res['status'] === 0
            ? ($res['error'] ?? 'network error')
            : 'HTTP ' . $res['status']);
    rf_send_json(['ok' => false, 'error' => 'Sync failed: ' . $msg]);
}

// Save the playlist name so the listener knows what to poll
$config = rf_load_config();
$config['remotePlaylist'] = $playlistName;
rf_save_config($config);

rf_log('Synced playlist "' . $playlistName . '" (' . count($sequences) . ' sequences)');

$serverStats = is_array($res['body']) ? $res['body'] : [];
rf_send_json([
    'ok' => true,
    'total'    => $serverStats['total']    ?? count($sequences),
    'inserted' => $serverStats['inserted'] ?? 0,
    'updated'  => $serverStats['updated']  ?? 0,
]);
