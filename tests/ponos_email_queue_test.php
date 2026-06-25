<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/web/localization.php';
require_once dirname(__DIR__) . '/web/ponos_storage.php';
require_once dirname(__DIR__) . '/web/ponos_email_queue.php';
require_once dirname(__DIR__) . '/web/ponos_notify.php';

ponos_test('ponos_notify_task_assigned queues email instead of sending immediately', function (): void {
    ponos_db_wipe_all();
    $group = ponos_create_group('Queue Group', 'creator@kvt.nl');
    assert_true(is_array($group));

    ponos_add_group_member($group['id'], 'assignee@kvt.nl');

    $task = ponos_create_task($group['id'], 'creator@kvt.nl', [
        'title' => 'Queued assign',
        'description' => 'Body',
        'assignee_email' => 'assignee@kvt.nl',
    ]);
    assert_true(is_array($task));

    $stmt = ponos_db()->prepare(
        'SELECT COUNT(*) FROM email_queue WHERE recipient_email = ? AND task_id = ? AND notification_type = ?'
    );
    $stmt->execute(['assignee@kvt.nl', $task['id'], 'assigned']);
    assert_eq(1, (int) $stmt->fetchColumn());
});

ponos_test('ponos_mark_task_seen cancels queued emails for viewed task', function (): void {
    ponos_db_wipe_all();
    $group = ponos_create_group('Seen Group', 'creator@kvt.nl');
    assert_true(is_array($group));
    ponos_add_group_member($group['id'], 'assignee@kvt.nl');

    $task = ponos_create_task($group['id'], 'creator@kvt.nl', [
        'title' => 'Seen task',
        'description' => 'Body',
        'assignee_email' => 'assignee@kvt.nl',
    ]);
    assert_true(is_array($task));

    ponos_mark_task_seen('assignee@kvt.nl', $task['id']);

    $stmt = ponos_db()->prepare('SELECT COUNT(*) FROM email_queue WHERE recipient_email = ? AND task_id = ?');
    $stmt->execute(['assignee@kvt.nl', $task['id']]);
    assert_eq(0, (int) $stmt->fetchColumn());
});

ponos_test('ponos_process_email_queue cancels read message notifications', function (): void {
    ponos_db_wipe_all();
    $group = ponos_create_group('Message Group', 'creator@kvt.nl');
    assert_true(is_array($group));
    ponos_add_group_member($group['id'], 'assignee@kvt.nl');

    $task = ponos_create_task($group['id'], 'creator@kvt.nl', [
        'title' => 'Message task',
        'description' => 'Body',
        'assignee_email' => 'assignee@kvt.nl',
    ]);
    assert_true(is_array($task));

    $message = ponos_add_task_message($group['id'], $task['id'], 'creator@kvt.nl', 'Hallo daar');
    assert_true(is_array($message));

    ponos_mark_task_messages_read('assignee@kvt.nl', $task['id']);
    ponos_mark_task_seen('assignee@kvt.nl', $task['id']);

    $result = ponos_process_email_queue();
    assert_eq(0, $result['sent']);
    assert_true($result['cancelled'] >= 0);

    $stmt = ponos_db()->prepare('SELECT COUNT(*) FROM email_queue WHERE task_id = ?');
    $stmt->execute([$task['id']]);
    assert_eq(0, (int) $stmt->fetchColumn());
});

ponos_test('ponos_queue_task_email upserts by recipient task and type', function (): void {
    ponos_db_wipe_all();
    $group = ponos_create_group('Upsert Group', 'creator@kvt.nl');
    assert_true(is_array($group));

    $task = ponos_create_task($group['id'], 'creator@kvt.nl', [
        'title' => 'Upsert task',
        'description' => 'Body',
        'assignee_email' => 'assignee@kvt.nl',
    ]);
    assert_true(is_array($task));

    ponos_queue_task_email(
        $task,
        'assignee@kvt.nl',
        'creator@kvt.nl',
        'assigned',
        'First subject',
        'First intro',
        'Upsert Group'
    );
    ponos_queue_task_email(
        $task,
        'assignee@kvt.nl',
        'creator@kvt.nl',
        'assigned',
        'Second subject',
        'Second intro',
        'Upsert Group'
    );

    $stmt = ponos_db()->prepare(
        'SELECT subject, intro FROM email_queue WHERE recipient_email = ? AND task_id = ? AND notification_type = ?'
    );
    $stmt->execute(['assignee@kvt.nl', $task['id'], 'assigned']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    assert_true(is_array($row));
    assert_eq('Second subject', $row['subject']);
    assert_eq('Second intro', $row['intro']);

    $countStmt = ponos_db()->query('SELECT COUNT(*) FROM email_queue');
    assert_eq(1, (int) $countStmt->fetchColumn());
});

ponos_test('ponos_run_nightly_jobs returns reminders and queue stats', function (): void {
    ponos_db_wipe_all();
    $result = ponos_run_nightly_jobs();
    assert_array_has_key('reminders', $result);
    assert_array_has_key('queue', $result);
    assert_array_has_key('sent', $result['queue']);
    assert_array_has_key('cancelled', $result['queue']);
});
