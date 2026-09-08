#!/bin/bash
#
# Request Falcon plugin — uninstall hook.
# Runs before FPP deletes the plugin directory.
#

# Kill the listener if it's running. Use pkill on the full script path
# so we only stop OUR listener, not some other PHP process.
LISTENER_PATH="/home/fpp/media/plugins/request-falcon-plugin/listener.php"
if pgrep -f "$LISTENER_PATH" > /dev/null 2>&1; then
    pkill -f "$LISTENER_PATH"
fi

# Note: we deliberately DON'T remove the config file at
# /home/fpp/media/config/plugin.request-falcon.json — if the user
# reinstalls, their token and settings are preserved. They can delete
# it manually if they truly want a clean slate.

exit 0
