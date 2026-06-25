<?php

/**
 * Includes/requires
 */
require_once __DIR__ . '/ponos_data.php';
require_once __DIR__ . '/ponos_db.php';

/**
 * Functies
 */

function ponos_group_has_open_access(string $groupId): bool
{
    if (trim($groupId) === PONOS_GROUP_MY_TASKS) {
        return true;
    }

    $stmt = ponos_db()->prepare('SELECT open_access FROM groups WHERE id = ?');
    $stmt->execute([$groupId]);
    $value = $stmt->fetchColumn();
    if ($value === false) {
        return false;
    }

    return !empty($value);
}

function ponos_list_group_member_emails(string $groupId): array
{
    $stmt = ponos_db()->prepare(
        'SELECT user_email FROM group_members WHERE group_id = ? ORDER BY user_email ASC'
    );
    $stmt->execute([$groupId]);

    $members = [];
    foreach ($stmt->fetchAll() as $row) {
        $email = strtolower(trim((string) ($row['user_email'] ?? '')));
        if ($email !== '') {
            $members[] = $email;
        }
    }

    return $members;
}

function ponos_user_has_group_access(string $userEmail, string $groupId, ?bool $isAdmin = null): bool
{
    if (trim($groupId) === PONOS_GROUP_MY_TASKS) {
        return true;
    }

    if ($isAdmin === null) {
        $isAdmin = ponos_current_user_is_admin();
    }

    if ($isAdmin) {
        return true;
    }

    $userEmail = strtolower(trim($userEmail));
    if ($userEmail === '') {
        return false;
    }

    if (ponos_group_has_open_access($groupId)) {
        return true;
    }

    $stmt = ponos_db()->prepare(
        'SELECT 1 FROM group_members WHERE group_id = ? AND LOWER(user_email) = ?'
    );
    $stmt->execute([$groupId, $userEmail]);

    return (bool) $stmt->fetchColumn();
}

function ponos_filter_groups_by_user_access(array $groups, string $userEmail, ?bool $isAdmin = null): array
{
    if ($isAdmin === null) {
        $isAdmin = ponos_current_user_is_admin();
    }

    $filtered = [];
    foreach ($groups as $group) {
        if (!empty($group['virtual'])) {
            $filtered[] = $group;
            continue;
        }

        if (ponos_user_has_group_access($userEmail, (string) ($group['id'] ?? ''), $isAdmin)) {
            $filtered[] = $group;
        }
    }

    return $filtered;
}

function ponos_normalize_assignee_for_group(string $groupId, string $assigneeEmail): string
{
    $assigneeEmail = strtolower(trim($assigneeEmail));
    if ($assigneeEmail === '') {
        return '';
    }

    if (ponos_user_has_group_access($assigneeEmail, $groupId, false)) {
        return $assigneeEmail;
    }

    return '';
}

function ponos_get_group_access_settings(string $groupId): ?array
{
    if (trim($groupId) === PONOS_GROUP_MY_TASKS) {
        return null;
    }

    $stmt = ponos_db()->prepare('SELECT open_access FROM groups WHERE id = ?');
    $stmt->execute([$groupId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }

    return [
        'open_access' => !empty($row['open_access']),
        'members' => ponos_list_group_member_emails($groupId),
    ];
}

function ponos_set_group_open_access(string $groupId, bool $openAccess): bool
{
    $stmt = ponos_db()->prepare('UPDATE groups SET open_access = ? WHERE id = ?');
    $stmt->execute([$openAccess ? 1 : 0, $groupId]);

    return $stmt->rowCount() > 0;
}

function ponos_add_group_member(string $groupId, string $userEmail): bool
{
    $userEmail = strtolower(trim($userEmail));
    if ($userEmail === '') {
        return false;
    }

    $pdo = ponos_db();
    $pdo->prepare(
        'INSERT OR IGNORE INTO group_members(group_id, user_email, created_at) VALUES(?, ?, ?)'
    )->execute([$groupId, $userEmail, gmdate('c')]);

    return true;
}

function ponos_remove_group_member(string $groupId, string $userEmail, string $actorEmail = ''): bool
{
    $userEmail = strtolower(trim($userEmail));
    if ($userEmail === '') {
        return false;
    }

    $actorEmail = strtolower(trim($actorEmail));
    if ($actorEmail !== '' && $userEmail === $actorEmail) {
        return false;
    }

    $stmt = ponos_db()->prepare('DELETE FROM group_members WHERE group_id = ? AND LOWER(user_email) = ?');
    $stmt->execute([$groupId, $userEmail]);

    return $stmt->rowCount() > 0;
}

function ponos_enrich_group_for_client(array $group, string $userEmail, ?bool $isAdmin = null): array
{
    if (!empty($group['virtual'])) {
        return $group;
    }

    $groupId = (string) ($group['id'] ?? '');
    $group['open_access'] = ponos_group_has_open_access($groupId);
    $group['member_emails'] = $group['open_access']
        ? []
        : ponos_list_group_member_emails($groupId);

    return $group;
}
