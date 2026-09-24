KVT Energise — KWin window open/close effect
============================================

Based on Burn-My-Windows "Energize B", with an analytical SVG linger mask.
On close the effect sweeps at the same speed everywhere; logo-mask pixels keep
their effect color ~2× as long. The mask is computed in the shader from geometry
(no baked bitmap), so it stays sharp at any window size.

Requirements
------------
- CachyOS / Arch (or similar) with KDE Plasma 6 + KWin
- Scripted KWin effects support (standard on Plasma)

Install
-------
1. Copy this whole folder to the other computer (USB, scp, …).
2. Open a terminal in this folder.
3. Run:

     ./install.sh

4. Close a window to test. In System Settings the effect is named "KVT Energise".

Uninstall
---------
  rm -rf ~/.local/share/kwin/effects/kwin6_effect_kvt_energise
  kwriteconfig6 --file kwinrc --group Plugins --key kwin6_effect_kvt_energiseEnabled false
  qdbus6 org.kde.KWin /KWin reconfigure

License
-------
GPLv3 (inherits Burn-My-Windows / KWin effect licensing).
