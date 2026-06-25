<?php

/**
 * Includes/requires
 */
require_once __DIR__ . '/ponos_data.php';
require_once __DIR__ . '/ponos_db.php';

/**
 * Functies
 */

function ponos_task_color_key(array $task): string
{
    return trim((string) ($task['category_label'] ?? ''));
}

function ponos_category_display_label(string $label): string
{
    return trim($label) !== '' ? trim($label) : LOC('ponos.category.uncategorized');
}

function ponos_normalize_category_row(array $row): array
{
    return [
        'id' => (string) ($row['id'] ?? ''),
        'group_id' => (string) ($row['group_id'] ?? ''),
        'name' => trim((string) ($row['name'] ?? '')),
        'sort_order' => (int) ($row['sort_order'] ?? 0),
        'created_at' => (string) ($row['created_at'] ?? ''),
    ];
}

function ponos_list_categories(string $groupId): array
{
    if (trim($groupId) === PONOS_GROUP_MY_TASKS) {
        return [];
    }

    $stmt = ponos_db()->prepare(
        'SELECT id, group_id, name, sort_order, created_at FROM categories
         WHERE group_id = ? ORDER BY sort_order ASC, name ASC'
    );
    $stmt->execute([$groupId]);

    $categories = [];
    foreach ($stmt->fetchAll() as $row) {
        $categories[] = ponos_normalize_category_row($row);
    }

    return $categories;
}

function ponos_find_category(string $groupId, string $categoryId): ?array
{
    $stmt = ponos_db()->prepare(
        'SELECT id, group_id, name, sort_order, created_at FROM categories WHERE id = ? AND group_id = ?'
    );
    $stmt->execute([$categoryId, $groupId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }

    return ponos_normalize_category_row($row);
}

function ponos_resolve_task_category(string $groupId, string $categoryId): array
{
    $categoryId = trim($categoryId);
    if ($categoryId === '') {
        return ['category_id' => '', 'category_label' => ''];
    }

    $category = ponos_find_category($groupId, $categoryId);
    if ($category === null) {
        return ['category_id' => '', 'category_label' => ''];
    }

    return [
        'category_id' => $category['id'],
        'category_label' => $category['name'],
    ];
}

function ponos_create_category(string $groupId, string $name): ?array
{
    if (trim($groupId) === PONOS_GROUP_MY_TASKS) {
        return null;
    }

    $name = trim($name);
    if ($name === '') {
        return null;
    }

    $pdo = ponos_db();
    $sortStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) FROM categories WHERE group_id = ?');
    $sortStmt->execute([$groupId]);
    $sortOrder = ((int) $sortStmt->fetchColumn()) + 1;

    $category = [
        'id' => ponos_new_id(),
        'group_id' => $groupId,
        'name' => $name,
        'sort_order' => $sortOrder,
        'created_at' => gmdate('c'),
    ];

    $pdo->prepare(
        'INSERT INTO categories(id, group_id, name, sort_order, created_at) VALUES(?, ?, ?, ?, ?)'
    )->execute([
        $category['id'],
        $category['group_id'],
        $category['name'],
        $category['sort_order'],
        $category['created_at'],
    ]);

    return ponos_normalize_category_row($category);
}

function ponos_update_category(string $groupId, string $categoryId, string $name): ?array
{
    $name = trim($name);
    if ($name === '') {
        return null;
    }

    $pdo = ponos_db();
    $stmt = $pdo->prepare('UPDATE categories SET name = ? WHERE id = ? AND group_id = ?');
    $stmt->execute([$name, $categoryId, $groupId]);
    if ($stmt->rowCount() === 0) {
        return null;
    }

    $pdo->prepare('UPDATE tasks SET category_label = ? WHERE category_id = ? AND group_id = ?')
        ->execute([$name, $categoryId, $groupId]);

    return ponos_find_category($groupId, $categoryId);
}

function ponos_delete_category(string $groupId, string $categoryId): bool
{
    $pdo = ponos_db();
    $pdo->prepare('UPDATE tasks SET category_id = NULL WHERE category_id = ? AND group_id = ?')
        ->execute([$categoryId, $groupId]);

    $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ? AND group_id = ?');
    $stmt->execute([$categoryId, $groupId]);

    return $stmt->rowCount() > 0;
}
