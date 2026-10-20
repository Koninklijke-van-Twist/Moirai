# Moirai

ICT-apparaatbeheer op sleutels.kvt.nl: laptops, telefoons, accessoires en SIM-kaarten bijhouden, toewijzen aan gebruikers en delen voor een actueel overzicht van ICT-voorraad.

## Structuur

- `web/index.php` — hoofdpagina (apparatenlijst, toewijzing, labels)
- `web/moirai_data.php` — SQLite-data-laag (apparaten, accessoires, SIM-kaarten, filters)
  - SIM-kaarten (`simcards`): code en telefoonnummer uniek (genormaliseerd, unieke DB-indexen); geen labels. Tabel en indexen worden idempotent aangemaakt bij de eerste DB-connectie.
- `web/odata.php` — OData-client, lokale filecache-widget, optionele Mímir-proxy
- `web/auth_helper.php` — company-discovery / environment-helpers (BC of Mímir)
- `web/moirai_budget.php` — telefoonbudget (rekenregels, aankopen, import); UI in `web/telefoonbudget_ui.php` + `web/js/telefoonbudget.js`
- `web/localization.php` — meertalige UI-teksten
- `web/nightly.php` — nightly aging-alerts (SQLite/mail; geen OData-fetch)
- `web/auth.php` — credentials (niet in git, lokaal/server aanwezig)

## Telefoonbudget (alleen ICT-admins)

Tabblad **Telefoonbudget** (`web/telefoonbudget_ui.php`, `web/js/telefoonbudget.js`, API `web/budget_api.php`, logica `web/moirai_budget.php`). Alle bedragen worden in **centen** (integers) opgeslagen en berekend.

