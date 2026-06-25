<?php

/**
 * Includes/requires
 */
require_once __DIR__ . '/ponos_data.php';
require_once __DIR__ . '/ponos_email.php';
require_once __DIR__ . '/ponos_email_queue.php';

const PONOS_REMINDER_COOLDOWN_SECONDS = 3600;

/**
 * Functies — user prefs & notification dispatch (see ponos_email.php for HTML).
 */

function ponos_load_skip_task_reminder_confirm(string $email): bool
{
    if (!function_exists('loadUserPrefs')) {
        return false;
    }

    $stored = loadUserPrefs($email);

    return !empty($stored['ponos_skip_task_reminder_confirm']);
}

function ponos_save_skip_task_reminder_confirm(string $email, bool $skip): void
{
    if (!function_exists('saveUserPref')) {
        return;
    }

    saveUserPref($email, 'ponos_skip_task_reminder_confirm', $skip);
}

function ponos_task_can_remind(array $task): bool
{
    $assignee = strtolower(trim((string) ($task['assignee_email'] ?? '')));
    if ($assignee === '') {
        return false;
    }

    $last = trim((string) ($task['last_reminder_at'] ?? ''));
    if ($last === '') {
        return true;
    }

    $timestamp = strtotime($last);

    return $timestamp === false || (time() - $timestamp) >= PONOS_REMINDER_COOLDOWN_SECONDS;
}

function ponos_notify_manual_task_reminder(array $task, string $actorEmail, string $groupName): bool
{
    $assignee = strtolower(trim((string) ($task['assignee_email'] ?? '')));
    if ($assignee === '') {
        return false;
    }

    $title = (string) ($task['title'] ?? '');
    $actorEmail = strtolower(trim($actorEmail));
    $subject = LOC('ponos.email.subject.reminder', $title);
    $intro = LOC('ponos.email.intro.reminder', $actorEmail, $title, $groupName);

    return ponos_email_send_task_notice($task, $assignee, $subject, $intro, $groupName);
}

function ponos_email_pref_keys(): array
{
    return [
        'assigned',
        'status_changed',
        'message',
        'checklist',
        'daily_reminder',
    ];
}

function ponos_default_email_prefs(): array
{
    $defaults = [];
    foreach (ponos_email_pref_keys() as $key) {
        $defaults[$key] = true;
    }

    return $defaults;
}

function ponos_load_email_prefs(string $email): array
{
    if (!function_exists('loadUserPrefs')) {
        return ponos_default_email_prefs();
    }

    $stored = loadUserPrefs($email);
    $prefs = ponos_default_email_prefs();
    foreach (ponos_email_pref_keys() as $key) {
        $prefKey = 'ponos_email_' . $key;
        if (array_key_exists($prefKey, $stored)) {
            $prefs[$key] = !empty($stored[$prefKey]);
        }
    }

    return $prefs;
}

function ponos_save_email_prefs(string $email, array $input): array
{
    if (!function_exists('saveUserPref')) {
        return ponos_default_email_prefs();
    }

    $prefs = ponos_default_email_prefs();
    foreach (ponos_email_pref_keys() as $key) {
        $enabled = !empty($input[$key]);
        $prefs[$key] = $enabled;
        saveUserPref($email, 'ponos_email_' . $key, $enabled);
    }

    return $prefs;
}

function ponos_load_pinned_groups(string $email): array
{
    if (!function_exists('loadUserPrefs')) {
        return [];
    }

    $stored = loadUserPrefs($email);
    $pinned = $stored['ponos_pinned_groups'] ?? [];
    if (!is_array($pinned)) {
        return [];
    }

    $normalized = [];
    foreach ($pinned as $groupId) {
        $groupId = trim((string) $groupId);
        if ($groupId !== '' && $groupId !== PONOS_GROUP_MY_TASKS) {
            $normalized[] = $groupId;
        }
    }

    return array_values(array_unique($normalized));
}

