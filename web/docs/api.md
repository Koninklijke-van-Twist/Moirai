# Moirai device API

HTTP JSON API for ICT device actions. Endpoint: `api.php` (same folder as the webapp).

This is the complete specification. Real API keys stay in local `auth.php` (gitignored). See `auth.example.php`.

Machine-readable spec: `GET api.php?action=help` or `GET api.php?action=spec` (no key required).

The browser UI keeps using session-authenticated `devices_api.php`. `api.php` is the machine/API-key surface with the same data layer (`moirai_data.php`).

**CSRF:** the session-based UI endpoints (`devices_api.php` save/assign/delete/verify_qr, `print_label.php`, `lib/kvt-chat/api.php` add/edit/delete, `budget_api.php` write actions) require the header `X-CSRF-Token` (or field `_csrf`) with the token from `<meta name="moirai-csrf">` in `index.php`; without it they return `403` with `error_code: "csrf"`. `index.php` adds the header automatically to every same-origin fetch/XHR that is not GET/HEAD. **`api.php` with an API key (`X-API-Key` / `Bearer`) does not need a CSRF token**; integrations such as Metis keep working unchanged.

## Authentication

Every action except `help` / `spec` needs a valid service key from local `auth.php`:

```php
$apiKeys = [
    "voorbeeldKey" => "REPLACE_WITH_A_RANDOM_API_KEY",
];
```

Label/name => secret. A valid key is ICT access. Client-supplied `admin` / `is_admin` / `user_is_admin` flags are ignored. Copy `auth.example.php` to `auth.php` and replace the placeholder with a random secret. `REPLACE_WITH_A_RANDOM_API_KEY` is ignored, so copying the example unchanged does not enable the API.

Send the **secret** (not the label) via one of:

- header `X-API-Key: REPLACE_WITH_A_RANDOM_API_KEY`
- header `Authorization: Bearer REPLACE_WITH_A_RANDOM_API_KEY`
- POST JSON/form field `api_key`

Do **not** put keys in the querystring. Any `?api_key=` — including on `help`/`spec`, or together with a header/body key — returns `401` `api_key_query`.

```json
{
  "ok": false,
  "error": "Ongeldige of ontbrekende API-key.",
  "error_code": "unauthorized"
}
```

## Request format

- **GET** — query parameters (never `api_key`). Read actions: `help`, `list`, `get`, `filters`, `lookups`, `users`, `whoami`, `notes_list`, `label_pos` / `print_label`, `budget_get`
- **POST** — JSON (`Content-Type: application/json`) or form-data. Required for all mutations
- Action via `action` (query or body)
- Responses are JSON UTF-8 with `ok` (bool)
- Wrong method on a mutation → `405` `method_not_allowed`

`type` accepts `laptop` / `laptops`, `phone` / `phones`, `accessory` / `accessories`, `simcard` / `simcards` (also `sim`, `simkaart`, `sim-kaart`).

Device id:

- laptop: `serienummer`
- phone: `imei`
- accessory: `accessory_id` (assigned on create)
- simcard: `code`

## UI inventory → API actions

| UI (ICT) | API `action` |
| --- | --- |
| List + search + status chips + attribute filters | `list` |
| Filter dropdown options | `filters` |
| Open device detail | `get` |
| New device in a category | `create` (also `save` without id) |
| Edit fields | `update` / `save` |
| Change fysieke staat | `set_condition` (aliases: `set_physical_state`, `set_fysieke_staat`) |
| Assign to a directory user | `assign` |
| Set Reserve | `unassign` / `set_reserve` (or `assign` with empty assignee) |
| Set Onbeschikbaar | `set_unavailable` |
| Delete device | `delete` |
| QR marked valid after print/scan | `verify_qr` |
| Directory users (assign modal) | `users` |
| Print label / fetch `.pos` + `posprint://` URL | `label_pos` (`get_pos`, `pos`, `print_label`) |
| Notes list / add / edit / delete | `notes_list`, `notes_add`, `notes_edit`, `notes_delete` |
| Verouderd badge | **read-only** (`device.verouderd`). Set by nightly aging, not the UI. |

There is no archive action in the UI.

## Lookups

`GET/POST action=lookups` — types, fields, filters, statuses, condition values, OS/keyboard options.

Statuses: `all`, `assigned`, `reserve`, `unavailable`.

Physical state (`fysieke_staat`): `uitstekend`, `netjes`, `lichte_slijtage`, `beschadigd`.

## List / get

`GET api.php?action=list&type=laptop&q=thinkpad&status=reserve&os=Windows`

