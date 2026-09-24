# Elpis

Webapp om per projectmanager projecten en inkoopplanningsregels (ontvangstmonitor) uit Business Central te tonen.

## Structuur

- `web/index.php` — hoofdpagina
- `web/elpis_data.php` — OData-queries en data-opbouw
- `web/odata.php` — OData-client en cache-widget
- `web/localization.php` — meertalige UI-teksten
- `web/auth.php` — credentials (niet in git, lokaal aanwezig)

## Lokaal draaien

Via XAMPP: `http://localhost/Elpis/web/index.php`

Productie: `https://sleutels.kvt.nl/elpis/`

Dev-hulpmiddel voor BC-probes: `php web/bc_probe.php`

## Device API

Machine/JSON API for ICT device actions: `web/api.php` (API keys in local `auth.php`). Spec: `web/docs/api.md` or `GET api.php?action=help`.

## Linux-laptop init

De enroll-download (`web/download_enroll.php`, alleen admins) pakt `web/init-laptop/` on-the-fly in als `init-laptop.zip`. Die map is de bron in git; er staat geen binary zip in de repository. Directe HTTP-toegang tot de map is geblokkeerd (`web/init-laptop/.htaccess`). De zip die de gebruiker krijgt heeft dezelfde top-levelindeling als voorheen (`init-device.sh`, `enroll.sh`, `hosts.sh`, `kvt-rdp/`, `kvt-rds-connect/`, `KVT-Energise/`, `bootanimation/`, afbeeldingen).
