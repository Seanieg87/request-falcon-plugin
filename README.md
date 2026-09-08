# Request Falcon FPP Plugin

Connects your [Falcon Player (FPP)](https://falconchristmas.com/) to
[Request Falcon](https://requestfalcon.com) so viewers can request or
vote on the songs your light show plays.

## What this does

- Reports what FPP is currently playing to your Request Falcon dashboard
- Fetches viewer requests/votes from Request Falcon and queues them in FPP
- Syncs your FPP playlist's sequences to Request Falcon so viewers can pick from them

## Requirements

- **FPP 8.0 or newer.** Older versions aren't supported.
- A Request Falcon account with at least one show set up
- Internet access on the Pi (the listener polls Request Falcon over HTTPS)

## Install

In FPP:

1. Go to **Content Setup → Plugin Manager → Install Plugin From URL**
2. Paste:
   ```
   https://raw.githubusercontent.com/Seanieg87/request-falcon-plugin/main/pluginInfo.json
   ```
3. Click Install. **Reboot the Pi when prompted.**

After the reboot, a new menu item appears under Content Setup: **Request Falcon**.

## Setup

1. Open **Content Setup → Request Falcon**
2. In your Request Falcon dashboard, go to **Setup → Plugin tokens** for the show you want to connect. Generate a new token, copy it.
3. Paste the token into the plugin's **Show Token** field
4. Click **Save settings**
5. Click **Test connectivity** — should show "Connected — show: [your show name]"
6. Pick your FPP playlist from the **Remote Playlist** dropdown (the one whose sequences viewers should be able to request)
7. Click **Sync playlist** — this uploads your sequences to Request Falcon

Your viewer page at `https://requestfalcon.com/<your-slug>` is now live.

## Running multiple shows

Each Request Falcon show has its own token. This FPP install can only be
connected to one show at a time. To switch (e.g. Halloween show →
Christmas show):

1. Open Content Setup → Request Falcon
2. Paste the new show's token
3. Change the Remote Playlist to match
4. Save and Sync

Your previous show's data (sequences, settings) is preserved on the
Request Falcon side.

## Troubleshooting

**"Listener not running"** — click **Restart listener**. If it keeps
saying not running, click **Show log tail** to see why.

**"Token was rejected"** — regenerate the token in Request Falcon and
paste it again. Old tokens can be revoked.

**Sync says success but no sequences appear on the dashboard** — hard
refresh the Playlist tab (Cmd+Shift+R / Ctrl+Shift+F5). If still empty,
check the log for errors.

## Log location

Everything the listener does is written to:
```
/home/fpp/media/logs/request-falcon.log
```

Also viewable via the **Show log tail** button on the config page.

## Uninstall

FPP → Content Setup → Plugin Manager → find "Request Falcon" → click
Uninstall. Your config file is preserved so a reinstall picks up where
you left off. To wipe it manually:
```
rm /home/fpp/media/config/plugin.request-falcon.json
```

## License

MIT. See [LICENSE](LICENSE).