Optional attribute filters are the same as the UI (`os`, `os_versie`, `model`, `ram`, `opslag`, `toetsenbord`, `fysieke_staat` for laptops; see `lookups`).

```json
{
  "ok": true,
  "count": 1,
  "devices": [ ]
}
```

Each device includes `status` and `last_note` (or `null`). `verouderd` is included when the nightly job has flagged the device.

`GET api.php?action=get&type=laptop&id=SN-1`

Not found → `404` `device_not_found`.

## Create

Laptop **required** by the API: `model`, `serienummer`. In practice ICT still fills the optional spec fields on create: `ram`, `opslag`, `cpu`, `os`, `os_versie`, `toetsenbord`, `aanschafdatum`, `fysieke_staat`.

**Lenovo model names:** marketing name only, no MTM/CTO suffix. Use `ThinkBook 14 2-in-1 G6 IPL`, not `ThinkBook 14 2-in-1 G6 IPL (22ARCTO1WW)`.

```bash
curl -X POST "https://sleutels.kvt.nl/moirai/api.php?action=create" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: REPLACE_WITH_A_RANDOM_API_KEY" \
  -d '{
    "type": "laptop",
    "model": "ThinkBook 14 2-in-1 G6 IPL",
    "serienummer": "SN-EXAMPLE",
    "ram": "16 GB",
    "opslag": "512 GB",
    "cpu": "Intel Core Ultra 5",
    "aanschafdatum": "2026-09-18",
    "os": "Windows",
    "os_versie": "11",
    "toetsenbord": "QWERTY (US)",
    "fysieke_staat": "uitstekend"
  }'
```

Laptop fields: `model` (required), `serienummer` (required), `ram`, `opslag`, `cpu`, `aanschafdatum` (`YYYY-MM-DD`), `os`, `os_versie`, `toetsenbord`, `fysieke_staat`.

Phone fields: `model`, `imei`, `schermformaat`, `opslag`, `os`, `os_versie`, `aanschafdatum`, `fysieke_staat`. Fill optional phone spec fields on create as well.

Accessory fields: `naam`, `modelnummer`, `beschrijving`, `aanschafdatum`, `fysieke_staat`. Accessory id is generated.

SIM card fields: `code` (required, unique), `telefoonnummer` (required, unique), `fysieke_staat`. `code` is compared case-insensitively without spaces/dashes. `telefoonnummer` is stored as entered (readable) and compared in normalized E.164 form (`telefoonnummer_norm`, e.g. `+31612345678`): spaces, dashes, `+31`, `0031`, `(0)` and a leading `0` are treated alike. A duplicate returns `400` with a Dutch message naming the existing SIM card.

Success → `201` `{ "ok": true, "device": { } }`.

`verouderd`, `qr_geldig`, and admin flags in the body are ignored.

After a key is in local `auth.php`, ICT can smoke-test a real create (e.g. serial `MP2VY6C6`) against production.

## Update

Partial updates are merged with the stored device, then saved with the same validators as the UI.

```bash
curl -X POST "https://sleutels.kvt.nl/moirai/api.php?action=update" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer REPLACE_WITH_A_RANDOM_API_KEY" \
  -d "{\"type\":\"laptop\",\"id\":\"SN-API-1\",\"ram\":\"32 GB\",\"fysieke_staat\":\"netjes\"}"
```

`save` matches the UI: omit `original_key` / `id` to create, or send them to update.

## Physical state

```json
{
  "action": "set_condition",
  "type": "laptop",
  "id": "SN-API-1",
  "fysieke_staat": "beschadigd"
}
```

## Assign / reserve / unavailable

```json
{
  "action": "assign",
  "type": "laptop",
  "id": "SN-API-1",
  "uitgegeven_aan": { "email": "naam@kvt.nl" }
}
```

`uitgegeven_email` is also accepted. Empty / `reserve` / omitted user → Reserve. `__unavailable__` or `unavailable` → Onbeschikbaar.

Directory users are required for a real person (same rule as the UI). Convenience actions: `unassign`, `set_reserve`, `set_unavailable`.

## Delete / QR / print / users / notes

```json
{ "action": "delete", "type": "laptop", "id": "SN-API-1" }
```

```json
{ "action": "verify_qr", "type": "laptop", "id": "SN-API-1" }
```

One action returns **both** the UI PosFile and the ready-to-open print URL (same helpers as `print_label.php`):

- `pos` — JSON from `moirai_build_device_pos_document()`
- `url` — `posprint://print?v=1&d=…&noconfirm=1…` from `moirai_build_device_posprint_url()`

