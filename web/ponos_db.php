<?php

/**
 * Includes/requires
 */
require_once __DIR__ . '/ponos_data.php';

/**
 * Functies
 */

function ponos_db_path(): string
{
    if (defined('PONOS_TEST_DB_PATH')) {
        return (string) PONOS_TEST_DB_PATH;
    }

    return ponos_data_dir() . DIRECTORY_SEPARATOR . 'ponos.sqlite';
}

function ponos_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('PDO sqlite driver is not available');
    }

    $dir = dirname(ponos_db_path());
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    $pdo = new PDO('sqlite:' . ponos_db_path());
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    ponos_db_migrate_schema($pdo);
    ponos_db_migrate_json_if_needed($pdo);

    return $pdo;
}

function ponos_db_migrate_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS groups (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            created_at TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0,
            can_create_tasks INTEGER NOT NULL DEFAULT 1
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS tasks (
            id TEXT PRIMARY KEY,
            group_id TEXT NOT NULL REFERENCES groups(id) ON DELETE CASCADE,
            title TEXT NOT NULL,
            description TEXT NOT NULL,
            status TEXT NOT NULL,
            assignee_email TEXT NOT NULL DEFAULT "",
            due_date TEXT NOT NULL DEFAULT "",
            created_by TEXT NOT NULL DEFAULT "",
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0
        )'
    );

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_tasks_group_id ON tasks(group_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_tasks_group_status ON tasks(group_id, status)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_tasks_assignee ON tasks(assignee_email)');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS checklist_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            task_id TEXT NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
            label TEXT NOT NULL,
            done INTEGER NOT NULL DEFAULT 0,
            sort_order INTEGER NOT NULL DEFAULT 0
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            task_id TEXT NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
            email TEXT NOT NULL,
            text TEXT NOT NULL,
            kind TEXT NOT NULL DEFAULT "user",
            created_at TEXT NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS attachments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            task_id TEXT NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
            message_id INTEGER NULL REFERENCES messages(id) ON DELETE SET NULL,
            filename TEXT NOT NULL,
            stored_name TEXT NOT NULL,
            mime TEXT NOT NULL DEFAULT "",
            size_bytes INTEGER NOT NULL DEFAULT 0,
            uploaded_by TEXT NOT NULL DEFAULT "",
            created_at TEXT NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_meta (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS task_message_reads (
            user_email TEXT NOT NULL,
            task_id TEXT NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
            last_read_message_id INTEGER NOT NULL DEFAULT 0,
            PRIMARY KEY (user_email, task_id)
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS categories (
            id TEXT PRIMARY KEY,
            group_id TEXT NOT NULL REFERENCES groups(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL
        )'
    );

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_categories_group_id ON categories(group_id)');

    ponos_db_ensure_column($pdo, 'tasks', 'category_id', 'TEXT NULL');
    ponos_db_ensure_column($pdo, 'tasks', 'category_label', 'TEXT NOT NULL DEFAULT ""');
    ponos_db_ensure_column($pdo, 'tasks', 'done_at', 'TEXT NULL');
    ponos_db_ensure_column($pdo, 'tasks', 'last_reminder_at', 'TEXT NULL');
    ponos_db_ensure_column($pdo, 'groups', 'open_access', 'INTEGER NOT NULL DEFAULT 0');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS group_members (
            group_id TEXT NOT NULL REFERENCES groups(id) ON DELETE CASCADE,
            user_email TEXT NOT NULL,
            created_at TEXT NOT NULL,
            PRIMARY KEY (group_id, user_email)
        )'
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_group_members_email ON group_members(user_email)');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS task_user_seen (
            user_email TEXT NOT NULL,
            task_id TEXT NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
            seen_at TEXT NOT NULL,
            PRIMARY KEY (user_email, task_id)
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS email_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            recipient_email TEXT NOT NULL,
            task_id TEXT NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
            group_id TEXT NOT NULL,
            notification_type TEXT NOT NULL,
            actor_email TEXT NOT NULL DEFAULT "",
            subject TEXT NOT NULL,
            intro TEXT NOT NULL,
            group_name TEXT NOT NULL DEFAULT "",
            reference_id INTEGER NULL,
            payload_json TEXT NOT NULL DEFAULT "{}",
            created_at TEXT NOT NULL,
            UNIQUE(recipient_email, task_id, notification_type)
        )'
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_email_queue_created ON email_queue(created_at)');

    if (ponos_db_meta_get($pdo, 'open_access_migrated') !== '1') {
        $pdo->exec('UPDATE groups SET open_access = 1');
        ponos_db_meta_set($pdo, 'open_access_migrated', '1');
    }

    require_once __DIR__ . '/ponos_archive.php';
    ponos_backfill_done_at($pdo);
}

function ponos_db_ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    $stmt = $pdo->query('PRAGMA table_info(' . $table . ')');
    foreach ($stmt->fetchAll() as $row) {
        if (($row['name'] ?? '') === $column) {
            return;
        }
    }

    $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
}

