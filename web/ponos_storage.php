<?php

/**
 * Includes/requires
 */
require_once __DIR__ . '/ponos_data.php';
require_once __DIR__ . '/ponos_db.php';
require_once __DIR__ . '/ponos_categories.php';
require_once __DIR__ . '/ponos_access.php';
require_once __DIR__ . '/ponos_archive.php';
require_once __DIR__ . '/ponos_notify.php';
require_once __DIR__ . '/ponos_reads.php';
require_once __DIR__ . '/ponos_avatars.php';

/**
 * Constants
 */

const PONOS_MESSAGE_MAX_LENGTH = 8000;

const PONOS_TASK_TITLE_MAX_LENGTH = 200;

const PONOS_ATTACHMENT_MAX_BYTES = 10485760;

/**
 * Functies
 */

function ponos_enrich_task_for_client(array $task): array
{
    $task['category_display'] = ponos_category_display_label((string) ($task['category_label'] ?? ''));
    $task['is_archived'] = ponos_task_is_archived($task);
    $task['can_remind'] = ponos_task_can_remind($task);

    return $task;
}

function ponos_get_task_for_client(string $groupId, string $taskId): ?array
{
    $task = ponos_get_task($groupId, $taskId);
    if ($task === null) {
        return null;
    }

    return ponos_enrich_task_for_client($task);
}

function ponos_send_task_email_reminder(string $groupId, string $taskId, string $actorEmail): array
{
    $task = ponos_db_fetch_task_array($taskId, false);
    if ($task === null) {
        return ['ok' => false, 'error' => LOC('ponos.error.task_not_found')];
    }

    $location = ponos_find_task_location($taskId);
    if ($location === null || $location['group_id'] !== $groupId) {
        return ['ok' => false, 'error' => LOC('ponos.error.task_not_found')];
    }

    if (!ponos_task_can_remind($task)) {
        return ['ok' => false, 'error' => LOC('ponos.error.reminder_rate_limited')];
    }

    $groupName = ponos_db_group_name($groupId);
    if (!ponos_notify_manual_task_reminder($task, $actorEmail, $groupName)) {
        return ['ok' => false, 'error' => LOC('ponos.error.reminder_send_failed')];
    }

    $now = gmdate('c');
    ponos_db()->prepare('UPDATE tasks SET last_reminder_at = ?, updated_at = ? WHERE id = ?')
        ->execute([$now, $now, $taskId]);

    ponos_db_insert_system_message(
        $taskId,
        LOC('ponos.system.reminder_sent', (string) ($task['assignee_email'] ?? ''))
    );

    return [
        'ok' => true,
        'last_reminder_at' => $now,
    ];
}

function ponos_list_archived_tasks(string $viewGroupId, string $userEmail, int $page = 1): array
{
    $page = max(1, $page);
    $offset = ($page - 1) * PONOS_ARCHIVE_PAGE_SIZE;
    $cutoff = ponos_archive_cutoff_iso();
    $pdo = ponos_db();

    if ($viewGroupId === PONOS_GROUP_MY_TASKS) {
        $userEmail = strtolower(trim($userEmail));
        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM tasks
             WHERE LOWER(assignee_email) = ?
               AND status = ?
               AND TRIM(COALESCE(NULLIF(done_at, ""), updated_at)) != ""
               AND TRIM(COALESCE(NULLIF(done_at, ""), updated_at)) < ?'
        );
        $countStmt->execute([$userEmail, PONOS_STATUS_DONE, $cutoff]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            'SELECT t.id, t.group_id, g.name AS group_name
             FROM tasks t
             INNER JOIN groups g ON g.id = t.group_id
             WHERE LOWER(t.assignee_email) = ?
               AND t.status = ?
               AND TRIM(COALESCE(NULLIF(t.done_at, ""), t.updated_at)) != ""
               AND TRIM(COALESCE(NULLIF(t.done_at, ""), t.updated_at)) < ?
             ORDER BY TRIM(COALESCE(NULLIF(t.done_at, ""), t.updated_at)) DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->execute([$userEmail, PONOS_STATUS_DONE, $cutoff, PONOS_ARCHIVE_PAGE_SIZE, $offset]);
    } else {
        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM tasks
             WHERE group_id = ?
               AND status = ?
               AND TRIM(COALESCE(NULLIF(done_at, ""), updated_at)) != ""
               AND TRIM(COALESCE(NULLIF(done_at, ""), updated_at)) < ?'
        );
        $countStmt->execute([$viewGroupId, PONOS_STATUS_DONE, $cutoff]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            'SELECT id, group_id
             FROM tasks
             WHERE group_id = ?
               AND status = ?
               AND TRIM(COALESCE(NULLIF(done_at, ""), updated_at)) != ""
               AND TRIM(COALESCE(NULLIF(done_at, ""), updated_at)) < ?
             ORDER BY TRIM(COALESCE(NULLIF(done_at, ""), updated_at)) DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->execute([$viewGroupId, PONOS_STATUS_DONE, $cutoff, PONOS_ARCHIVE_PAGE_SIZE, $offset]);
    }

    $tasks = [];
    foreach ($stmt->fetchAll() as $row) {
        $groupId = (string) $row['group_id'];
        $taskId = (string) $row['id'];
        $task = ponos_get_task_for_client($groupId, $taskId);
        if ($task === null) {
            continue;
        }

        if ($viewGroupId === PONOS_GROUP_MY_TASKS) {
            $task['home_group_id'] = $groupId;
            $task['home_group_name'] = (string) ($row['group_name'] ?? '');
        }

        $tasks[] = $task;
    }

    $totalPages = $total > 0 ? (int) ceil($total / PONOS_ARCHIVE_PAGE_SIZE) : 1;

    return [
        'tasks' => $tasks,
        'page' => $page,
        'total' => $total,
        'total_pages' => max(1, $totalPages),
        'page_size' => PONOS_ARCHIVE_PAGE_SIZE,
    ];
}

