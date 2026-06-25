<?php

/**
 * Includes/requires
 */
require_once __DIR__ . '/ponos_db.php';
require_once __DIR__ . '/ponos_reads.php';
require_once __DIR__ . '/ponos_email.php';

/**
 * Functies
 */

function ponos_queue_task_email(
    array $task,
    string $recipientEmail,
    string $actorEmail,
    string $notificationType,
    string $subject,
    string $intro,
    string $groupName,
    ?int $referenceId = null,
    array $payload = []
): void {
    $recipientEmail = strtolower(trim($recipientEmail));
    $taskId = trim((string) ($task['id'] ?? ''));
    $groupId = trim((string) ($task['group_id'] ?? $task['home_group_id'] ?? ''));
    if ($recipientEmail === '' || $taskId === '' || $groupId === '') {
        return;
    }

    $now = gmdate('c');
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($payloadJson)) {
        $payloadJson = '{}';
    }

    ponos_db()->prepare(
        'INSERT INTO email_queue(
            recipient_email, task_id, group_id, notification_type, actor_email,
            subject, intro, group_name, reference_id, payload_json, created_at
         ) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON CONFLICT(recipient_email, task_id, notification_type) DO UPDATE SET
            actor_email = excluded.actor_email,
            subject = excluded.subject,
            intro = excluded.intro,
            group_name = excluded.group_name,
            reference_id = excluded.reference_id,
            payload_json = excluded.payload_json,
            created_at = excluded.created_at'
    )->execute([
        $recipientEmail,
        $taskId,
        $groupId,
        $notificationType,
        strtolower(trim($actorEmail)),
        $subject,
        $intro,
        $groupName,
        $referenceId,
        $payloadJson,
        $now,
    ]);
}

function ponos_get_task_seen_at(string $userEmail, string $taskId): ?string
{
    $userEmail = strtolower(trim($userEmail));
    $taskId = trim($taskId);
    if ($userEmail === '' || $taskId === '') {
        return null;
    }

    $stmt = ponos_db()->prepare(
        'SELECT seen_at FROM task_user_seen WHERE user_email = ? AND task_id = ?'
    );
    $stmt->execute([$userEmail, $taskId]);
    $value = $stmt->fetchColumn();
    if ($value === false || trim((string) $value) === '') {
        return null;
    }

    return (string) $value;
}

function ponos_mark_task_seen(string $userEmail, string $taskId): void
{
    $userEmail = strtolower(trim($userEmail));
    $taskId = trim($taskId);
    if ($userEmail === '' || $taskId === '') {
        return;
    }

    ponos_db()->prepare(
        'INSERT INTO task_user_seen(user_email, task_id, seen_at)
         VALUES(?, ?, ?)
         ON CONFLICT(user_email, task_id) DO UPDATE SET seen_at = excluded.seen_at'
    )->execute([$userEmail, $taskId, gmdate('c')]);

    ponos_purge_obsolete_email_queue($userEmail, $taskId);
}

function ponos_email_queue_row_is_obsolete(array $row): bool
{
    $recipient = strtolower(trim((string) ($row['recipient_email'] ?? '')));
    $taskId = trim((string) ($row['task_id'] ?? ''));
    $type = (string) ($row['notification_type'] ?? '');
    $queuedAt = trim((string) ($row['created_at'] ?? ''));
    if ($recipient === '' || $taskId === '' || $queuedAt === '') {
        return true;
    }

    if ($type === 'message') {
        $messageId = (int) ($row['reference_id'] ?? 0);
        if ($messageId > 0 && ponos_get_last_read_message_id($recipient, $taskId) >= $messageId) {
            return true;
        }
    }

    $seenAt = ponos_get_task_seen_at($recipient, $taskId);
    if ($seenAt !== null && $seenAt >= $queuedAt) {
        return true;
    }

    return false;
}

function ponos_purge_obsolete_email_queue(?string $recipientEmail = null, ?string $taskId = null): int
{
    $pdo = ponos_db();
    $params = [];
    $sql = 'SELECT id, recipient_email, task_id, notification_type, reference_id, created_at FROM email_queue';
    $conditions = [];
    if ($recipientEmail !== null && trim($recipientEmail) !== '') {
        $conditions[] = 'recipient_email = ?';
        $params[] = strtolower(trim($recipientEmail));
    }
    if ($taskId !== null && trim($taskId) !== '') {
        $conditions[] = 'task_id = ?';
        $params[] = trim($taskId);
    }
    if ($conditions !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $removed = 0;
    $delete = $pdo->prepare('DELETE FROM email_queue WHERE id = ?');
    foreach ($stmt->fetchAll() as $row) {
        if (!ponos_email_queue_row_is_obsolete($row)) {
            continue;
        }
        $delete->execute([(int) ($row['id'] ?? 0)]);
        $removed++;
    }

    return $removed;
}

function ponos_fetch_task_for_email_queue(string $taskId, string $groupId): ?array
{
    require_once __DIR__ . '/ponos_storage.php';

    $task = ponos_get_task($groupId, $taskId);
    if ($task === null) {
        $location = ponos_find_task_location($taskId);
        if ($location === null) {
            return null;
        }

        $task = ponos_get_task($location['group_id'], $taskId);
    }

    return $task;
}

function ponos_process_email_queue(): array
{
    ponos_purge_obsolete_email_queue();

    $stmt = ponos_db()->query(
        'SELECT id, recipient_email, task_id, group_id, notification_type, subject, intro, group_name,
                reference_id, created_at
         FROM email_queue ORDER BY created_at ASC, id ASC'
    );

    $sent = 0;
    $cancelled = 0;
    $failed = 0;
    $delete = ponos_db()->prepare('DELETE FROM email_queue WHERE id = ?');

    foreach ($stmt->fetchAll() as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        if (ponos_email_queue_row_is_obsolete($row)) {
            $delete->execute([$id]);
            $cancelled++;
            continue;
        }

        $recipient = strtolower(trim((string) ($row['recipient_email'] ?? '')));
        $taskId = trim((string) ($row['task_id'] ?? ''));
        $groupId = trim((string) ($row['group_id'] ?? ''));
        $task = ponos_fetch_task_for_email_queue($taskId, $groupId);
        if ($task === null || $recipient === '') {
            $delete->execute([$id]);
            $cancelled++;
            continue;
        }

        $task['group_id'] = $groupId;
        $sentOk = ponos_email_send_task_notice(
            $task,
            $recipient,
            (string) ($row['subject'] ?? ''),
            (string) ($row['intro'] ?? ''),
            (string) ($row['group_name'] ?? '')
        );

        if ($sentOk) {
            $delete->execute([$id]);
            $sent++;
        } else {
            $failed++;
        }
    }

    return [
        'sent' => $sent,
        'cancelled' => $cancelled,
        'failed' => $failed,
        'pending' => (int) ponos_db()->query('SELECT COUNT(*) FROM email_queue')->fetchColumn(),
    ];
}
