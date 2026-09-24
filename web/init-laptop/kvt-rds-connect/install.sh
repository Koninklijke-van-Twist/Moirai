#!/usr/bin/env bash
# Install KVT RDS Connect on Arch / CachyOS (and similar).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_SRC="$SCRIPT_DIR/kvt_rds_connect.py"
SHARE="${XDG_DATA_HOME:-$HOME/.local/share}/kvt-rds-connect"
BIN_DIR="${XDG_BIN_HOME:-$HOME/.local/bin}"
APP_DIR="${XDG_DATA_HOME:-$HOME/.local/share}/applications"

die() { echo "error: $*" >&2; exit 1; }

[[ -f "$APP_SRC" ]] || die "missing $APP_SRC (run this from the kvt-rds-connect folder)"

if [[ "${EUID:-$(id -u)}" -eq 0 && ( -z "${SUDO_USER:-}" || "$SUDO_USER" == "root" ) && "$HOME" == "/root" ]]; then
  die "refusing to install as root into /root — run as the desktop user, or via init-device.sh"
fi

echo "==> Checking dependencies"

need_pkgs=()
command -v xfreerdp3 >/dev/null 2>&1 || need_pkgs+=(freerdp)
command -v lsusb >/dev/null 2>&1 || need_pkgs+=(usbutils)

if ! python3 -c 'import gi; gi.require_version("Gtk", "3.0"); from gi.repository import Gtk' >/dev/null 2>&1; then
  need_pkgs+=(python-gobject gtk3)
fi

pacman_install() {
  if [[ "${EUID:-$(id -u)}" -eq 0 ]]; then
    pacman -S --needed --noconfirm "$@"
  elif command -v sudo >/dev/null 2>&1; then
    sudo pacman -S --needed --noconfirm "$@"
  else
    die "need packages: $* (no root/sudo)"
  fi
}

if ((${#need_pkgs[@]})); then
  if ! command -v pacman >/dev/null 2>&1; then
    die "need packages: ${need_pkgs[*]} (pacman not found — install them manually)"
  fi
  echo "Installing: ${need_pkgs[*]}"
  pacman_install "${need_pkgs[@]}"
else
  echo "Dependencies already present."
fi

command -v xfreerdp3 >/dev/null 2>&1 || die "xfreerdp3 still missing after install"
command -v python3 >/dev/null 2>&1 || die "python3 missing"

echo "==> Installing application files"
mkdir -p "$SHARE" "$BIN_DIR" "$APP_DIR"
install -m 755 "$APP_SRC" "$SHARE/kvt_rds_connect.py"

cat > "$BIN_DIR/kvt-rds-connect" << WRAP
#!/usr/bin/env bash
exec /usr/bin/python3 "$SHARE/kvt_rds_connect.py" "\$@"
WRAP
chmod 755 "$BIN_DIR/kvt-rds-connect"

sed "s|HOME_BIN|$BIN_DIR|g" "$SCRIPT_DIR/kvt-rds-connect.desktop.in" \
  > "$APP_DIR/kvt-rds-connect.desktop"
chmod 644 "$APP_DIR/kvt-rds-connect.desktop"

if command -v update-desktop-database >/dev/null 2>&1; then
  update-desktop-database "$APP_DIR" >/dev/null 2>&1 || true
fi

echo
echo "Installed."
echo "  App:     $SHARE/kvt_rds_connect.py"
echo "  Command: $BIN_DIR/kvt-rds-connect"
echo "  Menu:    KVT RDS Connect"
echo
if [[ ":$PATH:" != *":$BIN_DIR:"* ]]; then
  echo "Note: add $BIN_DIR to your PATH, e.g. in ~/.zshrc:"
  echo "  export PATH=\"\$HOME/.local/bin:\$PATH\""
fi
echo "Launch with:  kvt-rds-connect"