function ponos_unarchive_task(string $groupId, string $taskId): ?array
{
    $task = ponos_db_fetch_task_array($taskId, false);
    if ($task === null) {
        return null;
    }

    $location = ponos_find_task_location($taskId);
    if ($location === null || $location['group_id'] !== $groupId) {
        return null;
    }

    if ((string) ($task['status'] ?? '') !== PONOS_STATUS_DONE) {
        return null;
    }

    if (!ponos_task_is_archived($task)) {
        return ponos_get_task_for_client($groupId, $taskId);
    }

    $now = gmdate('c');
    ponos_db()->prepare('UPDATE tasks SET done_at = ?, updated_at = ? WHERE id = ?')
        ->execute([$now, $now, $taskId]);

    ponos_db_insert_system_message($taskId, LOC('ponos.system.unarchived'));

    return ponos_get_task_for_client($groupId, $taskId);
}

function ponos_group_store_path(string $groupId): string
{
    return ponos_db_path();
}

function ponos_attachments_dir(): string
{
    $dir = ponos_data_dir() . DIRECTORY_SEPARATOR . 'attachments';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    return $dir;
}

function ponos_new_task_id(): string
{
    return ponos_new_id();
}

function ponos_trim_message_text(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    if (mb_strlen($text) > PONOS_MESSAGE_MAX_LENGTH) {
        return mb_substr($text, 0, PONOS_MESSAGE_MAX_LENGTH);
    }

    return $text;
}

function ponos_normalize_checklist_items(array $items): array
{
    $normalized = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $normalized[] = [
            'id' => (int) ($item['id'] ?? 0),
            'label' => (string) ($item['label'] ?? ''),
            'done' => !empty($item['done']),
            'sort_order' => (int) ($item['sort_order'] ?? 0),
        ];
    }

    usort($normalized, static fn(array $left, array $right): int => $left['sort_order'] <=> $right['sort_order']);

    return $normalized;
}

function ponos_normalize_attachment_row(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'task_id' => (string) ($row['task_id'] ?? ''),
        'message_id' => isset($row['message_id']) ? (int) $row['message_id'] : null,
        'filename' => (string) ($row['filename'] ?? ''),
        'mime' => (string) ($row['mime'] ?? ''),
        'size_bytes' => (int) ($row['size_bytes'] ?? 0),
        'uploaded_by' => strtolower(trim((string) ($row['uploaded_by'] ?? ''))),
        'created_at' => (string) ($row['created_at'] ?? ''),
    ];
}

