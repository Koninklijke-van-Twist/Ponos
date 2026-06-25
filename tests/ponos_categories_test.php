<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/web/localization.php';
require_once dirname(__DIR__) . '/web/ponos_categories.php';
require_once dirname(__DIR__) . '/web/ponos_storage.php';

ponos_test('ponos_create_category stores category per group', function (): void {
    ponos_db_wipe_all();
    $groupId = 'cat-group-01';
    ponos_db()->prepare(
        'INSERT INTO groups(id, name, created_at, sort_order, can_create_tasks) VALUES(?, ?, ?, 0, 1)'
    )->execute([$groupId, 'Cat Group', gmdate('c')]);

    $category = ponos_create_category($groupId, 'Backend');
    assert_true(is_array($category));
    assert_eq('Backend', $category['name']);
    assert_eq(1, count(ponos_list_categories($groupId)));
});

ponos_test('ponos_update_category renames tasks with linked category', function (): void {
    ponos_db_wipe_all();
    $groupId = 'cat-group-02';
    ponos_db()->prepare(
        'INSERT INTO groups(id, name, created_at, sort_order, can_create_tasks) VALUES(?, ?, ?, 0, 1)'
    )->execute([$groupId, 'Cat Group', gmdate('c')]);

    $category = ponos_create_category($groupId, 'Oud');
    assert_true(is_array($category));

    $task = ponos_create_task($groupId, 'tester@kvt.nl', [
        'title' => 'Taak',
        'description' => 'Beschrijving',
        'category_id' => $category['id'],
    ]);
    assert_true(is_array($task));
    assert_eq('Oud', $task['category_label']);

    $updated = ponos_update_category($groupId, $category['id'], 'Nieuw');
    assert_true(is_array($updated));
    assert_eq('Nieuw', $updated['name']);

    $reloaded = ponos_get_task($groupId, $task['id']);
    assert_true(is_array($reloaded));
    assert_eq('Nieuw', $reloaded['category_label']);
    assert_eq('Nieuw', ponos_task_color_key($reloaded));
});

ponos_test('ponos_delete_category keeps task label', function (): void {
    ponos_db_wipe_all();
    $groupId = 'cat-group-03';
    ponos_db()->prepare(
        'INSERT INTO groups(id, name, created_at, sort_order, can_create_tasks) VALUES(?, ?, ?, 0, 1)'
    )->execute([$groupId, 'Cat Group', gmdate('c')]);

    $category = ponos_create_category($groupId, 'Verwijderd');
    $task = ponos_create_task($groupId, 'tester@kvt.nl', [
        'title' => 'Taak',
        'description' => 'Beschrijving',
        'category_id' => $category['id'],
    ]);
    assert_true(is_array($task));

    assert_true(ponos_delete_category($groupId, $category['id']));
    assert_eq(0, count(ponos_list_categories($groupId)));

    $reloaded = ponos_get_task($groupId, $task['id']);
    assert_true(is_array($reloaded));
    assert_eq('', $reloaded['category_id']);
    assert_eq('Verwijderd', $reloaded['category_label']);
});
