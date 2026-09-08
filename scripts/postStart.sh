#!/bin/bash
# Request Falcon plugin — post-boot / watchdog hook.
#
# Runs:
#   - Once at FPP boot (via FPP's plugin postStart hook)
#   - Every minute via cron (installed on first run)
#
# Each invocation:
#   - Bails if listener config says disabled
#   - Bails if listener is already running (via PID file check)
#   - Otherwise launches the listener as a background PHP process

PLUGIN_DIR="/home/fpp/media/plugins/request-falcon-plugin"
LISTENER="$PLUGIN_DIR/listener.php"
CONFIG="/home/fpp/media/config/plugin.request-falcon-plugin"
PID_FILE="/home/fpp/media/logs/request-falcon-listener.pid"

# Bail if the plugin isn't installed
if [ ! -f "$LISTENER" ]; then
    exit 0
fi

# Bail if the user disabled the listener via the admin page
if [ -f "$CONFIG" ]; then
    if grep -qE '^listenerEnabled[[:space:]]*=[[:space:]]*"?false"?[[:space:]]*$' "$CONFIG"; then
        exit 0
    fi
fi

# Bail if already running (PID file exists and points to a live process).
# Using kill -0 rather than pgrep because pgrep -f false-positives on
# parent shells that spawned the listener.
if [ -f "$PID_FILE" ]; then
    PID=$(cat "$PID_FILE" 2>/dev/null)
    if [ -n "$PID" ] && kill -0 "$PID" 2>/dev/null; then
        exit 0
    fi
    # Stale PID file — process is gone, remove it
    rm -f "$PID_FILE"
fi

# Launch the listener
nohup /usr/bin/php "$LISTENER" > /dev/null 2>&1 &

# Install the cron watchdog if not already present. We install it for
# user 'fpp' regardless of who launched this script.
CRON_MARKER="$PLUGIN_DIR/.watchdog-installed"
if [ ! -f "$CRON_MARKER" ]; then
    CRON_LINE="* * * * * $PLUGIN_DIR/scripts/postStart.sh > /dev/null 2>&1"
    if ! sudo -u fpp crontab -l 2>/dev/null | grep -qF "$PLUGIN_DIR/scripts/postStart.sh"; then
        (sudo -u fpp crontab -l 2>/dev/null; echo "$CRON_LINE") | sudo -u fpp crontab -
    fi
    touch "$CRON_MARKER"
fi

exit 0