function ponos_task_attachments(array $task, ?int $messageId = null): array
{
    $attachments = is_array($task['attachments'] ?? null) ? $task['attachments'] : [];
    $result = [];
    foreach ($attachments as $attachment) {
        if (!is_array($attachment)) {
            continue;
        }

        $attachmentMessageId = isset($attachment['message_id']) ? (int) $attachment['message_id'] : null;
        if ($messageId === null && $attachmentMessageId !== null) {
            continue;
        }

        if ($messageId !== null && $attachmentMessageId !== $messageId) {
            continue;
        }

        $result[] = ponos_normalize_attachment_row($attachment);
    }

    return $result;
}

function ponos_normalize_task_row(array $row): array
{
    $checklist = ponos_normalize_checklist_items(is_array($row['checklist'] ?? null) ? $row['checklist'] : []);
    $checklistDone = 0;
    foreach ($checklist as $item) {
        if (!empty($item['done'])) {
            $checklistDone++;
        }
    }

    $colorKey = trim((string) ($row['_color_key'] ?? $row['category_label'] ?? ''));

    return [
        'id' => (string) ($row['id'] ?? ''),
        'title' => (string) ($row['title'] ?? ''),
        'description' => (string) ($row['description'] ?? ''),
        'status' => (string) ($row['status'] ?? PONOS_STATUS_TODO),
        'assignee_email' => strtolower(trim((string) ($row['assignee_email'] ?? ''))),
        'due_date' => trim((string) ($row['due_date'] ?? '')),
        'category_id' => trim((string) ($row['category_id'] ?? '')),
        'category_label' => trim((string) ($row['category_label'] ?? '')),
        'done_at' => trim((string) ($row['done_at'] ?? '')),
        'last_reminder_at' => trim((string) ($row['last_reminder_at'] ?? '')),
        'created_by' => strtolower(trim((string) ($row['created_by'] ?? ''))),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'updated_at' => (string) ($row['updated_at'] ?? ''),
        'sort_order' => (int) ($row['sort_order'] ?? 0),
        'checklist' => $checklist,
        'checklist_total' => count($checklist),
        'checklist_done' => $checklistDone,
        'attachments' => ponos_task_attachments($row),
        'colors' => ponos_category_color_from_text($colorKey),
    ];
}

function ponos_normalize_task_row_with_color(array $row, string $colorKey): array
{
    $row['_color_key'] = $colorKey;

    return ponos_normalize_task_row($row);
}

function ponos_normalize_message_row(array $row, array $task): array
{
    $messageId = (int) ($row['id'] ?? 0);
    $email = strtolower(trim((string) ($row['email'] ?? '')));

    return [
        'id' => $messageId,
        'email' => $email,
        'text' => (string) ($row['text'] ?? ''),
        'kind' => (string) ($row['kind'] ?? 'user'),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'colors' => ponos_color_from_text($email),
        'avatar_url' => ponos_user_avatar_url($email),
        'attachments' => ponos_task_attachments($task, $messageId),
    ];
}

function ponos_task_with_messages(array $task, string $colorKey = ''): array
{
    $normalized = $colorKey !== ''
        ? ponos_normalize_task_row_with_color($task, $colorKey)
        : ponos_normalize_task_row($task);
    $messages = is_array($task['messages'] ?? null) ? $task['messages'] : [];
    $normalized['messages'] = array_map(
        static fn(array $message): array => ponos_normalize_message_row($message, $task),
        $messages
    );

    return $normalized;
}

function ponos_list_tasks(string $groupId): array
{
    $stmt = ponos_db()->prepare(
        'SELECT id FROM tasks WHERE group_id = ? ORDER BY sort_order ASC, created_at ASC'
    );
    $stmt->execute([$groupId]);

    $tasks = [];
    foreach ($stmt->fetchAll() as $row) {
        $task = ponos_db_fetch_task_array((string) $row['id'], false);
        if ($task === null) {
            continue;
        }

        $normalized = ponos_enrich_task_for_client(ponos_normalize_task_row($task));
        if (!ponos_task_is_board_visible($normalized)) {
            continue;
        }

        $tasks[] = $normalized;
    }

    return $tasks;
}

function ponos_list_tasks_with_unread(string $groupId, string $userEmail): array
{
    return ponos_enrich_tasks_with_unread(ponos_list_tasks($groupId), $userEmail);
}

function ponos_get_task(string $groupId, string $taskId): ?array
{
    $stmt = ponos_db()->prepare('SELECT id FROM tasks WHERE id = ? AND group_id = ?');
    $stmt->execute([$taskId, $groupId]);
    if (!$stmt->fetchColumn()) {
        return null;
    }

    $task = ponos_db_fetch_task_array($taskId, true);
    if ($task === null) {
        return null;
    }

    return ponos_task_with_messages($task);
}

