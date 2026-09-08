#!/bin/bash
# Request Falcon plugin — post-install hook.

. ${FPPDIR}/scripts/common

# Whitelist requestfalcon.com so the browser can call our server directly
${FPPDIR}/scripts/ManageApacheContentPolicy.sh add connect-src https://requestfalcon.com

# Mark for reboot so postStart.sh runs and starts the listener
setSetting restartFlag 1

exit 0