`GET/POST action=label_pos` (aliases `get_pos`, `pos`, `print_label`). Params: `type`, `id`. Not available for `simcard` (a label does not fit on a SIM): returns `400` `print_not_supported`. Optional `download=1` streams the `.pos` file (`Content-Disposition: attachment`, filename `{id}.pos`). The JSON body still includes `url` when not downloading.

```bash
curl "https://sleutels.kvt.nl/moirai/api.php?action=label_pos&type=laptop&id=SN-API-1" \
  -H "X-API-Key: REPLACE_WITH_A_RANDOM_API_KEY"
```

```json
{
  "ok": true,
  "filename": "SN-API-1.pos",
  "pos": {
    "version": 1,
    "metadata": {
      "title": "Moirai: ThinkBook 14 2-in-1 G6 IPL",
      "page_width_mm": 53.0,
      "chars_per_line": 32,
      "codepage": "CP437"
    },
    "body": ":center:\n@image …\n# ThinkBook 14 2-in-1 G6 IPL\n@qr …\n"
  },
  "url": "posprint://print?v=1&d=…&noconfirm=1&referrer=…"
}
```

`users` — Graph directory list (`refresh=1` to bypass cache).

Notes: `notes_list` / `notes_add` / `notes_edit` / `notes_delete` with `type`, `id`, and for add/edit `message` / `message_text`. The API-key label is the note author.

## Telefoonbudget (Metis / Asclepius)

Gebruikt dezelfde API-keys en dezelfde authenticatie als de rest van `api.php`. Bedragen staan in de response altijd twee keer: in **centen** (integer, `*_cents`) en in **euro's als string met 2 decimalen** (`*_eur`, bijv. `"475.00"`). Datums zijn `JJJJ-MM-DD`.

**Bedragen:** `prijs` / `bedrag` is altijd het **volledige aankoopbedrag**. De eigen bijdrage wordt **niet opgeslagen** maar altijd berekend: `max(0, prijs − budget op de aankoopdatum)`. Ook `budget_voor_*`, `budget_na_*` en `totale_eigen_bijdrage_*` zijn berekende waarden; ze veranderen mee als ICT een eerdere aankoop of de instellingen aanpast.

