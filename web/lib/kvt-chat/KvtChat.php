<?php

declare(strict_types=1);

require_once __DIR__ . '/store.php';
require_once __DIR__ . '/avatars.php';

/**
 * Self-contained chat modal + SQLite message store.
 *
 * Host: KvtChat::configure([...]); KvtChat::render(); then KvtChat.open({threadKey}) in JS.
 */
final class KvtChat
{
    private static bool $configured = false;

    /** @var array<string, mixed> */
    private static array $config = [];

    private static ?KvtChatStore $store = null;

    /**
     * @param array<string, mixed> $config
     */
    public static function configure(array $config): void
    {
        $defaults = [
            'db_path' => '',
            'pdo' => null,
            'avatar_dir' => '',
            // Empty = auto absolute path from app root (avoids /moirai vs /moirai/ relative bugs).
            'avatar_url' => '',
            'api_url' => '',
            'base_path' => '',
            'viewer' => static fn(): array => ['email' => '', 'name' => ''],
            'require_auth' => static function (): void {},
            'is_admin' => static fn(): bool => false,
            'can_edit' => 'own',
            'can_delete' => 'own',
            'admin_bypass' => true,
            'dialog' => [
                'width' => 'min(720px, 96vw)',
                'maxHeight' => 'min(88vh, 820px)',
                'minHeight' => 'min(70vh, 560px)',
                'zIndex' => 1100,
            ],
            'i18n' => self::defaultI18n(),
            'poll_ms' => 12000,
            'max_length' => 4000,
            'date_locale' => 'nl-NL',
            'migrate_device_notes' => false,
        ];

        self::$config = array_replace_recursive($defaults, $config);
        self::$config['can_edit'] = self::normalizePermission((string) self::$config['can_edit']);
        self::$config['can_delete'] = self::normalizePermission((string) self::$config['can_delete']);

        if (trim((string) (self::$config['api_url'] ?? '')) === '') {
            self::$config['api_url'] = self::webUrl('lib/kvt-chat/api.php');
        } elseif (!self::isAbsoluteUrl((string) self::$config['api_url'])) {
            self::$config['api_url'] = self::webUrl((string) self::$config['api_url']);
        }

        if (trim((string) (self::$config['avatar_url'] ?? '')) === '') {
            self::$config['avatar_url'] = self::webUrl('lib/kvt-chat/avatar.php');
        } elseif (!self::isAbsoluteUrl((string) self::$config['avatar_url'])) {
            self::$config['avatar_url'] = self::webUrl((string) self::$config['avatar_url']);
        }

        self::$configured = true;
        self::$store = null;
    }

    /**
     * Application URL base path without trailing slash, e.g. "/moirai" or "/Moirai/web".
     * Root-absolute paths avoid broken relative resolution when the page URL has no trailing slash.
     */
    public static function webBasePath(): string
    {
        $configured = trim(str_replace('\\', '/', (string) (self::$config['base_path'] ?? '')), '/');
        if ($configured !== '') {
            return '/' . $configured;
        }

        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($script === '') {
            return '';
        }

        if (preg_match('#^(.*?)/lib/kvt-chat(?:/|$)#', $script, $matches) === 1) {
            return rtrim($matches[1], '/');
        }

        $dir = str_replace('\\', '/', dirname($script));
        if ($dir === '/' || $dir === '.' || $dir === '\\') {
            return '';
        }

        return rtrim($dir, '/');
    }

    /**
     * Root-absolute URL path under the app, e.g. "/moirai/lib/kvt-chat/api.php".
     */
    public static function webUrl(string $relative): string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        $base = self::webBasePath();

