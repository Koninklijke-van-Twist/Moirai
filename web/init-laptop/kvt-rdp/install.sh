#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MODE="user"
DESKTOP_NAME="nl.kvt.rdp.desktop"

usage() {
	cat <<USAGE
Usage: ./install.sh [--user|--system]

  --user    Install for current user (~/.local) [default]
  --system  Install for every user (binary in /usr/local, menu entry in /usr/share)
USAGE
}

for arg in "$@"; do
	case "$arg" in
		--user) MODE=user ;;
		--system) MODE=system ;;
		-h|--help) usage; exit 0 ;;
		*) echo "Unknown option: $arg" >&2; usage; exit 1 ;;
	esac
done

if [ "$MODE" = "user" ]; then
	if [ "${EUID:-$(id -u)}" -eq 0 ]; then
		echo "error: refusing user install as root. Run as the desktop user, or pass --system." >&2
		exit 1
	fi
	# XDG_DATA_HOME is already the share dir (default ~/.local/share), not ~/.local.
	DATA_HOME="${XDG_DATA_HOME:-$HOME/.local/share}"
	BIN_DIR="$HOME/.local/bin"
	APP_DIR="$DATA_HOME/applications"
	ICON_ROOT="$DATA_HOME/icons/hicolor"
else
	if [ "${EUID:-$(id -u)}" -ne 0 ]; then
		exec sudo bash "$0" --system
	fi
	BIN_DIR="/usr/local/bin"
	# Kickoff reads /usr/share/applications. A desktop file in
	# /usr/local/share/applications stays invisible when the session's
	# XDG_DATA_DIRS does not include /usr/local/share.
	APP_DIR="/usr/share/applications"
	ICON_ROOT="/usr/share/icons/hicolor"
fi

install -d "$BIN_DIR" "$APP_DIR"
install -Dm755 "$SCRIPT_DIR/bin/kvt-rdp" "$BIN_DIR/kvt-rdp"
install -Dm644 "$SCRIPT_DIR/share/applications/$DESKTOP_NAME" "$APP_DIR/$DESKTOP_NAME"

ICON_FILE=""
for size in 256x256 512x512; do
	src="$SCRIPT_DIR/share/icons/hicolor/$size/apps/nl.kvt.rdp.png"
	[ -f "$src" ] || continue
	install -Dm644 "$src" "$ICON_ROOT/$size/apps/nl.kvt.rdp.png"
	if [ -z "$ICON_FILE" ]; then
		ICON_FILE="$ICON_ROOT/$size/apps/nl.kvt.rdp.png"
	fi
done

# Absolute Exec and Icon, so the launcher does not depend on PATH or an icon cache.
if command -v desktop-file-edit >/dev/null 2>&1; then
	desktop-file-edit --set-key=Exec --set-value="$BIN_DIR/kvt-rdp" "$APP_DIR/$DESKTOP_NAME"
	if [ -n "$ICON_FILE" ]; then
		desktop-file-edit --set-key=Icon --set-value="$ICON_FILE" "$APP_DIR/$DESKTOP_NAME"
	fi
else
	sed -i "s|^Exec=.*|Exec=$BIN_DIR/kvt-rdp|" "$APP_DIR/$DESKTOP_NAME"
	if [ -n "$ICON_FILE" ]; then
		sed -i "s|^Icon=.*|Icon=$ICON_FILE|" "$APP_DIR/$DESKTOP_NAME"
	fi
fi

if [ "$MODE" = "system" ]; then
	rm -f "/usr/local/share/applications/$DESKTOP_NAME"
	rm -f /usr/local/share/icons/hicolor/256x256/apps/nl.kvt.rdp.png
	rm -f /usr/local/share/icons/hicolor/512x512/apps/nl.kvt.rdp.png
else
	# Old user installs treated XDG_DATA_HOME as ~/.local and nested an extra share/.
	rm -f "${DATA_HOME}/share/applications/$DESKTOP_NAME"
	rm -f "${XDG_DATA_HOME:-$HOME/.local/share}/applications/net.local.RDP.sh.desktop"
fi

if command -v update-desktop-database >/dev/null 2>&1; then
	update-desktop-database "$APP_DIR" >/dev/null 2>&1 || true
fi
if command -v gtk-update-icon-cache >/dev/null 2>&1 && [ -f "$ICON_ROOT/index.theme" ]; then
	gtk-update-icon-cache -f -t "$ICON_ROOT" >/dev/null 2>&1 || true
fi
if [ "${EUID:-$(id -u)}" -ne 0 ] && command -v kbuildsycoca6 >/dev/null 2>&1; then
	kbuildsycoca6 --noincremental >/dev/null 2>&1 || true
fi
if command -v xdg-desktop-menu >/dev/null 2>&1; then
	xdg-desktop-menu forceupdate >/dev/null 2>&1 || true
fi

printf 'Installed KVT RDP (%s)\n' "$MODE"
printf '  binary:  %s\n' "$BIN_DIR/kvt-rdp"
printf '  desktop: %s\n' "$APP_DIR/$DESKTOP_NAME"
printf 'Search for "KVT RDP" in your app launcher.\n'
