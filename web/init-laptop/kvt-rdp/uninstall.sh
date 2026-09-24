#!/usr/bin/env bash
set -euo pipefail

MODE="user"
for arg in "$@"; do
	case "$arg" in
		--user) MODE=user ;;
		--system) MODE=system ;;
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

rm -f "$BIN_DIR/kvt-rdp"
rm -f "$APP_DIR/nl.kvt.rdp.desktop"
rm -f "$ICON_ROOT"/256x256/apps/nl.kvt.rdp.png
rm -f "$ICON_ROOT"/512x512/apps/nl.kvt.rdp.png

command -v update-desktop-database >/dev/null 2>&1 && update-desktop-database "$APP_DIR" >/dev/null 2>&1 || true
printf 'Uninstalled KVT RDP (%s)\n' "$MODE"
