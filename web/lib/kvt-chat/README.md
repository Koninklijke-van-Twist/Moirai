# kvt-chat

Self-contained chat modal with Asclepius-style bubbles, pixel avatars, and SQLite storage.

Copy this whole folder (`web/lib/kvt-chat/`) into another project under `web/`. Nothing outside `web/` is required.

## 3-step host setup

### 1. Boot file (`web/kvt_chat_boot.php`)

```php
<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';
require_once __DIR__ . '/lib/kvt-chat/KvtChat.php';

KvtChat::configure([
    'db_path'    => __DIR__ . '/data/app.sqlite',
    'avatar_dir' => __DIR__ . '/data/user_avatars',
    'avatar_url' => 'lib/kvt-chat/avatar.php',
    'api_url'    => 'lib/kvt-chat/api.php',
    'viewer'     => static fn(): array => [
        'email' => strtolower((string) ($_SESSION['user']['email'] ?? '')),
        'name'  => (string) ($_SESSION['user']['name'] ?? ''),
    ],
    'is_admin'   => static fn(): bool => !empty($_SESSION['user']['admin']),
    'can_edit'   => 'own',   // yes | no | own
    'can_delete' => 'own',
    'admin_bypass' => true,
]);
```

Leave `api_url` / `avatar_url` empty (or omit them) to auto-build root-absolute paths like `/moirai/lib/kvt-chat/api.php`. That avoids broken relative URLs when the page is `/moirai` without a trailing slash.

`api.php` and `avatar.php` load this boot file automatically.

### 2. Render once on the page

```php
<?php
require_once __DIR__ . '/kvt_chat_boot.php';
// … your HTML …
KvtChat::render();
```

### 3. Open a thread

```js
KvtChat.open({
  threadKey: 'ticket:42',
  title: 'Notes'
});
```

## Config reference

| Key | Type | Default | Meaning |
|-----|------|---------|---------|
| `db_path` | string | — | SQLite file path (or pass `pdo`) |
| `pdo` | PDO | null | Reuse an existing connection |
| `avatar_dir` | string | `web/data/user_avatars` | PNG cache directory |
| `avatar_url` | string | auto `/…/lib/kvt-chat/avatar.php` | Avatar endpoint (root-absolute) |
| `api_url` | string | auto `/…/lib/kvt-chat/api.php` | Chat API (root-absolute) |
| `base_path` | string | auto from `SCRIPT_NAME` | App base, e.g. `/moirai` |
| `viewer` | callable/array | — | `{ email, name }` |
| `require_auth` | callable | no-op | Runs before API/avatar |
| `is_admin` | callable | false | Admin flag for `own` + bypass |
| `can_edit` | string | `own` | `yes` / `no` / `own` |
| `can_delete` | string | `own` | `yes` / `no` / `own` |
| `admin_bypass` | bool | true | Admins may edit/delete any message when mode is `own` |
| `dialog.width` | string | `min(720px, 96vw)` | CSS width |
| `dialog.maxHeight` | string | `min(88vh, 820px)` | CSS max-height |
| `dialog.minHeight` | string | `min(70vh, 560px)` | CSS min-height |
| `dialog.zIndex` | int | 1100 | Overlay z-index |
| `i18n` | object | English defaults | UI strings |
| `poll_ms` | int | 12000 | Refresh interval |
| `max_length` | int | 4000 | Max message length |
| `date_locale` | string | `nl-NL` | Datetime labels |
| `migrate_device_notes` | bool | false | One-shot import from legacy `device_notes` |

### Permissions

- **yes** — any logged-in viewer (non-empty email) may edit/delete all messages  
- **no** — never show/allow edit or delete  
- **own** — only the author; plus admin if `admin_bypass` is true  

Server enforces this; the client only hides buttons based on `can_edit` / `can_delete` flags on each message.

### Per-open overrides (JS)

```js
KvtChat.open({
  threadKey: 'device:laptops:SN1',
  title: 'Notities',
  dialog: { width: 'min(900px, 96vw)', zIndex: 2000 },
  i18n: { empty: 'Nog geen berichten.' }
});
```

## Render one message anywhere

```js
KvtChat.renderMessage(document.querySelector('#preview'), message, {
  actions: false,   // default for embeds: no edit/delete buttons
  replace: true,    // default: replace container contents
  className: 'kvt-chat-embed'
});

// Or get HTML string:
var html = KvtChat.messageHtml(message, { actions: false });
```

## API contract (`lib/kvt-chat/api.php?action=…`)

All responses: `{ "ok": true, … }` or `{ "ok": false, "error": "…" }`.

| Action | Method | Body / query | Result |
|--------|--------|--------------|--------|
| `list` | GET | `thread_key` | `{ messages: [...] }` |
| `last` | GET | `thread_keys=a,b,c` | `{ messages: { "a": msg, … } }` |
| `add` | POST JSON | `{ thread_key, message_text }` | `{ message }` |
| `edit` | POST JSON | `{ message_id, message_text }` | `{ message }` |
| `delete` | POST JSON | `{ message_id }` | `{ ok: true }` |

Message shape includes `id`, `user_email`, `user_label`, `message_text`, `created_at_label`, `updated_at`, `can_edit`, `can_delete`, `colors`.

## PHP helpers

```php
KvtChat::deleteThread('device:laptops:SN1'); // cascade when host deletes entity
KvtChat::store(); // underlying KvtChatStore
```

## Storage

Table `kvt_chat_messages`: `id`, `thread_key`, `author_email`, `author_naam`, `body`, `created_at`, `updated_at`.

`thread_key` is opaque; the host chooses the scheme (e.g. `device:laptops:IMEI`).

## Folder layout

```
kvt-chat/
  README.md
  KvtChat.php
  store.php
  avatars.php
  api.php
  avatar.php
  assets/kvt-chat.css
  assets/kvt-chat.js
  templates/modal.php
```