- Tabellen `budget_settings`, `budget_people` (e-mail = sleutel, indiensttreding) en `budget_purchases` (AUTOINCREMENT-id, volledig aankoopbedrag, datum, status, telefoon, notitie, gekoppelde telefoon-IMEI, import-hash, client_ref). De eigen bijdrage en budget voor/na worden **niet opgeslagen** maar altijd uit de tijdlijn berekend. Idempotente migratie `moirai_migrate_budget_tables()` bij de eerste DB-connectie (verwijdert ook de oude kolommen `eigen_bijdrage_cents`/`budget_voor_cents`/`budget_na_cents` als die bestaan).
- Rechten: elke actie vereist `moirai_is_admin()`; schrijfacties vereisen POST + `X-CSRF-Token`.
- **Personen (handmatige lijst, sinds 9 okt 2026):** de lijst is de tabel `budget_list` en wordt **alleen handmatig** bijgehouden. Graph/Entra vult de lijst niet meer (tot en met #13 toonde de lijst bij elke paginaload alle actieve Graph-gebruikers plus `budget_people`; er was geen nightly/hourly-import).
  - Eenmalige seed bij de migratie (vlag `budget_meta.manual_list_seeded_at`, aantal in `manual_list_seed_count`): iedereen met een indiensttreding (`budget_people`) of minstens één aankoop. Graph-gebruikers zonder enige budgetdata komen er niet in. De seed draait nooit opnieuw.
  - *Persoon toevoegen*: e-mail + naam verplicht, indiensttreding optioneel. Zoekveld *Zoeken in Microsoft 365 (hulp)* (actie `directory_search`) vult alleen e-mail/naam in; opslaan blijft handwerk. Ook collega's buiten de gebruikerslijst (bijv. @hunter.be).
  - *Verwijderen uit lijst* (persoonsmodal, actie `remove_person`, POST + CSRF): alleen zichtbaar én server-side toegestaan bij nul aankopen (ook geen onbevestigde). Verwijdert ook de indiensttreding; de persoon komt niet vanzelf terug.
  - Import: een naam/e-mail op de lijst kan "zeker" matchen; Graph-gebruikers en onbekende e-mailadressen zijn hooguit een kandidaat en worden altijd gevraagd. Pas na die keuze + indiensttreding komt iemand op de lijst.
  - `employeeHireDate` uit Graph blijft alleen een voorstel voor de indiensttreding.
  - api.php `budget_get`/`budget_add_purchase` werken alleen voor personen op de lijst; anders `person_not_found`.

**Rekenregels** (instelbaar via de knop *Instellingen* of constanten in `moirai_budget.php`):

| Regel | Standaard | Om te zetten |
| --- | --- | --- |
| Startbudget vanaf indiensttreding | € 600 | instelling `start_cents` |
| Opbouw per hele maand na de laatste aankoop | € 25 | instelling `monthly_cents` |
| Hele maand | telt zodra de dag-van-de-maand van de aankoop is bereikt (31 jan → 28/29 feb) | `moirai_budget_months_between()` |
| Opbouw vóór de eerste aankoop | nee (budget blijft € 600) | `MOIRAI_BUDGET_ACCRUE_BEFORE_FIRST_PURCHASE` |
| Maximum budget | geen (0) | instelling `max_cents` |
| Onbevestigde aankopen tellen mee | **nee** (sinds 9 okt 2026): geen effect op budget, opbouw of telefoonwaarde. Wel zichtbaar en gemarkeerd, met de eigen bijdrage "bij bevestigen" | `MOIRAI_BUDGET_COUNT_UNCONFIRMED` |
| Huidige waarde telefoon (alleen informatief) | prijs laatste aankoop − € 25 per hele maand, min. 0 | instelling `depreciation_cents` |

Een aankoop trekt het volledige bedrag af; het budget komt niet onder 0 en de eigen bijdrage is `max(0, prijs − budget op de aankoopdatum)` (berekend, niet opgeslagen). Elke wijziging (toevoegen, aanpassen, (on)bevestigen, verwijderen, indiensttreding of instellingen wijzigen) herberekent de hele tijdlijn.

**Excel-import** (knop *Excel importeren*): `.xls` (BIFF8) en `.xlsx`, gelezen door `web/lib/moirai_spreadsheet.php` (geen externe library). Verwachte kolommen: `datum`, `persoon`, `soort telefoon` (→ Telefoon), `bedrag`, optioneel `status` en een naamloze/`notitie`-kolom (→ Notitie). Preview toont nieuwe/al geïmporteerde/foute regels en per persoon de koppeling (zeker / controleer / niet herkend). Matching op naam: spaties en hoofdletters genormaliseerd, accenten weg, tussenvoegsels (van, de, der, den, ter, …) genegeerd en voor-/achternaamvolgorde ("Berg, Jan van den") maakt niet uit; alleen een unieke treffer of een e-mailadres is "zeker". Voor elke onzekere of onbekende persoon volgt één voor één een modal (suggesties + e-mailadres invullen, of overslaan); een e-mailadres buiten de gebruikerslijst maakt een nieuwe persoon aan met de naam uit de Excel. Daarna krijgen personen zonder indiensttreding één voor één een modal; pas dan wordt opgeslagen. Idempotent via `import_hash` (inhoud + volgnummer van identieke regels). Geïmporteerde aankopen zijn **Bevestigd**, tenzij een `status`-kolom "onbevestigd" zegt. Het echte Excel-bestand hoort niet in git; tests gebruiken `tests/fixtures/telefoonbudget_fictief.xls` (verzonnen).

**Indiensttreding uit Microsoft 365 (`employeeHireDate`):** Moirai haalt `employeeHireDate` op met een eigen Graph-request (`/users?$select=id,mail,employeeHireDate&$filter=accountEnabled eq true`, app-only met dezelfde `$graphCredentials` uit `auth.php`; de gewone gebruikerslijst in `getusers_fetch.php` blijft ongewijzigd). De datum (omgezet naar Europe/Amsterdam) wordt alleen als **voorstel** gebruikt: vooringevuld in de indiensttreding-modals van de import, in *Persoon toevoegen* en bij het wijzigen van de indiensttreding in het persoonsvenster. Het wordt **nooit automatisch opgeslagen**; pas op opslaan wordt de (eventueel aangepaste) datum bewaard. Bestaat er al een andere opgeslagen datum, dan toont het venster alleen een hint. Cache: `<database>.hire_dates.json` (1 dag; na een Graph-fout 10 minuten). Gaat het ophalen mis (fout, veld ontbreekt, timeout, te weinig rechten) of is het veld leeg, dan is er stil geen voorstel: geen melding in de UI, alleen een regel in de PHP-errorlog (`employeeHireDate niet op te halen: …`). De admin-diagnose hieronder is optioneel.
- Rechten: `employeeHireDate` lezen kan met de applicatierechten **User.Read.All** (die de app voor de gebruikerslijst al nodig heeft). `employeeLeaveDateTime` vereist daarnaast **User-LifeCycleInfo.Read.All** (admin consent) en wordt alleen in de diagnose geprobeerd.
- **Controleren na deploy:** Telefoonbudget → *Instellingen* → *Controleer employeeHireDate*. Toont alleen aantallen: hoeveel gebruikers Moirai toont (actief + functie), hoeveel `employeeHireDate` gevuld hebben, het bereik van de jaartallen, of `employeeLeaveDateTime` leesbaar is, en de app-rechten (roles) uit het token. Geen namen of datums per persoon. Hetzelfde via de machine-API: `GET api.php?action=budget_hire_stats` met API-key.

**Machine-API** (`api.php`, bestaande API-keys): `budget_get` (opvragen per e-mail, case-insensitive) en `budget_add_purchase` (altijd Onbevestigd, idempotent met `client_ref`). Zie `web/docs/api.md`.

Tests: `php tests/telefoonbudget_test.php` en `php tests/telefoonbudget_api_test.php`.

**CSRF (hele app):** alle sessie-gebaseerde schrijfacties (`devices_api.php` save/assign/delete/verify_qr, `print_label.php`, notities via `lib/kvt-chat/api.php`, `budget_api.php`) vereisen het sessietoken (`web/moirai_csrf.php`). `index.php` zet het in `<meta name="moirai-csrf">` en voegt `X-CSRF-Token` automatisch toe aan elke same-origin fetch/XHR die geen GET/HEAD is. `api.php` met API-key heeft geen token nodig. Rooktest tegen een echte `php -S`-server: `php tests/csrf_smoke_test.php`.

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
