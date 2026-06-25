<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/web/localization.php';
require_once dirname(__DIR__) . '/web/ponos_storage.php';

ponos_test('ponos_create_task requires title and description', function (): void {
    ponos_db_wipe_all();
    $groupId = 'test-group-01';

    $missing = ponos_create_task($groupId, 'tester@kvt.nl', ['title' => 'Only title']);
    assert_eq(null, $missing);

    ponos_db()->prepare(
        'INSERT INTO groups(id, name, created_at, sort_order, can_create_tasks) VALUES(?, ?, ?, 0, 1)'
    )->execute([$groupId, 'Test Group', gmdate('c')]);

    $category = ponos_create_category($groupId, 'Urgent');
    assert_true(is_array($category));

    $task = ponos_create_task($groupId, 'tester@kvt.nl', [
        'title' => 'Eerste taak',
        'description' => 'Beschrijving',
        'category_id' => $category['id'],
    ]);
    assert_true(is_array($task));
    assert_eq('Eerste taak', $task['title']);
    assert_eq('Urgent', $task['category_label']);
    assert_eq(PONOS_STATUS_TODO, $task['status']);
});

ponos_test('ponos_update_task_status moves task and logs system message', function (): void {
    ponos_db_wipe_all();
    $groupId = 'test-group-02';
    ponos_db()->prepare(
        'INSERT INTO groups(id, name, created_at, sort_order, can_create_tasks) VALUES(?, ?, ?, 0, 1)'
    )->execute([$groupId, 'Test Group', gmdate('c')]);

    $created = ponos_create_task($groupId, 'creator@kvt.nl', [
        'title' => 'Status taak',
        'description' => 'Omschrijving',
    ]);
    assert_true(is_array($created));

    $updated = ponos_update_task_status($groupId, $created['id'], PONOS_STATUS_IN_PROGRESS);
    assert_eq(PONOS_STATUS_IN_PROGRESS, $updated['status']);

    $full = ponos_get_task($groupId, $created['id']);
    assert_true(is_array($full));
    assert_true(count($full['messages']) >= 2);
});

ponos_test('ponos_toggle_checklist_item updates progress counters', function (): void {
    ponos_db_wipe_all();
    $groupId = 'test-group-03';
    ponos_db()->prepare(
        'INSERT INTO groups(id, name, created_at, sort_order, can_create_tasks) VALUES(?, ?, ?, 0, 1)'
    )->execute([$groupId, 'Test Group', gmdate('c')]);

    $created = ponos_create_task($groupId, 'creator@kvt.nl', [
        'title' => 'Checklist taak',
        'description' => 'Omschrijving',
        'checklist' => ['A', 'B'],
    ]);

    $itemId = (int) ($created['checklist'][0]['id'] ?? 0);
    assert_true($itemId > 0);

    $toggled = ponos_toggle_checklist_item($groupId, $created['id'], $itemId, true);
    assert_eq(1, $toggled['checklist_done']);
    assert_true($toggled['checklist'][0]['done']);
});

ponos_test('ponos_add_task_message stores user message', function (): void {
    ponos_db_wipe_all();
    $groupId = 'test-group-04';
    ponos_db()->prepare(
        'INSERT INTO groups(id, name, created_at, sort_order, can_create_tasks) VALUES(?, ?, ?, 0, 1)'
    )->execute([$groupId, 'Test Group', gmdate('c')]);

    $created = ponos_create_task($groupId, 'creator@kvt.nl', [
        'title' => 'Chat taak',
        'description' => 'Omschrijving',
    ]);

    $message = ponos_add_task_message($groupId, $created['id'], 'reviewer@kvt.nl', 'Hallo team');
    assert_true(is_array($message));
    assert_eq('reviewer@kvt.nl', $message['email']);
});

ponos_test('ponos_update_task writes audit system message', function (): void {
    ponos_db_wipe_all();
    $groupId = 'test-group-05';
    ponos_db()->prepare(
        'INSERT INTO groups(id, name, created_at, sort_order, can_create_tasks) VALUES(?, ?, ?, 0, 1)'
    )->execute([$groupId, 'Test Group', gmdate('c')]);

    $created = ponos_create_task($groupId, 'creator@kvt.nl', [
        'title' => 'Oud',
        'description' => 'Omschrijving',
    ]);

    $updated = ponos_update_task($groupId, $created['id'], 'editor@kvt.nl', [
        'title' => 'Nieuw',
    ]);
    assert_eq('Nieuw', $updated['title']);

    $full = ponos_get_task($groupId, $created['id']);
    $hasAudit = false;
    foreach ($full['messages'] as $message) {
        if (($message['kind'] ?? '') === 'system' && str_contains((string) ($message['text'] ?? ''), 'Nieuw')) {
            $hasAudit = true;
            break;
        }
    }
    assert_true($hasAudit);
});

ponos_test('ponos_move_task relocates task between groups', function (): void {
    ponos_db_wipe_all();
    $fromGroup = 'test-group-from';
    $toGroup = 'test-group-to';
    $now = gmdate('c');
    $pdo = ponos_db();
    $pdo->prepare(
        'INSERT INTO groups(id, name, created_at, sort_order, can_create_tasks) VALUES(?, ?, ?, 0, 1)'
    )->execute([$fromGroup, 'From', $now]);
    $pdo->prepare(
        'INSERT INTO groups(id, name, created_at, sort_order, can_create_tasks) VALUES(?, ?, ?, 1, 1)'
    )->execute([$toGroup, 'To', $now]);

    $created = ponos_create_task($fromGroup, 'creator@kvt.nl', [
        'title' => 'Verplaatsbaar',
        'description' => 'Omschrijving',
    ]);

    $moved = ponos_move_task($fromGroup, $toGroup, $created['id'], 'editor@kvt.nl', 'Doelgroep');
    assert_true(is_array($moved));
    assert_eq('Verplaatsbaar', $moved['title']);

    assert_eq(null, ponos_get_task($fromGroup, $created['id']));
    assert_true(is_array(ponos_get_task($toGroup, $created['id'])));
});