function ponos_save_pinned_groups(string $email, array $groupIds): void
{
    if (!function_exists('saveUserPref')) {
        return;
    }

    $normalized = [];
    foreach ($groupIds as $groupId) {
        $groupId = trim((string) $groupId);
        if ($groupId !== '' && $groupId !== PONOS_GROUP_MY_TASKS) {
            $normalized[] = $groupId;
        }
    }

    saveUserPref($email, 'ponos_pinned_groups', array_values(array_unique($normalized)));
}

function ponos_toggle_pinned_group(string $email, string $groupId): bool
{
    if (trim($groupId) === PONOS_GROUP_MY_TASKS) {
        return false;
    }

    $pinned = ponos_load_pinned_groups($email);
    $index = array_search($groupId, $pinned, true);
    if ($index !== false) {
        array_splice($pinned, (int) $index, 1);
        ponos_save_pinned_groups($email, $pinned);

        return false;
    }

    $pinned[] = $groupId;
    ponos_save_pinned_groups($email, $pinned);

    return true;
}

function ponos_is_group_pinned(string $email, string $groupId): bool
{
    return in_array($groupId, ponos_load_pinned_groups($email), true);
}

function ponos_should_send_email(string $recipientEmail, string $actorEmail, string $prefKey): bool
{
    $recipientEmail = strtolower(trim($recipientEmail));
    $actorEmail = strtolower(trim($actorEmail));
    if ($recipientEmail === '') {
        return false;
    }

    if ($prefKey !== 'daily_reminder' && $recipientEmail === $actorEmail) {
        return false;
    }

    $prefs = ponos_load_email_prefs($recipientEmail);

    return !empty($prefs[$prefKey]);
}

function ponos_task_deep_link(string $groupId, string $taskId): string
{
    return ponos_email_task_link([
        'group_id' => $groupId,
        'id' => $taskId,
    ]);
}

function ponos_notify_task_assigned(array $task, string $actorEmail, string $groupName): void
{
    $assignee = strtolower(trim((string) ($task['assignee_email'] ?? '')));
    if ($assignee === '' || !ponos_should_send_email($assignee, $actorEmail, 'assigned')) {
        return;
    }

    $title = (string) ($task['title'] ?? '');
    $subject = LOC('ponos.email.subject.assigned', $title);
    $intro = LOC('ponos.email.intro.assigned', $title, $groupName);
    ponos_queue_task_email($task, $assignee, $actorEmail, 'assigned', $subject, $intro, $groupName);
}

function ponos_notify_task_status_changed(
    array $task,
    string $actorEmail,
    string $oldStatus,
    string $newStatus,
    string $groupName
): void {
    $assignee = strtolower(trim((string) ($task['assignee_email'] ?? '')));
    if ($assignee === '' || !ponos_should_send_email($assignee, $actorEmail, 'status_changed')) {
        return;
    }

    $title = (string) ($task['title'] ?? '');
    $subject = LOC('ponos.email.subject.status', $title);
    $intro = LOC(
        'ponos.email.intro.status',
        $title,
        ponos_status_label($oldStatus),
        ponos_status_label($newStatus),
        $groupName
    );
    ponos_queue_task_email(
        $task,
        $assignee,
        $actorEmail,
        'status_changed',
        $subject,
        $intro,
        $groupName,
        null,
        ['old_status' => $oldStatus, 'new_status' => $newStatus]
    );
}

function ponos_notify_task_message(
    array $task,
    string $actorEmail,
    string $messageText,
    string $groupName,
    ?int $messageId = null
): void {
    $assignee = strtolower(trim((string) ($task['assignee_email'] ?? '')));
    if ($assignee === '' || !ponos_should_send_email($assignee, $actorEmail, 'message')) {
        return;
    }

    $title = (string) ($task['title'] ?? '');
    $subject = LOC('ponos.email.subject.message', $title);
    $snippet = mb_strlen($messageText) > 240 ? mb_substr($messageText, 0, 237) . '...' : $messageText;
    $intro = LOC('ponos.email.intro.message', $title, $actorEmail, $snippet, $groupName);
    ponos_queue_task_email($task, $assignee, $actorEmail, 'message', $subject, $intro, $groupName, $messageId);
}