function ponos_find_task_location(string $taskId): ?array
{
    $stmt = ponos_db()->prepare('SELECT group_id FROM tasks WHERE id = ?');
    $stmt->execute([$taskId]);
    $groupId = $stmt->fetchColumn();
    if ($groupId === false) {
        return null;
    }

    return [
        'group_id' => (string) $groupId,
    ];
}

function ponos_get_task_anywhere(string $taskId): ?array
{
    $location = ponos_find_task_location($taskId);
    if ($location === null) {
        return null;
    }

    $task = ponos_get_task($location['group_id'], $taskId);
    if ($task === null) {
        return null;
    }

    $task['home_group_id'] = $location['group_id'];

    return $task;
}

function ponos_delete_group_task_store(string $groupId): void
{
    ponos_db()->prepare('DELETE FROM tasks WHERE group_id = ?')->execute([$groupId]);
}

function ponos_move_task(
    string $fromGroupId,
    string $toGroupId,
    string $taskId,
    string $editorEmail,
    string $toGroupName = ''
): ?array {
    if ($fromGroupId === $toGroupId) {
        return ponos_get_task($fromGroupId, $taskId);
    }

    $task = ponos_get_task($fromGroupId, $taskId);
    if ($task === null) {
        return null;
    }

    $status = (string) ($task['status'] ?? PONOS_STATUS_TODO);
    $sortOrder = ponos_db_next_task_sort_order($toGroupId, $status);
    $now = gmdate('c');
    $assignee = strtolower(trim((string) ($task['assignee_email'] ?? '')));
    $clearAssignee = $assignee !== '' && !ponos_user_has_group_access($assignee, $toGroupId, false);

    if ($clearAssignee) {
        ponos_db()->prepare(
            'UPDATE tasks SET group_id = ?, category_id = NULL, assignee_email = "", sort_order = ?, updated_at = ? WHERE id = ?'
        )->execute([$toGroupId, $sortOrder, $now, $taskId]);
        ponos_db_insert_system_message($taskId, LOC('ponos.system.cleared_assignee_on_move'));
    } else {
        ponos_db()->prepare(
            'UPDATE tasks SET group_id = ?, category_id = NULL, sort_order = ?, updated_at = ? WHERE id = ?'
        )->execute([$toGroupId, $sortOrder, $now, $taskId]);
    }

    ponos_db_insert_system_message(
        $taskId,
        LOC('ponos.system.moved_to_group', $toGroupName !== '' ? $toGroupName : $toGroupId)
    );

    return ponos_get_task($toGroupId, $taskId);
}

function ponos_create_task(
    string $groupId,
    string $createdBy,
    array $input,
    array $uploadedFiles = []
): ?array {
    $title = trim((string) ($input['title'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));
    if ($title === '' || $description === '') {
        return null;
    }

    if (mb_strlen($title) > PONOS_TASK_TITLE_MAX_LENGTH) {
        $title = mb_substr($title, 0, PONOS_TASK_TITLE_MAX_LENGTH);
    }

    $status = trim((string) ($input['status'] ?? PONOS_STATUS_TODO));
    if (!in_array($status, ponos_all_statuses(), true)) {
        $status = PONOS_STATUS_TODO;
    }

    $pdo = ponos_db();
    $taskId = ponos_new_task_id();
    $now = gmdate('c');
    $sortOrder = ponos_db_next_task_sort_order($groupId, $status);
    $category = ponos_resolve_task_category($groupId, trim((string) ($input['category_id'] ?? '')));
    $assignee = ponos_normalize_assignee_for_group(
        $groupId,
        (string) ($input['assignee_email'] ?? '')
    );

    $pdo->prepare(
        'INSERT INTO tasks(
            id, group_id, title, description, status, assignee_email, due_date,
            category_id, category_label, created_by, created_at, updated_at, sort_order
        ) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $taskId,
        $groupId,
        $title,
        $description,
        $status,
        $assignee,
        trim((string) ($input['due_date'] ?? '')),
        $category['category_id'],
        $category['category_label'],
        strtolower(trim($createdBy)),
        $now,
        $now,
        $sortOrder,
    ]);

    $checklistInput = is_array($input['checklist'] ?? null) ? $input['checklist'] : [];
    foreach ($checklistInput as $index => $label) {
        $label = trim((string) $label);
        if ($label === '') {
            continue;
        }

        $pdo->prepare(
            'INSERT INTO checklist_items(task_id, label, done, sort_order) VALUES(?, ?, 0, ?)'
        )->execute([$taskId, $label, (int) $index]);
    }

    ponos_db_insert_system_message($taskId, LOC('ponos.system.task_created', $title));

    $task = ponos_db_fetch_task_array($taskId, true);
    if ($task === null) {
        return null;
    }

    ponos_db_store_uploaded_files($task, null, strtolower(trim($createdBy)), $uploadedFiles);
    $pdo->prepare('UPDATE tasks SET updated_at = ? WHERE id = ?')->execute([gmdate('c'), $taskId]);

    $task = ponos_get_task($groupId, $taskId);
    if ($task !== null) {
        $groupName = ponos_db_group_name($groupId);
        $assignee = strtolower(trim((string) ($task['assignee_email'] ?? '')));
        if ($assignee !== '' && $assignee !== strtolower(trim($createdBy))) {
            $task['group_id'] = $groupId;
            ponos_notify_task_assigned($task, $createdBy, $groupName);
        }
    }

    return $task;
}

