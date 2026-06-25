<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Includes/requires
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';
require_once __DIR__ . '/localization.php';
require_once __DIR__ . '/ponos_data.php';
require_once __DIR__ . '/ponos_storage.php';
require_once __DIR__ . '/ponos_groups.php';
require_once __DIR__ . '/ponos_categories.php';
require_once __DIR__ . '/ponos_access.php';
require_once __DIR__ . '/ponos_notify.php';
require_once __DIR__ . '/ponos_reads.php';
require_once __DIR__ . '/ponos_stats.php';
require_once __DIR__ . '/ponos_attachments.php';

/**
 * Functies
 */

function ponos_api_json(array $payload, int $status = 200): void
{
    if (function_exists('xdebug_disable')) {
        xdebug_disable();
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ponos_api_require_params(array $keys): array
{
    $source = array_merge($_GET, $_POST);
    $values = [];
    foreach ($keys as $key) {
        $value = trim((string) ($source[$key] ?? ''));
        if ($value === '') {
            ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.missing_param', $key)], 400);
        }
        $values[$key] = $value;
    }

    return $values;
}

function ponos_api_collect_uploaded_files(string $fieldName = 'attachments'): array
{
    if (isset($_FILES[$fieldName])) {
        return ponos_api_normalize_files_superglobal($_FILES[$fieldName]);
    }

    $bracketName = $fieldName . '[]';
    if (isset($_FILES[$bracketName])) {
        return ponos_api_normalize_files_superglobal($_FILES[$bracketName]);
    }

    return [];
}

function ponos_api_normalize_files_superglobal(array $files): array
{
    if (!is_array($files['name'] ?? null)) {
        return [$files];
    }

    $result = [];
    $count = count($files['name']);
    for ($index = 0; $index < $count; $index++) {
        $result[] = [
            'name' => $files['name'][$index] ?? '',
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0,
        ];
    }

    return $result;
}

function ponos_api_parse_checklist_from_request(): array
{
    $raw = (string) ($_POST['checklist'] ?? $_GET['checklist'] ?? '');
    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    return $decoded;
}

function ponos_api_task_group_id(string $viewGroupId, string $taskId): ?string
{
    if (!ponos_is_my_tasks_group($viewGroupId)) {
        return $viewGroupId;
    }

    $location = ponos_find_task_location($taskId);

    return $location['group_id'] ?? null;
}

function ponos_api_require_group_access(string $groupId, string $userEmail, bool $isAdmin): void
{
    if (ponos_is_my_tasks_group($groupId)) {
        return;
    }

    if (!ponos_user_can_view_group($userEmail, $groupId, $isAdmin)) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.group_not_found')], 404);
    }
}

function ponos_api_require_group_task_access(string $groupId, string $userEmail): void
{
    if (ponos_is_my_tasks_group($groupId)) {
        return;
    }

    if (!ponos_user_has_group_access($userEmail, $groupId, false)) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.group_task_access_denied')], 403);
    }
}

function ponos_api_task_for_client(array $task, string $userEmail): array
{
    $task = ponos_enrich_task_for_client($task);
    $task['can_edit'] = ponos_task_can_edit($task, $userEmail);

    return $task;
}

/**
 * Page load
 */

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));
$userEmail = ponos_current_user_email();
$isAdmin = ponos_current_user_is_admin();

if ($action === 'set_dev_admin') {
    if (!ponos_user_has_admin_role()) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.admin_required')], 403);
    }

    $enabled = in_array(strtolower(trim((string) ($_POST['admin'] ?? '0'))), ['1', 'true', 'yes', 'on'], true);
    ponos_save_admin_enabled($userEmail, $enabled);

    ponos_api_json(['ok' => true, 'is_admin' => ponos_current_user_is_admin()]);
}

