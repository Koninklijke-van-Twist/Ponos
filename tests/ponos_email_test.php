<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/web/localization.php';
require_once dirname(__DIR__) . '/web/ponos_email.php';
require_once dirname(__DIR__) . '/web/ponos_notify.php';

ponos_test('ponos_default_email_prefs are all enabled', function (): void {
    $prefs = ponos_default_email_prefs();
    foreach (ponos_email_pref_keys() as $key) {
        assert_true(!empty($prefs[$key]), $key);
    }
});

ponos_test('ponos_email_task_card_html includes title and link', function (): void {
    putenv('PONOS_BASE_URL=https://ponos.example/web/index.php');
    $html = ponos_email_task_card_html([
        'id' => 'task-1',
        'group_id' => 'group-1',
        'title' => 'Testtaak',
        'description' => "Regel 1\nRegel 2",
        'status' => PONOS_STATUS_TODO,
        'due_date' => '2026-06-24',
        'assignee_email' => 'user@kvt.nl',
        'group_name' => 'Sprint',
        'checklist_total' => 2,
        'checklist_done' => 1,
    ]);

    assert_true(str_contains($html, 'Testtaak'));
    assert_true(str_contains($html, 'href="https://ponos.example/web/index.php?'));
    assert_true(str_contains($html, 'task=task-1'));
    putenv('PONOS_BASE_URL');
});

ponos_test('ponos_notify_daily_reminder skips empty task list', function (): void {
    assert_eq(false, ponos_notify_daily_reminder('user@kvt.nl', []));
});

ponos_test('ponos_send_daily_due_reminders sends nothing without due tasks', function (): void {
    require_once dirname(__DIR__) . '/web/ponos_db.php';
    ponos_db_wipe_all();
    $result = ponos_send_daily_due_reminders();
    assert_eq(0, $result['recipients']);
    assert_eq(0, $result['sent']);
});
