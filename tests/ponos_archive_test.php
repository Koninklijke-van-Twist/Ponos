<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/web/localization.php';
require_once dirname(__DIR__) . '/web/ponos_storage.php';

ponos_test('ponos_task_is_archived hides old done tasks from board', function (): void {
    ponos_db_wipe_all();
    $groupId = 'archive-group-01';
    ponos_db()->prepare(
        'INSERT INTO groups(id, name, created_at, sort_order, can_create_tasks) VALUES(?, ?, ?, 0, 1)'
    )->execute([$groupId, 'Archive Group', gmdate('c')]);

    $task = ponos_create_task($groupId, 'tester@kvt.nl', [
        'title' => 'Oude taak',
        'description' => 'Beschrijving',
    ]);
    assert_true(is_array($task));

    $oldDoneAt = gmdate('c', time() - (8 * 24 * 3600));
    ponos_db()->prepare('UPDATE tasks SET status = ?, done_at = ? WHERE id = ?')
        ->execute([PONOS_STATUS_DONE, $oldDoneAt, $task['id']]);

    $visible = ponos_list_tasks($groupId);
    assert_eq(0, count($visible));

    $archived = ponos_list_archived_tasks($groupId, 'tester@kvt.nl', 1);
    assert_eq(1, $archived['total']);
    assert_eq('Oude taak', $archived['tasks'][0]['title']);
    assert_true(!empty($archived['tasks'][0]['is_archived']));
});

ponos_test('ponos_unarchive_task restores done task for one week', function (): void {
    ponos_db_wipe_all();
    $groupId = 'archive-group-02';
    ponos_db()->prepare(
        'INSERT INTO groups(id, name, created_at, sort_order, can_create_tasks) VALUES(?, ?, ?, 0, 1)'
    )->execute([$groupId, 'Archive Group', gmdate('c')]);

    $task = ponos_create_task($groupId, 'tester@kvt.nl', [
        'title' => 'Herstelde taak',
        'description' => 'Beschrijving',
    ]);
    assert_true(is_array($task));

    $oldDoneAt = gmdate('c', time() - (8 * 24 * 3600));
    ponos_db()->prepare('UPDATE tasks SET status = ?, done_at = ? WHERE id = ?')
        ->execute([PONOS_STATUS_DONE, $oldDoneAt, $task['id']]);

    $restored = ponos_unarchive_task($groupId, $task['id']);
    assert_true(is_array($restored));
    assert_eq(false, $restored['is_archived']);

    $visible = ponos_list_tasks($groupId);
    assert_eq(1, count($visible));
});

ponos_test('ponos_update_task_status resets done_at when leaving done', function (): void {
    ponos_db_wipe_all();
    $groupId = 'archive-group-03';
    ponos_db()->prepare(
        'INSERT INTO groups(id, name, created_at, sort_order, can_create_tasks) VALUES(?, ?, ?, 0, 1)'
    )->execute([$groupId, 'Archive Group', gmdate('c')]);

    $task = ponos_create_task($groupId, 'tester@kvt.nl', [
        'title' => 'Status taak',
        'description' => 'Beschrijving',
    ]);
    assert_true(is_array($task));

    $done = ponos_update_task_status($groupId, $task['id'], PONOS_STATUS_DONE, 'tester@kvt.nl');
    assert_true(is_array($done));
    assert_true(trim((string) ($done['done_at'] ?? '')) !== '');

    $todo = ponos_update_task_status($groupId, $task['id'], PONOS_STATUS_TODO, 'tester@kvt.nl');
    assert_true(is_array($todo));
    assert_eq('', trim((string) ($todo['done_at'] ?? '')));
});

ponos_test('ponos_category_display_label uses uncategorized fallback', function (): void {
    assert_eq(LOC('ponos.category.uncategorized'), ponos_category_display_label(''));
    assert_eq('Backend', ponos_category_display_label('Backend'));
});
