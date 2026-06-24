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
require_once __DIR__ . '/odata.php';
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/ponos_data.php';

/**
 * Page load
 */

$userEmail = ponos_current_user_email();
$userPrefs = ponos_load_user_navigation_prefs($userEmail);
$companies = ponos_companies_for_page();
$company = ponos_resolve_company_choice($companies, trim((string) ($_GET['company'] ?? '')), $userPrefs['company']);
$department = trim((string) ($_GET['dept'] ?? $_GET['department'] ?? $userPrefs['department']));
$project = trim((string) ($_GET['project'] ?? ''));
$taskId = trim((string) ($_GET['task'] ?? ''));

auth_set_current_company_context($company);

$i18nKeys = [
    'ponos.empty.select_project', 'ponos.empty.no_tasks', 'ponos.btn.new_task', 'ponos.btn.save',
    'ponos.btn.cancel', 'ponos.btn.edit', 'ponos.btn.send', 'ponos.field.title', 'ponos.field.description',
    'ponos.field.category', 'ponos.field.assignee', 'ponos.field.due_date', 'ponos.field.checklist',
    'ponos.field.checklist_add', 'ponos.field.attachments', 'ponos.field.message', 'ponos.status.todo',
    'ponos.status.in_progress', 'ponos.status.done', 'ponos.task.messages', 'ponos.task.copy_link',
    'ponos.task.link_copied', 'ponos.error.load_failed', 'ponos.error.save_failed', 'ponos.sidebar.no_department',
];
$i18n = [];
foreach ($i18nKeys as $key) {
    $i18n[$key] = LOC($key);
}

