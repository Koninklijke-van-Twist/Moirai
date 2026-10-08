# Moirai

ICT-apparaatbeheer op sleutels.kvt.nl: laptops, telefoons, accessoires en SIM-kaarten bijhouden, toewijzen aan gebruikers en delen voor een actueel overzicht van ICT-voorraad.

## Structuur

- `web/index.php` — hoofdpagina (apparatenlijst, toewijzing, labels)
- `web/moirai_data.php` — SQLite-data-laag (apparaten, accessoires, SIM-kaarten, filters)
  - SIM-kaarten (`simcards`): code en telefoonnummer uniek (genormaliseerd, unieke DB-indexen); geen labels. Tabel en indexen worden idempotent aangemaakt bij de eerste DB-connectie.
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

De enroll-download (`web/download_enroll.php`, alleen admins) pakt `web/init-laptop/` on-the-fly in als `init-laptop.zip`. Die map is de bron in git; er staat geen binary zip in de repository. Directe HTTP-toegang tot de map is geblokkeerd (`web/init-laptop/.htaccess`). De zip die de gebruiker krijgt heeft dezelfde top-levelindeling als voorheen (`init-device.sh`, `enroll.sh`, `hosts.sh`, `kvt-rdp/`, `kvt-rds-connect/`, `KVT-Energise/`, `kwin-click-tween/`, `bootanimation/`, afbeeldingen).

## Mímir (optioneel)

Zet in `web/auth.php` (niet in git):

```php
$mimirApi  = 'mimir_…';
// optioneel:
$mimirBase = 'https://sleutels.kvt.nl/mimir/api';
```

Met `$mimirApi` gezet proberen company-discovery en alle OData-fetches (`odata_get_all`, `odata_mimir_query`, `odata_mimir_fetch_all`) eerst Mímir. Mislukt die aanroep (verbinding/timeout, non-2xx, ongeldige JSON of een Mímir-foutpayload), dan haalt Moirai dezelfde data op via het directe Business Central-pad (`$baseUrl`, `$auth` / `$auth_list`, `$environment`, lokale odata-filecache) en slaat Mímir voor de rest van dat PHP-proces over. Laat die BC-credentials in `auth.php` staan naast `$mimirApi`; ontbreken ze, dan komt de oorspronkelijke Mímir-fout terug. Dat geldt voor webverzoeken én voor CLI (`nightly.php`, `download_enroll.php` en andere scripts die `auth.php` laden). Zonder `$mimirApi` blijft het bestaande directe BC-pad ongewijzigd.

**max_age-beleid**

| Soort fetch | `max_age` naar Mímir |
| --- | --- |
| `nightly.php` | bestaat (aging-alerts, **geen** OData) — constant `MOIRAI_NIGHTLY_MAX_AGE` (**14400**, 4u) gereserveerd in `odata.php` voor eventuele future OData-nightly |
| `hourly.php` | niet aanwezig in Moirai |
| UI / on-demand | bestaande TTLs — default **300** (`odata_get_all`) |

Tim moet `$mimirApi` (en optioneel `$mimirBase`) lokaal/op de server zetten, en de BC-credentials daar laten staan als fallback. `auth.php` wordt niet gecommit. Zie [Mímir Implementatie](https://wiki.kvt.nl/books/mimir/page/implementatie).

## auth.php

Geen `auth.php` in deze repository (staat in `.gitignore`). Lokaal/op de server de Mímir-sleutel zetten zoals hierboven, en `$baseUrl`, `$auth_list`, `$environment` en `$auth` laten staan naast `$mimirApi` zodat de directe BC-fallback werkt als Mímir uitvalt (web, `nightly.php` en `download_enroll.php`). Graph-credentials blijven nodig voor gebruikerslijsten. Zie `web/auth.example.php`.
