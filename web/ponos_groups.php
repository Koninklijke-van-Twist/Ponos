<?php

/**
 * Includes/requires
 */
require_once __DIR__ . '/ponos_data.php';
require_once __DIR__ . '/ponos_db.php';
require_once __DIR__ . '/ponos_access.php';
require_once __DIR__ . '/ponos_storage.php';
require_once __DIR__ . '/ponos_notify.php';
require_once __DIR__ . '/ponos_reads.php';

/**
 * Functies
 */

function ponos_groups_db_path(): string
{
    return ponos_db_path();
}

function ponos_normalize_group_row(array $row): array
{
    return [
        'id' => (string) ($row['id'] ?? ''),
        'name' => trim((string) ($row['name'] ?? '')),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'sort_order' => (int) ($row['sort_order'] ?? 0),
        'virtual' => !empty($row['virtual']),
        'can_create_tasks' => !array_key_exists('can_create_tasks', $row) || !empty($row['can_create_tasks']),
        'open_access' => !array_key_exists('open_access', $row) || !empty($row['open_access']),
    ];
}

function ponos_virtual_my_tasks_group(): array
{
    return [
        'id' => PONOS_GROUP_MY_TASKS,
        'name' => LOC('ponos.group.my_tasks'),
        'created_at' => '',
        'sort_order' => -1,
        'virtual' => true,
        'can_create_tasks' => false,
        'open_access' => true,
    ];
}

function ponos_is_my_tasks_group(string $groupId): bool
{
    return trim($groupId) === PONOS_GROUP_MY_TASKS;
}

function ponos_list_groups(bool $includeVirtual = true): array
{
    $stmt = ponos_db()->query('SELECT id, name, created_at, sort_order, can_create_tasks, open_access FROM groups ORDER BY sort_order, name');
    $groups = [];
    foreach ($stmt->fetchAll() as $row) {
        $groups[] = ponos_normalize_group_row([
            'id' => $row['id'],
            'name' => $row['name'],
            'created_at' => $row['created_at'],
            'sort_order' => $row['sort_order'],
            'virtual' => false,
            'can_create_tasks' => !empty($row['can_create_tasks']),
            'open_access' => !empty($row['open_access']),
        ]);
    }

    if ($includeVirtual) {
        array_unshift($groups, ponos_virtual_my_tasks_group());
    }

    return $groups;
}

