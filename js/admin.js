/**
 * Request Falcon admin config page interactivity.
 *
 * Vanilla JS — no jQuery. Talks to api.php for all persistent actions.
 * Every AJAX call goes through apiCall() which handles error display
 * consistently.
 */
(function () {
  'use strict';

  // Build the API base URL. api.php sits next to config.php, so we use
  // FPP's plugin.php file-serving pattern with a nopage query param to
  // avoid FPP's normal page-wrapper rendering.
  //
  // We derive the plugin name from the current script's URL rather than
  // hardcoding "request-falcon-plugin" — this lets someone rename the
  // installed folder without breaking the JS.
  var scriptTag = document.currentScript ||
    document.querySelector('script[src*="admin.js"]');
  var pluginName = 'request-falcon-plugin';
  if (scriptTag && scriptTag.src) {
    var match = scriptTag.src.match(/[?&]plugin=([^&]+)/);
    if (match) pluginName = decodeURIComponent(match[1]);
  }
  var API_BASE = 'plugin.php?plugin=' + encodeURIComponent(pluginName) +
                 '&file=api.php&nopage=1&action=';

  // ─── Utilities ──────────────────────────────────────────────────

  function $(id) { return document.getElementById(id); }

  function setStatus(el, message, kind) {
    if (!el) return;
    el.textContent = message;
    el.className = 'rf-inline-status';
    if (kind === 'ok')    el.classList.add('rf-status-ok');
    if (kind === 'error') el.classList.add('rf-status-error');
    if (kind === 'busy')  el.classList.add('rf-status-busy');
  }

  function apiCall(action, opts) {
    opts = opts || {};
    var url = API_BASE + encodeURIComponent(action);
    var init = {
      method: opts.method || 'GET',
      headers: { 'Content-Type': 'application/json' },
    };
    if (opts.body) {
      init.body = JSON.stringify(opts.body);
    }
    return fetch(url, init)
      .then(function (resp) {
        return resp.json().catch(function () {
          return { ok: false, error: 'Server returned non-JSON response' };
        });
      })
      .catch(function (err) {
        return { ok: false, error: 'Network error: ' + (err && err.message) };
      });
  }

  function collectConfigFromForm() {
    return {
      token:                  $('rf-token').value.trim(),
      apiPath:                $('rf-api-path').value.trim(),
      interruptSchedule:      $('rf-interrupt').checked,
      fetchIntervalSec:       parseInt($('rf-fetch-interval').value, 10) || 3,
      statusCheckIntervalSec: parseInt($('rf-status-interval').value, 10) || 1,
      verboseLogging:         $('rf-verbose').checked,
    };
  }

  // ─── Actions ────────────────────────────────────────────────────

  function saveSettings() {
    var statusEl = $('rf-save-status');
    setStatus(statusEl, 'Saving…', 'busy');
    apiCall('saveConfig', { method: 'POST', body: collectConfigFromForm() })
      .then(function (res) {
        if (res.ok) {
          setStatus(statusEl, 'Saved', 'ok');
          setTimeout(function () { setStatus(statusEl, '', null); }, 3000);
        } else {
          setStatus(statusEl, res.error || 'Save failed', 'error');
        }
      });
  }

  function testConnectivity() {
    var statusEl = $('rf-save-status');
    setStatus(statusEl, 'Testing…', 'busy');
    // Save first so testConnect uses the latest values
    apiCall('saveConfig', { method: 'POST', body: collectConfigFromForm() })
      .then(function () {
        return apiCall('testConnect');
      })
      .then(function (res) {
        if (res.ok) {
          var msg = 'Connected';
          if (res.showName) msg += ' — show: ' + res.showName;
          if (typeof res.latencyMs === 'number') msg += ' (' + res.latencyMs + 'ms)';
          setStatus(statusEl, msg, 'ok');
        } else {
          setStatus(statusEl, res.error || 'Test failed', 'error');
        }
      });
  }

  function syncPlaylist() {
    var picker = $('rf-playlist');
    var playlist = picker.value;
    var statusEl = $('rf-save-status');
    if (!playlist) {
      setStatus(statusEl, 'Pick a playlist first', 'error');
      return;
    }
    setStatus(statusEl, 'Syncing "' + playlist + '"…', 'busy');
    // Save current form state first so token/URL are current
    apiCall('saveConfig', { method: 'POST', body: collectConfigFromForm() })
      .then(function () {
        return apiCall('syncPlaylist', { method: 'POST', body: { playlist: playlist } });
      })
      .then(function (res) {
        if (res.ok) {
          var counts = 'Synced ' + (res.total || 0) + ' sequence' +
                       ((res.total === 1) ? '' : 's');
          if (res.inserted) counts += ' (' + res.inserted + ' new)';
          setStatus(statusEl, counts, 'ok');
        } else {
          setStatus(statusEl, res.error || 'Sync failed', 'error');
        }
      });
  }

  function restartListener() {
    var statusEl = $('rf-save-status');
    setStatus(statusEl, 'Restarting listener…', 'busy');
    apiCall('restartListener', { method: 'POST' })
      .then(function (res) {
        if (res.ok) {
          setStatus(statusEl, 'Listener restarted (PID ' + res.pid + ')', 'ok');
          refreshListenerStatus();
        } else {
          setStatus(statusEl, res.error || 'Restart failed', 'error');
        }
      });
  }

  function stopListener() {
    if (!confirm('Stop the listener? It won\'t restart until you click Restart.')) return;
    var statusEl = $('rf-save-status');
    setStatus(statusEl, 'Stopping…', 'busy');
    apiCall('stopListener', { method: 'POST' })
      .then(function (res) {
        if (res.ok) {
          setStatus(statusEl, 'Listener stopped', 'ok');
          refreshListenerStatus();
        } else {
          setStatus(statusEl, res.error || 'Stop failed', 'error');
        }
      });
  }

  function tailLog() {
    var out = $('rf-log-output');
    out.textContent = 'Loading log…';
    apiCall('tailLog').then(function (res) {
      if (res.ok && Array.isArray(res.lines)) {
        out.textContent = res.lines.length
          ? res.lines.join('\n')
          : '(log is empty)';
      } else {
        out.textContent = res.error || 'Could not read log';
      }
    });
  }

  function refreshListenerStatus() {
    var statusEl = $('rf-listener-status');
    apiCall('listenerStatus').then(function (res) {
      if (res.ok) {
        if (res.running) {
          statusEl.textContent = 'Listener running (PID ' + res.pid + ')';
          statusEl.className = 'rf-status rf-status-ok';
        } else {
          statusEl.textContent = 'Listener not running';
          statusEl.className = 'rf-status rf-status-error';
        }
      } else {
        statusEl.textContent = 'Could not check listener status';
        statusEl.className = 'rf-status rf-status-error';
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

    refreshListenerStatus();
    // Periodically refresh listener status so if the user starts/stops
    // the listener via terminal or another admin session, the UI stays
    // in sync without a manual page reload.
    setInterval(refreshListenerStatus, 10000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
