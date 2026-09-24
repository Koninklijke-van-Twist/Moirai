#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENROLL_SCRIPT="$SCRIPT_DIR/enroll.sh"
KVT_INSTALL="$SCRIPT_DIR/KVT-Energise/install.sh"
KVT_EFFECT_ID="kwin6_effect_kvt_energise"
BACKGROUND_SRC="$SCRIPT_DIR/backgr_1.png"
LOGO_SRC="$SCRIPT_DIR/kvtlogo.png"
BOOTANIM_INSTALL="$SCRIPT_DIR/bootanimation/install.sh"
HOSTS_SCRIPT="$SCRIPT_DIR/hosts.sh"
KVT_RDP_INSTALL="$SCRIPT_DIR/kvt-rdp/install.sh"
RDS_CONNECT_INSTALL="$SCRIPT_DIR/kvt-rds-connect/install.sh"

LOGIN_WALLPAPER_DIR="/usr/share/wallpapers/login-custom"
LOGIN_WALLPAPER_PATH="$LOGIN_WALLPAPER_DIR/backgr_1.png"
PLASMALOGIN_CONF="/etc/plasmalogin.conf"

TARGET_USER=""
TARGET_HOME=""
TARGET_UID=""

log() {
	printf '[INFO] %s\n' "$1"
}

warn() {
	printf '[WARN] %s\n' "$1" >&2
}

die() {
	printf '[ERROR] %s\n' "$1" >&2
	exit 1
}

require_root() {
	if [ "${EUID:-$(id -u)}" -ne 0 ]; then
		log "Dit script heeft root-rechten nodig. Probeer opnieuw met sudo..."
		if command -v sudo >/dev/null 2>&1; then
			exec sudo -E bash "$0" "$@"
		fi
		die "Kon geen sudo vinden. Start dit script als root."
	fi
}

resolve_target_user() {
	if [ -n "${SUDO_USER:-}" ] && [ "$SUDO_USER" != "root" ]; then
		TARGET_USER="$SUDO_USER"
	elif [ "${EUID:-$(id -u)}" -ne 0 ]; then
		TARGET_USER="$(id -un)"
	fi

	if [ -z "$TARGET_USER" ]; then
		warn "Geen desktop-gebruiker gevonden (SUDO_USER ontbreekt). KVT Energise en taakbalk-iconen worden overgeslagen."
		return
	fi

	TARGET_HOME="$(getent passwd "$TARGET_USER" | cut -d: -f6)"
	TARGET_UID="$(id -u "$TARGET_USER")"
	[ -n "$TARGET_HOME" ] || die "Kon home-directory van $TARGET_USER niet bepalen."
}

run_as_user() {
	if [ -z "$TARGET_USER" ]; then
		die "Geen doelgebruiker om commando als uit te voeren."
	fi

	if [ "$(id -un)" = "$TARGET_USER" ]; then
		"$@"
		return
	fi

	local runtime="/run/user/$TARGET_UID"
	sudo -u "$TARGET_USER" \
		--preserve-env=DISPLAY,WAYLAND_DISPLAY \
		env HOME="$TARGET_HOME" \
			USER="$TARGET_USER" \
			LOGNAME="$TARGET_USER" \
			XDG_RUNTIME_DIR="$runtime" \
			DBUS_SESSION_BUS_ADDRESS="unix:path=$runtime/bus" \
			XDG_CONFIG_HOME="$TARGET_HOME/.config" \
			XDG_DATA_HOME="$TARGET_HOME/.local/share" \
			"$@"
}

run_enroll() {
	[ -f "$ENROLL_SCRIPT" ] || die "enroll.sh niet gevonden: $ENROLL_SCRIPT"
	log "Start enroll.sh..."
	bash "$ENROLL_SCRIPT"
	log "enroll.sh is klaar."
}

run_hosts() {
	[ -f "$HOSTS_SCRIPT" ] || die "hosts.sh niet gevonden: $HOSTS_SCRIPT"
	log "Start hosts.sh..."
	bash "$HOSTS_SCRIPT"
	log "hosts.sh is klaar."
}

