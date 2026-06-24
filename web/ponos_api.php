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

/**
 * Page load
 */

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));
$userEmail = ponos_current_user_email();

if ($action === 'navigation') {
    $company = trim((string) ($_GET['company'] ?? ''));
    $companies = ponos_companies_for_page();
    if ($company === '') {
        $prefs = ponos_load_user_navigation_prefs($userEmail);
        $company = ponos_resolve_company_choice($companies, '', $prefs['company']);
    } else {
        $company = ponos_resolve_company_choice($companies, $company, '');
    }

    auth_set_current_company_context($company);

    try {
        $projects = ponos_fetch_projects_for_company($company);
        $dimensionNames = ponos_fetch_dimension_names($company);
        $navigation = ponos_build_departments_payload($company, $projects, $dimensionNames);
    } catch (Throwable $error) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.load_failed')], 500);
    }

    ponos_api_json([
        'ok' => true,
        'company' => $company,
        'companies' => $companies,
        'categories' => PONOS_TASK_CATEGORIES,
        'statuses' => [
            PONOS_STATUS_TODO => LOC('ponos.status.todo'),
            PONOS_STATUS_IN_PROGRESS => LOC('ponos.status.in_progress'),
            PONOS_STATUS_DONE => LOC('ponos.status.done'),
        ],
        'departments' => $navigation['departments'],
        'projects_by_department' => $navigation['projects_by_department'],
        'user_email' => $userEmail,
        'prefs' => ponos_load_user_navigation_prefs($userEmail),
    ]);
}

if ($action === 'save_prefs') {
    $company = trim((string) ($_POST['company'] ?? ''));
    $department = trim((string) ($_POST['department'] ?? ''));
    if ($company !== '' || $department !== '') {
        ponos_save_user_navigation_prefs($userEmail, $company, $department);
    }

    ponos_api_json(['ok' => true, 'prefs' => ponos_load_user_navigation_prefs($userEmail)]);
}

if ($action === 'list_tasks') {
    $params = ponos_api_require_params(['company', 'project']);
    auth_set_current_company_context($params['company']);
    $tasks = ponos_list_tasks($params['company'], $params['project']);
    ponos_api_json(['ok' => true, 'tasks' => $tasks]);
}

if ($action === 'get_task') {
    $params = ponos_api_require_params(['company', 'project', 'task']);
    $task = ponos_get_task($params['company'], $params['project'], $params['task']);
    if ($task === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.task_not_found')], 404);
    }

    ponos_api_json(['ok' => true, 'task' => $task]);
}

if ($action === 'create_task') {
    $params = ponos_api_require_params(['company', 'project', 'title', 'description']);
    auth_set_current_company_context($params['company']);

    $input = [
        'title' => $params['title'],
        'description' => $params['description'],
        'category' => trim((string) ($_POST['category'] ?? PONOS_TASK_CATEGORIES[0])),
        'assignee_email' => trim((string) ($_POST['assignee_email'] ?? '')),
        'due_date' => trim((string) ($_POST['due_date'] ?? '')),
        'checklist' => ponos_api_parse_checklist_from_request(),
    ];

    try {
        $task = ponos_create_task(
            $params['company'],
            $params['project'],
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
    $params = ponos_api_require_params(['company', 'project', 'task']);
    $input = [];
    foreach (['title', 'description', 'category', 'assignee_email', 'due_date'] as $field) {
        if (array_key_exists($field, $_POST)) {
            $input[$field] = $_POST[$field];
        }
    }
    if (array_key_exists('checklist', $_POST)) {
        $input['checklist'] = ponos_api_parse_checklist_from_request();
    }

    try {
        $task = ponos_update_task(
            $params['company'],
            $params['project'],
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

    ponos_api_json(['ok' => true, 'task' => $task]);
}

if ($action === 'update_status') {
    $params = ponos_api_require_params(['company', 'project', 'task', 'status']);
    $task = ponos_update_task_status($params['company'], $params['project'], $params['task'], $params['status']);
    if ($task === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.save_failed')], 400);
    }

    ponos_api_json(['ok' => true, 'task' => $task]);
}

if ($action === 'toggle_checklist') {
    $params = ponos_api_require_params(['company', 'project', 'task', 'item_id']);
    $done = in_array(strtolower(trim((string) ($_POST['done'] ?? '1'))), ['1', 'true', 'yes', 'on'], true);
    $task = ponos_toggle_checklist_item(
        $params['company'],
        $params['project'],
        $params['task'],
        (int) $params['item_id'],
        $done
    );
    if ($task === null) {
        ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.save_failed')], 400);
    }

    ponos_api_json(['ok' => true, 'task' => $task]);
}

if ($action === 'add_message') {
    $params = ponos_api_require_params(['company', 'project', 'task', 'text']);
    try {
        $message = ponos_add_task_message(
            $params['company'],
            $params['project'],
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

if ($action === 'download_attachment') {
    $params = ponos_api_require_params(['company', 'project', 'attachment_id']);
    $attachment = ponos_find_attachment($params['company'], $params['project'], (int) $params['attachment_id']);
    if ($attachment === null) {
        http_response_code(404);
        echo LOC('ponos.error.attachment_not_found');
        exit;
    }

    header('Content-Type: ' . $attachment['mime']);
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $attachment['filename']) . '"');
    header('Content-Length: ' . (string) filesize($attachment['path']));
    readfile($attachment['path']);
    exit;
}

ponos_api_json(['ok' => false, 'error' => LOC('ponos.error.unknown_action')], 400);
