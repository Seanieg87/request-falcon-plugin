<?php
require_once __DIR__ . '/lib.php';

$config = rf_load_config();

// Get list of FPP playlists for the picker
$playlists = rf_fpp_request('GET', 'api/playlists');
if (!is_array($playlists)) {
    $playlists = [];
}

// Plugin name for building AJAX URLs — matches the folder name FPP
// installed us under. Fetched dynamically so it survives future renames.
$pluginName = basename(__DIR__);
?>

<style>
/* All CSS inlined. FPP plugin.php can't serve external .css files
   correctly — it renders them as HTML pages instead of returning raw
   file content. Learned this the hard way. */

.rf-body {
    max-width: 900px;
    margin: 1em auto;
    padding: 0 1em;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    line-height: 1.5;
}
.rf-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 6px;
    padding: 1.25em 1.5em;
    margin-bottom: 1em;
}
.rf-header-card {
    text-align: center;
}
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
    background: rgba(255, 255, 255, 0.05);
}
.rf-status-ok    { color: #4caf50; background: rgba(76, 175, 80, 0.1); }
.rf-status-error { color: #f44336; background: rgba(244, 67, 54, 0.1); }
.rf-status-busy  { color: #ff9800; background: rgba(255, 152, 0, 0.1); }

.rf-section-heading {
    font-size: 1.1em;
    font-weight: 600;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
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
    background: rgba(0, 0, 0, 0.3);
    padding: 0.1em 0.3em;
    border-radius: 3px;
    font-size: 0.9em;
}
.rf-input {
    width: 100%;
    padding: 0.6em 0.8em;
    font-size: 0.95em;
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 4px;
    color: inherit;
    box-sizing: border-box;
}
.rf-input:focus {
    outline: none;
    border-color: #ff9800;
    background: rgba(0, 0, 0, 0.4);
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
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 4px;
    color: inherit;
    cursor: pointer;
    transition: background-color 0.15s, border-color 0.15s;
}
.rf-btn:hover {
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(255, 255, 255, 0.25);
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
    color: #f44336;
}
.rf-log {
    background: rgba(0, 0, 0, 0.5);
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
    color: rgba(255, 255, 255, 0.85);
}
.rf-log:empty { display: none; }
.rf-advanced summary {
    font-weight: 600;
    cursor: pointer;
    padding: 0.25em 0;
    font-size: 1em;
}
.rf-advanced summary:hover { color: #ff9800; }
.rf-advanced[open] summary {
    margin-bottom: 1em;
    padding-bottom: 0.5em;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}
</style>

<div class="rf-body">

    <div class="rf-card rf-header-card">
        <h1 class="rf-title">Request Falcon</h1>
        <div class="rf-subtitle">Plugin v1.1.0</div>
        <div id="rf-listener-status" class="rf-status">Checking listener status...</div>
    </div>

    <div class="rf-card">
        <div class="rf-section-heading">Connection</div>

        <div class="rf-field">
            <label for="rf-token">Show Token</label>
            <p class="rf-hint">
                Paste your show's plugin token from Request Falcon &rarr; Setup tab &rarr; Plugin tokens.
                Each token connects this Pi to a single show.
            </p>
            <input type="password" id="rf-token" class="rf-input" value="<?php echo htmlspecialchars($config['token']); ?>" placeholder="e.g. a UUID like 8f9e2c1a-...">
        </div>

        <div class="rf-field">
            <label for="rf-playlist">Remote Playlist</label>
            <p class="rf-hint">
                The FPP playlist that contains the sequences viewers can request.
            </p>
            <div class="rf-inline">
                <select id="rf-playlist" class="rf-input">
                    <option value="">&mdash; pick a playlist &mdash;</option>
                    <?php foreach ($playlists as $name): ?>
                        <option value="<?php echo htmlspecialchars($name); ?>"
                                <?php echo ($name === $config['remotePlaylist']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($name); ?>
                        </option>
                    <?php endforeach; ?>
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
                <input type="checkbox" id="rf-interrupt" <?php echo $config['interruptSchedule'] ? 'checked' : ''; ?>>
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
            <button id="rf-tail-btn" class="rf-btn" type="button">Show log tail</button>
        </div>
        <pre id="rf-log-output" class="rf-log"></pre>
    </div>

    <details class="rf-card rf-advanced">
        <summary>Advanced settings</summary>

        <div class="rf-field">
            <label for="rf-fetch-interval">Request check interval (seconds)</label>
            <p class="rf-hint">How often the listener asks Request Falcon for the next request. Default 3.</p>
            <input type="number" id="rf-fetch-interval" class="rf-input" min="1" max="10" step="1" value="<?php echo (int) $config['fetchIntervalSec']; ?>">
        </div>

        <div class="rf-field">
            <label for="rf-status-interval">FPP status check interval (seconds)</label>
            <p class="rf-hint">How often the listener checks what FPP is currently playing. Default 1.</p>
            <input type="number" id="rf-status-interval" class="rf-input" min="1" max="10" step="1" value="<?php echo (int) $config['statusCheckIntervalSec']; ?>">
        </div>

        <div class="rf-field rf-field-inline">
            <label for="rf-verbose">
                <input type="checkbox" id="rf-verbose" <?php echo $config['verboseLogging'] ? 'checked' : ''; ?>>
                Verbose logging
            </label>
            <p class="rf-hint">Log every API call. Useful for debugging, noisy otherwise.</p>
        </div>

        <div class="rf-field">
            <label for="rf-api-path">Plugin API URL</label>
            <p class="rf-hint">
                The Request Falcon server address. Default:
                <code>https://requestfalcon.com/api/plugin</code>.
                Don't change unless you know what you're doing.
            </p>
            <input type="text" id="rf-api-path" class="rf-input rf-mono" value="<?php echo htmlspecialchars($config['apiPath']); ?>">
        </div>
    </details>

</div>

<script>
// All JS inlined. FPP plugin.php can't serve external .js files
// correctly. This runs in the FPP admin page's jQuery-enabled context
// but we use vanilla fetch() to avoid any jQuery version dependency.

(function () {
    'use strict';

    // Plugin name from PHP so URL building matches wherever FPP put us
    var PLUGIN = <?php echo json_encode($pluginName); ?>;

    // AJAX endpoint URLs. Each button hits its own PHP file.
    // Pattern: plugin.php?plugin=<name>&file=<script>.php&nopage=1
    // The nopage=1 strips FPP's admin chrome so we get raw JSON.
    function endpoint(scriptName) {
        return 'plugin.php?plugin=' + encodeURIComponent(PLUGIN) +
               '&file=' + encodeURIComponent(scriptName) + '&nopage=1';
    }

    function $(id) { return document.getElementById(id); }

    function setStatus(el, msg, kind) {
        if (!el) return;
        el.textContent = msg;
        el.className = 'rf-inline-status';
        if (kind === 'ok')    el.classList.add('rf-status-ok');
        if (kind === 'error') el.classList.add('rf-status-error');
        if (kind === 'busy')  el.classList.add('rf-status-busy');
    }

    function collectConfig() {
        return {
            token:                  $('rf-token').value.trim(),
            apiPath:                $('rf-api-path').value.trim(),
            interruptSchedule:      $('rf-interrupt').checked,
            fetchIntervalSec:       parseInt($('rf-fetch-interval').value, 10) || 3,
            statusCheckIntervalSec: parseInt($('rf-status-interval').value, 10) || 1,
            verboseLogging:         $('rf-verbose').checked,
        };
    }

    function api(scriptName, opts) {
        opts = opts || {};
        var init = {
            method: opts.method || 'GET',
            headers: { 'Content-Type': 'application/json' },
        };
        if (opts.body) init.body = JSON.stringify(opts.body);
        return fetch(endpoint(scriptName), init)
            .then(function (r) {
                return r.text().then(function (text) {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        return {
                            ok: false,
                            error: 'Server returned non-JSON: ' + text.slice(0, 200),
                        };
                    }
                });
            })
            .catch(function (err) {
                return { ok: false, error: 'Network error: ' + (err && err.message) };
            });
    }

    // ─── Actions ────────────────────────────────────────────────────

    function saveSettings() {
        var s = $('rf-save-status');
        setStatus(s, 'Saving...', 'busy');
        api('rf_save.php', { method: 'POST', body: collectConfig() })
            .then(function (res) {
                if (res.ok) {
                    setStatus(s, 'Saved', 'ok');
                    setTimeout(function () { setStatus(s, '', null); }, 3000);
                } else {
                    setStatus(s, res.error || 'Save failed', 'error');
                }
            });
    }

    function testConnectivity() {
        var s = $('rf-save-status');
        setStatus(s, 'Testing...', 'busy');
        // Save first so test uses current form values
        api('rf_save.php', { method: 'POST', body: collectConfig() })
            .then(function () { return api('rf_test.php'); })
            .then(function (res) {
                if (res.ok) {
                    var msg = 'Connected';
                    if (res.showName) msg += ' — show: ' + res.showName;
                    if (typeof res.latencyMs === 'number') msg += ' (' + res.latencyMs + 'ms)';
                    setStatus(s, msg, 'ok');
                } else {
                    setStatus(s, res.error || 'Test failed', 'error');
                }
            });
    }

    function syncPlaylist() {
        var picker = $('rf-playlist');
        var name = picker.value;
        var s = $('rf-save-status');
        if (!name) {
            setStatus(s, 'Pick a playlist first', 'error');
            return;
        }
        setStatus(s, 'Syncing "' + name + '"...', 'busy');
        api('rf_save.php', { method: 'POST', body: collectConfig() })
            .then(function () {
                return api('rf_sync.php', { method: 'POST', body: { playlist: name } });
            })
            .then(function (res) {
                if (res.ok) {
                    var msg = 'Synced ' + (res.total || 0) + ' sequence' +
                              ((res.total === 1) ? '' : 's');
                    if (res.inserted) msg += ' (' + res.inserted + ' new)';
                    setStatus(s, msg, 'ok');
                } else {
                    setStatus(s, res.error || 'Sync failed', 'error');
                }
            });
    }

    function restartListener() {
        var s = $('rf-save-status');
        setStatus(s, 'Restarting listener...', 'busy');
        api('rf_listener_restart.php', { method: 'POST' })
            .then(function (res) {
                if (res.ok) {
                    setStatus(s, 'Listener restarted (PID ' + res.pid + ')', 'ok');
                    refreshStatus();
                } else {
                    setStatus(s, res.error || 'Restart failed', 'error');
                }
            });
    }

    function stopListener() {
        if (!confirm("Stop the listener? It won't restart until you click Restart.")) return;
        var s = $('rf-save-status');
        setStatus(s, 'Stopping...', 'busy');
        api('rf_listener_stop.php', { method: 'POST' })
            .then(function (res) {
                if (res.ok) {
                    setStatus(s, 'Listener stopped', 'ok');
                    refreshStatus();
                } else {
                    setStatus(s, res.error || 'Stop failed', 'error');
                }
            });
    }

    function tailLog() {
        var out = $('rf-log-output');
        out.textContent = 'Loading log...';
        api('rf_tail_log.php').then(function (res) {
            if (res.ok && Array.isArray(res.lines)) {
                out.textContent = res.lines.length ? res.lines.join('\n') : '(log is empty)';
            } else {
                out.textContent = res.error || 'Could not read log';
            }
        });
    }

    function refreshStatus() {
        var el = $('rf-listener-status');
        api('rf_listener_status.php').then(function (res) {
            if (res.ok) {
                if (res.running) {
                    el.textContent = 'Listener running (PID ' + res.pid + ')';
                    el.className = 'rf-status rf-status-ok';
                } else {
                    el.textContent = 'Listener not running';
                    el.className = 'rf-status rf-status-error';
                }
            } else {
                el.textContent = 'Could not check listener status';
                el.className = 'rf-status rf-status-error';
            }
        });
    }

    // ─── Wire up ────────────────────────────────────────────────────

    function init() {
        var el;
        if ((el = $('rf-save-btn')))    el.addEventListener('click', saveSettings);
        if ((el = $('rf-test-btn')))    el.addEventListener('click', testConnectivity);
        if ((el = $('rf-sync-btn')))    el.addEventListener('click', syncPlaylist);
        if ((el = $('rf-restart-btn'))) el.addEventListener('click', restartListener);
        if ((el = $('rf-stop-btn')))    el.addEventListener('click', stopListener);
        if ((el = $('rf-tail-btn')))    el.addEventListener('click', tailLog);

        refreshStatus();
        setInterval(refreshStatus, 10000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