function ponos_update_task(
    string $groupId,
    string $taskId,
    string $editorEmail,
    array $input,
    array $uploadedFiles = []
): ?array {
    $task = ponos_db_fetch_task_array($taskId, true);
    if ($task === null) {
        return null;
    }

    $location = ponos_find_task_location($taskId);
    if ($location === null || $location['group_id'] !== $groupId) {
        return null;
    }

    $existing = ponos_normalize_task_row($task);
    $changes = [];
    $assigneeChanged = false;
    $checklistChanged = false;
    $pdo = ponos_db();

    if (array_key_exists('title', $input)) {
        $title = trim((string) $input['title']);
        if ($title !== '' && $title !== $existing['title']) {
            $task['title'] = $title;
            $changes[] = LOC('ponos.system.changed_title', $existing['title'], $title);
        }
    }

    if (array_key_exists('description', $input)) {
        $description = trim((string) $input['description']);
        if ($description !== '' && $description !== $existing['description']) {
            $task['description'] = $description;
            $changes[] = LOC('ponos.system.changed_description');
        }
    }

    if (array_key_exists('assignee_email', $input)) {
        $assignee = ponos_normalize_assignee_for_group($groupId, (string) $input['assignee_email']);
        if ($assignee !== $existing['assignee_email']) {
            $task['assignee_email'] = $assignee;
            $assigneeChanged = true;
            $changes[] = LOC('ponos.system.changed_assignee', $existing['assignee_email'] ?: '-', $assignee ?: '-');
        }
    }

    if (array_key_exists('due_date', $input)) {
        $dueDate = trim((string) $input['due_date']);
        if ($dueDate !== $existing['due_date']) {
            $task['due_date'] = $dueDate;
            $changes[] = LOC(
                'ponos.system.changed_due_date',
                $existing['due_date'] ? ponos_format_display_date($existing['due_date']) : '-',
                $dueDate ? ponos_format_display_date($dueDate) : '-'
            );
        }
    }

    $categoryId = (string) ($task['category_id'] ?? $existing['category_id']);
    $categoryLabel = (string) ($task['category_label'] ?? $existing['category_label']);
    if (array_key_exists('category_id', $input)) {
        $resolved = ponos_resolve_task_category($groupId, trim((string) $input['category_id']));
        if ($resolved['category_id'] !== $existing['category_id'] || $resolved['category_label'] !== $existing['category_label']) {
            $categoryId = $resolved['category_id'];
            $categoryLabel = $resolved['category_label'];
            $task['category_id'] = $categoryId;
            $task['category_label'] = $categoryLabel;
            $changes[] = LOC('ponos.system.changed_category', $existing['category_label'] ?: '-', $categoryLabel ?: '-');
        }
    }

    if (array_key_exists('checklist', $input) && is_array($input['checklist'])) {
        $labels = array_values(array_filter(
            array_map(static fn($label): string => trim((string) $label), $input['checklist']),
            static fn(string $label): bool => $label !== ''
        ));
        $existingLabels = array_map(static fn(array $item): string => (string) ($item['label'] ?? ''), $existing['checklist']);
        if ($labels !== $existingLabels) {
            $pdo->prepare('DELETE FROM checklist_items WHERE task_id = ?')->execute([$taskId]);
            foreach ($labels as $sortOrder => $label) {
                $pdo->prepare(
                    'INSERT INTO checklist_items(task_id, label, done, sort_order) VALUES(?, ?, 0, ?)'
                )->execute([$taskId, $label, (int) $sortOrder]);
            }
            $changes[] = LOC('ponos.system.changed_checklist');
            $checklistChanged = true;
        }
    }

    ponos_db_store_uploaded_files($task, null, strtolower(trim($editorEmail)), $uploadedFiles);

    if ($changes !== []) {
        ponos_db_insert_system_message($taskId, LOC('ponos.system.task_updated') . ' ' . implode('; ', $changes));
    }

    $now = gmdate('c');
    $pdo->prepare(
        'UPDATE tasks SET title = ?, description = ?, assignee_email = ?, due_date = ?,
         category_id = ?, category_label = ?, updated_at = ? WHERE id = ?'
    )->execute([
        (string) $task['title'],
        (string) $task['description'],
        (string) $task['assignee_email'],
        (string) $task['due_date'],
        $categoryId !== '' ? $categoryId : null,
        $categoryLabel,
        $now,
        $taskId,
    ]);

    $updatedTask = ponos_get_task($groupId, $taskId);
    if ($updatedTask !== null) {
        $groupName = ponos_db_group_name($groupId);
        $updatedTask['group_id'] = $groupId;
        $editor = strtolower(trim($editorEmail));
        if ($assigneeChanged) {
            ponos_notify_task_assigned($updatedTask, $editor, $groupName);
        }
        if ($checklistChanged) {
            ponos_notify_checklist_changed($updatedTask, $editor, $groupName);
        }
    }

    return $updatedTask;
}

