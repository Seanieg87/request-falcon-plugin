#!/bin/bash
#
# Request Falcon plugin — post-install hook.
# Run once by FPP after the plugin files are placed on disk.
#

# Load FPP's common env (gives us $FPPDIR)
. ${FPPDIR}/scripts/common

# Whitelist requestfalcon.com so the browser-side Test Connectivity
# button (and any other browser-initiated AJAX from the admin page)
# isn't blocked by Apache's Content Security Policy.
${FPPDIR}/scripts/ManageApacheContentPolicy.sh add connect-src https://requestfalcon.com

# Mark for reboot so postStart.sh runs and starts the listener.
# Without this, the listener won't launch until the next reboot.
setSetting restartFlag 1

exit 0
