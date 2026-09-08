<?php
require_once __DIR__ . '/lib.php';

// Load current config so the form is pre-filled
$config = rf_load_config();

// Get list of FPP playlists so the picker is populated. If FPP is
// unreachable (unusual since we're literally running on the same box),
// we get null and show an empty picker.
$playlists = rf_fpp_request('GET', 'api/playlists');
if (!is_array($playlists)) {
    $playlists = [];
}

// Get FPP version for display
$fppStatus = rf_fpp_request('GET', 'api/fppd/status');
$fppVersion = is_array($fppStatus) && isset($fppStatus['version']) ? $fppStatus['version'] : 'unknown';

// Build the URL prefix for our assets. FPP serves plugin static files
// via plugin.php with a file= query param — this pattern works reliably
// regardless of the URL the plugin page itself is served under.
$pluginName = basename(__DIR__);
$assetBase = 'plugin.php?plugin=' . urlencode($pluginName) . '&file=';
?>
<link rel="stylesheet" type="text/css" href="<?php echo $assetBase; ?>css/admin.css">

<div class="rf-plugin-body">

  <div class="rf-card rf-header-card">
    <h1 class="rf-title">Request Falcon</h1>
    <div class="rf-subtitle">Plugin v1.0.0 &middot; FPP <?php echo htmlspecialchars($fppVersion); ?></div>
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
        Must exist on this Pi.
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
        When on, requests will play as soon as they arrive.
        When off, requests wait until the current sequence finishes.
      </p>
    </div>
  </div>

  <div class="rf-card">
    <div class="rf-section-heading">Listener</div>
    <p class="rf-hint">
      The listener is the background process that polls Request Falcon
      for requests and reports what FPP is playing. It starts
      automatically when FPP boots.
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

<script src="<?php echo $assetBase; ?>js/admin.js"></script>
