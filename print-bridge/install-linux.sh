#!/usr/bin/env bash
set -euo pipefail

SERVICE_NAME="moirai-print-bridge"
ENV_FILE="/etc/moirai/print-bridge.env"
UDEV_FILE="/etc/udev/rules.d/99-moirai-pt210.rules"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
INSTALL_DIR="${MOIRAI_BRIDGE_DIR:-$SCRIPT_DIR}"
SERVICE_USER="${MOIRAI_SERVICE_USER:-${SUDO_USER:-$(whoami)}}"
NODE_BIN="$(command -v node || true)"

usage() {
    cat <<'EOF'
Moirai print bridge – Linux installatie (systemd)

Gebruik:
  sudo ./install-linux.sh
  sudo ./install-linux.sh --user www-data
  sudo ./install-linux.sh --dir /opt/moirai/print-bridge
  sudo ./install-linux.sh --uninstall

Opties:
  --user <naam>   Gebruiker voor de systemd-service (standaard: huidige sudo-gebruiker)
  --dir <pad>     Installatiemap met package.json (standaard: map van dit script)
  --uninstall     Verwijder service, env-bestand en udev-regel
  -h, --help      Toon deze help
EOF
}

log() {
    printf '[moirai-install] %s\n' "$*"
}

require_root() {
    if [[ "${EUID}" -ne 0 ]]; then
        echo "Dit script moet als root worden uitgevoerd (gebruik sudo)." >&2
        exit 1
    fi
}

check_node() {
    if [[ -z "$NODE_BIN" ]]; then
        echo "Node.js (>= 18) is vereist maar niet gevonden in PATH." >&2
        exit 1
    fi

    local major
    major="$("$NODE_BIN" -p "process.versions.node.split('.')[0]")"
    if [[ "$major" -lt 18 ]]; then
        echo "Node.js 18 of hoger is vereist (gevonden: $("$NODE_BIN" -v))." >&2
        exit 1
    fi
}

install_deps() {
    log "npm install in $INSTALL_DIR"
    local home_dir
    home_dir="$(getent passwd "$SERVICE_USER" | cut -d: -f6)"
    cd "$INSTALL_DIR"
    if [[ -f package-lock.json ]]; then
        sudo -u "$SERVICE_USER" env HOME="$home_dir" npm ci --omit=dev
    else
        sudo -u "$SERVICE_USER" env HOME="$home_dir" npm install --omit=dev
    fi
}

write_env_file() {
    install -d -m 755 /etc/moirai
    if [[ ! -f "$ENV_FILE" ]]; then
        cat >"$ENV_FILE" <<EOF
# Moirai print bridge
MOIRAI_BRIDGE_HOST=127.0.0.1
MOIRAI_BRIDGE_PORT=9173
MOIRAI_USE_USB_DIRECT=1
# MOIRAI_USB_VID=28e9
# MOIRAI_USB_PID=0289
EOF
        chmod 644 "$ENV_FILE"
        log "Aangemaakt: $ENV_FILE"
    else
        log "Bestaand env-bestand behouden: $ENV_FILE"
    fi
}

write_udev_rule() {
    cat >"$UDEV_FILE" <<'EOF'
# GOOJPRT PT-210 – USB-toegang voor Moirai print bridge
SUBSYSTEM=="usb", ATTR{idVendor}=="28e9", ATTR{idProduct}=="0289", MODE="0660", GROUP="plugdev"
EOF
    chmod 644 "$UDEV_FILE"
    udevadm control --reload-rules
    udevadm trigger
    log "Udev-regel geïnstalleerd: $UDEV_FILE"
}

add_user_groups() {
    local groups=()
    if getent group plugdev >/dev/null 2>&1; then
        groups+=("plugdev")
    fi
    if getent group lp >/dev/null 2>&1; then
        groups+=("lp")
    fi

    for group in "${groups[@]}"; do
        if id -nG "$SERVICE_USER" | tr ' ' '\n' | grep -qx "$group"; then
            log "Gebruiker $SERVICE_USER zit al in groep $group"
        else
            usermod -aG "$group" "$SERVICE_USER"
            log "Gebruiker $SERVICE_USER toegevoegd aan groep $group"
        fi
    done
}

write_systemd_unit() {
    local unit_file="/etc/systemd/system/${SERVICE_NAME}.service"
    cat >"$unit_file" <<EOF
[Unit]
Description=Moirai print bridge (ESC/POS)
Documentation=file://${INSTALL_DIR}
After=network.target

[Service]
Type=simple
User=${SERVICE_USER}
WorkingDirectory=${INSTALL_DIR}
EnvironmentFile=-${ENV_FILE}
ExecStart=${NODE_BIN} index.js
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF
    chmod 644 "$unit_file"
    log "Systemd-unit geïnstalleerd: $unit_file"
}

enable_service() {
    systemctl daemon-reload
    systemctl enable "$SERVICE_NAME"
    systemctl restart "$SERVICE_NAME"
    log "Service gestart: $SERVICE_NAME"
    systemctl --no-pager --full status "$SERVICE_NAME" || true
}

uninstall() {
    require_root
    if systemctl is-enabled --quiet "$SERVICE_NAME" 2>/dev/null; then
        systemctl disable --now "$SERVICE_NAME"
    fi
    rm -f "/etc/systemd/system/${SERVICE_NAME}.service"
    rm -f "$ENV_FILE"
    rm -f "$UDEV_FILE"
    systemctl daemon-reload
    udevadm control --reload-rules
    log "Verwijderd: $SERVICE_NAME"
}

install_all() {
    require_root
    check_node

    if [[ ! -f "$INSTALL_DIR/package.json" ]]; then
        echo "Geen package.json gevonden in $INSTALL_DIR" >&2
        exit 1
    fi

    if ! id "$SERVICE_USER" >/dev/null 2>&1; then
        echo "Gebruiker bestaat niet: $SERVICE_USER" >&2
        exit 1
    fi

    chown -R "$SERVICE_USER":"$SERVICE_USER" "$INSTALL_DIR"
    install_deps
    write_env_file
    write_udev_rule
    add_user_groups
    write_systemd_unit
    enable_service

    cat <<EOF

Klaar.
  Status:  sudo systemctl status ${SERVICE_NAME}
  Logs:    sudo journalctl -u ${SERVICE_NAME} -f
  Herstart: sudo systemctl restart ${SERVICE_NAME}
  Config:  ${ENV_FILE}

Let op: uitloggen en opnieuw inloggen als de service-user net aan plugdev/lp is toegevoegd.
EOF
}

UNINSTALL=0

while [[ $# -gt 0 ]]; do
    case "$1" in
        --user)
            SERVICE_USER="$2"
            shift 2
            ;;
        --dir)
            INSTALL_DIR="$(cd "$2" && pwd)"
            shift 2
            ;;
        --uninstall)
            UNINSTALL=1
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Onbekende optie: $1" >&2
            usage
            exit 1
            ;;
    esac
done

if [[ "$UNINSTALL" -eq 1 ]]; then
    uninstall
else
    install_all
fi