function ponos_db_meta_get(PDO $pdo, string $key): ?string
{
    $stmt = $pdo->prepare('SELECT value FROM schema_meta WHERE key = ?');
    $stmt->execute([$key]);

    $value = $stmt->fetchColumn();

    return $value === false ? null : (string) $value;
}

function ponos_db_meta_set(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare('INSERT INTO schema_meta(key, value) VALUES(?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    $stmt->execute([$key, $value]);
}

function ponos_db_migrate_json_if_needed(PDO $pdo): void
{
    if (ponos_db_meta_get($pdo, 'json_migrated') === '1') {
        return;
    }

    ponos_db_migrate_groups_json($pdo);
    ponos_db_migrate_tasks_json_dir($pdo, ponos_data_dir() . DIRECTORY_SEPARATOR . 'tasks');
    ponos_db_migrate_tasks_json_dir($pdo, ponos_data_dir() . DIRECTORY_SEPARATOR . 'projects');

    $companiesDir = ponos_data_dir() . DIRECTORY_SEPARATOR . 'companies';
    if (is_dir($companiesDir)) {
        foreach (glob($companiesDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $companyDir) {
            ponos_db_migrate_groups_json_file($pdo, $companyDir . DIRECTORY_SEPARATOR . 'groups.json');
            ponos_db_migrate_tasks_json_dir($pdo, $companyDir . DIRECTORY_SEPARATOR . 'tasks');
        }
    }

    ponos_db_meta_set($pdo, 'json_migrated', '1');
}

function ponos_db_migrate_groups_json(PDO $pdo): void
{
    ponos_db_migrate_groups_json_file($pdo, ponos_data_dir() . DIRECTORY_SEPARATOR . 'groups.json');
}

function ponos_db_migrate_groups_json_file(PDO $pdo, string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return;
    }

    foreach ($decoded['groups'] ?? [] as $row) {
        if (!is_array($row)) {
            continue;
        }

        $id = trim((string) ($row['id'] ?? ''));
        $name = trim((string) ($row['name'] ?? ''));
        if ($id === '' || $name === '') {
            continue;
        }

        $stmt = $pdo->prepare(
            'INSERT OR IGNORE INTO groups(id, name, created_at, sort_order, can_create_tasks)
             VALUES(?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $id,
            $name,
            (string) ($row['created_at'] ?? gmdate('c')),
            (int) ($row['sort_order'] ?? 0),
            !array_key_exists('can_create_tasks', $row) || !empty($row['can_create_tasks']) ? 1 : 0,
        ]);
    }
}

function ponos_db_migrate_tasks_json_dir(PDO $pdo, string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    foreach (glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $path) {
        $groupId = basename($path, '.json');
        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            continue;
        }

        $pdo->prepare('INSERT OR IGNORE INTO groups(id, name, created_at, sort_order, can_create_tasks) VALUES(?, ?, ?, ?, 1)')
            ->execute([$groupId, $groupId, gmdate('c'), 0]);

        foreach ($decoded['tasks'] ?? [] as $task) {
            if (!is_array($task)) {
                continue;
            }

            ponos_db_import_task_array($pdo, $groupId, $task);
        }
    }
}

