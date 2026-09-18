# Moirai device API

HTTP JSON API for ICT device actions. Endpoint: `api.php` (same folder as the webapp).

This is the complete specification. Real API keys stay in local `auth.php` (gitignored). See `auth.example.php`.

Machine-readable spec: `GET api.php?action=help` or `GET api.php?action=spec` (no key required).

The browser UI keeps using session-authenticated `devices_api.php`. `api.php` is the machine/API-key surface with the same data layer (`moirai_data.php`).

## Authentication

Every action except `help` / `spec` needs a valid service key from local `auth.php`:

```php
$apiKeys = [
    "voorbeeldKey" => "1234-5678-1234",
];
```

Label/name => secret. A valid key is ICT access. Client-supplied `admin` / `is_admin` / `user_is_admin` flags are ignored.

Send the **secret** (not the label) via one of:

- header `X-API-Key: 1234-5678-1234`
- header `Authorization: Bearer 1234-5678-1234`
- POST JSON/form field `api_key`

Do **not** put keys in the querystring. `?api_key=` is ignored and, when it is the only key, returns `401` `api_key_query`.

```json
{
  "ok": false,
  "error": "Ongeldige of ontbrekende API-key.",
  "error_code": "unauthorized"
}
```

## Request format

- **GET** — query parameters (never `api_key`). Read actions: `help`, `list`, `get`, `filters`, `lookups`, `users`, `whoami`, `notes_list`, `label_pos`
- **POST** — JSON (`Content-Type: application/json`) or form-data. Required for all mutations
- Action via `action` (query or body)
- Responses are JSON UTF-8 with `ok` (bool)
- Wrong method on a mutation → `405` `method_not_allowed`

`type` accepts `laptop` / `laptops`, `phone` / `phones`, `accessory` / `accessories`.

Device id:

- laptop: `serienummer`
- phone: `imei`
- accessory: `accessory_id` (assigned on create)

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
| Print label (`posprint://` URL) | `print_label` |
| Fetch `.pos` label document | `label_pos` (`get_pos`, `pos`) |
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
  -H "X-API-Key: 1234-5678-1234" \
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

Success → `201` `{ "ok": true, "device": { } }`.

`verouderd`, `qr_geldig`, and admin flags in the body are ignored.

After a key is in local `auth.php`, ICT can smoke-test a real create (e.g. serial `MP2VY6C6`) against production.

## Update

Partial updates are merged with the stored device, then saved with the same validators as the UI.

```bash
curl -X POST "https://sleutels.kvt.nl/moirai/api.php?action=update" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer 1234-5678-1234" \
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

```json
{ "action": "print_label", "type": "laptop", "id": "SN-API-1" }
```

Print returns `{ "ok": true, "url": "posprint://..." }` like the UI.

### `.pos` label data

The UI print button builds a PosFile via `moirai_build_device_pos_document()` (version, metadata, body) and wraps it in a `posprint://` URL. `label_pos` returns that same document so clients can store or print a `.pos` file.

`GET/POST action=label_pos` (aliases `get_pos`, `pos`). Params: `type`, `id`. Optional `download=1` streams the file (`Content-Disposition: attachment`, filename `{id}.pos`).

```bash
curl "https://sleutels.kvt.nl/moirai/api.php?action=label_pos&type=laptop&id=SN-API-1" \
  -H "X-API-Key: 1234-5678-1234"
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
  }
}
```

`users` — Graph directory list (`refresh=1` to bypass cache).

Notes: `notes_list` / `notes_add` / `notes_edit` / `notes_delete` with `type`, `id`, and for add/edit `message` / `message_text`. The API-key label is the note author.

## Errors

| HTTP | `error_code` |
| --- | --- |
| `401` | `unauthorized`, `api_key_missing`, `api_key_query` |
| `405` | `method_not_allowed` |
| `400` | `unknown_action`, `invalid_input`, plus validation messages from `moirai_data` (model/serial/IMEI/date/condition/…) |
| `403` | `forbidden` |
| `404` | `device_not_found`, `note_not_found` |
| `500` | `generic` |
