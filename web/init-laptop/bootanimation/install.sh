#!/usr/bin/env bash
# Install KVT Plymouth boot animation and Plasma login splash for all users.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
THEME_NAME="kvt"
THEME_DIR="/usr/share/plymouth/themes/${THEME_NAME}"
SPLASH_ID="org.kvt.splash"
SPLASH_DIR="/usr/share/plasma/look-and-feel/${SPLASH_ID}"

log() { printf '[INFO] %s\n' "$1"; }
warn() { printf '[WARN] %s\n' "$1" >&2; }
die() { printf '[ERROR] %s\n' "$1" >&2; exit 1; }

require_root() {
	if [ "${EUID:-$(id -u)}" -ne 0 ]; then
		if command -v sudo >/dev/null 2>&1; then
			exec sudo -E bash "$0" "$@"
		fi
		die "Root-rechten nodig."
	fi
}

each_regular_user() {
	getent passwd | awk -F: '$3 >= 1000 && $3 < 65534 && $6 ~ /^\/home\// { print $1 " " $6 }'
}

write_ksplashrc() {
	local dest="$1" owner="${2:-}"
	mkdir -p "$(dirname "$dest")"
	cat > "$dest" <<EOF
[KSplash]
Theme=${SPLASH_ID}
EOF
	if [ -n "$owner" ]; then
		chown "$owner:$owner" "$dest" 2>/dev/null || true
	fi
}

install_plymouth() {
	local f src

	[ -f "$SCRIPT_DIR/main.png" ] || die "main.png ontbreekt in $SCRIPT_DIR"
	[ -f "$SCRIPT_DIR/dot1.png" ] || die "dot1.png ontbreekt"
	[ -f "$SCRIPT_DIR/dot2.png" ] || die "dot2.png ontbreekt"
	[ -f "$SCRIPT_DIR/dot3.png" ] || die "dot3.png ontbreekt"
	[ -f "$SCRIPT_DIR/kvt.plymouth" ] || die "kvt.plymouth ontbreekt"
	[ -f "$SCRIPT_DIR/kvt.script" ] || die "kvt.script ontbreekt"

	if ! command -v plymouth-set-default-theme >/dev/null 2>&1; then
		warn "plymouth-set-default-theme niet gevonden; sla boot-animatie over."
		return
	fi

	log "Installeer Plymouth-thema '$THEME_NAME'..."
	mkdir -p "$THEME_DIR"
	install -m644 "$SCRIPT_DIR/kvt.plymouth" "$THEME_DIR/kvt.plymouth"
	install -m644 "$SCRIPT_DIR/kvt.script" "$THEME_DIR/kvt.script"
	install -m644 "$SCRIPT_DIR/main.png" "$THEME_DIR/main.png"
	install -m644 "$SCRIPT_DIR/dot1.png" "$THEME_DIR/dot1.png"
	install -m644 "$SCRIPT_DIR/dot2.png" "$THEME_DIR/dot2.png"
	install -m644 "$SCRIPT_DIR/dot3.png" "$THEME_DIR/dot3.png"

	for f in box.png bullet.png entry.png lock.png; do
		src=""
		if [ -f "/usr/share/plymouth/themes/script/$f" ]; then
			src="/usr/share/plymouth/themes/script/$f"
		elif [ -f "/usr/share/plymouth/themes/cachyos-bootanimation/$f" ]; then
			src="/usr/share/plymouth/themes/cachyos-bootanimation/$f"
		elif [ -f "/usr/share/plymouth/themes/spinner/$f" ]; then
			src="/usr/share/plymouth/themes/spinner/$f"
		fi
		if [ -n "$src" ]; then
			install -m644 "$src" "$THEME_DIR/$f"
		else
			warn "Plymouth-dialoogasset ontbreekt: $f"
		fi
	done

	log "Zet KVT als standaard Plymouth-thema en herbouw initramfs..."
	plymouth-set-default-theme "$THEME_NAME" --rebuild-initrd
	log "Plymouth-thema '$THEME_NAME' is actief."
}

install_splash() {
	local name home dest

	[ -f "$SCRIPT_DIR/splash/metadata.json" ] || die "splash/metadata.json ontbreekt"
	[ -f "$SCRIPT_DIR/splash/contents/splash/Splash.qml" ] || die "Splash.qml ontbreekt"

	log "Installeer Plasma splash '$SPLASH_ID'..."
	rm -rf "$SPLASH_DIR"
	mkdir -p "$SPLASH_DIR/contents/splash/images"
	install -m644 "$SCRIPT_DIR/splash/metadata.json" "$SPLASH_DIR/metadata.json"
	install -m644 "$SCRIPT_DIR/splash/contents/splash/Splash.qml" "$SPLASH_DIR/contents/splash/Splash.qml"
	install -m644 "$SCRIPT_DIR/main.png" "$SPLASH_DIR/contents/splash/images/main.png"
	install -m644 "$SCRIPT_DIR/dot1.png" "$SPLASH_DIR/contents/splash/images/dot1.png"
	install -m644 "$SCRIPT_DIR/dot2.png" "$SPLASH_DIR/contents/splash/images/dot2.png"
	install -m644 "$SCRIPT_DIR/dot3.png" "$SPLASH_DIR/contents/splash/images/dot3.png"

	write_ksplashrc "/etc/xdg/ksplashrc"
	write_ksplashrc "/etc/skel/.config/ksplashrc"

	while read -r name home; do
		[ -d "$home" ] || continue
		dest="$home/.config/ksplashrc"
		log "  splash voor $name: $dest"
		write_ksplashrc "$dest" "$name"
	done < <(each_regular_user)

	log "KVT splash is ingesteld voor alle gebruikers."
}

main() {
	require_root "$@"
	install_plymouth
	install_splash
	log "Boot-animatie en login-splash zijn geinstalleerd."
}

main "$@"
