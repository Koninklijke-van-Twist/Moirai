#!/usr/bin/env bash

set -euo pipefail

HOSTS_FILE="/etc/hosts"
MARKER_BEGIN="# BEGIN KVT hosts (managed by hosts.sh)"
MARKER_END="# END KVT hosts (managed by hosts.sh)"
# Legacy markers from earlier hosts.sh versions
LEGACY_BEGIN="# BEGIN KVT-RDS hosts (managed by hosts.sh)"
LEGACY_END="# END KVT-RDS hosts (managed by hosts.sh)"

log() {
	printf '[INFO] %s\n' "$1"
}

die() {
	printf '[ERROR] %s\n' "$1" >&2
	exit 1
}

require_root() {
	if [ "${EUID:-$(id -u)}" -ne 0 ]; then
		die "hosts.sh moet als root draaien."
	fi
}

HOSTS_BLOCK=$(cat <<'HOSTS'
# Windows host addresses
10.154.110.40 KVT-DC1
10.154.110.41 KVT-DC2
10.154.110.63 KVT-VAULT
10.154.110.66 KVT-VAULT-NTT
10.154.110.54 kvt-invoicein.kvt.local
10.154.110.54 kvt-invoicein
10.154.110.48 KVT-VLT01
10.154.110.60 KVTMD365SQL
10.154.110.58 KVTMD365NW
10.154.110.49 KVT-LT01
10.154.110.50 KVT-LC01NW
10.154.110.51 KVT-LC01SQL
10.154.110.61 KVT-Analysis
10.154.110.43 KVTAP01
10.154.110.44 KVTAP02
10.154.110.80 KVT-RDS-CB
10.154.110.78 KVT-RDS-SH1
10.154.110.79 KVT-RDS-SH2
10.154.110.80 KVT-RDS-CB.kvt.local
10.154.110.78 KVT-RDS-SH1.kvt.local
10.154.110.79 KVT-RDS-SH2.kvt.local
HOSTS
)

strip_managed_block() {
	local begin="$1" end="$2" src="$3" dest="$4"
	awk -v begin="$begin" -v end="$end" '
		$0 == begin { skip=1; next }
		$0 == end { skip=0; next }
		!skip { print }
	' "$src" > "$dest"
}

update_hosts() {
	[ -f "$HOSTS_FILE" ] || die "Hosts-bestand niet gevonden: $HOSTS_FILE"

	local tmp stripped
	tmp="$(mktemp)"
	stripped="$(mktemp)"

	cp "$HOSTS_FILE" "$tmp"

	if grep -qF "$MARKER_BEGIN" "$tmp"; then
		strip_managed_block "$MARKER_BEGIN" "$MARKER_END" "$tmp" "$stripped"
		mv "$stripped" "$tmp"
		log "Bestaande KVT hosts-regels vervangen."
	elif grep -qF "$LEGACY_BEGIN" "$tmp"; then
		strip_managed_block "$LEGACY_BEGIN" "$LEGACY_END" "$tmp" "$stripped"
		mv "$stripped" "$tmp"
		log "Oude KVT-RDS hosts-regels vervangen."
	else
		rm -f "$stripped"
		log "KVT hosts-regels toevoegen."
	fi

	# Ensure a blank line before the block when the file does not end with one.
	if [ -s "$tmp" ] && [ "$(tail -c1 "$tmp" | wc -l)" -eq 0 ]; then
		printf '\n' >> "$tmp"
	fi
	if [ -s "$tmp" ] && [ "$(tail -n1 "$tmp")" != "" ]; then
		printf '\n' >> "$tmp"
	fi

	{
		printf '%s\n' "$MARKER_BEGIN"
		printf '%s\n' "$HOSTS_BLOCK"
		printf '%s\n' "$MARKER_END"
	} >> "$tmp"

	install -m 644 "$tmp" "$HOSTS_FILE"
	rm -f "$tmp"
	log "Hosts-bestand bijgewerkt: $HOSTS_FILE"
}

main() {
	require_root
	update_hosts
}

main "$@"