        return ($base === '' ? '' : $base) . '/' . $relative;
    }

    private static function isAbsoluteUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        if (isset($url[0]) && $url[0] === '/') {
            return true;
        }

        return (bool) preg_match('#^[a-z][a-z0-9+.-]*:#i', $url);
    }

    public static function isConfigured(): bool
    {
        return self::$configured;
    }

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        self::assertConfigured();

        return self::$config;
    }

    public static function avatarDir(): string
    {
        self::assertConfigured();
        $dir = trim((string) (self::$config['avatar_dir'] ?? ''));
        if ($dir === '') {
            $dir = __DIR__ . '/../../data/user_avatars';
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        return $dir;
    }

    public static function avatarUrl(string $email): string
    {
        self::assertConfigured();
        $base = rtrim((string) self::$config['avatar_url'], '?&');
        $sep = strpos($base, '?') !== false ? '&' : '?';

        return $base . $sep . 'email=' . rawurlencode(strtolower(trim($email)));
    }

    public static function store(): KvtChatStore
    {
        self::assertConfigured();
        if (self::$store instanceof KvtChatStore) {
            return self::$store;
        }

        if (self::$config['pdo'] instanceof PDO) {
            self::$store = new KvtChatStore(self::$config['pdo']);
        } else {
            $path = trim((string) self::$config['db_path']);
            if ($path === '') {
                throw new RuntimeException('KvtChat: configure db_path or pdo.');
            }
            self::$store = KvtChatStore::fromPath($path);
        }

        if (!empty(self::$config['migrate_device_notes'])) {
            self::$store->migrateFromDeviceNotes();
        }

        return self::$store;
    }

    public static function deleteThread(string $threadKey): void
    {
        $threadKey = trim($threadKey);
        if ($threadKey === '') {
            return;
        }
        self::store()->deleteThread($threadKey);
    }

    public static function render(): void
    {
        self::assertConfigured();
        $i18n = self::$config['i18n'];
        $dialog = self::$config['dialog'];
        $boot = [
            'apiUrl' => (string) self::$config['api_url'],
            'avatarUrl' => (string) self::$config['avatar_url'],
            'pollMs' => (int) self::$config['poll_ms'],
            'maxLength' => (int) self::$config['max_length'],
            'dialog' => $dialog,
            'i18n' => $i18n,
        ];

        $cssHref = self::assetUrl('assets/kvt-chat.css');
        $jsHref = self::assetUrl('assets/kvt-chat.js');

        echo '<link rel="stylesheet" href="' . htmlspecialchars($cssHref, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        require __DIR__ . '/templates/modal.php';
        echo '<script>window.KvtChatBoot = ' . json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>' . "\n";
        echo '<script src="' . htmlspecialchars($jsHref, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
    }

    public static function handleApi(): void
    {
        self::assertConfigured();
        self::runAuth();

        header('Content-Type: application/json; charset=utf-8');

        $action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));
        $payload = [];
        if (in_array($action, ['add', 'edit', 'delete'], true)) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode((string) $raw, true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        try {
            switch ($action) {
                case 'list':
                    $threadKey = trim((string) ($_GET['thread_key'] ?? ''));
                    self::jsonOk(['messages' => self::listMapped($threadKey)]);
                    break;

                case 'last':
                    $rawKeys = $_GET['thread_keys'] ?? ($payload['thread_keys'] ?? []);
                    if (is_string($rawKeys)) {
                        $rawKeys = array_filter(array_map('trim', explode(',', $rawKeys)));
                    }
                    if (!is_array($rawKeys)) {
                        $rawKeys = [];
                    }
                    self::jsonOk(['messages' => self::lastMapped(array_values($rawKeys))]);
                    break;

                case 'add':
                    $threadKey = trim((string) ($payload['thread_key'] ?? ''));
                    $text = trim((string) ($payload['message_text'] ?? ''));
                    $message = self::addMapped($threadKey, $text);
                    self::jsonOk(['message' => $message]);
                    break;

                case 'edit':
                    $id = (int) ($payload['message_id'] ?? 0);
                    $text = trim((string) ($payload['message_text'] ?? ''));
                    $message = self::editMapped($id, $text);
                    self::jsonOk(['message' => $message]);
                    break;

                case 'delete':
                    $id = (int) ($payload['message_id'] ?? 0);
                    self::deleteMapped($id);
                    self::jsonOk([]);
                    break;

                default:
                    self::jsonError('Unknown action.', 400);
            }
        } catch (InvalidArgumentException $error) {
            self::jsonError($error->getMessage(), 400);
        } catch (Throwable $error) {
            self::jsonError('Request failed.', 500);
        }
    }

    public static function handleAvatar(): void
    {
        self::assertConfigured();
        self::runAuth();

        $email = strtolower(trim((string) ($_GET['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            exit;
        }

        if (!kvt_chat_ensure_user_avatar($email)) {
            http_response_code(500);
            exit;
        }

        $path = kvt_chat_user_avatar_path($email);
        if (!is_file($path)) {
            http_response_code(404);
            exit;
        }

        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=31536000, immutable');
        readfile($path);
        exit;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listMapped(string $threadKey): array
    {
        $threadKey = trim($threadKey);
        if ($threadKey === '') {
            throw new InvalidArgumentException(self::i18n('error.thread_required'));
        }

        $viewer = self::viewer();
        $rows = self::store()->listMessages($threadKey);
        $out = [];
        foreach ($rows as $row) {
            $out[] = self::mapMessage($row, $viewer);
        }

        return $out;
    }

    /**
     * @param list<string> $threadKeys
     * @return array<string, array<string, mixed>>
     */
    public static function lastMapped(array $threadKeys, bool $withActions = false): array
    {
        $viewer = self::viewer();
        $rows = self::store()->lastMessages($threadKeys);
        $out = [];
        foreach ($rows as $threadKey => $row) {
            $message = self::mapMessage($row, $viewer);
            if (!$withActions) {
                $message['can_edit'] = false;
                $message['can_delete'] = false;
            }
            $out[$threadKey] = $message;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function addMapped(string $threadKey, string $body): array
    {
        $threadKey = trim($threadKey);
        $body = trim($body);
        $viewer = self::viewer();
        $email = $viewer['email'];

        if ($threadKey === '') {
            throw new InvalidArgumentException(self::i18n('error.thread_required'));
        }
        if ($email === '') {
            throw new InvalidArgumentException(self::i18n('error.forbidden'));
        }
        self::assertBody($body);

        $row = self::store()->addMessage($threadKey, $email, $viewer['name'], $body);

        return self::mapMessage($row, $viewer);
    }

    /**
     * @return array<string, mixed>
     */
    public static function editMapped(int $id, string $body): array
    {
        $body = trim($body);
        self::assertBody($body);

        $row = self::store()->getMessage($id);
        if ($row === null) {
            throw new InvalidArgumentException(self::i18n('error.not_found'));
        }

        $viewer = self::viewer();
        if (!self::mayManage($row, $viewer, 'can_edit')) {
            throw new InvalidArgumentException(self::i18n('error.forbidden'));
        }

        $updated = self::store()->updateMessage($id, $body);

        return self::mapMessage($updated, $viewer);
    }

    public static function deleteMapped(int $id): void
    {
        $row = self::store()->getMessage($id);
        if ($row === null) {
            throw new InvalidArgumentException(self::i18n('error.not_found'));
        }

        $viewer = self::viewer();
        if (!self::mayManage($row, $viewer, 'can_delete')) {
            throw new InvalidArgumentException(self::i18n('error.forbidden'));
        }

        self::store()->deleteMessage($id);
    }

    /**
     * @param array<string, mixed> $row
     * @param array{email: string, name: string, is_admin: bool} $viewer
     * @return array<string, mixed>
     */
    public static function mapMessage(array $row, array $viewer): array
    {
        $email = strtolower(trim((string) ($row['author_email'] ?? '')));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'thread_key' => (string) ($row['thread_key'] ?? ''),
            'user_email' => $email,
            'user_label' => trim((string) ($row['author_naam'] ?? '')),
            'message_text' => (string) ($row['body'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'created_at_label' => self::formatDatetime((string) ($row['created_at'] ?? '')),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'updated_at_label' => self::formatDatetime((string) ($row['updated_at'] ?? '')),
            'can_edit' => self::mayManage($row, $viewer, 'can_edit'),
            'can_delete' => self::mayManage($row, $viewer, 'can_delete'),
            'colors' => kvt_chat_colors_for_email($email),
        ];
    }

    /**
     * @return array{email: string, name: string, is_admin: bool}
     */
    public static function viewer(): array
    {
        self::assertConfigured();
        $raw = self::$config['viewer'];
        $data = is_callable($raw) ? $raw() : (is_array($raw) ? $raw : []);
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $name = $email;
        }
        $isAdmin = false;
        if (is_callable(self::$config['is_admin'])) {
            $isAdmin = (bool) (self::$config['is_admin'])();
        }

        return [
            'email' => $email,
            'name' => $name,
            'is_admin' => $isAdmin,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function defaultI18n(): array
    {
        return [
            'title' => 'Chat',
            'messages' => 'Messages',
            'empty' => 'No messages yet. Write the first one below.',
            'message_label' => 'Message',
            'edit_label' => 'Edit message',
            'close' => 'Close',
            'edit' => 'Edit',
            'delete' => 'Delete',
            'cancel' => 'Cancel',
            'cancel_edit' => 'Cancel editing',
            'edited_suffix' => ' (edited)',
            'delete_confirm_title' => 'Delete message',
            'delete_confirm_body' => 'This message will be permanently deleted.',
            'delete_confirm_yes' => 'Yes, delete',
            'load_failed' => 'Messages could not be loaded.',
            'send_failed' => 'Message could not be sent.',
            'save_failed' => 'Change could not be saved.',
            'delete_failed' => 'Message could not be deleted.',
            'unknown_user' => 'Unknown',
            'error.thread_required' => 'Thread key is required.',
            'error.forbidden' => 'Forbidden.',
            'error.empty' => 'Message cannot be empty.',
            'error.too_long' => 'Message is too long.',
            'error.not_found' => 'Message not found.',
        ];
    }

    private static function assertConfigured(): void
    {
        if (!self::$configured) {
            throw new RuntimeException('KvtChat::configure() must be called first.');
        }
    }

    private static function runAuth(): void
    {
        $hook = self::$config['require_auth'] ?? null;
        if (is_callable($hook)) {
            $hook();
        }
    }

    private static function normalizePermission(string $value): string
    {
        $value = strtolower(trim($value));
        if (in_array($value, ['yes', 'true', '1', 'all'], true)) {
            return 'yes';
        }
        if (in_array($value, ['no', 'false', '0', 'none'], true)) {
            return 'no';
        }

        return 'own';
    }

    /**
     * @param array<string, mixed> $row
     * @param array{email: string, name: string, is_admin: bool} $viewer
     */
    private static function mayManage(array $row, array $viewer, string $configKey): bool
    {
        $mode = self::normalizePermission((string) (self::$config[$configKey] ?? 'own'));
        if ($mode === 'no') {
            return false;
        }
        if ($mode === 'yes') {
            return $viewer['email'] !== '';
        }

        $author = strtolower(trim((string) ($row['author_email'] ?? '')));
        if ($viewer['email'] !== '' && $viewer['email'] === $author) {
            return true;
        }

        return !empty(self::$config['admin_bypass']) && !empty($viewer['is_admin']);
    }

    private static function assertBody(string $body): void
    {
        if ($body === '') {
            throw new InvalidArgumentException(self::i18n('error.empty'));
        }
        $max = (int) self::$config['max_length'];
        if ($max > 0 && mb_strlen($body) > $max) {
            throw new InvalidArgumentException(self::i18n('error.too_long'));
        }
    }

    private static function i18n(string $key): string
    {
        $map = self::$config['i18n'] ?? [];
        if (is_array($map) && isset($map[$key]) && is_string($map[$key])) {
            return $map[$key];
        }
        $defaults = self::defaultI18n();

        return $defaults[$key] ?? $key;
    }

    private static function formatDatetime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        try {
            $date = new DateTimeImmutable($value);
            $locale = (string) (self::$config['date_locale'] ?? 'nl-NL');
            if (class_exists(IntlDateFormatter::class)) {
                $formatter = new IntlDateFormatter(
                    $locale,
                    IntlDateFormatter::NONE,
                    IntlDateFormatter::NONE,
                    null,
                    null,
                    'd MMMM yyyy HH:mm'
                );
                $formatted = $formatter->format($date);
                if (is_string($formatted) && $formatted !== '') {
                    return $formatted;
                }
            }

            return $date->format('j M Y H:i');
        } catch (Throwable) {
            return $value;
        }
    }

    private static function assetUrl(string $relative): string
    {
        return self::webUrl('lib/kvt-chat/' . ltrim($relative, '/'));
    }

    /**
     * @param array<string, mixed> $extra
     */
    private static function jsonOk(array $extra): void
    {
        echo json_encode(['ok' => true] + $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function jsonError(string $message, int $status): void
    {
        http_response_code($status);
        echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
