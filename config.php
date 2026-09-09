<?php
// Request Falcon plugin admin page.
//
// All AJAX goes through FPP's built-in settings API:
//   POST /api/plugin/request-falcon-plugin/settings/<key>  → save
//   GET  /api/plugin/request-falcon-plugin/settings/<key>  → load
// FPP persists these to an INI file at:
//   /home/fpp/media/config/plugin.request-falcon-plugin
// which the listener reads with parse_ini_file().
//
// The plugin name used in these URLs MUST match repoName in pluginInfo.json.
// We compute it from __DIR__ to survive future renames.
?>

<style>
/* All CSS inlined. FPP's plugin.php always returns Content-Type: text/html,
   so external .css files can't load — the browser rejects them for MIME
   mismatch. Same reason JS is inlined below. */

.rf-body {
    max-width: 900px;
    margin: 1em auto;
    padding: 0 1em;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    line-height: 1.5;
}
.rf-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(0, 0, 0, 0.15);
    border-radius: 6px;
    padding: 1.25em 1.5em;
    margin-bottom: 1em;
}
.rf-header-card { text-align: center; }
.rf-title {
    font-size: 1.75em;
    font-weight: 600;
    margin: 0 0 0.25em 0;
}
.rf-subtitle {
    font-size: 0.85em;
    opacity: 0.7;
    margin-bottom: 0.75em;
}
.rf-status {
    display: inline-block;
    padding: 0.4em 0.8em;
    border-radius: 4px;
    font-size: 0.9em;
    background: rgba(0, 0, 0, 0.05);
}
.rf-status-ok    { color: #2e7d32; background: rgba(76, 175, 80, 0.12); }
.rf-status-error { color: #c62828; background: rgba(244, 67, 54, 0.12); }
.rf-status-busy  { color: #ef6c00; background: rgba(255, 152, 0, 0.12); }

.rf-section-heading {
    font-size: 1.1em;
    font-weight: 600;
    border-bottom: 1px solid rgba(0, 0, 0, 0.12);
    padding-bottom: 0.5em;
    margin-bottom: 1em;
}
.rf-field { margin-bottom: 1.5em; }
.rf-field label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.25em;
    font-size: 0.95em;
}
.rf-field-inline label {
    display: inline;
    font-weight: normal;
    cursor: pointer;
}
.rf-field-inline input[type="checkbox"] {
    margin-right: 0.5em;
    transform: scale(1.2);
    vertical-align: middle;
}
.rf-hint {
    font-size: 0.85em;
    opacity: 0.75;
    margin: 0.25em 0 0.75em 0;
    line-height: 1.4;
}
.rf-hint code {
    background: rgba(0, 0, 0, 0.08);
    padding: 0.1em 0.3em;
    border-radius: 3px;
    font-size: 0.9em;
}
.rf-input {
    width: 100%;
    padding: 0.6em 0.8em;
    font-size: 0.95em;
    background: rgba(255, 255, 255, 0.6);
    border: 1px solid rgba(0, 0, 0, 0.2);
    border-radius: 4px;
    color: inherit;
    box-sizing: border-box;
}
.rf-input:focus {
    outline: none;
    border-color: #ff9800;
    background: #fff;
}
.rf-mono {
    font-family: "SF Mono", Menlo, Consolas, monospace;
    font-size: 0.85em;
}
.rf-inline {
    display: flex;
    gap: 0.5em;
    align-items: stretch;
}
.rf-inline select.rf-input { flex: 1; }
.rf-inline button { flex-shrink: 0; }
.rf-actions {
    display: flex;
    gap: 0.75em;
    align-items: center;
    flex-wrap: wrap;
    margin-top: 1em;
}
.rf-inline-status {
    font-size: 0.9em;
    margin-left: 0.5em;
}
.rf-btn {
    padding: 0.55em 1.1em;
    font-size: 0.9em;
    font-weight: 500;
    background: rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.15);
    border-radius: 4px;
    color: inherit;
    cursor: pointer;
    transition: background-color 0.15s, border-color 0.15s;
}
.rf-btn:hover {
    background: rgba(0, 0, 0, 0.15);
    border-color: rgba(0, 0, 0, 0.25);
}
.rf-btn-primary {
    background: #ff9800;
    border-color: #ff9800;
    color: #1a1a1a;
}
.rf-btn-primary:hover {
    background: #ffb040;
    border-color: #ffb040;
}
.rf-btn-danger:hover {
    background: rgba(244, 67, 54, 0.2);
    border-color: #f44336;
    color: #c62828;
}
.rf-log {
    background: rgba(0, 0, 0, 0.08);
    padding: 1em;
    border-radius: 4px;
    max-height: 250px;
    overflow-y: auto;
    font-family: "SF Mono", Menlo, Consolas, monospace;
    font-size: 0.8em;
    line-height: 1.4;
    margin-top: 1em;
    white-space: pre-wrap;
    word-break: break-word;
}
.rf-log:empty { display: none; }
.rf-advanced summary {
    font-weight: 600;
    cursor: pointer;
    padding: 0.25em 0;
    font-size: 1em;
}
.rf-advanced summary:hover { color: #ef6c00; }
.rf-advanced[open] summary {
    margin-bottom: 1em;
    padding-bottom: 0.5em;
    border-bottom: 1px solid rgba(0, 0, 0, 0.12);
}
</style>

<div class="rf-body">

    <div class="rf-card rf-header-card">
        <h1 class="rf-title">Request Falcon</h1>
        <div class="rf-subtitle">Plugin v1.2.0</div>
        <div id="rf-listener-status" class="rf-status">Loading...</div>
    </div>

    <div class="rf-card">
        <div class="rf-section-heading">Connection</div>

        <div class="rf-field">
            <label for="rf-token">Show Token</label>
            <p class="rf-hint">
                Paste your show's plugin token from Request Falcon &rarr; Setup tab &rarr; Plugin tokens.
                Each token connects this Pi to a single show.
            </p>
            <input type="password" id="rf-token" class="rf-input" placeholder="e.g. a UUID like 8f9e2c1a-...">
        </div>

        <div class="rf-field">
            <label for="rf-playlist">Remote Playlist</label>
            <p class="rf-hint">
                The FPP playlist that contains the sequences viewers can request.
            </p>
            <div class="rf-inline">
                <select id="rf-playlist" class="rf-input">
                    <option value="">Loading playlists...</option>
                </select>
                <button id="rf-sync-btn" class="rf-btn rf-btn-primary" type="button">Sync playlist</button>
            </div>
        </div>

        <div class="rf-actions">
            <button id="rf-save-btn" class="rf-btn rf-btn-primary" type="button">Save settings</button>
            <button id="rf-test-btn" class="rf-btn" type="button">Test connectivity</button>
            <span id="rf-save-status" class="rf-inline-status"></span>
        </div>
    </div>

    <div class="rf-card">
        <div class="rf-section-heading">Behavior</div>

        <div class="rf-field rf-field-inline">
            <label for="rf-interrupt">
                <input type="checkbox" id="rf-interrupt">
                Interrupt scheduled playlist for requests/votes
            </label>
            <p class="rf-hint">
                When on, requests play as soon as they arrive.
                When off, requests wait until the current sequence finishes.
            </p>
        </div>
    </div>

    <div class="rf-card">
        <div class="rf-section-heading">Listener</div>
        <p class="rf-hint">
            The listener polls Request Falcon for requests and reports what FPP is playing.
            It starts automatically when FPP boots.
        </p>
        <div class="rf-actions">
            <button id="rf-restart-btn" class="rf-btn" type="button">Restart listener</button>
            <button id="rf-stop-btn" class="rf-btn rf-btn-danger" type="button">Stop listener</button>
        </div>
        <p class="rf-hint" style="margin-top: 0.75em;">
            To view logs, SSH into the Pi and run:
            <code>tail -f /home/fpp/media/logs/request-falcon.log</code>
        </p>
    </div>

    <details class="rf-card rf-advanced">
        <summary>Advanced settings</summary>

        <div class="rf-field">
            <label for="rf-fetch-interval">Request check interval (seconds)</label>
            <p class="rf-hint">How often the listener asks Request Falcon for the next request. Default 3.</p>
            <input type="number" id="rf-fetch-interval" class="rf-input" min="1" max="10" step="1" value="3">
        </div>

        <div class="rf-field">
            <label for="rf-status-interval">FPP status check interval (seconds)</label>
            <p class="rf-hint">How often the listener checks what FPP is currently playing. Default 1.</p>
            <input type="number" id="rf-status-interval" class="rf-input" min="1" max="10" step="1" value="1">
        </div>

        <div class="rf-field rf-field-inline">
            <label for="rf-verbose">
                <input type="checkbox" id="rf-verbose">
                Verbose logging
            </label>
            <p class="rf-hint">Log every API call. Useful for debugging, noisy otherwise.</p>
        </div>

        <div class="rf-field">
            <label for="rf-api-path">Plugin API URL</label>
            <p class="rf-hint">
                The Request Falcon server address. Default:
                <code>https://requestfalcon.com/api/plugin</code>.
            </p>
            <input type="text" id="rf-api-path" class="rf-input rf-mono" value="https://requestfalcon.com/api/plugin">
        </div>
    </details>

</div>

<script>
(function () {
    'use strict';

    // Plugin name — must match repoName in pluginInfo.json exactly, since
    // this is the path FPP uses for settings storage.
    var PLUGIN = 'request-falcon-plugin';

    // FPP settings API URLs
    var SETTINGS_BASE = '/api/plugin/' + PLUGIN + '/settings/';

    var DEFAULTS = {
        token: '',
        remotePlaylist: '',
        apiPath: 'https://requestfalcon.com/api/plugin',
        interruptSchedule: 'false',
        fetchIntervalSec: '3',
        statusCheckIntervalSec: '1',
        verboseLogging: 'false',
        listenerEnabled: 'true',
        listenerRestarting: 'false',
    };

    function $(id) { return document.getElementById(id); }

    function setStatus(el, msg, kind) {
        if (!el) return;
        el.textContent = msg;
        el.className = 'rf-inline-status';
        if (kind === 'ok')    el.classList.add('rf-status-ok');
        if (kind === 'error') el.classList.add('rf-status-error');
        if (kind === 'busy')  el.classList.add('rf-status-busy');
    }

    // ─── FPP settings helpers ────────────────────────────────────────
    // FPP's settings API is unusual — GET returns {"<key>": "<value>"},
    // POST takes the raw value as the body. Wrap both patterns.

    function getSetting(key) {
        return fetch(SETTINGS_BASE + encodeURIComponent(key))
            .then(function (r) {
                if (!r.ok) return DEFAULTS[key] || '';
                return r.json().then(function (data) {
                    // FPP wraps the value as { "<key>": "value" }
                    var val = data[key];
                    if (typeof val === 'string') return val;
                    return DEFAULTS[key] || '';
                });
            })
            .catch(function () { return DEFAULTS[key] || ''; });
    }

    function setSetting(key, value) {
        return fetch(SETTINGS_BASE + encodeURIComponent(key), {
            method: 'POST',
            headers: { 'Content-Type': 'text/plain' },
            body: String(value),
        }).then(function (r) {
            return r.ok;
        }).catch(function () { return false; });
    }

    // ─── FPP local API helpers ──────────────────────────────────────

    function fppGet(path) {
        return fetch(path).then(function (r) {
            return r.ok ? r.json() : null;
        }).catch(function () { return null; });
    }

    // ─── Load initial settings + populate form ──────────────────────

    function loadSettings() {
        return Promise.all([
            getSetting('token').then(function (v) { $('rf-token').value = v; }),
            getSetting('apiPath').then(function (v) { $('rf-api-path').value = v; }),
            getSetting('interruptSchedule').then(function (v) { $('rf-interrupt').checked = v === 'true'; }),
            getSetting('fetchIntervalSec').then(function (v) { $('rf-fetch-interval').value = parseInt(v, 10) || 3; }),
            getSetting('statusCheckIntervalSec').then(function (v) { $('rf-status-interval').value = parseInt(v, 10) || 1; }),
            getSetting('verboseLogging').then(function (v) { $('rf-verbose').checked = v === 'true'; }),
        ]);
    }

    // Playlist picker: fetch from FPP, remember selected value from settings
    function loadPlaylists() {
        return Promise.all([
            fppGet('/api/playlists'),
            getSetting('remotePlaylist'),
        ]).then(function (results) {
            var playlists = Array.isArray(results[0]) ? results[0] : [];
            var selected = results[1];
            var picker = $('rf-playlist');
            picker.innerHTML = '';

            var placeholderOpt = document.createElement('option');
            placeholderOpt.value = '';
            placeholderOpt.textContent = '\u2014 pick a playlist \u2014';
            picker.appendChild(placeholderOpt);

            playlists.forEach(function (name) {
                var opt = document.createElement('option');
                opt.value = name;
                opt.textContent = name;
                if (name === selected) opt.selected = true;
                picker.appendChild(opt);
            });
        });
    }

    // ─── Save all settings back to FPP ───────────────────────────────

    function saveSettings() {
        var s = $('rf-save-status');
        setStatus(s, 'Saving...', 'busy');
        return Promise.all([
            setSetting('token', $('rf-token').value.trim()),
            setSetting('apiPath', $('rf-api-path').value.trim()),
            setSetting('interruptSchedule', $('rf-interrupt').checked ? 'true' : 'false'),
            setSetting('fetchIntervalSec', String(parseInt($('rf-fetch-interval').value, 10) || 3)),
            setSetting('statusCheckIntervalSec', String(parseInt($('rf-status-interval').value, 10) || 1)),
            setSetting('verboseLogging', $('rf-verbose').checked ? 'true' : 'false'),
        ]).then(function (results) {
            var allOk = results.every(function (ok) { return ok; });
            if (allOk) {
                setStatus(s, 'Saved', 'ok');
                setTimeout(function () { setStatus(s, '', null); }, 3000);
            } else {
                setStatus(s, 'Some settings failed to save', 'error');
            }
            return allOk;
        });
    }

    // ─── Test connectivity: hit Request Falcon directly from browser ─

    function testConnectivity() {
        var s = $('rf-save-status');
        setStatus(s, 'Testing...', 'busy');
        saveSettings().then(function () {
            var apiPath = $('rf-api-path').value.trim();
            var token = $('rf-token').value.trim();
            if (!token) {
                setStatus(s, 'No token configured', 'error');
                return;
            }
            var url = apiPath.replace(/\/$/, '') + '/q/health';
            var startMs = Date.now();
            fetch(url, {
                headers: { 'remotetoken': token },
            }).then(function (r) {
                var latencyMs = Date.now() - startMs;
                return r.json().then(function (data) {
                    if (r.ok && data && data.ok) {
                        var msg = 'Connected';
                        if (data.showName) msg += ' \u2014 show: ' + data.showName;
                        msg += ' (' + latencyMs + 'ms)';
                        setStatus(s, msg, 'ok');
                    } else {
                        var err = (data && data.error) || ('HTTP ' + r.status);
                        setStatus(s, 'Failed: ' + err, 'error');
                    }
                });
            }).catch(function (err) {
                setStatus(s, 'Network error: ' + (err && err.message), 'error');
            });
        });
    }

    // ─── Sync playlist: read from FPP, POST to Request Falcon ───────

    function syncPlaylist() {
        var picker = $('rf-playlist');
        var name = picker.value;
        var s = $('rf-save-status');
        if (!name) {
            setStatus(s, 'Pick a playlist first', 'error');
            return;
        }
        setStatus(s, 'Syncing "' + name + '"...', 'busy');

        // Save the picked playlist name first, then sync
        setSetting('remotePlaylist', name)
            .then(saveSettings)
            .then(function () {
                return fppGet('/api/playlist/' + encodeURIComponent(name));
            })
            .then(function (playlist) {
                if (!playlist) {
                    setStatus(s, 'Could not load playlist from FPP', 'error');
                    return null;
                }
                var items = Array.isArray(playlist.mainPlaylist) ? playlist.mainPlaylist : [];
                var sequences = [];
                var index = 0;
                items.forEach(function (item) {
                    var type = item.type;
                    var seqName = null, duration = null;
                    if (type === 'both' || type === 'sequence') {
                        if (typeof item.sequenceName === 'string' && item.sequenceName) {
                            seqName = item.sequenceName.replace(/\.fseq$/i, '');
                            duration = typeof item.duration === 'number' ? item.duration : null;
                        }
                    } else if (type === 'media') {
                        if (typeof item.mediaName === 'string' && item.mediaName) {
                            seqName = item.mediaName.replace(/\.(mp3|mp4|wav|ogg|flac|m4a)$/i, '');
                        }
                    }
                    if (!seqName) return;
                    sequences.push({ sequence: seqName, index: index, duration: duration });
                    index++;
                });
                if (sequences.length === 0) {
                    setStatus(s, 'Playlist has no requestable sequences', 'error');
                    return null;
                }
                var apiPath = $('rf-api-path').value.trim().replace(/\/$/, '');
                var token = $('rf-token').value.trim();
                return fetch(apiPath + '/syncPlaylists', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'remotetoken': token,
                    },
                    body: JSON.stringify({ playlists: sequences }),
                }).then(function (r) {
                    return r.json().then(function (data) {
                        if (r.ok && data) {
                            var count = data.total || sequences.length;
                            var msg = 'Synced ' + count + ' sequence' + (count === 1 ? '' : 's');
                            if (data.inserted) msg += ' (' + data.inserted + ' new)';
                            setStatus(s, msg, 'ok');
                        } else {
                            var err = (data && data.error) || ('HTTP ' + r.status);
                            setStatus(s, 'Sync failed: ' + err, 'error');
                        }
                    });
                });
            })
            .catch(function (err) {
                setStatus(s, 'Error: ' + (err && err.message), 'error');
            });
    }

    // ─── Listener control via settings flags ────────────────────────

    function restartListener() {
        var s = $('rf-save-status');
        setStatus(s, 'Restarting listener...', 'busy');
        // Set restarting=true, listener sees it and exits; postStart-like
        // mechanism should relaunch. Also ensure enabled=true.
        Promise.all([
            setSetting('listenerEnabled', 'true'),
            setSetting('listenerRestarting', 'true'),
        ]).then(function () {
            setStatus(s, 'Restart requested. May take a few seconds.', 'ok');
            setTimeout(refreshListenerStatus, 3000);
        });
    }

    function stopListener() {
        if (!confirm("Stop the listener? It won't restart until you click Restart.")) return;
        var s = $('rf-save-status');
        setStatus(s, 'Stopping...', 'busy');
        setSetting('listenerEnabled', 'false').then(function () {
            setStatus(s, 'Listener stop requested', 'ok');
            setTimeout(refreshListenerStatus, 3000);
        });
    }

    function refreshListenerStatus() {
        // Check the heartbeat timestamp the listener writes every loop
        // iteration. Fresh (<15 sec old) means it's actually running.
        // Compare against enabled flag to distinguish stopped vs crashed.
        var el = $('rf-listener-status');
        Promise.all([
            getSetting('listenerEnabled'),
            getSetting('listenerRestarting'),
            getSetting('listenerLastHeartbeat'),
        ]).then(function (results) {
            var enabled = results[0] === 'true';
            var restarting = results[1] === 'true';
            var heartbeat = parseInt(results[2] || '0', 10);
            var nowSec = Math.floor(Date.now() / 1000);
            var heartbeatAge = nowSec - heartbeat;
            // Consider alive if heartbeat is within 15 seconds. Listener
            // loops every 1 sec so this gives plenty of margin.
            var alive = heartbeat > 0 && heartbeatAge < 15;

            if (!enabled) {
                el.textContent = 'Listener stopped (disabled in settings)';
                el.className = 'rf-status rf-status-error';
            } else if (restarting) {
                el.textContent = 'Listener restarting...';
                el.className = 'rf-status rf-status-busy';
            } else if (alive) {
                el.textContent = 'Listener running (last heartbeat ' + heartbeatAge + 's ago)';
                el.className = 'rf-status rf-status-ok';
            } else if (heartbeat === 0) {
                el.textContent = 'Listener enabled but never started — try Restart';
                el.className = 'rf-status rf-status-error';
            } else {
                el.textContent = 'Listener not responding (last heartbeat ' + heartbeatAge + 's ago) — likely crashed';
                el.className = 'rf-status rf-status-error';
            }
        });
    }

    // ─── Init ────────────────────────────────────────────────────────

    function init() {
        var el;
        if ((el = $('rf-save-btn')))    el.addEventListener('click', saveSettings);
        if ((el = $('rf-test-btn')))    el.addEventListener('click', testConnectivity);
        if ((el = $('rf-sync-btn')))    el.addEventListener('click', syncPlaylist);
        if ((el = $('rf-restart-btn'))) el.addEventListener('click', restartListener);
        if ((el = $('rf-stop-btn')))    el.addEventListener('click', stopListener);

        loadSettings();
        loadPlaylists();
        refreshListenerStatus();
        setInterval(refreshListenerStatus, 5000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