if ($action === 'navigation') {
    $navigation = ponos_navigation_payload($userEmail, $isAdmin);

    ponos_api_json([
        'ok' => true,
        'groups' => $navigation['groups'],
        'statuses' => [
            PONOS_STATUS_TODO => LOC('ponos.status.todo'),
            PONOS_STATUS_IN_PROGRESS => LOC('ponos.status.in_progress'),
            PONOS_STATUS_DONE => LOC('ponos.status.done'),
        ],
        'user_email' => $userEmail,
        'is_admin' => $isAdmin,
        'prefs' => array_merge(
            ponos_load_user_navigation_prefs($userEmail),
            [
                'pinned_groups' => $navigation['pinned_groups'],
                'email_prefs' => $navigation['email_prefs'],
                'skip_task_reminder_confirm' => $navigation['skip_task_reminder_confirm'],
            ]
        ),
    ]);
}

if ($action === 'save_email_prefs') {
    $input = [];
    foreach (ponos_email_pref_keys() as $key) {
        $input[$key] = in_array(strtolower(trim((string) ($_POST[$key] ?? '0'))), ['1', 'true', 'yes', 'on'], true);
    }

    $prefs = ponos_save_email_prefs($userEmail, $input);
    ponos_api_json(['ok' => true, 'email_prefs' => $prefs]);
}

if ($action === 'toggle_group_pin') {
    $params = ponos_api_require_params(['group']);
    if (ponos_is_my_tasks_group($params['group'])) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.group_not_found')], 400);
    }
    ponos_api_require_group_access($params['group'], $userEmail, $isAdmin);

    $pinned = ponos_toggle_pinned_group($userEmail, $params['group']);
    $navigation = ponos_navigation_payload($userEmail, $isAdmin);
    ponos_api_json([
        'ok' => true,
        'pinned' => $pinned,
        'pinned_groups' => $navigation['pinned_groups'],
        'groups' => $navigation['groups'],
    ]);
}

if ($action === 'save_prefs') {
    $group = trim((string) ($_POST['group'] ?? ''));
    ponos_save_user_navigation_prefs($userEmail, $group);

    ponos_api_json(['ok' => true, 'prefs' => ponos_load_user_navigation_prefs($userEmail)]);
}

if ($action === 'create_group') {
    if (!$isAdmin) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.admin_required')], 403);
    }

    $params = ponos_api_require_params(['name']);
    $group = ponos_create_group($params['name'], $userEmail);
    if ($group === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.save_failed')], 400);
    }

    ponos_api_json(['ok' => true, 'group' => $group]);
}

if ($action === 'update_group') {
    if (!$isAdmin) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.admin_required')], 403);
    }

    $params = ponos_api_require_params(['group', 'name']);
    $group = ponos_update_group($params['group'], $params['name']);
    if ($group === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.group_not_found')], 404);
    }

    ponos_api_json(['ok' => true, 'group' => $group]);
}

if ($action === 'delete_group') {
    if (!$isAdmin) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.admin_required')], 403);
    }

    $params = ponos_api_require_params(['group']);
    $confirm = in_array(strtolower(trim((string) ($_POST['confirm'] ?? '0'))), ['1', 'true', 'yes', 'on'], true);
    $result = ponos_delete_group($params['group'], $confirm);
    if (empty($result['ok'])) {
        $status = !empty($result['needs_confirm']) ? 409 : 400;
        ponos_api_json($result, $status);
    }

    ponos_api_json($result);
}

if ($action === 'list_categories') {
    $params = ponos_api_require_params(['group']);
    ponos_api_require_group_access($params['group'], $userEmail, $isAdmin);
    if (ponos_is_my_tasks_group($params['group'])) {
        ponos_api_json(['ok' => true, 'categories' => []]);
    }

    ponos_api_json(['ok' => true, 'categories' => ponos_list_categories($params['group'])]);
}

if ($action === 'get_group_access') {
    if (!$isAdmin) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.admin_required')], 403);
    }

    $params = ponos_api_require_params(['group']);
    ponos_api_require_group_access($params['group'], $userEmail, $isAdmin);
    $settings = ponos_get_group_access_settings($params['group']);
    if ($settings === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.group_not_found')], 404);
    }

    ponos_api_json(['ok' => true, 'access' => $settings]);
}