install_kvt_rdp() {
	[ -f "$KVT_RDP_INSTALL" ] || die "KVT RDP install-script niet gevonden: $KVT_RDP_INSTALL"
	log "Installeer KVT RDP..."
	# System-wide so every user on the device gets the launcher
	bash "$KVT_RDP_INSTALL" --system
	log "KVT RDP is geinstalleerd."
}

kwin_plugin_ids_for_open_close() {
	local extra=(
		fade scale glide
		kwin4_effect_fade kwin4_effect_scale
		kwin6_effect_glide kwin6_effect_fade kwin6_effect_scale
		kwin6_effect_energize_a kwin6_effect_energize_b
		kwin6_effect_fire kwin6_effect_tv
		kwin6_effect_hexagon kwin6_effect_incinerate
	)
	local dir f id
	local dirs=(
		/usr/share/kwin-wayland/effects
		/usr/share/kwin-wayland/builtin-effects
		/usr/share/kwin/effects
		"$TARGET_HOME/.local/share/kwin/effects"
	)

	printf '%s\n' "${extra[@]}"

	for dir in "${dirs[@]}"; do
		[ -d "$dir" ] || continue
		while IFS= read -r f; do
			grep -q 'toplevel-open-close-animation' "$f" || continue
			id="$(awk -F'"' '/"Id":/ { print $4; exit }' "$f")"
			if [ -z "$id" ]; then
				id="$(basename "$f" .json)"
				if [ "$id" = "metadata" ]; then
					id="$(basename "$(dirname "$f")")"
				fi
			fi
			[ -n "$id" ] && printf '%s\n' "$id"
		done < <(find "$dir" -type f \( -name metadata.json -o -name '*.json' \) 2>/dev/null)
	done
}

kwin_write_plugin() {
	local plugin_id="$1" enabled="$2"
	run_as_user kwriteconfig6 --file kwinrc --group Plugins --key "${plugin_id}Enabled" "$enabled"
}

kwin_dbus() {
	if ! command -v qdbus6 >/dev/null 2>&1; then
		return 1
	fi
	run_as_user qdbus6 org.kde.KWin "$@" >/dev/null 2>&1 || true
}

activate_kvt_open_close_animation() {
	local plugin_id
	local seen="|"

	log "Zet KVT Energise als animatie bij venster openen/sluiten..."

	while IFS= read -r plugin_id; do
		[ -n "$plugin_id" ] || continue
		[[ "$seen" == *"|$plugin_id|"* ]] && continue
		seen+="$plugin_id|"
		if [ "$plugin_id" = "$KVT_EFFECT_ID" ]; then
			continue
		fi
		kwin_write_plugin "$plugin_id" false
		kwin_dbus /Effects org.kde.kwin.Effects.unloadEffect "$plugin_id"
	done < <(kwin_plugin_ids_for_open_close)

	kwin_write_plugin "$KVT_EFFECT_ID" true
	kwin_dbus /KWin org.kde.KWin.reconfigure
	kwin_dbus /Effects org.kde.kwin.Effects.loadEffect "$KVT_EFFECT_ID"
	kwin_dbus /Effects org.kde.kwin.Effects.reconfigureEffect "$KVT_EFFECT_ID"

	local enabled
	enabled="$(run_as_user kreadconfig6 --file kwinrc --group Plugins --key "${KVT_EFFECT_ID}Enabled")"
	if [ "$enabled" != "true" ]; then
		warn "KVT Energise staat niet op true in kwinrc (waarde: ${enabled:-leeg})."
		return
	fi
	log "KVT Energise is actief als venster openen/sluiten-animatie."
}

