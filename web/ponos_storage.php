<?php

/**
 * Includes/requires
 */
require_once __DIR__ . '/ponos_data.php';

/**
 * Constants
 */

const PONOS_MESSAGE_MAX_LENGTH = 8000;

const PONOS_TASK_TITLE_MAX_LENGTH = 200;

const PONOS_ATTACHMENT_MAX_BYTES = 10485760;

/**
 * Functies
 */

function ponos_storage_dir(): string
{
    $dir = ponos_data_dir() . DIRECTORY_SEPARATOR . 'projects';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    return $dir;
}

function ponos_attachments_dir(): string
{
    $dir = ponos_data_dir() . DIRECTORY_SEPARATOR . 'attachments';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    return $dir;
}

function ponos_project_store_path(string $company, string $projectNo): string
{
    $safeCompany = ponos_normalize_company_key($company);
    $safeProject = preg_replace('/[^a-z0-9._-]/i', '_', trim($projectNo)) ?? 'project';

    return ponos_storage_dir() . DIRECTORY_SEPARATOR . $safeCompany . '_' . $safeProject . '.json';
}

function ponos_empty_project_store(): array
{
    return [
        'tasks' => [],
        'next_checklist_id' => 1,
        'next_message_id' => 1,
        'next_attachment_id' => 1,
    ];
}

function ponos_load_project_store(string $company, string $projectNo): array
{
    $path = ponos_project_store_path($company, $projectNo);
    if (!is_file($path)) {
        return ponos_empty_project_store();
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return ponos_empty_project_store();
    }

    if (!is_array($decoded['tasks'] ?? null)) {
        $decoded['tasks'] = [];
    }

    $decoded['next_checklist_id'] = max(1, (int) ($decoded['next_checklist_id'] ?? 1));
    $decoded['next_message_id'] = max(1, (int) ($decoded['next_message_id'] ?? 1));
    $decoded['next_attachment_id'] = max(1, (int) ($decoded['next_attachment_id'] ?? 1));

    return $decoded;
}