if ($action === 'set_group_open_access') {
    if (!$isAdmin) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.admin_required')], 403);
    }

    $params = ponos_api_require_params(['group']);
    ponos_api_require_group_access($params['group'], $userEmail, $isAdmin);
    $openAccess = in_array(strtolower(trim((string) ($_POST['open_access'] ?? '0'))), ['1', 'true', 'yes', 'on'], true);
    if (!ponos_set_group_open_access($params['group'], $openAccess)) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.group_not_found')], 404);
    }

    $settings = ponos_get_group_access_settings($params['group']);
    ponos_api_json(['ok' => true, 'access' => $settings]);
}

if ($action === 'add_group_member') {
    if (!$isAdmin) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.admin_required')], 403);
    }

    $params = ponos_api_require_params(['group', 'email']);
    ponos_api_require_group_access($params['group'], $userEmail, $isAdmin);
    if (!ponos_add_group_member($params['group'], $params['email'])) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.save_failed')], 400);
    }

    ponos_api_json(['ok' => true, 'access' => ponos_get_group_access_settings($params['group'])]);
}

if ($action === 'remove_group_member') {
    if (!$isAdmin) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.admin_required')], 403);
    }

    $params = ponos_api_require_params(['group', 'email']);
    ponos_api_require_group_access($params['group'], $userEmail, $isAdmin);
    if (!ponos_remove_group_member($params['group'], $params['email'], $userEmail)) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.save_failed')], 400);
    }

    ponos_api_json(['ok' => true, 'access' => ponos_get_group_access_settings($params['group'])]);
}

if ($action === 'create_category') {
    if (!$isAdmin) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.admin_required')], 403);
    }

    $params = ponos_api_require_params(['group', 'name']);
    $category = ponos_create_category($params['group'], $params['name']);
    if ($category === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.save_failed')], 400);
    }

    ponos_api_json(['ok' => true, 'category' => $category]);
}

if ($action === 'update_category') {
    if (!$isAdmin) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.admin_required')], 403);
    }

    $params = ponos_api_require_params(['group', 'category', 'name']);
    $category = ponos_update_category($params['group'], $params['category'], $params['name']);
    if ($category === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.category_not_found')], 404);
    }

    ponos_api_json(['ok' => true, 'category' => $category]);
}

if ($action === 'delete_category') {
    if (!$isAdmin) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.admin_required')], 403);
    }

    $params = ponos_api_require_params(['group', 'category']);
    if (!ponos_delete_category($params['group'], $params['category'])) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.category_not_found')], 404);
    }

    ponos_api_json(['ok' => true]);
}

if ($action === 'list_tasks') {
    $params = ponos_api_require_params(['group']);
    ponos_api_require_group_access($params['group'], $userEmail, $isAdmin);
    $tasks = ponos_list_tasks_for_view($params['group'], $userEmail, $isAdmin);
    ponos_api_json([
        'ok' => true,
        'tasks' => $tasks,
        'board_revision' => ponos_group_board_revision_from_tasks($tasks),
    ]);
}

if ($action === 'board_revision') {
    $params = ponos_api_require_params(['group']);
    ponos_api_require_group_access($params['group'], $userEmail, $isAdmin);
    $tasks = ponos_list_tasks_for_view($params['group'], $userEmail, $isAdmin);
    ponos_api_json([
        'ok' => true,
        'revision' => ponos_group_board_revision_from_tasks($tasks),
        'task_count' => count($tasks),
    ]);
}

if ($action === 'group_stats') {
    $params = ponos_api_require_params(['group']);
    ponos_api_require_group_access($params['group'], $userEmail, $isAdmin);
    ponos_api_json(['ok' => true, 'stats' => ponos_group_stats($params['group'])]);
}