**Personen:** naast de Moirai-gebruikerslijst zoekt de API ook in de budgettabel. Personen die alleen daar staan (bijv. collega's met een @hunter.be-adres of zonder functie, aangemaakt via de Excel-import of "Persoon toevoegen" in de UI) werken gewoon met `budget_get` en `budget_add_purchase`.

Via de API kun je alleen **opvragen** en **een aankoop toevoegen**. Zo'n aankoop krijgt altijd de status **Onbevestigd**. Bevestigen, aanpassen en verwijderen kan alleen een ICT-admin in de UI (tab Telefoonbudget).

### `budget_get` (alias `budget`), GET of POST

| Parameter | Verplicht | |
| --- | --- | --- |
| `email` | ja | Wordt case-insensitive gematcht (`Jan.Jansen@KVT.nl` = `jan.jansen@kvt.nl`) |

```bash
curl -s -H "X-API-Key: $MOIRAI_API_KEY" \
  "https://sleutels.kvt.nl/moirai/api.php?action=budget_get&email=jan.jansen@kvt.nl"
```

```json
{
  "ok": true,
  "email": "jan.jansen@kvt.nl",
  "naam": "Jan Jansen",
  "indiensttreding": "2024-03-01",
  "startbudget_cents": 60000, "startbudget_eur": "600.00",
  "opbouw_per_maand_cents": 2500, "opbouw_per_maand_eur": "25.00",
  "maximum_cents": null,
  "budget_cents": 47500, "budget_eur": "475.00",
  "totaal_besteed_cents": 80000, "totaal_besteed_eur": "800.00",
  "totale_eigen_bijdrage_cents": 17500, "totale_eigen_bijdrage_eur": "175.00",
  "laatste_aankoop": "2025-02-28",
  "telefoon_waarde": { "purchase_id": 2, "waarde_cents": 0, "waarde_eur": "0.00", "maanden": 19 },
  "volgende_opbouw": "2026-10-28",
  "peildatum": "2026-10-08",
  "aankopen": [
    {
      "id": 2, "datum": "2025-02-28",
      "prijs_cents": 35000, "prijs_eur": "350.00",
      "eigen_bijdrage_cents": 17500, "eigen_bijdrage_eur": "175.00",
      "telefoon": "Voorbeeldfoon 15", "notitie": "Asclepius #4711",
      "status": "onbevestigd", "status_label": "Onbevestigd",
      "toestel": { "imei": "350000000000001", "model": "Voorbeeldfoon 15" },
      "client_ref": "asclepius-4711"
    }
  ],
  "rekenregels": ["Startbudget € 600,00 vanaf de indiensttreding.", "…"]
}
```

- `aankopen` staan op datum (oudste eerst). `toestel` is `null` zolang er geen telefoon uit het tabblad Telefoons aan gekoppeld is.
- `volgende_opbouw` is de datum waarop de volgende opbouw van € 25 erbij komt. Die is `null` vóór de eerste aankoop (dan is er geen opbouw) of als het maximum bereikt is.
- `telefoon_waarde` is alleen informatief: de prijs van de laatste aankoop min € 25 per hele maand, minimaal 0. Het telt niet mee in het budget.

### `budget_add_purchase` (alias `budget_purchase_add`), alleen POST

| Veld | Verplicht | |
| --- | --- | --- |
| `email` | ja | Case-insensitive |
| `prijs` (of `price`) | ja | `350`, `"349.95"` of `"349,95"`; wordt omgerekend naar centen |
| `datum` (of `date`) | nee | `JJJJ-MM-DD`, standaard vandaag. Voor een datum in het verleden wordt gerekend met het budget op die datum |
| `telefoon` (of `phone`) | nee | Vrije tekst, max. 200 tekens |
| `notitie` (of `note`) | nee | Vrije tekst, meerdere regels, max. 4000 tekens |
| `client_ref` | nee | Idempotentiesleutel (max. 200 tekens), bijv. `asclepius-<ticketnummer>` |

Een eventueel meegestuurde `status` wordt genegeerd: de aankoop is altijd `onbevestigd`.

```bash
curl -s -X POST "https://sleutels.kvt.nl/moirai/api.php?action=budget_add_purchase" \
  -H "X-API-Key: $MOIRAI_API_KEY" -H "Content-Type: application/json" \
  -d '{"email":"lies.peeters@hunter.be","prijs":"649.00","datum":"2026-10-08","telefoon":"Voorbeeldfoon 16","notitie":"Asclepius #4712","client_ref":"asclepius-4712"}'
```

Een nieuwe aankoop geeft `201`. Herhaal je hetzelfde verzoek met dezelfde `client_ref`, dan krijg je `200` met `"idempotent_replay": true` en de bestaande aankoop terug, zonder dubbele aankoop.

```json
{
  "ok": true,
  "idempotent_replay": false,
  "aankoop": {
    "id": 3, "datum": "2026-10-08", "prijs_cents": 64900, "prijs_eur": "649.00",
    "eigen_bijdrage_cents": 4900, "eigen_bijdrage_eur": "49.00",
    "telefoon": "Voorbeeldfoon 16", "notitie": "Asclepius #4712",
    "status": "onbevestigd", "status_label": "Onbevestigd", "toestel": null,
    "client_ref": "asclepius-4712",
    "budget_voor_cents": 60000, "budget_voor_eur": "600.00",
    "budget_na_cents": 0, "budget_na_eur": "0.00"
  },
  "eigen_bijdrage_cents": 4900, "eigen_bijdrage_eur": "49.00",
  "budget": { "…": "zelfde velden als budget_get, na deze aankoop" }
}
```

### Foutcodes telefoonbudget

| HTTP | `error_code` | Betekenis |
| --- | --- | --- |
| `400` | `invalid_email` | E-mail ontbreekt of is ongeldig |
| `400` | `invalid_amount` / `invalid_date` | Prijs of datum ongeldig |
| `401` | `api_key_missing` / `unauthorized` / `api_key_query` | Authenticatie (zie boven) |
| `404` | `person_not_found` | E-mail onbekend in de gebruikerslijst én in de budgettabel |
| `404` | `no_budget` | Persoon is bekend, maar er is nog geen indiensttreding geregistreerd. ICT moet die eerst in de UI invullen |
| `405` | `method_not_allowed` | `budget_add_purchase` via GET |
| `409` | `client_ref_conflict` | De `client_ref` is al gebruikt voor een andere persoon |
| `503` | `users_unavailable` | Gebruikerslijst (Graph) tijdelijk onbereikbaar, en de persoon heeft nog geen budget |


## Errors

| HTTP | `error_code` |
| --- | --- |
| `401` | `unauthorized`, `api_key_missing`, `api_key_query` |
| `405` | `method_not_allowed` |
| `400` | `unknown_action`, `invalid_input`, `invalid_email`, `invalid_amount`, `invalid_date`, plus validation messages from `moirai_data` (model/serial/IMEI/date/condition/…) |
| `403` | `forbidden` |
| `404` | `device_not_found`, `note_not_found`, `person_not_found`, `no_budget` |
| `409` | `client_ref_conflict` |
| `503` | `users_unavailable` |
| `500` | `generic` |