?><!DOCTYPE html>
<html lang="<?= ponos_h(getHtmlLang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= ponos_h(LOC('app.title')) ?></title>
    <link rel="stylesheet" href="brand.css">
    <link rel="manifest" href="site.webmanifest">
    <link rel="icon" href="box.svg" type="image/svg+xml">
    <?php renderLanguageSwitcherStyles(); ?>
    <style>
        .ponos-shell { min-height: 100vh; display: flex; flex-direction: column; }
        .ponos-topbar {
            display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between;
            padding: 14px 18px; border-bottom: 3px solid var(--kvt-main-blue); background: #fff;
        }
        .ponos-topbar-left { display: flex; flex-wrap: wrap; gap: 14px; align-items: center; }
        .ponos-topbar img { max-height: 42px; width: auto; }
        .ponos-topbar h1 { margin: 0; font-size: 1.35rem; color: var(--kvt-perkins-blue); }
        .ponos-company-select { font: inherit; border-radius: 10px; border: 1px solid var(--kvt-line); padding: 10px 12px; min-width: 220px; }
        .ponos-body { display: grid; grid-template-columns: 280px 1fr; flex: 1; min-height: 0; }
        .ponos-sidebar {
            border-right: 1px solid var(--kvt-line); background: #f7fbff; padding: 16px 12px; overflow: auto;
        }
        .ponos-sidebar h2 { margin: 0 0 12px; font-size: 0.95rem; color: var(--kvt-perkins-blue); }
        .ponos-dept { margin-bottom: 10px; }
        .ponos-dept-toggle {
            width: 100%; text-align: left; border: 1px solid var(--kvt-line); background: #fff; border-radius: 10px;
            padding: 10px 12px; font: inherit; font-weight: 700; color: var(--kvt-perkins-blue); cursor: pointer;
        }
        .ponos-dept-toggle.is-active { border-color: var(--kvt-main-blue); background: #e8f4fc; }
        .ponos-project-list { list-style: none; margin: 8px 0 0; padding: 0 0 0 8px; display: grid; gap: 6px; }
        .ponos-project-link {
            display: block; padding: 8px 10px; border-radius: 8px; text-decoration: none; color: var(--kvt-text);
            border: 1px solid transparent; font-size: 0.92rem;
        }
        .ponos-project-link:hover { background: #edf6ff; }
        .ponos-project-link.is-active { background: #fff; border-color: rgba(0,153,204,.35); font-weight: 700; }
        .ponos-main { padding: 18px; overflow: auto; background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%); }
        .ponos-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        .ponos-btn {
            font: inherit; border-radius: 10px; border: 1px solid var(--kvt-perkins-blue); padding: 10px 14px;
            background: linear-gradient(180deg, var(--kvt-main-blue) 0%, var(--kvt-perkins-blue) 100%);
            color: #fff; cursor: pointer; font-weight: 700;
        }
        .ponos-btn--ghost { background: #fff; color: var(--kvt-perkins-blue); }
        .ponos-muted { color: var(--kvt-muted); }
        .ponos-alert { border: 1px solid #fecaca; background: #fef2f2; color: var(--kvt-danger); border-radius: 10px; padding: 12px; margin-bottom: 12px; }
        .ponos-board { display: grid; grid-template-columns: repeat(3, minmax(220px, 1fr)); gap: 14px; align-items: start; }
        .ponos-column {
            background: var(--kvt-panel-bg); border: 1px solid var(--kvt-line); border-radius: 14px; min-height: 320px;
            display: flex; flex-direction: column;
        }
        .ponos-column-head {
            padding: 12px 14px; font-weight: 700; color: var(--kvt-perkins-blue); border-bottom: 1px solid var(--kvt-line);
        }
        .ponos-column-body { padding: 10px; display: grid; gap: 10px; flex: 1; }
        .ponos-column-body.is-drop-target { background: #edf6ff; outline: 2px dashed rgba(0,153,204,.45); }
        .ponos-card {
            border: 1px solid var(--kvt-line); border-radius: 12px; background: #fff; overflow: hidden;
            box-shadow: 0 4px 14px rgba(0,82,155,.06); cursor: pointer;
        }
        .ponos-card-bar {
            height: 18px;
            min-height: 18px;
            position: relative;
            background: var(--ponos-bar-dark, #64748b);
            touch-action: none;
        }
        .ponos-card-bar-fill {
            position: absolute; inset: 0 auto 0 0; width: 0; background: var(--ponos-bar-light, #94a3b8);
        }
        .ponos-card-bar[draggable="true"] { cursor: grab; }
        .ponos-card-bar[draggable="true"]:active { cursor: grabbing; }
        @media (max-width: 960px) {
            .ponos-card-bar { height: 24px; min-height: 24px; }
        }
        .ponos-card-body { padding: 12px 14px 14px; }
        .ponos-card-title { font-weight: 700; color: var(--kvt-perkins-blue); margin: 0 0 6px; }
        .ponos-card-meta { font-size: 0.82rem; color: var(--kvt-muted); display: grid; gap: 4px; }
        .ponos-detail {
            position: fixed; inset: 0; z-index: 10000; display: none; background: rgba(15,23,42,.35);
        }
        .ponos-detail.is-open { display: grid; place-items: stretch; }
        .ponos-detail-panel {
            margin-left: auto;
            width: min(1120px, 96vw);
            height: 100%;
            background: #fff;
            box-shadow: -12px 0 40px rgba(15,23,42,.18);
            display: grid;
            grid-template-rows: auto 1fr;
            overflow: hidden;
        }
        .ponos-detail-head {
            display: flex; justify-content: space-between; gap: 10px; align-items: flex-start;
            padding: 16px 20px; border-bottom: 1px solid var(--kvt-line); background: #fff; z-index: 2;
        }
        .ponos-detail-body {
            min-height: 0;
            overflow: hidden;
            padding: 0;
        }
        .ponos-detail-layout {
            display: grid;
            grid-template-columns: minmax(300px, 1fr) minmax(380px, 1.2fr);
            height: 100%;
            min-height: 0;
        }
        .ponos-detail-layout--edit {
            grid-template-columns: 1fr;
        }
        .ponos-detail-main {
            padding: 18px 20px 24px;
            overflow-y: auto;
            min-height: 0;
            border-right: 1px solid var(--kvt-line);
        }
        .ponos-detail-main--full {
            border-right: 0;
        }
        .ponos-detail-chat {
            display: flex;
            flex-direction: column;
            min-height: 0;
            height: 100%;
            padding: 18px 20px 20px;
            background: linear-gradient(180deg, #f8fbff 0%, #f3f7fc 100%);
        }
        .ponos-detail-chat-title {
            margin: 0 0 12px;
            font-size: 1rem;
            color: var(--kvt-perkins-blue);
        }
        .ponos-form { display: grid; gap: 12px; }
        .ponos-form label { display: grid; gap: 6px; font-weight: 700; color: var(--kvt-perkins-blue); font-size: 0.9rem; }
        .ponos-form input, .ponos-form select, .ponos-form textarea {
            font: inherit; border-radius: 10px; border: 1px solid var(--kvt-line); padding: 10px 12px; width: 100%; box-sizing: border-box;
        }
        .ponos-checklist { display: grid; gap: 8px; }
        .ponos-checklist-item { display: flex; gap: 8px; align-items: center; }
        .ponos-messages {
            display: grid;
            gap: 10px;
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            padding-right: 4px;
            align-content: start;
        }
        .ponos-message {
            border: 1px solid var(--kvt-line); border-radius: 12px; padding: 10px 12px;
        }
        .ponos-message-meta { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 6px; font-size: 0.82rem; }
        .ponos-message-email {
            display: inline-block; padding: 2px 8px; border-radius: 999px; font-weight: 700;
        }
        .ponos-message--system { font-style: italic; color: var(--kvt-muted); background: #f8fafc; }
        .ponos-message-compose {
            display: grid;
            gap: 8px;
            flex: 0 0 auto;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--kvt-line);
            background: linear-gradient(180deg, #f8fbff 0%, #f3f7fc 100%);
        }
        .ponos-attachments { display: grid; gap: 6px; font-size: 0.9rem; }
        .ponos-attachments a { color: var(--kvt-main-blue); }
        @media (max-width: 960px) {
            .ponos-body { grid-template-columns: 1fr; }
            .ponos-sidebar { border-right: 0; border-bottom: 1px solid var(--kvt-line); max-height: 240px; }
            .ponos-board { grid-template-columns: 1fr; }
            .ponos-detail-panel { width: 100%; }
            .ponos-detail-layout { grid-template-columns: 1fr; height: auto; }
            .ponos-detail-main { border-right: 0; border-bottom: 1px solid var(--kvt-line); max-height: 45vh; }
            .ponos-detail-chat { height: auto; min-height: 45vh; }
            .ponos-messages { max-height: 32vh; }
        }
    </style>
</head>
<body>
<div class="ponos-shell">
    <header class="ponos-topbar">
        <div class="ponos-topbar-left">
            <img src="logo-website.png" alt="KVT">
            <h1 class="brand-display"><?= ponos_h(LOC('ponos.hero.title')) ?></h1>
            <label>
                <span class="ponos-muted"><?= ponos_h(LOC('ponos.label.company')) ?></span>
                <select id="ponos-company" class="ponos-company-select">
                    <?php foreach ($companies as $companyOption): ?>
                        <option value="<?= ponos_h($companyOption) ?>"<?= $companyOption === $company ? ' selected' : '' ?>><?= ponos_h($companyOption) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <?php renderLanguageSwitcher(); ?>
    </header>

    <div class="ponos-body">
        <aside class="ponos-sidebar">
            <h2><?= ponos_h(LOC('ponos.sidebar.departments')) ?></h2>
            <div id="ponos-sidebar-content" class="ponos-muted"><?= ponos_h(LOC('ponos.error.load_failed')) ?></div>
        </aside>

        <main class="ponos-main">
            <div id="ponos-alert" class="ponos-alert" hidden></div>
            <div class="ponos-toolbar">
                <div>
                    <strong id="ponos-project-title"></strong>
                    <div id="ponos-project-subtitle" class="ponos-muted"></div>
                </div>
                <button type="button" id="ponos-new-task" class="ponos-btn" hidden><?= ponos_h(LOC('ponos.btn.new_task')) ?></button>
            </div>
            <div id="ponos-board" class="ponos-board" hidden></div>
            <p id="ponos-empty" class="ponos-muted"><?= ponos_h(LOC('ponos.empty.select_project')) ?></p>
        </main>
    </div>

    <?= injectTimerHtml([
        'statusUrl' => 'odata.php?action=cache_status',
        'deleteUrl' => 'odata.php?action=cache_delete',
        'clearUrl' => 'odata.php?action=cache_clear',
        'title' => 'Cachebestanden',
        'label' => 'Cache',
        'css' => '{{root}} .odata-cache-widget{top:16px;right:16px;left:auto;} {{root}} .odata-cache-popout{top:64px;right:16px;left:auto;}',
    ]) ?>
</div>

<div id="ponos-detail" class="ponos-detail" aria-hidden="true">
    <div class="ponos-detail-panel" role="dialog" aria-modal="true">
        <div class="ponos-detail-head">
            <div>
                <h2 id="ponos-detail-title" style="margin:0;"></h2>
                <p id="ponos-detail-meta" class="ponos-muted" style="margin:6px 0 0;"></p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="button" id="ponos-copy-link" class="ponos-btn ponos-btn--ghost"><?= ponos_h(LOC('ponos.task.copy_link')) ?></button>
                <button type="button" id="ponos-edit-task" class="ponos-btn ponos-btn--ghost"><?= ponos_h(LOC('ponos.btn.edit')) ?></button>
                <button type="button" id="ponos-close-detail" class="ponos-btn ponos-btn--ghost">✕</button>
            </div>
        </div>
        <div class="ponos-detail-body" id="ponos-detail-body"></div>
    </div>
</div>

<script>
window.PONOS_BOOT = <?= json_encode([
    'company' => $company,
    'department' => $department,
    'project' => $project,
    'task' => $taskId,
    'userEmail' => $userEmail,
    'categories' => PONOS_TASK_CATEGORIES,
    'statuses' => [
        'todo' => LOC('ponos.status.todo'),
        'in_progress' => LOC('ponos.status.in_progress'),
        'done' => LOC('ponos.status.done'),
    ],
    'i18n' => $i18n,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="ponos.js"></script>
<?php renderLanguageSwitcherScript(); ?>
</body>
</html>