if ($action === 'get_task') {
    $params = ponos_api_require_params(['group', 'task']);
    if (ponos_is_my_tasks_group($params['group'])) {
        $task = ponos_get_task_anywhere($params['task']);
        if ($task === null) {
            ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.task_not_found')], 404);
        }

        $homeGroupId = (string) ($task['home_group_id'] ?? '');
        if (!ponos_user_can_view_group($userEmail, $homeGroupId, $isAdmin)) {
            ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.task_not_found')], 404);
        }

        $homeGroup = ponos_find_group($homeGroupId);
        if ($homeGroup !== null) {
            $task['home_group_name'] = (string) ($homeGroup['name'] ?? '');
        }
        ponos_mark_task_messages_read($userEmail, $params['task']);
        $task['unread_count'] = 0;
        ponos_api_json(['ok' => true, 'task' => ponos_api_task_for_client($task, $userEmail)]);
    }

    ponos_api_require_group_access($params['group'], $userEmail, $isAdmin);

    $task = ponos_get_task($params['group'], $params['task']);
    if ($task === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.task_not_found')], 404);
    }

    ponos_mark_task_messages_read($userEmail, $params['task']);
    $task['unread_count'] = 0;

    ponos_api_json(['ok' => true, 'task' => ponos_api_task_for_client($task, $userEmail)]);
}

if ($action === 'send_task_reminder') {
    $params = ponos_api_require_params(['group', 'task']);
    $taskGroupId = ponos_api_task_group_id($params['group'], $params['task']);
    if ($taskGroupId === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.task_not_found')], 404);
    }
    ponos_api_require_group_access($params['group'], $userEmail, $isAdmin);
    ponos_api_require_group_task_access($taskGroupId, $userEmail);

    $skipConfirm = in_array(strtolower(trim((string) ($_POST['skip_confirm'] ?? '0'))), ['1', 'true', 'yes', 'on'], true);
    if ($skipConfirm) {
        ponos_save_skip_task_reminder_confirm($userEmail, true);
    }

    $result = ponos_send_task_email_reminder($taskGroupId, $params['task'], $userEmail);
    if (empty($result['ok'])) {
        ponos_api_json(['ok' => false, 'error' => (string) ($result['error'] ?? LOC('ponos.error.save_failed'))], 400);
    }

    ponos_api_json([
        'ok' => true,
        'last_reminder_at' => (string) ($result['last_reminder_at'] ?? ''),
        'skip_task_reminder_confirm' => ponos_load_skip_task_reminder_confirm($userEmail),
    ]);
}

if ($action === 'list_archived_tasks') {
    $params = ponos_api_require_params(['group']);
    ponos_api_require_group_access($params['group'], $userEmail, $isAdmin);
    $page = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
    $payload = ponos_list_archived_tasks($params['group'], $userEmail, $page);
    ponos_api_json(array_merge(['ok' => true], $payload));
}

if ($action === 'unarchive_task') {
    $params = ponos_api_require_params(['group', 'task']);
    ponos_api_require_group_access($params['group'], $userEmail, $isAdmin);
    $taskGroupId = ponos_api_task_group_id($params['group'], $params['task']);
    if ($taskGroupId === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.task_not_found')], 404);
    }
    ponos_api_require_group_task_access($taskGroupId, $userEmail);

    $task = ponos_unarchive_task($taskGroupId, $params['task']);
    if ($task === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.save_failed')], 400);
    }

    ponos_api_json(['ok' => true, 'task' => $task]);
}

if ($action === 'create_task') {
    $params = ponos_api_require_params(['group', 'title', 'description']);
    if (ponos_is_my_tasks_group($params['group'])) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.cannot_create_in_my_tasks')], 400);
    }

    ponos_api_require_group_access($params['group'], $userEmail, $isAdmin);
    ponos_api_require_group_task_access($params['group'], $userEmail);

    $group = ponos_find_group($params['group']);
    if ($group === null || empty($group['can_create_tasks'])) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.group_not_found')], 404);
    }

    $input = [
        'title' => $params['title'],
        'description' => $params['description'],
        'assignee_email' => trim((string) ($_POST['assignee_email'] ?? '')),
        'due_date' => trim((string) ($_POST['due_date'] ?? '')),
        'checklist' => ponos_api_parse_checklist_from_request(),
        'category_id' => trim((string) ($_POST['category_id'] ?? '')),
    ];

    try {
        $task = ponos_create_task(
            $params['group'],
            $userEmail,
            $input,
            ponos_api_collect_uploaded_files()
        );
    } catch (Throwable $error) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.save_failed')], 500);
    }

    if ($task === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.save_failed')], 400);
    }

    ponos_api_json(['ok' => true, 'task' => $task]);
}

