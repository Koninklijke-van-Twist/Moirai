# KVT RDP

Installable FreeRDP connection picker (from `RDP.sh`).

## Install

```bash
./install.sh
```

User install goes to `~/.local/share/applications` (that path is `XDG_DATA_HOME/applications`, not `XDG_DATA_HOME/share/applications`). For all users:

```bash
./install.sh --system
```

The system menu entry is `/usr/share/applications/nl.kvt.rdp.desktop`. The binary stays `/usr/local/bin/kvt-rdp`.

## Uninstall

```bash
./uninstall.sh
# or
./uninstall.sh --system
```

## Dependencies

- `kdialog`
- `secret-tool` (libsecret)
- `xfreerdp3`

Connections stay in `~/.config/rdp_connections.csv`; passwords in the Secret Service (`service=RDP`).