function ponos_db_import_task_array(PDO $pdo, string $groupId, array $task): void
{
    $taskId = trim((string) ($task['id'] ?? ''));
    if ($taskId === '') {
        return;
    }

    $exists = $pdo->prepare('SELECT 1 FROM tasks WHERE id = ?');
    $exists->execute([$taskId]);
    if ($exists->fetchColumn()) {
        return;
    }

    $pdo->prepare(
        'INSERT INTO tasks(
            id, group_id, title, description, status, assignee_email, due_date,
            created_by, created_at, updated_at, sort_order
        ) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $taskId,
        $groupId,
        (string) ($task['title'] ?? ''),
        (string) ($task['description'] ?? ''),
        (string) ($task['status'] ?? PONOS_STATUS_TODO),
        strtolower(trim((string) ($task['assignee_email'] ?? ''))),
        trim((string) ($task['due_date'] ?? '')),
        strtolower(trim((string) ($task['created_by'] ?? ''))),
        (string) ($task['created_at'] ?? gmdate('c')),
        (string) ($task['updated_at'] ?? gmdate('c')),
        (int) ($task['sort_order'] ?? 0),
    ]);

    foreach (is_array($task['checklist'] ?? null) ? $task['checklist'] : [] as $item) {
        if (!is_array($item)) {
            continue;
        }

        $itemId = (int) ($item['id'] ?? 0);
        if ($itemId > 0) {
            $pdo->prepare(
                'INSERT OR IGNORE INTO checklist_items(id, task_id, label, done, sort_order) VALUES(?, ?, ?, ?, ?)'
            )->execute([
                $itemId,
                $taskId,
                (string) ($item['label'] ?? ''),
                !empty($item['done']) ? 1 : 0,
                (int) ($item['sort_order'] ?? 0),
            ]);
        } else {
            $pdo->prepare(
                'INSERT INTO checklist_items(task_id, label, done, sort_order) VALUES(?, ?, ?, ?)'
            )->execute([
                $taskId,
                (string) ($item['label'] ?? ''),
                !empty($item['done']) ? 1 : 0,
                (int) ($item['sort_order'] ?? 0),
            ]);
        }
    }

    foreach (is_array($task['messages'] ?? null) ? $task['messages'] : [] as $message) {
        if (!is_array($message)) {
            continue;
        }

        $messageId = (int) ($message['id'] ?? 0);
        if ($messageId > 0) {
            $pdo->prepare(
                'INSERT OR IGNORE INTO messages(id, task_id, email, text, kind, created_at) VALUES(?, ?, ?, ?, ?, ?)'
            )->execute([
                $messageId,
                $taskId,
                strtolower(trim((string) ($message['email'] ?? ''))),
                (string) ($message['text'] ?? ''),
                (string) ($message['kind'] ?? 'user'),
                (string) ($message['created_at'] ?? gmdate('c')),
            ]);
        } else {
            $pdo->prepare(
                'INSERT INTO messages(task_id, email, text, kind, created_at) VALUES(?, ?, ?, ?, ?)'
            )->execute([
                $taskId,
                strtolower(trim((string) ($message['email'] ?? ''))),
                (string) ($message['text'] ?? ''),
                (string) ($message['kind'] ?? 'user'),
                (string) ($message['created_at'] ?? gmdate('c')),
            ]);
        }
    }

    foreach (is_array($task['attachments'] ?? null) ? $task['attachments'] : [] as $attachment) {
        if (!is_array($attachment)) {
            continue;
        }

        $messageId = isset($attachment['message_id']) ? (int) $attachment['message_id'] : null;
        if ($messageId === 0) {
            $messageId = null;
        }

        $pdo->prepare(
            'INSERT INTO attachments(
                id, task_id, message_id, filename, stored_name, mime, size_bytes, uploaded_by, created_at
            ) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            (int) ($attachment['id'] ?? 0) ?: null,
            $taskId,
            $messageId,
            (string) ($attachment['filename'] ?? ''),
            (string) ($attachment['stored_name'] ?? ''),
            (string) ($attachment['mime'] ?? ''),
            (int) ($attachment['size_bytes'] ?? 0),
            strtolower(trim((string) ($attachment['uploaded_by'] ?? ''))),
            (string) ($attachment['created_at'] ?? gmdate('c')),
        ]);
    }
}

function ponos_db_fetch_checklist_for_task(string $taskId): array
{
    $stmt = ponos_db()->prepare('SELECT id, label, done, sort_order FROM checklist_items WHERE task_id = ? ORDER BY sort_order, id');
    $stmt->execute([$taskId]);
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $items[] = [
            'id' => (int) $row['id'],
            'label' => (string) $row['label'],
            'done' => !empty($row['done']),
            'sort_order' => (int) $row['sort_order'],
        ];
    }

    return $items;
}

function ponos_db_fetch_messages_for_task(string $taskId): array
{
    $stmt = ponos_db()->prepare('SELECT id, email, text, kind, created_at FROM messages WHERE task_id = ? ORDER BY id');
    $stmt->execute([$taskId]);
    $messages = [];
    foreach ($stmt->fetchAll() as $row) {
        $messages[] = [
            'id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'text' => (string) $row['text'],
            'kind' => (string) $row['kind'],
            'created_at' => (string) $row['created_at'],
        ];
    }

    return $messages;
}

