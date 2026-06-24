<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/web/ponos_storage.php';

ponos_test('ponos_create_task requires title and description', function (): void {
    $company = 'Storage Test Co';
    $project = 'PRJTEST01';
    $dbPath = ponos_project_store_path($company, $project);
    if (is_file($dbPath)) {
        unlink($dbPath);
    }

    $missing = ponos_create_task($company, $project, 'tester@kvt.nl', ['title' => 'Only title']);
    assert_eq(null, $missing);

    $task = ponos_create_task($company, $project, 'tester@kvt.nl', [
        'title' => 'Eerste taak',
        'description' => 'Beschrijving',
        'category' => PONOS_TASK_CATEGORIES[0],
        'checklist' => ['Stap 1', 'Stap 2'],
    ]);
    assert_true(is_array($task));
    assert_eq('Eerste taak', $task['title']);
    assert_eq(2, $task['checklist_total']);
    assert_eq(PONOS_STATUS_TODO, $task['status']);

    if (is_file($dbPath)) {
        unlink($dbPath);
    }
});

ponos_test('ponos_update_task_status moves task and logs system message', function (): void {
    $company = 'Storage Test Co';
    $project = 'PRJTEST02';
    $dbPath = ponos_project_store_path($company, $project);
    if (is_file($dbPath)) {
        unlink($dbPath);
    }

    $created = ponos_create_task($company, $project, 'creator@kvt.nl', [
        'title' => 'Status taak',
        'description' => 'Omschrijving',
    ]);
    assert_true(is_array($created));

    $updated = ponos_update_task_status($company, $project, $created['id'], PONOS_STATUS_IN_PROGRESS);
    assert_eq(PONOS_STATUS_IN_PROGRESS, $updated['status']);

    $full = ponos_get_task($company, $project, $created['id']);
    assert_true(is_array($full));
    assert_true(count($full['messages']) >= 2);

    if (is_file($dbPath)) {
        unlink($dbPath);
    }
});

ponos_test('ponos_toggle_checklist_item updates progress counters', function (): void {
    $company = 'Storage Test Co';
    $project = 'PRJTEST03';
    $dbPath = ponos_project_store_path($company, $project);
    if (is_file($dbPath)) {
        unlink($dbPath);
    }

    $created = ponos_create_task($company, $project, 'creator@kvt.nl', [
        'title' => 'Checklist taak',
        'description' => 'Omschrijving',
        'checklist' => ['A', 'B'],
    ]);

    $itemId = (int) ($created['checklist'][0]['id'] ?? 0);
    assert_true($itemId > 0);

    $toggled = ponos_toggle_checklist_item($company, $project, $created['id'], $itemId, true);
    assert_eq(1, $toggled['checklist_done']);
    assert_true($toggled['checklist'][0]['done']);

    if (is_file($dbPath)) {
        unlink($dbPath);
    }
});

ponos_test('ponos_add_task_message stores user message', function (): void {
    $company = 'Storage Test Co';
    $project = 'PRJTEST04';
    $dbPath = ponos_project_store_path($company, $project);
    if (is_file($dbPath)) {
        unlink($dbPath);
    }

    $created = ponos_create_task($company, $project, 'creator@kvt.nl', [
        'title' => 'Chat taak',
        'description' => 'Omschrijving',
    ]);

    $message = ponos_add_task_message($company, $project, $created['id'], 'reviewer@kvt.nl', 'Hallo team');
    assert_true(is_array($message));
    assert_eq('reviewer@kvt.nl', $message['email']);

    if (is_file($dbPath)) {
        unlink($dbPath);
    }
});

ponos_test('ponos_update_task writes audit system message', function (): void {
    $company = 'Storage Test Co';
    $project = 'PRJTEST05';
    $dbPath = ponos_project_store_path($company, $project);
    if (is_file($dbPath)) {
        unlink($dbPath);
    }

    $created = ponos_create_task($company, $project, 'creator@kvt.nl', [
        'title' => 'Oud',
        'description' => 'Omschrijving',
    ]);

    $updated = ponos_update_task($company, $project, $created['id'], 'editor@kvt.nl', [
        'title' => 'Nieuw',
    ]);
    assert_eq('Nieuw', $updated['title']);

    $full = ponos_get_task($company, $project, $created['id']);
    $hasAudit = false;
    foreach ($full['messages'] as $message) {
        if (($message['kind'] ?? '') === 'system' && str_contains((string) ($message['text'] ?? ''), 'Nieuw')) {
            $hasAudit = true;
            break;
        }
    }
    assert_true($hasAudit);

    if (is_file($dbPath)) {
        unlink($dbPath);
    }
});