if ($action === 'update_task') {
    $params = ponos_api_require_params(['group', 'task']);
    ponos_api_require_group_access($params['group'], $userEmail, $isAdmin);
    $taskGroupId = ponos_api_task_group_id($params['group'], $params['task']);
    if ($taskGroupId === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.task_not_found')], 404);
    }
    ponos_api_require_group_task_access($taskGroupId, $userEmail);

    $input = [];
    foreach (['title', 'description', 'assignee_email', 'due_date', 'category_id'] as $field) {
        if (array_key_exists($field, $_POST)) {
            $input[$field] = $_POST[$field];
        }
    }
    if (array_key_exists('checklist', $_POST)) {
        $input['checklist'] = ponos_api_parse_checklist_from_request();
    }

    try {
        $task = ponos_update_task(
            $taskGroupId,
            $params['task'],
            $userEmail,
            $input,
            ponos_api_collect_uploaded_files()
        );
    } catch (Throwable $error) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.save_failed')], 500);
    }

    if ($task === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.task_not_found')], 404);
    }

    $targetGroupId = trim((string) ($_POST['target_group'] ?? ''));
    if ($targetGroupId !== '' && $targetGroupId !== $taskGroupId && !ponos_is_my_tasks_group($targetGroupId)) {
        if (!ponos_user_has_group_access($userEmail, $targetGroupId, false)) {
            ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.group_task_access_denied')], 403);
        }

        $targetGroup = ponos_find_group($targetGroupId);
        if ($targetGroup === null) {
            ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.group_not_found')], 404);
        }

        $task = ponos_move_task(
            $taskGroupId,
            $targetGroupId,
            $params['task'],
            $userEmail,
            (string) ($targetGroup['name'] ?? '')
        );
        if ($task === null) {
            ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.save_failed')], 400);
        }
    }

    ponos_api_json(['ok' => true, 'task' => $task]);
}

if ($action === 'move_task') {
    $params = ponos_api_require_params(['group', 'task', 'target_group']);
    ponos_api_require_group_access($params['group'], $userEmail, $isAdmin);
    if (ponos_is_my_tasks_group($params['target_group'])) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.group_not_found')], 400);
    }
    if (!ponos_user_has_group_access($userEmail, $params['target_group'], false)) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.group_task_access_denied')], 403);
    }

    $fromGroupId = ponos_api_task_group_id($params['group'], $params['task']);
    if ($fromGroupId === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.task_not_found')], 404);
    }
    ponos_api_require_group_task_access($fromGroupId, $userEmail);

    $targetGroup = ponos_find_group($params['target_group']);
    if ($targetGroup === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.group_not_found')], 404);
    }

    $task = ponos_move_task(
        $fromGroupId,
        $params['target_group'],
        $params['task'],
        $userEmail,
        (string) ($targetGroup['name'] ?? '')
    );
    if ($task === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.save_failed')], 400);
    }

    ponos_api_json(['ok' => true, 'task' => $task]);
}

if ($action === 'update_status') {
    $params = ponos_api_require_params(['group', 'task', 'status']);
    ponos_api_require_group_access($params['group'], $userEmail, $isAdmin);
    $taskGroupId = ponos_api_task_group_id($params['group'], $params['task']);
    if ($taskGroupId === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.task_not_found')], 404);
    }
    ponos_api_require_group_task_access($taskGroupId, $userEmail);

    $task = ponos_update_task_status($taskGroupId, $params['task'], $params['status'], $userEmail);
    if ($task === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.save_failed')], 400);
    }

    ponos_api_json(['ok' => true, 'task' => $task]);
}

