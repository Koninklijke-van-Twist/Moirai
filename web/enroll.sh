#!/usr/bin/env bash

set -euo pipefail

FLEET_URL="https://kvt-fleetmdm.kvt.nl"
ENROLL_SECRET="ySxRSFnpHkUtX5HkWl2SB097ELYaDyVv"
OS_RELEASE_FILE="/etc/os-release"
OS_RELEASE_BACKUP_FILE="/etc/os-release.bak"

declare -A ID_MAP=(
	["cachyos"]="arch"
	["endeavouros"]="arch"
	["manjaro"]="arch"
	["garuda"]="arch"
	["arcolinux"]="arch"
)

declare -A ID_LIKE_MAP=(
	["cachyos"]="arch"
	["endeavouros"]="arch"
	["manjaro"]="arch"
	["garuda"]="arch"
	["arcolinux"]="arch"
)

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

normalize_os_release_id() {
	local current_id new_id new_id_like tmp_file

	if [[ ! -f "$OS_RELEASE_FILE" ]]; then
		die "Fout: $OS_RELEASE_FILE bestaat niet."
	fi

	current_id="$(
		awk -F= '
			$1 == "ID" {
				gsub(/^"/, "", $2)
				gsub(/"$/, "", $2)
				print $2
				exit
			}
		' "$OS_RELEASE_FILE"
	)"

	if [[ -z "${current_id:-}" ]]; then
		die "Fout: geen ID veld gevonden in $OS_RELEASE_FILE."
	fi

	log "Huidige ID: $current_id"

	if [[ -z "${ID_MAP[$current_id]+x}" ]]; then
		log "Geen mapping gevonden voor ID '$current_id'. Niets gewijzigd."
		return
	fi

	new_id="${ID_MAP[$current_id]}"
	new_id_like="${ID_LIKE_MAP[$current_id]:-$new_id}"

	log "Nieuwe ID: $new_id"
	log "Nieuwe ID_LIKE: $new_id_like"

	cp "$OS_RELEASE_FILE" "$OS_RELEASE_BACKUP_FILE"
	log "Backup gemaakt: $OS_RELEASE_BACKUP_FILE"

	tmp_file="$(mktemp)"

	awk -v new_id="$new_id" -v new_id_like="$new_id_like" '
		BEGIN {
			replaced_id = 0
			replaced_id_like = 0
		}
		/^ID=/ && replaced_id == 0 {
			print "ID=" new_id
			replaced_id = 1
			next
		}
		/^ID_LIKE=/ && replaced_id_like == 0 {
			print "ID_LIKE=" new_id_like
			replaced_id_like = 1
			next
		}
		{ print }
		END {
			if (replaced_id_like == 0) {
				print "ID_LIKE=" new_id_like
			}
		}
	' "$OS_RELEASE_FILE" > "$tmp_file"

	cat "$tmp_file" > "$OS_RELEASE_FILE"
	rm -f "$tmp_file"

	log "Bestand bijgewerkt: $OS_RELEASE_FILE"
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

load_os_release() {
	OS_ID=""
	OS_ID_LIKE=""

	if [ -r /etc/os-release ]; then
		# shellcheck disable=SC1091
		. /etc/os-release
		OS_ID="${ID:-}"
		OS_ID_LIKE="${ID_LIKE:-}"
	fi
}

detect_package_manager() {
	if command -v apt-get >/dev/null 2>&1; then
		echo "apt"
		return
	fi
	if command -v dnf >/dev/null 2>&1; then
		echo "dnf"
		return
	fi
	if command -v yum >/dev/null 2>&1; then
		echo "yum"
		return
	fi
	if command -v pacman >/dev/null 2>&1; then
		echo "pacman"
		return
	fi
	if command -v zypper >/dev/null 2>&1; then
		echo "zypper"
		return
	fi
	if command -v apk >/dev/null 2>&1; then
		echo "apk"
		return
	fi

	echo "unknown"
}

detect_fleet_package_type() {
	local ids
	ids="${OS_ID} ${OS_ID_LIKE}"

	if echo "$ids" | grep -Eiq 'arch|manjaro|endeavouros'; then
		echo "pkg.tar.zst"
		return
	fi

	if echo "$ids" | grep -Eiq 'fedora|rhel|centos|rocky|almalinux|ol|suse|opensuse'; then
		echo "rpm"
		return
	fi

	echo "deb"
}

ask_package_type() {
	local choice
	echo "Kon distro-type niet betrouwbaar bepalen. Kies package type:"
	echo "1) deb"
	echo "2) rpm"
	echo "3) pkg.tar.zst"
	read -r -p "Maak een keuze [1-3]: " choice
	case "$choice" in
		1) echo "deb" ;;
		2) echo "rpm" ;;
		3) echo "pkg.tar.zst" ;;
		*) die "Ongeldige keuze." ;;
	esac
}

install_fleetctl() {
	if command -v fleetctl >/dev/null 2>&1; then
		log "fleetctl is al geinstalleerd."
		return
	fi

	log "Installeer fleetctl..."
	curl -sSL https://fleetdm.com/resources/install-fleetctl.sh | bash
}

