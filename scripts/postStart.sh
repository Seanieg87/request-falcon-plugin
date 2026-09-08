#!/bin/bash
#
# Request Falcon plugin — post-boot hook.
# Runs after FPP daemon starts (each boot). Launches the listener
# in the background if the plugin config has listenerEnabled=true.
#

PLUGIN_DIR="/home/fpp/media/plugins/request-falcon-plugin"
LISTENER="$PLUGIN_DIR/listener.php"
CONFIG="/home/fpp/media/config/plugin.request-falcon.json"

# Bail if the listener file isn't there for any reason
if [ ! -f "$LISTENER" ]; then
    exit 0
fi

# Don't start if the user has explicitly disabled the listener via
# the admin page. Check the config JSON for listenerEnabled=false.
if [ -f "$CONFIG" ]; then
    # Simple grep — we're looking for "listenerEnabled": false with
    # tolerant whitespace. If we can't tell, err on the side of
    # starting the listener.
    if grep -q '"listenerEnabled"[[:space:]]*:[[:space:]]*false' "$CONFIG"; then
        exit 0
    fi
fi

# Don't start a second copy if one's already running
if pgrep -f "$LISTENER" > /dev/null 2>&1; then
    exit 0
fi

nohup /usr/bin/php "$LISTENER" > /dev/null 2>&1 &

exit 0
