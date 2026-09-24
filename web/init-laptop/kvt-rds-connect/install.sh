#!/usr/bin/env bash
# Install KVT RDS Connect on Arch / CachyOS (and similar).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_SRC="$SCRIPT_DIR/kvt_rds_connect.py"
MODE="user"
DESKTOP_NAME="kvt-rds-connect.desktop"

usage() {
	cat <<USAGE
Usage: ./install.sh [--user|--system]

  --user    Install for the current user (~/.local) [default]
  --system  Install for every user (binary in /usr/local, menu entry in /usr/share)
USAGE
}

die() { echo "error: $*" >&2; exit 1; }

for arg in "$@"; do
	case "$arg" in
		--user) MODE=user ;;
		--system) MODE=system ;;
		-h|--help) usage; exit 0 ;;
		*) die "unknown option: $arg" ;;
	esac
done

[[ -f "$APP_SRC" ]] || die "missing $APP_SRC (run this from the kvt-rds-connect folder)"

if [[ "$MODE" == "system" ]]; then
	if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
		exec sudo bash "$0" --system
	fi
	SHARE="/usr/local/share/kvt-rds-connect"
	BIN_DIR="/usr/local/bin"
	APP_DIR="/usr/share/applications"
	ICON_ROOT="/usr/share/icons/hicolor"
else
	if [[ "${EUID:-$(id -u)}" -eq 0 ]]; then
		die "refusing user install as root — run as the desktop user, or pass --system"
	fi
	DATA_HOME="${XDG_DATA_HOME:-$HOME/.local/share}"
	SHARE="$DATA_HOME/kvt-rds-connect"
	BIN_DIR="$HOME/.local/bin"
	APP_DIR="$DATA_HOME/applications"
	ICON_ROOT="$DATA_HOME/icons/hicolor"
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

ICON_FILE=""
for size in 256x256 512x512; do
	src="$SCRIPT_DIR/share/icons/hicolor/$size/apps/nl.kvt.rds.png"
	[ -f "$src" ] || continue
	install -Dm644 "$src" "$ICON_ROOT/$size/apps/nl.kvt.rds.png"
	if [ -z "$ICON_FILE" ]; then
		ICON_FILE="$ICON_ROOT/$size/apps/nl.kvt.rds.png"
	fi
done

sed -e "s|HOME_BIN|$BIN_DIR|g" -e "s|ICON_PATH|${ICON_FILE:-network-connect}|g" \
	"$SCRIPT_DIR/kvt-rds-connect.desktop.in" \
	> "$APP_DIR/$DESKTOP_NAME"
chmod 644 "$APP_DIR/$DESKTOP_NAME"

if [[ "$MODE" == "system" ]]; then
	rm -f "/usr/local/share/applications/$DESKTOP_NAME"
fi

if command -v update-desktop-database >/dev/null 2>&1; then
	update-desktop-database "$APP_DIR" >/dev/null 2>&1 || true
fi
if command -v gtk-update-icon-cache >/dev/null 2>&1 && [ -f "$ICON_ROOT/index.theme" ]; then
	gtk-update-icon-cache -f -t "$ICON_ROOT" >/dev/null 2>&1 || true
fi
if [[ "${EUID:-$(id -u)}" -ne 0 ]] && command -v kbuildsycoca6 >/dev/null 2>&1; then
	kbuildsycoca6 --noincremental >/dev/null 2>&1 || true
fi

echo
echo "Installed."
echo "  App:     $SHARE/kvt_rds_connect.py"
echo "  Command: $BIN_DIR/kvt-rds-connect"
echo "  Menu:    $APP_DIR/$DESKTOP_NAME"
echo "Search for \"KVT RDS Connect\" in your app launcher."
echo
if [[ "$MODE" == "user" && ":$PATH:" != *":$BIN_DIR:"* ]]; then
	echo "Note: add $BIN_DIR to your PATH, e.g. in ~/.zshrc:"
	echo "  export PATH=\"\$HOME/.local/bin:\$PATH\""
fi
echo "Launch with:  kvt-rds-connect"