function ponos_db_fetch_attachments_for_task(string $taskId): array
{
    $stmt = ponos_db()->prepare(
        'SELECT id, task_id, message_id, filename, stored_name, mime, size_bytes, uploaded_by, created_at
         FROM attachments WHERE task_id = ? ORDER BY id'
    );
    $stmt->execute([$taskId]);
    $attachments = [];
    foreach ($stmt->fetchAll() as $row) {
        $attachments[] = [
            'id' => (int) $row['id'],
            'task_id' => (string) $row['task_id'],
            'message_id' => $row['message_id'] !== null ? (int) $row['message_id'] : null,
            'filename' => (string) $row['filename'],
            'stored_name' => (string) $row['stored_name'],
            'mime' => (string) $row['mime'],
            'size_bytes' => (int) $row['size_bytes'],
            'uploaded_by' => (string) $row['uploaded_by'],
            'created_at' => (string) $row['created_at'],
        ];
    }

    return $attachments;
}

function ponos_db_fetch_task_array(string $taskId, bool $withMessages = true): ?array
{
    $stmt = ponos_db()->prepare('SELECT * FROM tasks WHERE id = ?');
    $stmt->execute([$taskId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }

    $task = [
        'id' => (string) $row['id'],
        'group_id' => (string) ($row['group_id'] ?? ''),
        'title' => (string) $row['title'],
        'description' => (string) $row['description'],
        'status' => (string) $row['status'],
        'assignee_email' => (string) $row['assignee_email'],
        'due_date' => (string) $row['due_date'],
        'category_id' => trim((string) ($row['category_id'] ?? '')),
        'category_label' => trim((string) ($row['category_label'] ?? '')),
        'done_at' => trim((string) ($row['done_at'] ?? '')),
        'last_reminder_at' => trim((string) ($row['last_reminder_at'] ?? '')),
        'created_by' => (string) $row['created_by'],
        'created_at' => (string) $row['created_at'],
        'updated_at' => (string) $row['updated_at'],
        'sort_order' => (int) $row['sort_order'],
        'checklist' => ponos_db_fetch_checklist_for_task($taskId),
        'messages' => $withMessages ? ponos_db_fetch_messages_for_task($taskId) : [],
        'attachments' => ponos_db_fetch_attachments_for_task($taskId),
    ];

    return $task;
}

function ponos_db_next_task_sort_order(string $groupId, string $status): int
{
    $stmt = ponos_db()->prepare('SELECT COALESCE(MAX(sort_order), -1) FROM tasks WHERE group_id = ? AND status = ?');
    $stmt->execute([$groupId, $status]);

    return ((int) $stmt->fetchColumn()) + 1;
}

function ponos_db_insert_system_message(string $taskId, string $text): void
{
    $text = trim($text);
    if ($text === '') {
        return;
    }

    if (mb_strlen($text) > 8000) {
        $text = mb_substr($text, 0, 8000);
    }

    ponos_db()->prepare(
        'INSERT INTO messages(task_id, email, text, kind, created_at) VALUES(?, ?, ?, ?, ?)'
    )->execute([
        $taskId,
        'system@ponos.local',
        $text,
        'system',
        gmdate('c'),
    ]);
}

function ponos_db_group_name(string $groupId): string
{
    $stmt = ponos_db()->prepare('SELECT name FROM groups WHERE id = ?');
    $stmt->execute([$groupId]);
    $name = $stmt->fetchColumn();

    return $name === false ? '' : (string) $name;
}

function ponos_db_wipe_all(): void
{
    $pdo = ponos_db();
    $pdo->exec('DELETE FROM email_queue');
    $pdo->exec('DELETE FROM task_user_seen');
    $pdo->exec('DELETE FROM attachments');
    $pdo->exec('DELETE FROM task_message_reads');
    $pdo->exec('DELETE FROM messages');
    $pdo->exec('DELETE FROM checklist_items');
    $pdo->exec('DELETE FROM tasks');
    $pdo->exec('DELETE FROM categories');
    $pdo->exec('DELETE FROM group_members');
    $pdo->exec('DELETE FROM groups');
    $pdo->exec('DELETE FROM schema_meta');
}
