# Request Falcon FPP Plugin

Connects your [Falcon Player (FPP)](https://falconchristmas.com/) to
[Request Falcon](https://requestfalcon.com) so viewers can request or
vote on the songs your light show plays.

## Requirements

- **FPP 8.0 or newer**
- A Request Falcon account with at least one show set up
- Internet access on the Pi

## Install

In FPP: **Content Setup → Plugin Manager → Install Plugin From URL**

```
https://raw.githubusercontent.com/Seanieg87/request-falcon-plugin/main/pluginInfo.json
```

Click Install. **Reboot the Pi when prompted.**

## Setup

1. Open **Content Setup → Request Falcon**
2. Paste your show token (get one from Request Falcon dashboard → Setup → Plugin tokens)
3. Click **Save settings**
4. Click **Test connectivity** — should show "Connected — show: [your show name]"
5. Pick your FPP playlist from the dropdown
6. Click **Sync playlist**

Your viewer page at `https://requestfalcon.com/<your-slug>` is now live.

## Running multiple shows

Each Request Falcon show has its own token. This Pi can only connect to
one show at a time. To switch (e.g. Halloween → Christmas), paste the
new token, change the Remote Playlist, save, sync.

## Troubleshooting

**Nothing appearing on the viewer page after sync** — hard refresh the
Playlist tab (Cmd+Shift+R). If still empty, check the listener log:
```
tail -f /home/fpp/media/logs/request-falcon.log
```

**"Token was rejected"** — regenerate in Request Falcon and re-paste.

**Listener not responding** — click Restart listener. Give it 30-60 seconds.

## License

MIT. See [LICENSE](LICENSE).