install_kvt_energise() {
	if [ -z "$TARGET_USER" ]; then
		warn "Sla KVT Energise over: geen desktop-gebruiker."
		return
	fi

	[ -f "$KVT_INSTALL" ] || die "KVT Energise install-script niet gevonden: $KVT_INSTALL"
	log "Installeer KVT Energise voor gebruiker $TARGET_USER..."
	run_as_user bash "$KVT_INSTALL"
	log "KVT Energise is geinstalleerd."
	activate_kvt_open_close_animation
}

current_sddm_theme() {
	local theme=""
	local file

	for file in /etc/sddm.conf /etc/sddm.conf.d/*.conf /usr/lib/sddm/sddm.conf.d/*.conf /etc/plasmalogin.conf; do
		[ -f "$file" ] || continue
		theme="$(awk -F= '
			$1 == "Current" {
				gsub(/\r/, "", $2)
				print $2
			}
		' "$file" | tail -n1)"
		if [ -n "$theme" ]; then
			printf '%s\n' "$theme"
			return
		fi
	done

	printf 'breeze\n'
}

set_login_background() {
	[ -f "$BACKGROUND_SRC" ] || die "Achtergrond niet gevonden: $BACKGROUND_SRC"

	log "Kopieer login-achtergrond naar $LOGIN_WALLPAPER_PATH"
	install -Dm644 "$BACKGROUND_SRC" "$LOGIN_WALLPAPER_PATH"

	if getent passwd plasmalogin >/dev/null 2>&1; then
		local pl_dir="/var/lib/plasmalogin/wallpapers"
		mkdir -p "$pl_dir"
		install -Dm644 "$BACKGROUND_SRC" "$pl_dir/backgr_1.png"
		chown plasmalogin:plasmalogin "$pl_dir/backgr_1.png" 2>/dev/null || true
	fi

	if [ -e /etc/plasmalogin.conf ] || command -v plasmalogin >/dev/null 2>&1; then
		log "Stel Plasma Login Manager achtergrond in..."
		if command -v kwriteconfig6 >/dev/null 2>&1; then
			kwriteconfig6 --file "$PLASMALOGIN_CONF" --group Greeter --key WallpaperPluginId org.kde.image
			kwriteconfig6 --file "$PLASMALOGIN_CONF" --group Greeter --group Wallpaper --group org.kde.image --group General --key Image "file://$LOGIN_WALLPAPER_PATH"
		else
			die "kwriteconfig6 niet gevonden; kan $PLASMALOGIN_CONF niet bijwerken."
		fi
		log "Plasma Login Manager gebruikt nu $LOGIN_WALLPAPER_PATH"
	fi

	local theme theme_dir
	theme="$(current_sddm_theme)"
	theme_dir="/usr/share/sddm/themes/$theme"
	if [ -d "$theme_dir" ]; then
		log "Stel SDDM-thema '$theme' achtergrond in via theme.conf.user..."
		cat > "$theme_dir/theme.conf.user" <<EOF
[General]
background=$LOGIN_WALLPAPER_PATH
type=image
EOF
	fi

	set_lockscreen_all_users
	set_desktop_wallpaper_all_users
}

each_regular_user() {
	getent passwd | awk -F: '$3 >= 1000 && $3 < 65534 && $6 ~ /^\/home\// { print $1 " " $6 }'
}

write_lockscreen_keys() {
	local file="$1"
	kwriteconfig6 --file "$file" --group Greeter --key WallpaperPluginId org.kde.image
	kwriteconfig6 --file "$file" --group Greeter --group Wallpaper --group org.kde.image --group General --key Image "file://$LOGIN_WALLPAPER_PATH"
	kwriteconfig6 --file "$file" --group Greeter --group Wallpaper --group org.kde.image --group General --key PreviewImage "file://$LOGIN_WALLPAPER_PATH"
}

set_lockscreen_all_users() {
	local name home

	log "Stel lockscreen-achtergrond in voor alle gebruikers..."

	mkdir -p /etc/xdg /etc/skel/.config
	write_lockscreen_keys /etc/xdg/kscreenlockerrc
	write_lockscreen_keys /etc/skel/.config/kscreenlockerrc

	while read -r name home; do
		[ -d "$home" ] || continue
		mkdir -p "$home/.config"
		chown "$name:$name" "$home/.config" 2>/dev/null || true
		write_lockscreen_keys "$home/.config/kscreenlockerrc"
		chown "$name:$name" "$home/.config/kscreenlockerrc"
		log "  lockscreen voor $name"
	done < <(each_regular_user)

	log "Lockscreen gebruikt $LOGIN_WALLPAPER_PATH"
}

run_as_home_user() {
	local user="$1" home="$2"
	shift 2
	local uid runtime
	uid="$(id -u "$user")"
	runtime="/run/user/$uid"
	sudo -u "$user" \
		--preserve-env=DISPLAY,WAYLAND_DISPLAY \
		env HOME="$home" \
			USER="$user" \
			LOGNAME="$user" \
			XDG_RUNTIME_DIR="$runtime" \
			DBUS_SESSION_BUS_ADDRESS="unix:path=$runtime/bus" \
			XDG_CONFIG_HOME="$home/.config" \
			XDG_DATA_HOME="$home/.local/share" \
			"$@"
}

set_appletsrc_desktop_wallpaper() {
	local appletsrc="$1"
	local owner="${2:-}"
	local section="" id=""
	local -A folder_ids=()
	local -A image_ids=()

	[ -f "$appletsrc" ] || return 0

	while IFS= read -r line || [ -n "$line" ]; do
		case "$line" in
			\[*\])
				section="${line#\[}"
				section="${section%\]}"
				if [[ "$section" =~ ^Containments\]\[([0-9]+)$ ]]; then
					id="${BASH_REMATCH[1]}"
				else
					id=""
				fi
				if [[ "$section" =~ ^Containments\]\[([0-9]+)\]\[Wallpaper\]\[org\.kde\.image\]\[General$ ]]; then
					image_ids["${BASH_REMATCH[1]}"]=1
				fi
				;;
			plugin=org.kde.plasma.folder)
				if [ -n "$id" ]; then
					folder_ids["$id"]=1
				fi
				;;
		esac
	done < "$appletsrc"

	local cid
	for cid in "${!folder_ids[@]}"; do
		kwriteconfig6 --file "$appletsrc" --group Containments --group "$cid" --key wallpaperplugin org.kde.image
		kwriteconfig6 --file "$appletsrc" --group Containments --group "$cid" --group Wallpaper --group org.kde.image --group General --key Image "file://$LOGIN_WALLPAPER_PATH"
		kwriteconfig6 --file "$appletsrc" --group Containments --group "$cid" --group Wallpaper --group org.kde.image --group General --key PreviewImage "file://$LOGIN_WALLPAPER_PATH"
		log "  desktop-containment $cid -> backgr_1 ($appletsrc)"
	done

	for cid in "${!image_ids[@]}"; do
		[ -z "${folder_ids[$cid]+x}" ] || continue
		kwriteconfig6 --file "$appletsrc" --group Containments --group "$cid" --group Wallpaper --group org.kde.image --group General --key Image "file://$LOGIN_WALLPAPER_PATH"
		kwriteconfig6 --file "$appletsrc" --group Containments --group "$cid" --group Wallpaper --group org.kde.image --group General --key PreviewImage "file://$LOGIN_WALLPAPER_PATH"
		log "  wallpaper-groep $cid -> backgr_1 ($appletsrc)"
	done

	if [ -n "$owner" ]; then
		chown "$owner:$owner" "$appletsrc"
	fi
}

apply_desktop_wallpaper_live_for() {
	local user="$1" home="$2"
	local uid runtime js
	uid="$(id -u "$user" 2>/dev/null)" || return 0
	runtime="/run/user/$uid"
	[ -S "$runtime/bus" ] || return 0

	if ! run_as_home_user "$user" "$home" bash -c 'command -v qdbus6 >/dev/null && qdbus6 org.kde.plasmashell /PlasmaShell org.kde.PlasmaShell.evaluateScript "true" >/dev/null 2>&1'; then
		return 0
	fi

	log "  live desktop-wallpaper voor $user"
	js="$(cat <<EOF
var image = "file://$LOGIN_WALLPAPER_PATH";
var desks = desktops();
for (var i = 0; i < desks.length; ++i) {
    var d = desks[i];
    d.wallpaperPlugin = "org.kde.image";
    d.currentConfigGroup = ["Wallpaper", "org.kde.image", "General"];
    d.writeConfig("Image", image);
    d.writeConfig("PreviewImage", image);
}
EOF
)"
	run_as_home_user "$user" "$home" qdbus6 org.kde.plasmashell /PlasmaShell org.kde.PlasmaShell.evaluateScript "$js" >/dev/null || true
	if command -v plasma-apply-wallpaperimage >/dev/null 2>&1; then
		run_as_home_user "$user" "$home" plasma-apply-wallpaperimage "$LOGIN_WALLPAPER_PATH" >/dev/null 2>&1 || true
	fi
}

set_desktop_wallpaper_all_users() {
	local name home appletsrc

	log "Stel desktop-achtergrond in voor alle gebruikers..."

	appletsrc="/etc/skel/.config/plasma-org.kde.plasma.desktop-appletsrc"
	if [ -f "$appletsrc" ]; then
		set_appletsrc_desktop_wallpaper "$appletsrc"
	fi

	while read -r name home; do
		[ -d "$home" ] || continue
		appletsrc="$home/.config/plasma-org.kde.plasma.desktop-appletsrc"
		if [ -f "$appletsrc" ]; then
			set_appletsrc_desktop_wallpaper "$appletsrc" "$name"
		else
			warn "Geen Plasma-desktopconfig voor $name ($appletsrc)"
		fi
		apply_desktop_wallpaper_live_for "$name" "$home"
	done < <(each_regular_user)

	log "Desktop-wallpaper gebruikt $LOGIN_WALLPAPER_PATH"
}

install_boot_and_splash() {
	[ -f "$BOOTANIM_INSTALL" ] || die "Boot-animatie installer niet gevonden: $BOOTANIM_INSTALL"
	log "Installeer boot-animatie en login-splash..."
	bash "$BOOTANIM_INSTALL"
}

set_launcher_icons() {
	if [ -z "$TARGET_USER" ]; then
		warn "Sla taakbalk-iconen over: geen desktop-gebruiker."
		return
	fi

	[ -f "$LOGO_SRC" ] || die "Logo niet gevonden: $LOGO_SRC"

	local icon_dest="$TARGET_HOME/.local/share/icons/kvtlogo.png"
	local appletsrc="$TARGET_HOME/.config/plasma-org.kde.plasma.desktop-appletsrc"

	log "Kopieer applicatiestarter-icoon naar $icon_dest"
	install -o "$TARGET_USER" -g "$TARGET_USER" -Dm644 "$LOGO_SRC" "$icon_dest"

	if [ ! -f "$appletsrc" ]; then
		warn "Geen Plasma applets-config gevonden ($appletsrc). Probeer alleen live Plasma-update."
	else
		log "Zet kvtlogo.png op alle applicatiestarters in $appletsrc"
		local section="" plugin="" updated=0
		while IFS= read -r line || [ -n "$line" ]; do
			case "$line" in
				\[*\])
					section="${line#\[}"
					section="${section%\]}"
					plugin=""
					;;
				plugin=org.kde.plasma.kickoff|plugin=org.kde.plasma.kicker|plugin=org.kde.plasma.kickerdash)
					plugin="${line#plugin=}"
					case "$section" in
						Containments\]\[*\]\[Applets\]\[*)
							local group_args=()
							local part rest="$section"
							while [ -n "$rest" ]; do
								part="${rest%%][*}"
								if [ "$part" = "$rest" ]; then
									group_args+=(--group "$rest")
									break
								fi
								group_args+=(--group "$part")
								rest="${rest#*][}"
							done
							run_as_user kwriteconfig6 \
								--file plasma-org.kde.plasma.desktop-appletsrc \
								"${group_args[@]}" \
								--group Configuration \
								--group General \
								--key icon "$icon_dest"
							updated=$((updated + 1))
							log "  - $plugin ($section) -> $icon_dest"
							;;
					esac
					;;
			esac
		done < "$appletsrc"

		if [ "$updated" -eq 0 ]; then
			warn "Geen applicatiestarters (kickoff/kicker/kickerdash) in appletsrc gevonden."
		else
			log "$updated applicatiestarter(s) bijgewerkt in config."
		fi
	fi

	apply_launcher_icons_live "$icon_dest"
}

apply_launcher_icons_live() {
	local icon_dest="$1"
	local js

	if ! run_as_user bash -c 'command -v qdbus6 >/dev/null && qdbus6 org.kde.plasmashell /PlasmaShell org.kde.PlasmaShell.evaluateScript "true" >/dev/null 2>&1'; then
		warn "plasmashell is niet bereikbaar; iconen worden bij de volgende login/sessie-herstart zichtbaar."
		return
	fi

	log "Pas applicatiestarter-iconen live toe in plasmashell..."
	js="$(cat <<EOF
var icon = "$icon_dest";
var types = {
    "org.kde.plasma.kickoff": true,
    "org.kde.plasma.kicker": true,
    "org.kde.plasma.kickerdash": true
};
var pans = panels();
for (var i = 0; i < pans.length; ++i) {
    var widgets = pans[i].widgets();
    for (var j = 0; j < widgets.length; ++j) {
        var w = widgets[j];
        if (types[w.type]) {
            w.currentConfigGroup = ["General"];
            w.writeConfig("icon", icon);
            try { w.reloadConfig(); } catch (e) {}
        }
    }
}
EOF
)"
	run_as_user qdbus6 org.kde.plasmashell /PlasmaShell org.kde.PlasmaShell.evaluateScript "$js" >/dev/null
	log "Live Plasma-update aangevraagd."
}


install_kvt_rds_connect() {
	if [ -z "$TARGET_USER" ]; then
		warn "Sla KVT RDS Connect over: geen desktop-gebruiker."
		return
	fi

	[ -f "$RDS_CONNECT_INSTALL" ] || die "KVT RDS Connect installer niet gevonden: $RDS_CONNECT_INSTALL"

	# Dependencies as root (init-device already runs as root)
	local need_pkgs=()
	command -v xfreerdp3 >/dev/null 2>&1 || need_pkgs+=(freerdp)
	command -v lsusb >/dev/null 2>&1 || need_pkgs+=(usbutils)
	if ! python3 -c 'import gi; gi.require_version("Gtk", "3.0"); from gi.repository import Gtk' >/dev/null 2>&1; then
		need_pkgs+=(python-gobject gtk3)
	fi
	if [ "${#need_pkgs[@]}" -gt 0 ]; then
		log "Installeer KVT RDS Connect afhankelijkheden: ${need_pkgs[*]}"
		pacman -S --needed --noconfirm "${need_pkgs[@]}"
	fi

	log "Installeer KVT RDS Connect voor gebruiker $TARGET_USER..."
	run_as_user bash "$RDS_CONNECT_INSTALL"
	log "KVT RDS Connect is geinstalleerd (menu: KVT RDS Connect)."
}

main() {
	require_root "$@"
	resolve_target_user

	[ -f "$BACKGROUND_SRC" ] || die "Achtergrond niet gevonden: $BACKGROUND_SRC"
	[ -f "$LOGO_SRC" ] || die "Logo niet gevonden: $LOGO_SRC"

	run_hosts
	run_enroll
	install_kvt_rdp
	install_kvt_energise
	set_login_background
	set_launcher_icons
	install_boot_and_splash
	install_kvt_rds_connect

	log "Klaar. Device-init is uitgevoerd."
}

main "$@"
