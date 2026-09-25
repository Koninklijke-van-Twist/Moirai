# Moirai

ICT-apparaatbeheer op sleutels.kvt.nl: laptops, telefoons en accessoires bijhouden, toewijzen aan gebruikers en delen voor een actueel overzicht van ICT-voorraad.

## Structuur

- `web/index.php` — hoofdpagina (apparatenlijst, toewijzing, labels)
- `web/moirai_data.php` — SQLite-data-laag (apparaten, accessoires, filters)
- `web/odata.php` — OData-client, lokale filecache-widget, optionele Mímir-proxy
- `web/auth_helper.php` — company-discovery / environment-helpers (BC of Mímir)
- `web/localization.php` — meertalige UI-teksten
- `web/nightly.php` — nightly aging-alerts (SQLite/mail; geen OData-fetch)
- `web/auth.php` — credentials (niet in git, lokaal/server aanwezig)

## Lokaal draaien

Via XAMPP: `http://localhost/Moirai/web/index.php`

Productie: `https://sleutels.kvt.nl/moirai/`

## Device API

Machine/JSON API for ICT device actions: `web/api.php` (API keys in local `auth.php`). Spec: `web/docs/api.md` or `GET api.php?action=help`.

## Linux-laptop init

De enroll-download (`web/download_enroll.php`, alleen admins) pakt `web/init-laptop/` on-the-fly in als `init-laptop.zip`. Die map is de bron in git; er staat geen binary zip in de repository. Directe HTTP-toegang tot de map is geblokkeerd (`web/init-laptop/.htaccess`). De zip die de gebruiker krijgt heeft dezelfde top-levelindeling als voorheen (`init-device.sh`, `enroll.sh`, `hosts.sh`, `kvt-rdp/`, `kvt-rds-connect/`, `KVT-Energise/`, `bootanimation/`, afbeeldingen).

## Mímir (optioneel)

Zet in `web/auth.php` (niet in git):

```php
$mimirApi  = 'mimir_…';
// optioneel:
$mimirBase = 'https://sleutels.kvt.nl/mimir/api';
```

Met `$mimirApi` gezet zijn `$auth_list`, `$environment`, `$baseUrl` en `$auth` ongebruikt voor Business Central — company-discovery en alle OData-fetches (`odata_get_all`) lopen via Mímir. Zonder `$mimirApi` blijft het bestaande directe BC-pad ongewijzigd.

**max_age-beleid**

| Soort fetch | `max_age` naar Mímir |
| --- | --- |
| `nightly.php` | bestaat (aging-alerts, **geen** OData) — constant `MOIRAI_NIGHTLY_MAX_AGE` (**14400**, 4u) gereserveerd in `odata.php` voor eventuele future OData-nightly |
| `hourly.php` | niet aanwezig in Moirai |
| UI / on-demand | bestaande TTLs — default **300** (`odata_get_all`) |

Tim moet `$mimirApi` (en optioneel `$mimirBase`) lokaal/op de server zetten; `auth.php` wordt niet gecommit. Zie [Mímir Implementatie](https://wiki.kvt.nl/books/mimir/page/implementatie).

## auth.php

Geen `auth.php` in deze repository (staat in `.gitignore`). Lokaal/op de server de Mímir-sleutel zetten zoals hierboven; legacy BC-credentials alleen nodig zonder `$mimirApi`. Graph-credentials blijven nodig voor gebruikerslijsten.
