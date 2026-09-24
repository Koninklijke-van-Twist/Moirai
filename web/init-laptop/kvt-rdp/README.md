# KVT RDP

Installable FreeRDP connection picker (from `RDP.sh`).

## Install

```bash
./install.sh
```

User install goes to `~/.local`. For all users:

```bash
./install.sh --system
```

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