function ponos_update_task_status(string $groupId, string $taskId, string $status, string $actorEmail = ''): ?array
{
    if (!in_array($status, ponos_all_statuses(), true)) {
        return null;
    }

    $task = ponos_db_fetch_task_array($taskId, false);
    if ($task === null) {
        return null;
    }

    $location = ponos_find_task_location($taskId);
    if ($location === null || $location['group_id'] !== $groupId) {
        return null;
    }

    $existingStatus = (string) ($task['status'] ?? PONOS_STATUS_TODO);
    if ($existingStatus === $status) {
        return ponos_get_task($groupId, $taskId);
    }

    $sortOrder = ponos_db_next_task_sort_order($groupId, $status);
    $now = gmdate('c');
    $doneAt = ponos_done_at_for_status($status);

    ponos_db()->prepare('UPDATE tasks SET status = ?, sort_order = ?, updated_at = ?, done_at = ? WHERE id = ?')
        ->execute([$status, $sortOrder, $now, $doneAt, $taskId]);

    ponos_db_insert_system_message(
        $taskId,
        LOC('ponos.system.changed_status', ponos_status_label($existingStatus), ponos_status_label($status))
    );

    $updatedTask = ponos_get_task($groupId, $taskId);
    if ($updatedTask !== null) {
        $groupName = ponos_db_group_name($groupId);
        $updatedTask['group_id'] = $groupId;
        ponos_notify_task_status_changed($updatedTask, strtolower(trim($actorEmail)), $existingStatus, $status, $groupName);
    }

    return $updatedTask;
}

function ponos_toggle_checklist_item(
    string $groupId,
    string $taskId,
    int $itemId,
    bool $done,
    string $actorEmail = ''
): ?array {
    $location = ponos_find_task_location($taskId);
    if ($location === null || $location['group_id'] !== $groupId) {
        return null;
    }

    $stmt = ponos_db()->prepare('UPDATE checklist_items SET done = ? WHERE id = ? AND task_id = ?');
    $stmt->execute([$done ? 1 : 0, $itemId, $taskId]);
    if ($stmt->rowCount() === 0) {
        return null;
    }

    ponos_db()->prepare('UPDATE tasks SET updated_at = ? WHERE id = ?')->execute([gmdate('c'), $taskId]);

    $updatedTask = ponos_get_task($groupId, $taskId);
    if ($updatedTask !== null) {
        $groupName = ponos_db_group_name($groupId);
        $updatedTask['group_id'] = $groupId;
        ponos_notify_checklist_changed($updatedTask, strtolower(trim($actorEmail)), $groupName);
    }

    return $updatedTask;
}

