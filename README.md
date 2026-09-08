# Request Falcon FPP Plugin

Connects your [Falcon Player (FPP)](https://falconchristmas.com/) to
[Request Falcon](https://requestfalcon.com) so viewers can request or
vote on the songs your light show plays.

## What this does

- Reports what FPP is currently playing to your Request Falcon dashboard
- Fetches viewer requests/votes from Request Falcon and queues them in FPP
- Syncs your FPP playlist's sequences to Request Falcon

## Requirements

- **FPP 8.0 or newer.** Older versions aren't supported.
- A Request Falcon account with at least one show set up
- Internet access on the Pi

## Install

In FPP:

1. **Content Setup → Plugin Manager → Install Plugin From URL**
2. Paste:
   ```
   https://raw.githubusercontent.com/Seanieg87/request-falcon-plugin/main/pluginInfo.json
   ```
3. Click Install. **Reboot the Pi when prompted.**

After the reboot, a new menu item appears under Content Setup: **Request Falcon**.

## Setup

1. Open **Content Setup → Request Falcon**
2. In your Request Falcon dashboard, go to **Setup → Plugin tokens**. Generate a token.
3. Paste the token into the plugin's **Show Token** field
4. Click **Save settings**
5. Click **Test connectivity** — should show "Connected — show: [your show name]"
6. Pick your FPP playlist from the **Remote Playlist** dropdown
7. Click **Sync playlist**

Your viewer page at `https://requestfalcon.com/<your-slug>` is now live.

## Running multiple shows

Each Request Falcon show has its own token. This FPP install can only be
connected to one show at a time. To switch (e.g. Halloween → Christmas):

1. Open Content Setup → Request Falcon
2. Paste the new show's token
3. Change the Remote Playlist to match
4. Save and Sync

## Troubleshooting

**"Listener not running"** — click **Restart listener**. If it stays down,
click **Show log tail** for the reason.

**"Token was rejected"** — regenerate the token in Request Falcon and re-paste.

**Sync says success but no sequences appear** — hard refresh the Playlist tab
(Cmd+Shift+R). If still empty, check the log.

## Log location

```
/home/fpp/media/logs/request-falcon.log
```

Also viewable via the **Show log tail** button.

## Uninstall

FPP → Content Setup → Plugin Manager → Uninstall. Your config file is
preserved for reinstalls. Manual wipe:
```
rm /home/fpp/media/config/plugin.request-falcon.json
```

## License

MIT. See [LICENSE](LICENSE).
