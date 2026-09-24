#!/usr/bin/env bash
set -euo pipefail

MODE="user"
for arg in "$@"; do
	case "$arg" in
		--user) MODE=user ;;
		--system) MODE=system ;;
	esac
done

remove_icons() {
	local root="$1"
	rm -f "$root/256x256/apps/nl.kvt.rdp.png"
	rm -f "$root/512x512/apps/nl.kvt.rdp.png"
}

if [ "$MODE" = "user" ]; then
	if [ "${EUID:-$(id -u)}" -eq 0 ]; then
		echo "error: refusing user uninstall as root. Pass --system, or run as the desktop user." >&2
		exit 1
	fi
	DATA_HOME="${XDG_DATA_HOME:-$HOME/.local/share}"
	rm -f "$HOME/.local/bin/kvt-rdp"
	rm -f "$DATA_HOME/applications/nl.kvt.rdp.desktop"
	rm -f "$DATA_HOME/share/applications/nl.kvt.rdp.desktop"
	remove_icons "$DATA_HOME/icons/hicolor"
	remove_icons "$DATA_HOME/share/icons/hicolor"
	APP_DIR="$DATA_HOME/applications"
	ICON_ROOT="$DATA_HOME/icons/hicolor"
else
	if [ "${EUID:-$(id -u)}" -ne 0 ]; then
		exec sudo bash "$0" --system
	fi
	rm -f /usr/local/bin/kvt-rdp
	rm -f /usr/share/applications/nl.kvt.rdp.desktop
	rm -f /usr/local/share/applications/nl.kvt.rdp.desktop
	remove_icons /usr/share/icons/hicolor
	remove_icons /usr/local/share/icons/hicolor
	APP_DIR="/usr/share/applications"
	ICON_ROOT="/usr/share/icons/hicolor"
fi

command -v update-desktop-database >/dev/null 2>&1 && update-desktop-database "$APP_DIR" >/dev/null 2>&1 || true
if command -v gtk-update-icon-cache >/dev/null 2>&1 && [ -f "$ICON_ROOT/index.theme" ]; then
	gtk-update-icon-cache -f -t "$ICON_ROOT" >/dev/null 2>&1 || true
fi
printf 'Uninstalled KVT RDP (%s)\n' "$MODE"
