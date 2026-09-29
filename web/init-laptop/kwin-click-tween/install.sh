#!/usr/bin/env bash
set -euo pipefail
cd -- "$(dirname -- "${BASH_SOURCE[0]}")"

mode=${1:-install}
case "$mode" in
    install|--build-only|--uninstall) ;;
    *) echo "Gebruik: $0 [--build-only|--uninstall]" >&2; exit 2 ;;
esac
if [[ $mode != --build-only ]]; then
    [[ $EUID -ne 0 ]] || { echo 'Start dit script als gewone gebruiker, zonder sudo ervoor.' >&2; exit 1; }
    for tool in sudo kwriteconfig6 qdbus6; do
        command -v "$tool" >/dev/null || { echo "Ontbreekt: $tool" >&2; exit 1; }
    done
fi

# This build intentionally targets Arch's Plasma 6.7 layout and private API.
plugin_dir=/usr/lib/qt6/plugins/kwin/effects/plugins
plugin_file=$plugin_dir/kwin_effect_clicktween.so
config_file=/usr/lib/qt6/plugins/kwin/effects/configs/kwin_clicktween_config.so
if [[ $mode == --uninstall ]]; then
    kwriteconfig6 --file kwinrc --group Plugins --key kwin_effect_clicktweenEnabled false
    qdbus6 org.kde.KWin /Effects org.kde.kwin.Effects.unloadEffect kwin_effect_clicktween || true
    sudo rm -f -- "$plugin_file" "$config_file"
    echo 'Click Tween verwijderd. Bij een D-Bus-fout: meld opnieuw aan.'
    exit 0
fi

for tool in g++ pkg-config; do
    command -v "$tool" >/dev/null || { echo "Ontbreekt: $tool. Installeer: sudo pacman -S --needed gcc pkgconf" >&2; exit 1; }
done
[[ -x /usr/lib/qt6/moc && -f /usr/include/kwin/config-kwin.h ]] || {
    echo 'KWin/Qt-ontwikkelbestanden ontbreken. Nodig op Arch: kwin qt6-base qt6-declarative.' >&2
    exit 1
}
version=$(sed -n 's/^#define KWIN_PLUGIN_VERSION_STRING "\([^"]*\)"/\1/p' /usr/include/kwin/config-kwin.h)
[[ $version == 6.7.* ]] || { echo "Deze versie ondersteunt KWin 6.7.x; gevonden: $version" >&2; exit 1; }
mkdir -p build
read -r -a qt_flags <<< "$(pkg-config --cflags Qt6Core Qt6Gui Qt6Quick Qt6DBus Qt6Widgets wayland-server epoxy libdrm)"
read -r -a qt_libs <<< "$(pkg-config --libs Qt6Core Qt6Gui Qt6Quick Qt6DBus Qt6Widgets wayland-server epoxy libdrm)"
includes=(-I/usr/include/kwin -I/usr/include/KF6/KCoreAddons -I/usr/include/KF6/KConfig -I/usr/include/KF6/KConfigCore -I/usr/include/KF6/KCMUtils -I/usr/include/KF6/KCMUtilsCore -I/usr/include/KF6/KWindowSystem -I. -Ibuild)
/usr/lib/qt6/moc "${qt_flags[@]}" "${includes[@]}" clicktween.cpp -o build/clicktween.moc
g++ -std=c++23 -O2 -Wall -Wextra -fPIC -shared -Wl,-z,defs \
    "${qt_flags[@]}" "${includes[@]}" clicktween.cpp -o build/kwin_effect_clicktween.so \
    -lkwin -lKF6CoreAddons -lKF6ConfigCore "${qt_libs[@]}"
/usr/lib/qt6/moc "${qt_flags[@]}" "${includes[@]}" clicktween_config.cpp -o build/clicktween_config.moc
g++ -std=c++23 -O2 -Wall -Wextra -fPIC -shared -Wl,-z,defs \
    "${qt_flags[@]}" "${includes[@]}" clicktween_config.cpp -o build/kwin_clicktween_config.so \
    -lKF6KCMUtils -lKF6KCMUtilsCore -lKF6CoreAddons -lKF6ConfigCore "${qt_libs[@]}"
g++ -std=c++17 -fPIC "${qt_flags[@]}" check-plugin.cpp -o build/check-plugin "${qt_libs[@]}"
build/check-plugin "$PWD/build/kwin_effect_clicktween.so"
/usr/lib/qt6/moc "${qt_flags[@]}" check-config.cpp -o build/check-config.moc
g++ -std=c++23 -fPIC "${qt_flags[@]}" "${includes[@]}" check-config.cpp -o build/check-config \
    -lKF6KCMUtils -lKF6KCMUtilsCore -lKF6CoreAddons -lKF6ConfigCore "${qt_libs[@]}"
# Isolate configuration and D-Bus so the check cannot change the live desktop.
test_config=$(mktemp -d)
QT_QPA_PLATFORM=offscreen XDG_CONFIG_HOME="$test_config" \
    dbus-run-session -- build/check-config "$PWD/build/kwin_clicktween_config.so"
rm -rf -- "$test_config"
echo "Gebouwd voor KWin $version; instellingenmodule en 31 curves gecontroleerd."
[[ $mode != --build-only ]] || exit 0

# Obtain credentials before touching the running effect.
sudo -v

# Qt keeps native plugin libraries resident (PreventUnloadHint). Recreating
# the effect does not reload updated machine code. Leave the running effect
# alone and replace files atomically; an upgrade requires a new KWin session.
upgrading=false
[[ ! -f "$plugin_file" ]] || upgrading=true
sudo install -Dm755 build/kwin_effect_clicktween.so "$plugin_file.new"
sudo mv -f -- "$plugin_file.new" "$plugin_file"
sudo install -Dm755 build/kwin_clicktween_config.so "$config_file.new"
sudo mv -f -- "$config_file.new" "$config_file"
# Animation defaults are compiled from settings.h (150 ms / easeOutBack,
# 720 ms / easeOutElastic). Existing user settings take precedence.
kwriteconfig6 --file kwinrc --group Plugins --key kwin_effect_clicktweenEnabled true
# Also activate on reinstall, but never unload an already running instance.
if result=$(qdbus6 org.kde.KWin /Effects org.kde.kwin.Effects.isEffectLoaded kwin_effect_clicktween 2>/dev/null) && [[ $result == true ]]; then
    echo 'Click Tween is geïnstalleerd en actief.'
elif result=$(qdbus6 org.kde.KWin /Effects org.kde.kwin.Effects.loadEffect kwin_effect_clicktween 2>/dev/null) && [[ $result == true ]]; then
    echo 'Click Tween is geïnstalleerd en actief.'
else
    echo 'Geïnstalleerd en ingeschakeld. Meld af en opnieuw aan om het effect te laden.'
fi
if [[ $upgrading == true ]]; then
    echo 'Update geïnstalleerd. Meld één keer af en opnieuw aan om de nieuwe plugincode te laden.'
    echo 'Uit-/aanvinken of Systeeminstellingen heropenen vervangt de code in KWin niet.'
fi
