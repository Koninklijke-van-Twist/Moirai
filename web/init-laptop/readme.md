# Linux enroll

## Wat doet dit init-script

`init-device.sh` maakt een CachyOS-laptop klaar voor gebruik: Fleet-enrollment, KVT RDP, KVT-huisstijl (login-, lockscreen- en desktop-achtergrond, applicatiestarter-iconen), het KWin-effect KVT Energise, de KVT boot-animatie en login-splash, plus **KVT RDS Connect** (FreeRDP-launcher met USB-doorvoer).

## Vereisten

- Standaard CachyOS-installatie
- KDE Plasma als desktop environment
- Deze map compleet op de laptop (inclusief `enroll.sh`, `hosts.sh`, `kvt-rdp/`, `KVT-Energise/`, `bootanimation/`, `kvt-rds-connect/`, `backgr_1.png` en `kvtlogo.png`)
- Netwerkverbinding (voor Fleet-enrollment)
- Een account met sudo-rechten; voer het script uit als de desktop-gebruiker, niet als root-login

## Hoe te gebruiken

Open een terminal in deze map en start het script als de gebruiker wiens Plasma-sessie je wilt inrichten:

```bash
./init-device.sh
```

of:

```bash
bash init-device.sh
```

Het script vraagt zelf om sudo. Je kunt ook direct `sudo ./init-device.sh` gebruiken; KVT Energise en de taakbalk-iconen worden dan nog steeds op `$SUDO_USER` toegepast, niet op root.

Enrollment kan enkele minuten duren (Node.js, npm, fleetctl en het Fleet-package). De boot-animatie herbouwt daarna de initramfs; dat duurt ook even. Blijf bij de laptop tot het script `[INFO] Klaar. Device-init is uitgevoerd.` toont.

Controleer daarna:

- Systeeminstellingen → Animaties → Venster openen/sluiten: **KVT Energise**
- Login-scherm (na uitloggen): achtergrond `backgr_1`
- Lockscreen: dezelfde achtergrond
- Bureaublad: dezelfde achtergrond op alle schermen
- Alle panels: KVT-logo op de applicatiestarter
- Boot: KVT-logo met pulserende dots op zwart
- Splash na inloggen: dezelfde animatie
- App-menu: **KVT RDP** (FreeRDP-verbindingen)
- App-menu: **KVT RDS Connect** (RDP naar de KVT-desktops, optioneel USB)

## Wat doet het script exact

Het script stopt bij de eerste fout (`set -euo pipefail`). Volgorde:

1. **Root**  
   Als het niet als root draait, herstart het zichzelf met `sudo -E bash`. Daarna wordt de desktop-gebruiker bepaald via `SUDO_USER`. Gebruikersgebonden stappen (KWin, Plasma) lopen als die gebruiker, met diens `HOME`, `XDG_*` en D-Bus-sessie.

2. **Fleet-enrollment (`enroll.sh`)**  
   Start `bash enroll.sh`. Dat normaliseert `/etc/os-release` (CachyOS → Arch, zodat Fleet het herkent), installeert Node.js/npm en fleetctl, bouwt een fleetd-package en installeert dat.

3. **KVT RDP (`kvt-rdp/install.sh --system`)**  
   Installeert de KVT RDP-app system-wide (`/usr/local/bin/kvt-rdp` + desktop entry), zodat FreeRDP-verbindingen via het app-menu beschikbaar zijn.

4. **KVT Energise**  
   Draait `KVT-Energise/install.sh` als de desktop-gebruiker. Het effect wordt gekopieerd naar `~/.local/share/kwin/effects/kwin6_effect_kvt_energise`.  
   Daarna wordt het **actief gezet** als venster openen/sluiten-animatie: in `~/.config/kwinrc` onder `[Plugins]` gaan concurrerende effecten in de exclusieve categorie `toplevel-open-close-animation` uit (`scale`, `glide`, `fade` en bekende Burn-My-Windows-varianten), en `kwin6_effect_kvt_energiseEnabled=true`. Via D-Bus (`org.kde.KWin`) wordt KWin herconfigureerd, oude effecten unloaded en KVT Energise geladen. In Systeeminstellingen → Animaties hoort **KVT Energise** geselecteerd te staan.

5. **Login-achtergrond**  
   Kopieert `backgr_1.png` naar `/usr/share/wallpapers/login-custom/backgr_1.png` (en indien aanwezig naar `/var/lib/plasmalogin/wallpapers/`).  
   Voor **Plasma Login Manager** schrijft het in `/etc/plasmalogin.conf`:

   ```
   [Greeter]
   WallpaperPluginId=org.kde.image

   [Greeter][Wallpaper][org.kde.image][General]
   Image=file:///usr/share/wallpapers/login-custom/backgr_1.png
   ```

   Als er een SDDM-thema staat, wordt dezelfde afbeelding ook in `theme.conf.user` gezet.

6. **Lockscreen (alle gebruikers)**  
   Zet dezelfde `backgr_1.png` als lockscreen-wallpaper in `/etc/xdg/kscreenlockerrc` (systeemdefault), `/etc/skel/.config/kscreenlockerrc` (nieuwe accounts) en `~/.config/kscreenlockerrc` van elke bestaande gebruiker onder `/home`.

7. **Desktop-wallpaper (alle gebruikers)**  
   Zet `backgr_1.png` als bureaublad-achtergrond op elke Plasma-desktop (`org.kde.plasma.folder`) in `plasma-org.kde.plasma.desktop-appletsrc`, inclusief `/etc/skel` voor nieuwe accounts. Het wallpaper-plugin wordt op `org.kde.image` gezet. Als plasmashell draait, wordt het live toegepast op alle schermen.

8. **Applicatiestarter-iconen**  
   Kopieert `kvtlogo.png` naar `~/.local/share/icons/kvtlogo.png`. In `~/.config/plasma-org.kde.plasma.desktop-appletsrc` krijgt elke Kickoff/Kicker/Kickerdash-applet in een panel `icon=` op dat pad. Als plasmashell draait, wordt hetzelfde live gezet via `evaluateScript` op alle panels.

9. **Boot-animatie en login-splash (`bootanimation/install.sh`)**  
   Installeert het Plymouth-thema `kvt` (zwarte achtergrond, `main.png` blijft zichtbaar, `dot1`–`dot3` lerpen in een cyclus) naar `/usr/share/plymouth/themes/kvt`, zet het als default en herbouwt de initramfs.  
   Dezelfde animatie wordt als Plasma-splash `org.kvt.splash` gezet voor alle gebruikers (`/etc/xdg/ksplashrc`, `/etc/skel`, bestaande homes).

   Animatielogica (boot en splash, ~0,7 s per fade, lineair):

   - `main.png` altijd zichtbaar
   - start: `dot1` op 100%, lerp naar 0%
   - bij passeren van 50% omlaag: volgende dot lerp naar 100%
   - bij 100%: die dot lerp terug naar 0%
   - cyclus: dot1 → dot2 → dot3 → dot1


10. **KVT RDS Connect (`kvt-rds-connect/install.sh`)**  
   Installeert FreeRDP (`xfreerdp3`) en GTK-afhankelijkheden indien nodig, daarna de launcher naar `~/.local/share/kvt-rds-connect/`, wrapper `~/.local/bin/kvt-rds-connect` en een app-menu-item. Domain is vast `KVT`; de gebruiker vult username, wachtwoord en eventueel een USB-apparaat in.

