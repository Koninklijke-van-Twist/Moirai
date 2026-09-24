#!/usr/bin/env bash
# Installs KVT RDP and KVT RDS Connect into a fake home (and, with sudo, system paths)
# and checks the desktop entries Plasma can actually see.
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

fakebin="$tmp/bin"
mkdir -p "$fakebin"
for cmd in python3 xfreerdp3 lsusb; do
	printf '#!/bin/sh\nexit 0\n' > "$fakebin/$cmd"
	chmod 755 "$fakebin/$cmd"
done

fail() {
	printf 'FAIL  %s\n' "$1" >&2
	exit 1
}

ok() {
	printf 'ok  %s\n' "$1"
}

assert_desktop() {
	local file="$1"
	local name="$2"
	local exec_path="$3"
	[ -f "$file" ] || fail "missing desktop file $file"
	grep -qx 'Type=Application' "$file" || fail "$file Type"
	grep -qx "Name=$name" "$file" || fail "$file Name"
	grep -qx "Exec=$exec_path" "$file" || fail "$file Exec ($(grep '^Exec=' "$file" || true))"
	local icon
	icon="$(sed -n 's/^Icon=//p' "$file")"
	[ -n "$icon" ] || fail "$file Icon empty"
	[ -f "$icon" ] || fail "$file Icon is not a file: $icon"
	[ -x "$exec_path" ] || fail "$exec_path is not executable"
	ok "$name desktop entry"
}

home="$tmp/home"
mkdir -p "$home"
export HOME="$home"
export XDG_DATA_HOME="$home/.local/share"
export PATH="$fakebin:$PATH"

bash "$root/web/init-laptop/kvt-rdp/install.sh" --user
assert_desktop \
	"$home/.local/share/applications/nl.kvt.rdp.desktop" \
	"KVT RDP" \
	"$home/.local/bin/kvt-rdp"
[ ! -e "$home/.local/share/share/applications/nl.kvt.rdp.desktop" ] || fail "RDP desktop landed in share/share"
ok "RDP user install avoids the doubled XDG share path"

bash "$root/web/init-laptop/kvt-rds-connect/install.sh" --user
assert_desktop \
	"$home/.local/share/applications/kvt-rds-connect.desktop" \
	"KVT RDS Connect" \
	"$home/.local/bin/kvt-rds-connect"
ok "RDS user install writes a launcher"

bash "$root/web/init-laptop/kvt-rdp/uninstall.sh" --user
[ ! -e "$home/.local/share/applications/nl.kvt.rdp.desktop" ] || fail "RDP user desktop left behind"
[ ! -e "$home/.local/bin/kvt-rdp" ] || fail "RDP user binary left behind"
ok "RDP user uninstall"

if ! sudo -n true >/dev/null 2>&1; then
	ok "skipped system install (sudo needs a password)"
	echo passed
	exit 0
fi

sudo bash "$root/web/init-laptop/kvt-rdp/install.sh" --system
assert_desktop \
	/usr/share/applications/nl.kvt.rdp.desktop \
	"KVT RDP" \
	/usr/local/bin/kvt-rdp
[ ! -e /usr/local/share/applications/nl.kvt.rdp.desktop ] || fail "RDP still installs a desktop file under /usr/local/share"
ok "RDP system menu entry is in /usr/share/applications"

sudo env PATH="$fakebin:$PATH" bash "$root/web/init-laptop/kvt-rds-connect/install.sh" --system
assert_desktop \
	/usr/share/applications/kvt-rds-connect.desktop \
	"KVT RDS Connect" \
	/usr/local/bin/kvt-rds-connect
[ ! -e /usr/local/share/applications/kvt-rds-connect.desktop ] || fail "RDS desktop file under /usr/local/share"
ok "RDS system menu entry is in /usr/share/applications"

# Root must not dump a user install into /root.
if sudo bash "$root/web/init-laptop/kvt-rdp/install.sh" --user >/dev/null 2>&1; then
	fail "RDP user install as root should fail"
fi
if sudo env PATH="$fakebin:$PATH" bash "$root/web/init-laptop/kvt-rds-connect/install.sh" --user >/dev/null 2>&1; then
	fail "RDS user install as root should fail"
fi
ok "user install refuses root"

sudo bash "$root/web/init-laptop/kvt-rdp/uninstall.sh" --system
sudo rm -f \
	/usr/local/bin/kvt-rds-connect \
	/usr/share/applications/kvt-rds-connect.desktop \
	/usr/share/icons/hicolor/256x256/apps/nl.kvt.rds.png \
	/usr/share/icons/hicolor/512x512/apps/nl.kvt.rds.png \
	/usr/local/share/kvt-rds-connect/kvt_rds_connect.py
sudo rmdir /usr/local/share/kvt-rds-connect 2>/dev/null || true
[ ! -e /usr/share/applications/nl.kvt.rdp.desktop ] || fail "RDP system desktop left behind"
[ ! -e /usr/local/bin/kvt-rdp ] || fail "RDP system binary left behind"
[ ! -e /usr/share/applications/kvt-rds-connect.desktop ] || fail "RDS system desktop left behind"
ok "system install cleaned up"

echo passed