install_node_and_npm() {
	local pm="$1"
	log "Installeer Node.js en npm via package manager: $pm"

	case "$pm" in
		apt)
			apt-get update -y
			apt-get install -y curl ca-certificates nodejs npm
			;;
		dnf)
			dnf install -y curl ca-certificates nodejs npm
			;;
		yum)
			yum install -y curl ca-certificates nodejs npm
			;;
		pacman)
			pacman -Sy --noconfirm --needed curl ca-certificates nodejs npm
			;;
		zypper)
			zypper --non-interactive refresh
			zypper --non-interactive install curl ca-certificates nodejs npm
			;;
		apk)
			apk add --no-cache bash curl ca-certificates nodejs npm
			;;
		*)
			die "Onbekende package manager. Node.js/npm kunnen niet automatisch worden geinstalleerd."
			;;
	esac
}

generate_fleet_package() {
	local package_type="$1"
	local output

	log "Genereer Fleet package type: $package_type"
	output="$(fleetctl package --type=$package_type --enable-scripts --fleet-desktop --fleet-url=$FLEET_URL --enroll-secret=$ENROLL_SECRET 2>&1)" || {
		printf '%s\n' "$output"
		die "fleetctl package mislukt (zie output hierboven)."
	}
	printf '%s\n' "$output"

	PACKAGE_PATH="$(printf '%s\n' "$output" | sed -n 's/^Success! You generated fleetd at \(.*\)$/\1/p' | tail -n1)"

	if [ -n "$PACKAGE_PATH" ] && [ -f "$PACKAGE_PATH" ]; then
		return
	fi

	warn "Pad niet gevonden in fleetctl output; probeer automatisch te zoeken."
	case "$package_type" in
		deb)
			PACKAGE_PATH="$(find /root "$HOME" -maxdepth 3 -type f -name '*.deb' -printf '%T@ %p\n' 2>/dev/null | sort -nr | head -n1 | cut -d' ' -f2-)"
			;;
		rpm)
			PACKAGE_PATH="$(find /root "$HOME" -maxdepth 3 -type f -name '*.rpm' -printf '%T@ %p\n' 2>/dev/null | sort -nr | head -n1 | cut -d' ' -f2-)"
			;;
		pkg.tar.zst)
			PACKAGE_PATH="$(find /root "$HOME" -maxdepth 3 -type f -name '*.pkg.tar.zst' -printf '%T@ %p\n' 2>/dev/null | sort -nr | head -n1 | cut -d' ' -f2-)"
			;;
	esac

	[ -n "${PACKAGE_PATH:-}" ] || die "Kon gegenereerd package-bestand niet vinden."
	[ -f "$PACKAGE_PATH" ] || die "Bestand bestaat niet: $PACKAGE_PATH"
}

install_generated_package() {
	local package_type="$1"
	local pm="$2"
	local pkg_path="$3"

	log "Installeer gegenereerd package: $pkg_path"

	case "$package_type" in
		deb)
			if command -v apt-get >/dev/null 2>&1; then
				apt-get install -y "$pkg_path"
			elif command -v dpkg >/dev/null 2>&1; then
				dpkg -i "$pkg_path"
			else
				die "Geen geschikte installer gevonden voor deb package."
			fi
			;;
		rpm)
			if [ "$pm" = "dnf" ] && command -v dnf >/dev/null 2>&1; then
				dnf install -y "$pkg_path"
			elif [ "$pm" = "yum" ] && command -v yum >/dev/null 2>&1; then
				yum localinstall -y "$pkg_path"
			elif command -v rpm >/dev/null 2>&1; then
				rpm -i "$pkg_path"
			else
				die "Geen geschikte installer gevonden voor rpm package."
			fi
			;;
		pkg.tar.zst)
			if command -v pacman >/dev/null 2>&1; then
				pacman -U --noconfirm "$pkg_path"
			else
				die "pacman niet gevonden voor pkg.tar.zst installatie."
			fi
			;;
		*)
			die "Onbekend package type: $package_type"
			;;
	esac
}

main() {
	require_root "$@"
	normalize_os_release_id
	load_os_release

	local pm
	pm="$(detect_package_manager)"
	[ "$pm" != "unknown" ] || die "Geen ondersteunde package manager gevonden (apt/dnf/yum/pacman/zypper/apk)."

	install_node_and_npm "$pm"
	install_fleetctl

	local package_type
	package_type="$(detect_fleet_package_type)"

	if [ -z "${OS_ID}" ] && [ -z "${OS_ID_LIKE}" ]; then
		package_type="$(ask_package_type)"
	fi

	log "Installeer fleetctl via npm..."
	npm install -g fleetctl

	generate_fleet_package "$package_type"
	install_generated_package "$package_type" "$pm" "$PACKAGE_PATH"

	log "Klaar. Fleet is geinstalleerd en enrollment is uitgevoerd via package scripts."
}

main "$@"
