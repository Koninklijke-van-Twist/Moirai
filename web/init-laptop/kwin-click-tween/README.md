# Click Tween voor KDE Plasma 6.7 / Wayland

De muisaanwijzer krimpt naar 80% in 150 ms wanneer links of rechts wordt
ingedrukt. Hij blijft klein tijdens vasthouden en slepen en groeit in 720 ms
terug na loslaten. Bij beide knoppen tegelijk blijft hij klein totdat beide
los zijn. De middelste knop verandert de animatie niet.

Het effect schaalt KWin's bestaande cursor rond zijn hotspot. Cursorvorm,
zichtbaarheid en positie blijven door KWin beheerd. Het onderschept geen
klikken. Bij uitschakelen en schermvergrendeling wordt de schaal hersteld.

## Instellingen

Open Systeeminstellingen → Bureaubladeffecten → Click Tween en klik op de
instellingenknop. Meld na een plugin-update één keer af en opnieuw aan.
KWin/Qt houdt native plugincode in het geheugen; uit-/aanvinken of alleen
Systeeminstellingen heropenen laadt een vervangen bibliotheek niet opnieuw.
Daarna werken gewone instellingenwijzigingen direct via Toepassen, zonder afmelden.

- Klik-tween-tijd: 0–5000 ms, standaard 150 ms.
- Release-tween-tijd: 0–5000 ms, standaard 720 ms.
- Afzonderlijke easing: standaard easeOutBack voor klik en easeOutElastic voor release.
- 31 keuzes: Linear plus In, Out en InOut voor Sine, Quad, Cubic, Quart,
  Quint, Expo, Circ, Back, Elastic en Bounce, zoals op https://easings.net/.
  De implementatie gebruikt Qt QEasingCurve; curveparameters zijn Qt-standaardwaarden.

0 ms betekent direct. Back en Elastic kunnen voorbij de eindgrootte veren.
Toepassen bewaart de instellingen in `~/.config/kwinrc`, groep
`Effect-clicktween`, en werkt direct voor de volgende animatie.
Standaardinstellingen herstelt de oorspronkelijke tijden en curves.
De klik wordt niet vertraagd: de animatie begint bij het indrukken.

## Installeren

Voor jouw Arch-gebaseerde systeem met KWin 6.7.x:

```bash
cd ~/kwin-click-tween
./install.sh
```

Het script bouwt lokaal, vraagt sudo voor de plugin in de systeemmap,
en schakelt hem voor de huidige gebruiker direct en blijvend in, ook bij
een herinstallatie. Bestaande animatie-instellingen blijven behouden; zonder
opgeslagen instellingen gelden de bovenstaande standaardwaarden.
Bij een update blijft af- en aanmelden nodig om de nieuwe plugincode te laden. Start het script als je eigen
gebruiker, niet met `sudo ./install.sh`. Bouwvereisten: `gcc`, `pkgconf`,
`kwin`, `qt6-base`, `qt6-declarative`, `kcoreaddons`, `kconfig`,
`kcmutils`, `kwindowsystem`, `wayland`, `libepoxy` en `libdrm`.
De meeste zijn al onderdeel van jouw Plasma-installatie.

Alleen bouwen zonder installeren of instellingen wijzigen:

```bash
./install.sh --build-only
```

## Uitschakelen of verwijderen

Uitschakelen kan via Systeeminstellingen → Bureaubladeffecten → Click Tween,
of via:

```bash
kwriteconfig6 --file kwinrc --group Plugins --key kwin_effect_clicktweenEnabled false
qdbus6 org.kde.KWin /Effects org.kde.kwin.Effects.unloadEffect kwin_effect_clicktween
```

Volledig verwijderen:

```bash
./install.sh --uninstall
```

## Compatibiliteit en controle

Dit is een experimentele native plugin voor de interne KWin 6.7 API.
Na een KWin-update voer je het installatiescript opnieuw uit. Andere
KWin-minorversies worden geweigerd totdat de code daarvoor is gecontroleerd.
De plugin draait in KWin zelf; bewaar open werk voordat je de eerste keer
installeert. Gelijktijdige cursor-effecten zoals Shake Cursor of Zoom kunnen
hun eigen cursor tekenen en moeten apart worden gecontroleerd.

Handmatig controleren na installatie:

- Links en rechts: korte klik, vasthouden, slepen en snel herhaald klikken.
- Beide knoppen indrukken; één loslaten mag de cursor niet herstellen.
- Test pijl, tekstcursor en handje, ook in XWayland-apps.
- Controleer op meerdere schermen met verschillende schaalfactoren.
- Uitschakelen tijdens vasthouden moet de oorspronkelijke grootte herstellen.

De buildcontrole controleert compileren, linken, metadata en het laden van
de pluginfactory tegen de lokale KWin-versie. Visueel gedrag moet nog in een
actieve sessie worden getest.

Licentie: GPL-2.0-or-later.