function ponos_notify_checklist_changed(array $task, string $actorEmail, string $groupName): void
{
    $assignee = strtolower(trim((string) ($task['assignee_email'] ?? '')));
    if ($assignee === '' || !ponos_should_send_email($assignee, $actorEmail, 'checklist')) {
        return;
    }

    $title = (string) ($task['title'] ?? '');
    $subject = LOC('ponos.email.subject.checklist', $title);
    $intro = LOC('ponos.email.intro.checklist', $title, $groupName);
    ponos_queue_task_email($task, $assignee, $actorEmail, 'checklist', $subject, $intro, $groupName);
}

function ponos_fetch_tasks_due_today(): array
{
    require_once __DIR__ . '/ponos_db.php';

    $today = (new DateTimeImmutable('today', new DateTimeZone('Europe/Amsterdam')))->format('Y-m-d');
    $stmt = ponos_db()->prepare(
        'SELECT t.id, t.group_id, t.title, t.description, t.status, t.due_date, t.assignee_email,
                t.category_label, g.name AS group_name,
                (SELECT COUNT(*) FROM checklist_items ci WHERE ci.task_id = t.id) AS checklist_total,
                (SELECT COUNT(*) FROM checklist_items ci WHERE ci.task_id = t.id AND ci.done = 1) AS checklist_done
         FROM tasks t
         INNER JOIN groups g ON g.id = t.group_id
         WHERE t.due_date = ? AND t.status != ? AND TRIM(t.assignee_email) != ""'
    );
    $stmt->execute([$today, PONOS_STATUS_DONE]);

    $byAssignee = [];
    foreach ($stmt->fetchAll() as $row) {
        $email = strtolower(trim((string) $row['assignee_email']));
        if ($email === '') {
            continue;
        }
        $byAssignee[$email][] = [
            'id' => (string) $row['id'],
            'group_id' => (string) $row['group_id'],
            'title' => (string) $row['title'],
            'description' => (string) $row['description'],
            'status' => (string) $row['status'],
            'due_date' => (string) $row['due_date'],
            'assignee_email' => $email,
            'category_label' => (string) $row['category_label'],
            'group_name' => (string) $row['group_name'],
            'checklist_total' => (int) $row['checklist_total'],
            'checklist_done' => (int) $row['checklist_done'],
        ];
    }

    return $byAssignee;
}

function ponos_notify_daily_reminder(string $recipientEmail, array $tasks): bool
{
    $recipientEmail = strtolower(trim($recipientEmail));
    if ($recipientEmail === '' || $tasks === []) {
        return false;
    }

    if (!ponos_should_send_email($recipientEmail, '', 'daily_reminder')) {
        return false;
    }

    $subject = LOC('ponos.email.subject.daily_reminder');
    $intro = LOC('ponos.email.intro.daily_reminder', count($tasks));
    $plain = $intro . "\n\n" . ponos_email_plain_tasks($tasks);
    $html = ponos_email_layout_html(
        $subject,
        ponos_email_h($intro),
        ponos_email_task_cards_html($tasks)
    );

    return ponos_email_send($recipientEmail, $subject, $plain, $html);
}

function ponos_send_daily_due_reminders(): array
{
    $byAssignee = ponos_fetch_tasks_due_today();
    $sent = 0;
    $skipped = 0;

    foreach ($byAssignee as $email => $tasks) {
        if ($tasks === []) {
            $skipped++;
            continue;
        }
        if (ponos_notify_daily_reminder($email, $tasks)) {
            $sent++;
        } else {
            $skipped++;
        }
    }

    return [
        'date' => (new DateTimeImmutable('today', new DateTimeZone('Europe/Amsterdam')))->format('Y-m-d'),
        'recipients' => count($byAssignee),
        'sent' => $sent,
        'skipped' => $skipped,
    ];
}

function ponos_run_nightly_jobs(): array
{
    return [
        'reminders' => ponos_send_daily_due_reminders(),
        'queue' => ponos_process_email_queue(),
    ];
}
