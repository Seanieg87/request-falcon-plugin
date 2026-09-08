#!/bin/bash
# Request Falcon plugin — uninstall hook.

LISTENER="/home/fpp/media/plugins/request-falcon-plugin/listener.php"
if pgrep -f "$LISTENER" > /dev/null 2>&1; then
    pkill -f "$LISTENER"
fi

# Deliberately leave the config file intact for reinstalls
exit 0
