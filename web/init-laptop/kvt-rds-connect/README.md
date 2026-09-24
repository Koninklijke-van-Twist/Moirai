# KVT RDS Connect

GTK launcher for KVT RDS via `xfreerdp3` (USB redirection, domain fixed to KVT).

## Install (CachyOS / Arch)

```bash
cd ~/Downloads/kvt-rds-connect
./install.sh
```

Or from Downloads:

```bash
~/Downloads/install-kvt-rds-connect.sh
```

Installs FreeRDP / GTK deps if needed (asks for sudo), then places:

- `~/.local/share/kvt-rds-connect/`
- `~/.local/bin/kvt-rds-connect`
- App menu entry **KVT RDS Connect** (`~/.local/share/applications/kvt-rds-connect.desktop`)

For every user on the laptop (what `init-device.sh` does):

```bash
sudo ./install.sh --system
```

That writes `/usr/share/applications/kvt-rds-connect.desktop` and `/usr/local/bin/kvt-rds-connect`.

## Run

```bash
kvt-rds-connect
```

Password is never saved. Last username and USB choice are remembered in `~/.config/kvt-rds-connect/`.