function ponos_save_project_store(string $company, string $projectNo, array $store): void
{
    $path = ponos_project_store_path($company, $projectNo);
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    file_put_contents($path, json_encode($store, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function ponos_find_task_index(array $store, string $taskId): ?int
{
    foreach ($store['tasks'] as $index => $task) {
        if (!is_array($task)) {
            continue;
        }

        if ((string) ($task['id'] ?? '') === $taskId) {
            return (int) $index;
        }
    }

    return null;
}

function ponos_new_task_id(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);

    return substr($hex, 0, 8) . '-'
        . substr($hex, 8, 4) . '-'
        . substr($hex, 12, 4) . '-'
        . substr($hex, 16, 4) . '-'
        . substr($hex, 20, 12);
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

    $category = trim((string) ($row['category'] ?? ''));

    return [
        'id' => (string) ($row['id'] ?? ''),
        'title' => (string) ($row['title'] ?? ''),
        'description' => (string) ($row['description'] ?? ''),
        'status' => (string) ($row['status'] ?? PONOS_STATUS_TODO),
        'category' => $category,
        'assignee_email' => strtolower(trim((string) ($row['assignee_email'] ?? ''))),
        'due_date' => trim((string) ($row['due_date'] ?? '')),
        'created_by' => strtolower(trim((string) ($row['created_by'] ?? ''))),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'updated_at' => (string) ($row['updated_at'] ?? ''),
        'sort_order' => (int) ($row['sort_order'] ?? 0),
        'checklist' => $checklist,
        'checklist_total' => count($checklist),
        'checklist_done' => $checklistDone,
        'attachments' => ponos_task_attachments($row),
        'colors' => ponos_color_from_text($category),
    ];
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
        'attachments' => ponos_task_attachments($task, $messageId),
    ];
}

function ponos_task_with_messages(array $task): array
{
    $normalized = ponos_normalize_task_row($task);
    $messages = is_array($task['messages'] ?? null) ? $task['messages'] : [];
    $normalized['messages'] = array_map(
        static fn(array $message): array => ponos_normalize_message_row($message, $task),
        $messages
    );

    return $normalized;
}

function ponos_next_sort_order(array $store, string $status): int
{
    $max = -1;
    foreach ($store['tasks'] as $task) {
        if (!is_array($task)) {
            continue;
        }

        if ((string) ($task['status'] ?? '') !== $status) {
            continue;
        }

        $max = max($max, (int) ($task['sort_order'] ?? 0));
    }

    return $max + 1;
}

function ponos_append_system_message(array &$store, array &$task, string $text): void
{
    $text = ponos_trim_message_text($text);
    if ($text === '') {
        return;
    }

    if (!isset($task['messages']) || !is_array($task['messages'])) {
        $task['messages'] = [];
    }

    $task['messages'][] = [
        'id' => $store['next_message_id']++,
        'email' => 'system@ponos.local',
        'text' => $text,
        'kind' => 'system',
        'created_at' => gmdate('c'),
    ];
}

function ponos_list_tasks(string $company, string $projectNo): array
{
    $store = ponos_load_project_store($company, $projectNo);
    $tasks = [];
    foreach ($store['tasks'] as $task) {
        if (!is_array($task)) {
            continue;
        }

        $tasks[] = ponos_normalize_task_row($task);
    }

    usort($tasks, static function (array $left, array $right): int {
        $order = ($left['sort_order'] ?? 0) <=> ($right['sort_order'] ?? 0);
        if ($order !== 0) {
            return $order;
        }

        return strcmp((string) ($left['created_at'] ?? ''), (string) ($right['created_at'] ?? ''));
    });

    return $tasks;
}

function ponos_get_task(string $company, string $projectNo, string $taskId): ?array
{
    $store = ponos_load_project_store($company, $projectNo);
    $index = ponos_find_task_index($store, $taskId);
    if ($index === null) {
        return null;
    }

    $task = $store['tasks'][$index];
    if (!is_array($task)) {
        return null;
    }

    return ponos_task_with_messages($task);
}

function ponos_create_task(
    string $company,
    string $projectNo,
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

    $category = trim((string) ($input['category'] ?? PONOS_TASK_CATEGORIES[0]));
    if (!in_array($category, PONOS_TASK_CATEGORIES, true)) {
        $category = PONOS_TASK_CATEGORIES[0];
    }

    $status = trim((string) ($input['status'] ?? PONOS_STATUS_TODO));
    if (!in_array($status, ponos_all_statuses(), true)) {
        $status = PONOS_STATUS_TODO;
    }

    $store = ponos_load_project_store($company, $projectNo);
    $taskId = ponos_new_task_id();
    $now = gmdate('c');

    $checklist = [];
    $checklistInput = is_array($input['checklist'] ?? null) ? $input['checklist'] : [];
    foreach ($checklistInput as $index => $label) {
        $label = trim((string) $label);
        if ($label === '') {
            continue;
        }

        $checklist[] = [
            'id' => $store['next_checklist_id']++,
            'label' => $label,
            'done' => false,
            'sort_order' => (int) $index,
        ];
    }

    $task = [
        'id' => $taskId,
        'title' => $title,
        'description' => $description,
        'status' => $status,
        'category' => $category,
        'assignee_email' => strtolower(trim((string) ($input['assignee_email'] ?? ''))),
        'due_date' => trim((string) ($input['due_date'] ?? '')),
        'created_by' => strtolower(trim($createdBy)),
        'created_at' => $now,
        'updated_at' => $now,
        'sort_order' => ponos_next_sort_order($store, $status),
        'checklist' => $checklist,
        'messages' => [],
        'attachments' => [],
    ];

    ponos_append_system_message($store, $task, LOC('ponos.system.task_created', $title));
    ponos_store_uploaded_files($store, $task, null, strtolower(trim($createdBy)), $uploadedFiles);

    $store['tasks'][] = $task;
    ponos_save_project_store($company, $projectNo, $store);

    return ponos_get_task($company, $projectNo, $taskId);
}

function ponos_update_task(
    string $company,
    string $projectNo,
    string $taskId,
    string $editorEmail,
    array $input,
    array $uploadedFiles = []
): ?array {
    $store = ponos_load_project_store($company, $projectNo);
    $index = ponos_find_task_index($store, $taskId);
    if ($index === null) {
        return null;
    }

    $task = $store['tasks'][$index];
    if (!is_array($task)) {
        return null;
    }

    $existing = ponos_normalize_task_row($task);
    $changes = [];

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

    if (array_key_exists('category', $input)) {
        $category = trim((string) $input['category']);
        if ($category !== '' && in_array($category, PONOS_TASK_CATEGORIES, true) && $category !== $existing['category']) {
            $task['category'] = $category;
            $changes[] = LOC('ponos.system.changed_category', $existing['category'], $category);
        }
    }

    if (array_key_exists('assignee_email', $input)) {
        $assignee = strtolower(trim((string) $input['assignee_email']));
        if ($assignee !== $existing['assignee_email']) {
            $task['assignee_email'] = $assignee;
            $changes[] = LOC('ponos.system.changed_assignee', $existing['assignee_email'] ?: '-', $assignee ?: '-');
        }
    }

    if (array_key_exists('due_date', $input)) {
        $dueDate = trim((string) $input['due_date']);
        if ($dueDate !== $existing['due_date']) {
            $task['due_date'] = $dueDate;
            $changes[] = LOC('ponos.system.changed_due_date', $existing['due_date'] ?: '-', $dueDate ?: '-');
        }
    }

    if (array_key_exists('checklist', $input) && is_array($input['checklist'])) {
        $labels = array_values(array_filter(array_map(static fn($label): string => trim((string) $label), $input['checklist']), static fn(string $label): bool => $label !== ''));
        $existingLabels = array_map(static fn(array $item): string => (string) ($item['label'] ?? ''), $existing['checklist']);
        if ($labels !== $existingLabels) {
            $task['checklist'] = [];
            foreach ($labels as $sortOrder => $label) {
                $task['checklist'][] = [
                    'id' => $store['next_checklist_id']++,
                    'label' => $label,
                    'done' => false,
                    'sort_order' => (int) $sortOrder,
                ];
            }
            $changes[] = LOC('ponos.system.changed_checklist');
        }
    }

    ponos_store_uploaded_files($store, $task, null, strtolower(trim($editorEmail)), $uploadedFiles);

    if ($changes !== []) {
        ponos_append_system_message($store, $task, LOC('ponos.system.task_updated') . ' ' . implode('; ', $changes));
    }

    $task['updated_at'] = gmdate('c');
    $store['tasks'][$index] = $task;
    ponos_save_project_store($company, $projectNo, $store);

    return ponos_get_task($company, $projectNo, $taskId);
}

function ponos_update_task_status(string $company, string $projectNo, string $taskId, string $status): ?array
{
    if (!in_array($status, ponos_all_statuses(), true)) {
        return null;
    }

    $store = ponos_load_project_store($company, $projectNo);
    $index = ponos_find_task_index($store, $taskId);
    if ($index === null) {
        return null;
    }

    $task = $store['tasks'][$index];
    if (!is_array($task)) {
        return null;
    }

    $existingStatus = (string) ($task['status'] ?? PONOS_STATUS_TODO);
    if ($existingStatus === $status) {
        return ponos_get_task($company, $projectNo, $taskId);
    }

    $task['status'] = $status;
    $task['sort_order'] = ponos_next_sort_order($store, $status);
    $task['updated_at'] = gmdate('c');
    ponos_append_system_message(
        $store,
        $task,
        LOC('ponos.system.changed_status', ponos_status_label($existingStatus), ponos_status_label($status))
    );

    $store['tasks'][$index] = $task;
    ponos_save_project_store($company, $projectNo, $store);

    return ponos_get_task($company, $projectNo, $taskId);
}

function ponos_toggle_checklist_item(string $company, string $projectNo, string $taskId, int $itemId, bool $done): ?array
{
    $store = ponos_load_project_store($company, $projectNo);
    $index = ponos_find_task_index($store, $taskId);
    if ($index === null) {
        return null;
    }

    $task = $store['tasks'][$index];
    if (!is_array($task)) {
        return null;
    }

    $found = false;
    $checklist = is_array($task['checklist'] ?? null) ? $task['checklist'] : [];
    foreach ($checklist as $checkIndex => $item) {
        if (!is_array($item)) {
            continue;
        }

        if ((int) ($item['id'] ?? 0) !== $itemId) {
            continue;
        }

        $checklist[$checkIndex]['done'] = $done;
        $found = true;
        break;
    }

    if (!$found) {
        return null;
    }

    $task['checklist'] = $checklist;
    $task['updated_at'] = gmdate('c');
    $store['tasks'][$index] = $task;
    ponos_save_project_store($company, $projectNo, $store);

    return ponos_get_task($company, $projectNo, $taskId);
}

function ponos_add_task_message(
    string $company,
    string $projectNo,
    string $taskId,
    string $email,
    string $text,
    array $uploadedFiles = []
): ?array {
    $store = ponos_load_project_store($company, $projectNo);
    $index = ponos_find_task_index($store, $taskId);
    if ($index === null) {
        return null;
    }

    $email = strtolower(trim($email));
    $text = ponos_trim_message_text($text);
    if ($email === '' || $text === '') {
        return null;
    }

    $task = $store['tasks'][$index];
    if (!is_array($task)) {
        return null;
    }

    $messageId = $store['next_message_id']++;
    $createdAt = gmdate('c');
    $message = [
        'id' => $messageId,
        'email' => $email,
        'text' => $text,
        'kind' => 'user',
        'created_at' => $createdAt,
    ];

    if (!isset($task['messages']) || !is_array($task['messages'])) {
        $task['messages'] = [];
    }

    $task['messages'][] = $message;
    ponos_store_uploaded_files($store, $task, $messageId, $email, $uploadedFiles);
    $task['updated_at'] = gmdate('c');
    $store['tasks'][$index] = $task;
    ponos_save_project_store($company, $projectNo, $store);

    return ponos_normalize_message_row($message, $task);
}

function ponos_store_uploaded_files(
    array &$store,
    array &$task,
    ?int $messageId,
    string $uploadedBy,
    array $uploadedFiles
): void {
    if ($uploadedFiles === []) {
        return;
    }

    if (!isset($task['attachments']) || !is_array($task['attachments'])) {
        $task['attachments'] = [];
    }

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

        $task['attachments'][] = [
            'id' => $store['next_attachment_id']++,
            'task_id' => (string) ($task['id'] ?? ''),
            'message_id' => $messageId,
            'filename' => $originalName,
            'stored_name' => $storedName,
            'mime' => $mime,
            'size_bytes' => $size,
            'uploaded_by' => strtolower(trim($uploadedBy)),
            'created_at' => gmdate('c'),
        ];
    }
}

function ponos_find_attachment(string $company, string $projectNo, int $attachmentId): ?array
{
    $store = ponos_load_project_store($company, $projectNo);
    foreach ($store['tasks'] as $task) {
        if (!is_array($task)) {
            continue;
        }

        $attachments = is_array($task['attachments'] ?? null) ? $task['attachments'] : [];
        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            if ((int) ($attachment['id'] ?? 0) !== $attachmentId) {
                continue;
            }

            $storedName = basename((string) ($attachment['stored_name'] ?? ''));
            $path = ponos_attachments_dir() . DIRECTORY_SEPARATOR . $storedName;
            if (!is_file($path)) {
                return null;
            }

            return [
                'id' => (int) ($attachment['id'] ?? 0),
                'task_id' => (string) ($attachment['task_id'] ?? ''),
                'filename' => (string) ($attachment['filename'] ?? ''),
                'mime' => (string) ($attachment['mime'] ?? 'application/octet-stream'),
                'path' => $path,
            ];
        }
    }

    return null;
}
