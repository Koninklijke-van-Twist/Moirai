#!/usr/bin/env bash
# Pacman PostTransaction helper. A failed rebuild must not fail the transaction.
set -u

source_dir=/usr/local/share/kwin-click-tween
plugin_file=/usr/lib/qt6/plugins/kwin/effects/plugins/kwin_effect_clicktween.so
config_file=/usr/lib/qt6/plugins/kwin/effects/configs/kwin_clicktween_config.so

say_fail() {
    echo "Click Tween opnieuw bouwen na de KWin-upgrade is mislukt. De vorige plugin blijft staan." >&2
}

if [[ ! -x $source_dir/install.sh ]]; then
    say_fail
    echo "Bron ontbreekt: $source_dir" >&2
    exit 0
fi

work=$(mktemp -d) || { say_fail; exit 0; }
cleanup() { rm -rf -- "$work"; }
trap cleanup EXIT

cp -a -- "$source_dir/." "$work/" || { say_fail; exit 0; }
rm -rf -- "$work/build" || { say_fail; exit 0; }

set +e
( cd -- "$work" && ./install.sh --build-only )
build_status=$?
set -e
if [[ $build_status -ne 0 ]]; then
    say_fail
    exit 0
fi
if [[ ! -f $work/build/kwin_effect_clicktween.so || ! -f $work/build/kwin_clicktween_config.so ]]; then
    say_fail
    exit 0
fi

# Replace both plugins only after the new files are fully written.
install -Dm755 "$work/build/kwin_effect_clicktween.so" "$plugin_file.new" || { say_fail; exit 0; }
install -Dm755 "$work/build/kwin_clicktween_config.so" "$config_file.new" || { rm -f -- "$plugin_file.new"; say_fail; exit 0; }
mv -f -- "$plugin_file.new" "$plugin_file" || { say_fail; exit 0; }
mv -f -- "$config_file.new" "$config_file" || { say_fail; exit 0; }

echo "Click Tween is opnieuw gebouwd. Meld af en weer aan."
exit 0