function ponos_find_group(string $groupId): ?array
{
    if (ponos_is_my_tasks_group($groupId)) {
        return ponos_virtual_my_tasks_group();
    }

    $stmt = ponos_db()->prepare('SELECT id, name, created_at, sort_order, can_create_tasks, open_access FROM groups WHERE id = ?');
    $stmt->execute([$groupId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }

    return ponos_normalize_group_row([
        'id' => $row['id'],
        'name' => $row['name'],
        'created_at' => $row['created_at'],
        'sort_order' => $row['sort_order'],
        'virtual' => false,
        'can_create_tasks' => !empty($row['can_create_tasks']),
        'open_access' => !empty($row['open_access']),
    ]);
}

function ponos_group_task_count(string $groupId): int
{
    if (ponos_is_my_tasks_group($groupId)) {
        return 0;
    }

    $stmt = ponos_db()->prepare('SELECT COUNT(*) FROM tasks WHERE group_id = ?');
    $stmt->execute([$groupId]);

    return (int) $stmt->fetchColumn();
}

function ponos_create_group(string $name, string $createdBy): ?array
{
    $name = trim($name);
    if ($name === '') {
        return null;
    }

    $pdo = ponos_db();
    $sortStmt = $pdo->query('SELECT COALESCE(MAX(sort_order), -1) FROM groups');
    $sortOrder = ((int) $sortStmt->fetchColumn()) + 1;

    $group = [
        'id' => ponos_new_id(),
        'name' => $name,
        'created_at' => gmdate('c'),
        'sort_order' => $sortOrder,
        'virtual' => false,
        'can_create_tasks' => true,
        'open_access' => false,
    ];

    $pdo->prepare(
        'INSERT INTO groups(id, name, created_at, sort_order, can_create_tasks, open_access) VALUES(?, ?, ?, ?, 1, 0)'
    )->execute([
        $group['id'],
        $group['name'],
        $group['created_at'],
        $group['sort_order'],
    ]);

    $creatorEmail = strtolower(trim($createdBy));
    if ($creatorEmail !== '') {
        ponos_add_group_member($group['id'], $creatorEmail);
    }

    return ponos_normalize_group_row($group);
}

function ponos_update_group(string $groupId, string $name): ?array
{
    if (ponos_is_my_tasks_group($groupId)) {
        return null;
    }

    $name = trim($name);
    if ($name === '') {
        return null;
    }

    $pdo = ponos_db();
    $stmt = $pdo->prepare('UPDATE groups SET name = ? WHERE id = ?');
    $stmt->execute([$name, $groupId]);
    if ($stmt->rowCount() === 0) {
        return null;
    }

    return ponos_find_group($groupId);
}

function ponos_delete_group(string $groupId, bool $confirmDeleteTasks): array
{
    if (ponos_is_my_tasks_group($groupId)) {
        return ['ok' => false, 'error' => LOC('ponos.error.group_not_found')];
    }

    $taskCount = ponos_group_task_count($groupId);
    if ($taskCount > 0 && !$confirmDeleteTasks) {
        return [
            'ok' => false,
            'needs_confirm' => true,
            'task_count' => $taskCount,
            'message' => LOC('ponos.group.delete_confirm_message'),
        ];
    }

    ponos_delete_group_task_store($groupId);

    $stmt = ponos_db()->prepare('DELETE FROM groups WHERE id = ?');
    $stmt->execute([$groupId]);
    if ($stmt->rowCount() === 0) {
        return ['ok' => false, 'error' => LOC('ponos.error.group_not_found')];
    }

    return ['ok' => true, 'deleted_task_count' => $taskCount];
}

function ponos_list_tasks_for_view(string $groupId, string $userEmail, ?bool $isAdmin = null): array
{
    if ($isAdmin === null) {
        $isAdmin = ponos_current_user_is_admin();
    }

    if (!ponos_is_my_tasks_group($groupId) && !ponos_user_can_view_group($userEmail, $groupId, $isAdmin)) {
        return [];
    }

    if (ponos_is_my_tasks_group($groupId)) {
        return ponos_apply_task_permissions(
            ponos_enrich_tasks_with_unread(ponos_list_assigned_tasks($userEmail, $isAdmin), $userEmail),
            $userEmail
        );
    }

    $group = ponos_find_group($groupId);
    if ($group === null) {
        return [];
    }

    return ponos_apply_task_permissions(
        ponos_list_tasks_with_unread($groupId, $userEmail),
        $userEmail
    );
}

function ponos_list_assigned_tasks(string $userEmail, ?bool $isAdmin = null): array
{
    $userEmail = strtolower(trim($userEmail));
    if ($userEmail === '') {
        return [];
    }

    if ($isAdmin === null) {
        $isAdmin = ponos_current_user_is_admin();
    }

    $stmt = ponos_db()->prepare(
        'SELECT t.id, t.group_id, g.name AS group_name
         FROM tasks t
         INNER JOIN groups g ON g.id = t.group_id
         WHERE LOWER(t.assignee_email) = ?
         ORDER BY t.updated_at ASC'
    );
    $stmt->execute([$userEmail]);

    $tasks = [];
    foreach ($stmt->fetchAll() as $row) {
        $groupId = (string) $row['group_id'];
        if (!ponos_user_has_group_access($userEmail, $groupId, false)) {
            continue;
        }

        $task = ponos_get_task_for_client($groupId, (string) $row['id']);
        if ($task === null || !ponos_task_is_board_visible($task)) {
            continue;
        }

        $task['home_group_id'] = $groupId;
        $task['home_group_name'] = (string) $row['group_name'];
        $tasks[] = $task;
    }

    return array_reverse($tasks);
}

function ponos_groups_with_open_assigned_tasks(string $userEmail): array
{
    $userEmail = strtolower(trim($userEmail));
    if ($userEmail === '') {
        return [];
    }

    $stmt = ponos_db()->prepare(
        'SELECT DISTINCT group_id FROM tasks
         WHERE LOWER(assignee_email) = ? AND status != ?'
    );
    $stmt->execute([$userEmail, PONOS_STATUS_DONE]);

    $groupIds = [];
    foreach ($stmt->fetchAll() as $row) {
        $groupIds[] = (string) $row['group_id'];
    }

    return $groupIds;
}

function ponos_sort_groups_for_user(array $groups, string $userEmail): array
{
    $pinned = array_flip(ponos_load_pinned_groups($userEmail));
    $withAssigned = array_flip(ponos_groups_with_open_assigned_tasks($userEmail));

    $virtual = [];
    $real = [];
    foreach ($groups as $group) {
        if (!empty($group['virtual'])) {
            $virtual[] = $group;
            continue;
        }
        $real[] = $group;
    }

    usort($real, static function (array $left, array $right) use ($pinned, $withAssigned): int {
        $leftPinned = isset($pinned[$left['id']]);
        $rightPinned = isset($pinned[$right['id']]);
        if ($leftPinned !== $rightPinned) {
            return $rightPinned <=> $leftPinned;
        }

        $leftAssigned = isset($withAssigned[$left['id']]);
        $rightAssigned = isset($withAssigned[$right['id']]);
        if ($leftAssigned !== $rightAssigned) {
            return $rightAssigned <=> $leftAssigned;
        }

        return strcasecmp((string) $left['name'], (string) $right['name']);
    });

    return array_merge($virtual, $real);
}

function ponos_navigation_payload(string $userEmail, ?bool $isAdmin = null): array
{
    if ($isAdmin === null) {
        $isAdmin = ponos_current_user_is_admin();
    }

    $groups = ponos_filter_groups_by_user_access(
        ponos_sort_groups_for_user(ponos_list_groups(true), $userEmail),
        $userEmail,
        $isAdmin
    );
    $pinned = array_values(array_filter(
        ponos_load_pinned_groups($userEmail),
        static fn(string $groupId): bool => ponos_user_can_view_group($userEmail, $groupId, $isAdmin)
    ));
    $groupsPayload = [];
    foreach ($groups as $group) {
        $entry = ponos_enrich_group_for_client($group, $userEmail, $isAdmin);
        if ($group['virtual']) {
            $entry['task_count'] = count(ponos_list_assigned_tasks($userEmail, $isAdmin));
        } else {
            $entry['task_count'] = ponos_group_task_count($group['id']);
            $entry['pinned'] = in_array($group['id'], $pinned, true);
        }
        $groupsPayload[] = $entry;
    }

    return [
        'groups' => $groupsPayload,
        'pinned_groups' => $pinned,
        'email_prefs' => ponos_load_email_prefs($userEmail),
        'skip_task_reminder_confirm' => ponos_load_skip_task_reminder_confirm($userEmail),
    ];
}
