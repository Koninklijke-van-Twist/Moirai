#!/usr/bin/env bash
# Install KVT Energise (KWin window open/close effect) on KDE Plasma / CachyOS.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
EFFECT_ID="kwin6_effect_kvt_energise"
SRC="$SCRIPT_DIR/effect/$EFFECT_ID"
DEST_DIR="${XDG_DATA_HOME:-$HOME/.local/share}/kwin/effects"
DEST="$DEST_DIR/$EFFECT_ID"

die() { echo "Error: $*" >&2; exit 1; }

echo "==> KVT Energise installer"
echo

# Basic environment checks
if [[ ! -d "$SRC" ]]; then
  die "Effect payload missing at: $SRC"
fi

if [[ -z "${XDG_CURRENT_DESKTOP:-}" ]] && [[ -z "${KDE_FULL_SESSION:-}" ]]; then
  echo "Warning: KDE/Plasma session not clearly detected; continuing anyway."
fi

if ! command -v kwin_wayland >/dev/null 2>&1 && ! command -v kwin_x11 >/dev/null 2>&1; then
  echo "Warning: kwin binary not found in PATH. Is KWin installed?"
fi

mkdir -p "$DEST_DIR"
rm -rf "$DEST"
cp -a "$SRC" "$DEST"
echo "Installed effect to: $DEST"

# Enable as window open/close animation (exclusive category)
KWRITE=""
if command -v kwriteconfig6 >/dev/null 2>&1; then
  KWRITE=kwriteconfig6
elif command -v kwriteconfig5 >/dev/null 2>&1; then
  KWRITE=kwriteconfig5
fi

if [[ -n "$KWRITE" ]]; then
  # Turn off common competing open/close effects so this one can take over.
  for key in \
    kwin4_effect_fadeEnabled \
    kwin4_effect_scaleEnabled \
    kwin4_effect_fadingpopupsEnabled \
    magiclampEnabled \
    kwin6_effect_energize_aEnabled \
    kwin6_effect_energize_bEnabled \
    kwin6_effect_fireEnabled \
    kwin6_effect_glideEnabled \
    kwin6_effect_tvEnabled \
    kwin6_effect_hexagonEnabled \
    kwin6_effect_incinerateEnabled
  do
    "$KWRITE" --file kwinrc --group Plugins --key "$key" false 2>/dev/null || true
  done

  "$KWRITE" --file kwinrc --group Plugins --key "${EFFECT_ID}Enabled" true
  echo "Enabled plugin in ~/.config/kwinrc: ${EFFECT_ID}Enabled=true"
else
  echo "Warning: kwriteconfig6/5 not found; enable the effect manually in System Settings."
fi

# Ask KWin to reload config / load effect
reload_kwin() {
  if command -v qdbus6 >/dev/null 2>&1; then
    qdbus6 org.kde.KWin /KWin reconfigure >/dev/null 2>&1 || true
    qdbus6 org.kde.KWin /Effects loadEffect "$EFFECT_ID" >/dev/null 2>&1 || true
    return 0
  fi
  if command -v qdbus >/dev/null 2>&1; then
    qdbus org.kde.KWin /KWin reconfigure >/dev/null 2>&1 || true
    qdbus org.kde.KWin /Effects loadEffect "$EFFECT_ID" >/dev/null 2>&1 || true
    return 0
  fi
  if command -v dbus-send >/dev/null 2>&1; then
    dbus-send --session --dest=org.kde.KWin /KWin org.kde.KWin.reconfigure >/dev/null 2>&1 || true
    return 0
  fi
  return 1
}

if reload_kwin; then
  echo "Requested KWin reconfigure."
else
  echo "Could not talk to KWin over D-Bus; log out/in or run: systemsettings kcm_kwin_effects"
fi

echo
echo "Done. Look for \"KVT Energise\" under:"
echo "  System Settings → Window Management → Desktop Effects"
echo "  (Window Open/Close Animation)"
echo
echo "If it does not appear yet: toggle another effect Apply, then enable KVT Energise."