if ($action === 'toggle_checklist') {
    $params = ponos_api_require_params(['group', 'task', 'item_id']);
    ponos_api_require_group_access($params['group'], $userEmail, $isAdmin);
    $taskGroupId = ponos_api_task_group_id($params['group'], $params['task']);
    if ($taskGroupId === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.task_not_found')], 404);
    }
    ponos_api_require_group_task_access($taskGroupId, $userEmail);

    $done = in_array(strtolower(trim((string) ($_POST['done'] ?? '1'))), ['1', 'true', 'yes', 'on'], true);
    $task = ponos_toggle_checklist_item(
        $taskGroupId,
        $params['task'],
        (int) $params['item_id'],
        $done,
        $userEmail
    );
    if ($task === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.save_failed')], 400);
    }

    ponos_api_json(['ok' => true, 'task' => $task]);
}

if ($action === 'add_message') {
    $params = ponos_api_require_params(['group', 'task', 'text']);
    ponos_api_require_group_access($params['group'], $userEmail, $isAdmin);
    $taskGroupId = ponos_api_task_group_id($params['group'], $params['task']);
    if ($taskGroupId === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.task_not_found')], 404);
    }
    ponos_api_require_group_task_access($taskGroupId, $userEmail);

    try {
        $message = ponos_add_task_message(
            $taskGroupId,
            $params['task'],
            $userEmail,
            $params['text'],
            ponos_api_collect_uploaded_files()
        );
    } catch (Throwable $error) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.save_failed')], 500);
    }

    if ($message === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.save_failed')], 400);
    }

    ponos_api_json(['ok' => true, 'message' => $message]);
}

if ($action === 'preview_attachment') {
    $params = ponos_api_require_params(['group', 'attachment_id']);
    $taskId = trim((string) ($_GET['task'] ?? $_POST['task'] ?? ''));
    $groupId = ponos_attachment_resolve_group_for_download($params['group'], $taskId);
    $attachment = ponos_find_attachment($groupId, (int) $params['attachment_id']);
    if ($attachment === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.attachment_not_found')], 404);
    }

    $previewType = ponos_attachment_preview_kind($attachment['filename'], $attachment['mime']);
    if ($previewType === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.preview.unsupported')], 400);
    }

    $payload = [
        'ok' => true,
        'preview_type' => $previewType,
        'filename' => $attachment['filename'],
        'mime' => $attachment['mime'],
        'attachment_id' => $attachment['id'],
    ];

    if ($previewType === 'image') {
        ponos_api_json($payload);
    }

    $content = ponos_attachment_read_preview_content($attachment['path']);
    if ($content === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.preview.too_large')], 413);
    }

    if ($previewType === 'csv') {
        $payload['rows'] = ponos_attachment_parse_csv($content);
    } else {
        $payload['content'] = $content;
        $payload['language'] = ponos_attachment_language_from_filename($attachment['filename']);
    }

    ponos_api_json($payload);
}

if ($action === 'download_attachment') {
    $params = ponos_api_require_params(['group', 'attachment_id']);
    $taskId = trim((string) ($_GET['task'] ?? $_POST['task'] ?? ''));
    $groupId = ponos_attachment_resolve_group_for_download($params['group'], $taskId);
    $attachment = ponos_find_attachment($groupId, (int) $params['attachment_id']);
    if ($attachment === null) {
        http_response_code(404);
        echo LOC('ponos.error.attachment_not_found');
        exit;
    }

    $inline = trim((string) ($_GET['inline'] ?? '')) === '1';
    header('Content-Type: ' . $attachment['mime']);
    header(
        'Content-Disposition: '
        . ($inline ? 'inline' : 'attachment')
        . '; filename="' . str_replace('"', '', $attachment['filename']) . '"'
    );
    header('Content-Length: ' . (string) filesize($attachment['path']));
    readfile($attachment['path']);
    exit;
}

ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.unknown_action')], 400);
