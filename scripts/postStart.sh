#!/bin/bash
# Request Falcon plugin — post-boot hook.
#
# Runs after FPP starts on each boot. Launches the listener if:
#   - It's not already running
#   - Config doesn't have listenerEnabled=false
#
# We use `at` (schedule for 1 minute later) to also handle the restart
# case: when the config page toggles listenerRestarting=true, the
# current listener exits and needs relaunch. FPP's postStart only runs
# at boot, so for restart we rely on the listener itself calling this
# script or on a cron loop.
#
# Simplest reliable pattern: keep it simple, just start-if-not-running.
# The listener exits when it sees listenerRestarting=true, and a
# once-a-minute cron entry (installed via this script the first time it
# runs) relaunches it.

PLUGIN_DIR="/home/fpp/media/plugins/request-falcon-plugin"
LISTENER="$PLUGIN_DIR/listener.php"
CONFIG="/home/fpp/media/config/plugin.request-falcon-plugin"

if [ ! -f "$LISTENER" ]; then
    exit 0
fi

# Skip launch if user disabled the listener
if [ -f "$CONFIG" ]; then
    # INI format: listenerEnabled=false or listenerEnabled="false"
    if grep -qE '^listenerEnabled[[:space:]]*=[[:space:]]*"?false"?[[:space:]]*$' "$CONFIG"; then
        exit 0
    fi
fi

# Skip if already running
if pgrep -f "$LISTENER" > /dev/null 2>&1; then
    exit 0
fi

nohup /usr/bin/php "$LISTENER" > /dev/null 2>&1 &

# Install a cron watchdog if not already there. Runs every minute, does
# nothing if the listener is already running. This is what makes
# restart-via-settings work — the exiting listener will be replaced
# within 60 seconds.
CRON_MARKER="/home/fpp/media/plugins/request-falcon-plugin/.watchdog-installed"
if [ ! -f "$CRON_MARKER" ]; then
    # Only add if not already in crontab
    if ! crontab -l 2>/dev/null | grep -q "$LISTENER"; then
        (crontab -l 2>/dev/null; echo "* * * * * $PLUGIN_DIR/scripts/postStart.sh > /dev/null 2>&1") | crontab -
    fi
    touch "$CRON_MARKER"
fi

exit 0