function ponos_add_task_message(
    string $groupId,
    string $taskId,
    string $email,
    string $text,
    array $uploadedFiles = []
): ?array {
    $location = ponos_find_task_location($taskId);
    if ($location === null || $location['group_id'] !== $groupId) {
        return null;
    }

    $email = strtolower(trim($email));
    $text = ponos_trim_message_text($text);
    if ($email === '' || $text === '') {
        return null;
    }

    $createdAt = gmdate('c');
    $pdo = ponos_db();
    $pdo->prepare('INSERT INTO messages(task_id, email, text, kind, created_at) VALUES(?, ?, ?, ?, ?)')
        ->execute([$taskId, $email, $text, 'user', $createdAt]);
    $messageId = (int) $pdo->lastInsertId();

    $message = [
        'id' => $messageId,
        'email' => $email,
        'text' => $text,
        'kind' => 'user',
        'created_at' => $createdAt,
    ];

    $task = ponos_db_fetch_task_array($taskId, true);
    if ($task === null) {
        return null;
    }

    ponos_db_store_uploaded_files($task, $messageId, $email, $uploadedFiles);
    $pdo->prepare('UPDATE tasks SET updated_at = ? WHERE id = ?')->execute([gmdate('c'), $taskId]);

    $task = ponos_db_fetch_task_array($taskId, true);
    $normalized = ponos_normalize_message_row($message, $task ?? ['attachments' => []]);

    $fullTask = ponos_get_task($groupId, $taskId);
    if ($fullTask !== null) {
        $groupName = ponos_db_group_name($groupId);
        $fullTask['group_id'] = $groupId;
        ponos_notify_task_message($fullTask, $email, $text, $groupName);
    }

    return $normalized;
}

function ponos_db_store_uploaded_files(
    array $task,
    ?int $messageId,
    string $uploadedBy,
    array $uploadedFiles
): void {
    if ($uploadedFiles === []) {
        return;
    }

    $taskId = (string) ($task['id'] ?? '');
    if ($taskId === '') {
        return;
    }

    $pdo = ponos_db();
    foreach ($uploadedFiles as $file) {
        if (!is_array($file)) {
            continue;
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            continue;
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $originalName = trim((string) ($file['name'] ?? ''));
        $size = (int) ($file['size'] ?? 0);
        if ($tmpName === '' || $originalName === '' || !is_file($tmpName)) {
            continue;
        }

        if ($size <= 0 || $size > PONOS_ATTACHMENT_MAX_BYTES) {
            continue;
        }

        $storedName = ponos_new_task_id() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($originalName));
        $targetPath = ponos_attachments_dir() . DIRECTORY_SEPARATOR . $storedName;
        if (!@copy($tmpName, $targetPath) && !(@is_uploaded_file($tmpName) && move_uploaded_file($tmpName, $targetPath))) {
            continue;
        }

        $mime = (string) ($file['type'] ?? '');
        if ($mime === '' && function_exists('mime_content_type')) {
            $mime = (string) mime_content_type($targetPath);
        }

        $pdo->prepare(
            'INSERT INTO attachments(
                task_id, message_id, filename, stored_name, mime, size_bytes, uploaded_by, created_at
            ) VALUES(?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $taskId,
            $messageId,
            $originalName,
            $storedName,
            $mime,
            $size,
            strtolower(trim($uploadedBy)),
            gmdate('c'),
        ]);
    }
}

function ponos_find_attachment(string $groupId, int $attachmentId): ?array
{
    $stmt = ponos_db()->prepare(
        'SELECT a.id, a.task_id, a.filename, a.stored_name, a.mime, t.group_id
         FROM attachments a
         INNER JOIN tasks t ON t.id = a.task_id
         WHERE a.id = ? AND t.group_id = ?'
    );
    $stmt->execute([$attachmentId, $groupId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }

    $storedName = basename((string) ($row['stored_name'] ?? ''));
    $path = ponos_attachments_dir() . DIRECTORY_SEPARATOR . $storedName;
    if (!is_file($path)) {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'task_id' => (string) $row['task_id'],
        'filename' => (string) $row['filename'],
        'mime' => (string) ($row['mime'] ?? 'application/octet-stream'),
        'path' => $path,
    ];
}
