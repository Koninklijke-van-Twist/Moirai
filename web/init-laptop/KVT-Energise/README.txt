KVT Energise — KWin window open/close effect
============================================

Based on Burn-My-Windows "Energize B", with an analytical SVG linger mask.
On close the effect sweeps at the same speed everywhere; logo-mask pixels keep
their effect color ~2× as long. The mask is computed in the shader from geometry
(no baked bitmap), so it stays sharp at any window size.

Requirements
------------
- CachyOS / Arch (or similar) with KDE Plasma 6 + KWin 6.x
- Works on KWin 6.7 (desktop GLSL 1.40) and KWin 6.8 (OpenGL ES, GLSL ES 3.00)
- There is no hard KWin or Plasma version check. install.sh always tries the
  effect. It stops only when the user journal reports "Shader failed to load"
  for kwin6_effect_kvt_energise.
- Scripted KWin effects support (standard on Plasma)

Shaders
-------
KWin 6.8 compiles effect shaders as #version 300 es. GLSL ES rejects global
initializers that are not constant expressions, so uSize and iTexCoord are
macros (same fix as upstream Burn-My-Windows for OpenGL ES), not globals
initialized from uniforms. texture2D is aliased to texture() on GLSL 3.00+.
Both energize-b.frag (KWIN_LEGACY) and energize-b_core.frag (KWIN) follow this.
KWin loads the _core file when it is present.

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
