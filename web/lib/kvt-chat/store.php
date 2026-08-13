<?php

declare(strict_types=1);

/**
 * SQLite persistence for kvt-chat messages.
 */
final class KvtChatStore
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->ensureSchema();
    }

    public static function fromPath(string $dbPath): self
    {
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return new self($pdo);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function ensureSchema(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS kvt_chat_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                thread_key TEXT NOT NULL,
                author_email TEXT NOT NULL,
                author_naam TEXT NOT NULL DEFAULT '',
                body TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT
            )
        ");
        $this->pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_kvt_chat_messages_thread
             ON kvt_chat_messages(thread_key, id)'
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listMessages(string $threadKey): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, thread_key, author_email, author_naam, body, created_at, updated_at
             FROM kvt_chat_messages
             WHERE thread_key = :thread_key
             ORDER BY id ASC'
        );
        $stmt->execute(['thread_key' => $threadKey]);

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMessage(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, thread_key, author_email, author_naam, body, created_at, updated_at
             FROM kvt_chat_messages
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function addMessage(
        string $threadKey,
        string $authorEmail,
        string $authorName,
        string $body
    ): array {
        $now = (new DateTimeImmutable('now'))->format('c');
        $stmt = $this->pdo->prepare(
            'INSERT INTO kvt_chat_messages
                (thread_key, author_email, author_naam, body, created_at, updated_at)
             VALUES
                (:thread_key, :email, :naam, :body, :created_at, NULL)'
        );
        $stmt->execute([
            'thread_key' => $threadKey,
            'email' => $authorEmail,
            'naam' => $authorName,
            'body' => $body,
            'created_at' => $now,
        ]);

        $row = $this->getMessage((int) $this->pdo->lastInsertId());
        if ($row === null) {
            throw new RuntimeException('Failed to load inserted chat message.');
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function updateMessage(int $id, string $body): array
    {
        $now = (new DateTimeImmutable('now'))->format('c');
        $stmt = $this->pdo->prepare(
            'UPDATE kvt_chat_messages
             SET body = :body, updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            'body' => $body,
            'updated_at' => $now,
            'id' => $id,
        ]);

        $row = $this->getMessage($id);
        if ($row === null) {
            throw new RuntimeException('Chat message not found after update.');
        }

        return $row;
    }

    public function deleteMessage(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM kvt_chat_messages WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function deleteThread(string $threadKey): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM kvt_chat_messages WHERE thread_key = :thread_key');
        $stmt->execute(['thread_key' => $threadKey]);
    }

    /**
     * Latest message per thread key (by highest id).
     *
     * @param list<string> $threadKeys
     * @return array<string, array<string, mixed>>
     */
    public function lastMessages(array $threadKeys): array
    {
        $normalized = [];
        foreach ($threadKeys as $key) {
            $key = trim((string) $key);
            if ($key !== '') {
                $normalized[$key] = true;
            }
        }
        $keys = array_keys($normalized);
        if ($keys === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($keys as $index => $key) {
            $name = 'k' . $index;
            $placeholders[] = ':' . $name;
            $params[$name] = $key;
        }

        $sql = 'SELECT m.id, m.thread_key, m.author_email, m.author_naam, m.body, m.created_at, m.updated_at
                FROM kvt_chat_messages m
                INNER JOIN (
                    SELECT thread_key, MAX(id) AS max_id
                    FROM kvt_chat_messages
                    WHERE thread_key IN (' . implode(', ', $placeholders) . ')
                    GROUP BY thread_key
                ) latest ON latest.max_id = m.id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $threadKey = (string) ($row['thread_key'] ?? '');
            if ($threadKey !== '') {
                $out[$threadKey] = $row;
            }
        }

        return $out;
    }

    /**
     * One-time import from legacy Moirai device_notes table if present.
     */
    public function migrateFromDeviceNotes(): int
    {
        $tables = $this->pdo->query(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'device_notes'"
        )->fetchAll();
        if ($tables === []) {
            return 0;
        }

        $existing = (int) $this->pdo->query('SELECT COUNT(*) FROM kvt_chat_messages')->fetchColumn();
        if ($existing > 0) {
            return 0;
        }

        $rows = $this->pdo->query(
            'SELECT device_type, device_key, author_email, author_naam, body, created_at, updated_at
             FROM device_notes
             ORDER BY id ASC'
        )->fetchAll();

        $insert = $this->pdo->prepare(
            'INSERT INTO kvt_chat_messages
                (thread_key, author_email, author_naam, body, created_at, updated_at)
             VALUES
                (:thread_key, :email, :naam, :body, :created_at, :updated_at)'
        );

        $count = 0;
        foreach ($rows as $row) {
            $type = trim((string) ($row['device_type'] ?? ''));
            $key = trim((string) ($row['device_key'] ?? ''));
            if ($type === '' || $key === '') {
                continue;
            }
            $insert->execute([
                'thread_key' => 'device:' . $type . ':' . $key,
                'email' => strtolower(trim((string) ($row['author_email'] ?? ''))),
                'naam' => trim((string) ($row['author_naam'] ?? '')),
                'body' => (string) ($row['body'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'updated_at' => ($row['updated_at'] ?? null) !== null && $row['updated_at'] !== ''
                    ? (string) $row['updated_at']
                    : null,
            ]);
            $count++;
        }

        return $count;
    }
}
