#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MODE="user"

usage() {
	cat <<USAGE
Usage: ./install.sh [--user|--system]

  --user    Install for current user (~/.local) [default]
  --system  Install system-wide (/usr/local) — needs sudo
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
	PREFIX="${XDG_DATA_HOME:-$HOME/.local}"
	BIN_DIR="$HOME/.local/bin"
	APP_DIR="$PREFIX/share/applications"
	ICON_ROOT="$PREFIX/share/icons/hicolor"
else
	PREFIX="/usr/local"
	BIN_DIR="$PREFIX/bin"
	APP_DIR="$PREFIX/share/applications"
	ICON_ROOT="$PREFIX/share/icons/hicolor"
	if [ "${EUID:-$(id -u)}" -ne 0 ]; then
		exec sudo bash "$0" --system
	fi
fi

install -d "$BIN_DIR" "$APP_DIR"
install -Dm755 "$SCRIPT_DIR/bin/kvt-rdp" "$BIN_DIR/kvt-rdp"
install -Dm644 "$SCRIPT_DIR/share/applications/nl.kvt.rdp.desktop" "$APP_DIR/nl.kvt.rdp.desktop"

# Rewrite Exec to absolute path so PATH is not required for launchers
if command -v desktop-file-edit >/dev/null 2>&1; then
	desktop-file-edit --set-key=Exec --set-value="$BIN_DIR/kvt-rdp" "$APP_DIR/nl.kvt.rdp.desktop"
else
	sed -i "s|^Exec=.*|Exec=$BIN_DIR/kvt-rdp|" "$APP_DIR/nl.kvt.rdp.desktop"
fi

for size in 256x256 512x512; do
	src="$SCRIPT_DIR/share/icons/hicolor/$size/apps/nl.kvt.rdp.png"
	[ -f "$src" ] || continue
	install -Dm644 "$src" "$ICON_ROOT/$size/apps/nl.kvt.rdp.png"
done

# Drop the old Downloads-based launcher if present
OLD_DESKTOP="${XDG_DATA_HOME:-$HOME/.local/share}/applications/net.local.RDP.sh.desktop"
if [ -f "$OLD_DESKTOP" ] && [ "$MODE" = "user" ]; then
	rm -f "$OLD_DESKTOP"
fi

if command -v update-desktop-database >/dev/null 2>&1; then
	update-desktop-database "$APP_DIR" >/dev/null 2>&1 || true
fi
if command -v gtk-update-icon-cache >/dev/null 2>&1; then
	gtk-update-icon-cache -f -t "$ICON_ROOT" >/dev/null 2>&1 || true
fi
if command -v xdg-desktop-menu >/dev/null 2>&1; then
	xdg-desktop-menu forceupdate >/dev/null 2>&1 || true
fi

printf 'Installed KVT RDP (%s)\n' "$MODE"
printf '  binary:  %s\n' "$BIN_DIR/kvt-rdp"
printf '  desktop: %s\n' "$APP_DIR/nl.kvt.rdp.desktop"
printf 'Search for \"KVT RDP\" in your app launcher.\n'
