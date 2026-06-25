<?php

/**
 * Includes/requires
 */
require_once __DIR__ . '/ponos_db.php';

/**
 * Functies
 */

function ponos_get_last_read_message_id(string $userEmail, string $taskId): int
{
    $userEmail = strtolower(trim($userEmail));
    $taskId = trim($taskId);
    if ($userEmail === '' || $taskId === '') {
        return 0;
    }

    $stmt = ponos_db()->prepare(
        'SELECT last_read_message_id FROM task_message_reads WHERE user_email = ? AND task_id = ?'
    );
    $stmt->execute([$userEmail, $taskId]);
    $value = $stmt->fetchColumn();

    return $value === false ? 0 : (int) $value;
}

function ponos_count_unread_for_task(string $userEmail, string $taskId): int
{
    $userEmail = strtolower(trim($userEmail));
    $taskId = trim($taskId);
    if ($userEmail === '' || $taskId === '') {
        return 0;
    }

    $lastRead = ponos_get_last_read_message_id($userEmail, $taskId);
    $stmt = ponos_db()->prepare(
        'SELECT COUNT(*) FROM messages
         WHERE task_id = ? AND id > ? AND LOWER(email) != ? AND kind = ?'
    );
    $stmt->execute([$taskId, $lastRead, $userEmail, 'user']);

    return (int) $stmt->fetchColumn();
}

function ponos_unread_counts_for_tasks(string $userEmail, array $taskIds): array
{
    $userEmail = strtolower(trim($userEmail));
    $taskIds = array_values(array_filter(array_map(static fn($id): string => trim((string) $id), $taskIds)));
    if ($userEmail === '' || $taskIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
    $params = array_merge([$userEmail], $taskIds, [$userEmail, 'user']);
    $stmt = ponos_db()->prepare(
        "SELECT m.task_id, COUNT(*) AS unread_count
         FROM messages m
         LEFT JOIN task_message_reads r ON r.task_id = m.task_id AND r.user_email = ?
         WHERE m.task_id IN ({$placeholders}) AND m.id > COALESCE(r.last_read_message_id, 0)
           AND LOWER(m.email) != ? AND m.kind = ?
         GROUP BY m.task_id"
    );
    $stmt->execute($params);

    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        $counts[(string) $row['task_id']] = (int) $row['unread_count'];
    }

    return $counts;
}

function ponos_enrich_tasks_with_unread(array $tasks, string $userEmail): array
{
    $taskIds = array_map(static fn(array $task): string => (string) ($task['id'] ?? ''), $tasks);
    $counts = ponos_unread_counts_for_tasks($userEmail, $taskIds);

    foreach ($tasks as $index => $task) {
        $taskId = (string) ($task['id'] ?? '');
        $tasks[$index]['unread_count'] = $counts[$taskId] ?? 0;
    }

    return $tasks;
}

function ponos_mark_task_messages_read(string $userEmail, string $taskId): void
{
    $userEmail = strtolower(trim($userEmail));
    $taskId = trim($taskId);
    if ($userEmail === '' || $taskId === '') {
        return;
    }

    $stmt = ponos_db()->prepare('SELECT COALESCE(MAX(id), 0) FROM messages WHERE task_id = ?');
    $stmt->execute([$taskId]);
    $lastMessageId = (int) $stmt->fetchColumn();

    ponos_db()->prepare(
        'INSERT INTO task_message_reads(user_email, task_id, last_read_message_id)
         VALUES(?, ?, ?)
         ON CONFLICT(user_email, task_id) DO UPDATE SET last_read_message_id = excluded.last_read_message_id'
    )->execute([$userEmail, $taskId, $lastMessageId]);
}
