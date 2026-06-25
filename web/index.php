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

/**
 * Page load
 */

$userEmail = ponos_current_user_email();
$userPrefs = ponos_load_user_navigation_prefs($userEmail);
$group = trim((string) ($_GET['group'] ?? $userPrefs['group']));
$taskId = trim((string) ($_GET['task'] ?? ''));
$hasAdminRole = ponos_user_has_admin_role();
$isAdmin = ponos_current_user_is_admin();

$i18nKeys = [
    'ponos.empty.select_group', 'ponos.empty.no_groups', 'ponos.empty.no_tasks', 'ponos.btn.new_task',
    'ponos.btn.new_group', 'ponos.btn.delete', 'ponos.btn.save', 'ponos.btn.cancel', 'ponos.btn.edit',
    'ponos.btn.send', 'ponos.field.title', 'ponos.field.description', 'ponos.field.group',
    'ponos.field.assignee', 'ponos.field.due_date', 'ponos.field.checklist', 'ponos.field.checklist_add',
    'ponos.field.attachments', 'ponos.field.message', 'ponos.status.todo', 'ponos.status.in_progress',
    'ponos.status.done', 'ponos.task.messages', 'ponos.task.copy_link', 'ponos.task.link_copied',
    'ponos.error.load_failed', 'ponos.error.save_failed', 'ponos.group.my_tasks_hint',
    'ponos.label.group', 'ponos.group.new_title', 'ponos.group.rename_title',
    'ponos.group.delete_confirm_title', 'ponos.group.delete_confirm_message',
    'ponos.group.delete_confirm_yes', 'ponos.group.delete_confirm_no',
    'ponos.settings.title', 'ponos.settings.assigned', 'ponos.settings.status_changed',
    'ponos.settings.message', 'ponos.settings.checklist', 'ponos.settings.daily_reminder',
    'ponos.settings.hint', 'ponos.pin.pin', 'ponos.pin.unpin',
    'ponos.label.category', 'ponos.field.category', 'ponos.category.uncategorized', 'ponos.btn.new_category',
    'ponos.category.admin_title', 'ponos.category.new_title', 'ponos.category.rename_title',
    'ponos.empty.no_categories', 'ponos.reminder.confirm', 'ponos.reminder.yes', 'ponos.reminder.yes_always',
    'ponos.reminder.no', 'ponos.reminder.sent', 'ponos.btn.archive', 'ponos.archive.title',
    'ponos.empty.no_archived_tasks', 'ponos.btn.unarchive', 'ponos.btn.prev_page', 'ponos.btn.next_page',
    'ponos.group.admin_title', 'ponos.group.tab.categories', 'ponos.group.tab.access',
    'ponos.access.everyone', 'ponos.access.members', 'ponos.access.add_member', 'ponos.access.remove_member',
    'ponos.empty.no_members',
    'ponos.stats.title', 'ponos.stats.btn', 'ponos.stats.user', 'ponos.stats.created', 'ponos.stats.handled',
    'ponos.stats.on_time_title', 'ponos.stats.on_time_hint', 'ponos.stats.on_time_none',
    'ponos.stats.categories_title', 'ponos.stats.empty', 'ponos.stats.tasks',
    'ponos.error.group_task_access_denied', 'ponos.group.read_only_hint',
    'ponos.preview.download', 'ponos.preview.loading', 'ponos.preview.failed',
    'ponos.preview.too_large', 'ponos.preview.unsupported', 'ponos.preview.close',
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
    <link rel="icon" href="ponos-favicon.png" type="image/png">
    <?php renderLanguageSwitcherStyles(); ?>
    <style>
        .ponos-shell { min-height: 98vh; display: flex; flex-direction: column; }
        .ponos-topbar {
            display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between;
            padding: 14px 18px; border-bottom: 3px solid var(--kvt-main-blue); background: #fff;
        }
        .ponos-topbar-left { display: flex; flex-wrap: wrap; gap: 14px; align-items: center; }
        .ponos-topbar-tools { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
        .ponos-admin-toggle {
            position: fixed;
            top: 12px;
            right: 62px;
            z-index: 5000;
            display: inline-flex; align-items: center; gap: 8px; cursor: pointer;
            font-size: 0.82rem; font-weight: 700; color: #92400e; user-select: none;
            background: #fffbeb; border: 1px solid #f59e0b; border-radius: 999px; padding: 6px 12px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.12);
        }
        .ponos-admin-toggle input { width: 1rem; height: 1rem; margin: 0; cursor: pointer; }
        @media print {
            .ponos-admin-toggle { display: none !important; }
        }
        .ponos-topbar img { max-height: 42px; width: auto; }
        .ponos-topbar h1 { margin: 0; font-size: 1.35rem; color: var(--kvt-perkins-blue); }
        .ponos-body { display: grid; grid-template-columns: 280px 1fr; flex: 1; min-height: 0; }
        .ponos-sidebar {
            border-right: 1px solid var(--kvt-line); background: #f7fbff; padding: 16px 12px;
            display: flex; flex-direction: column; min-height: 0; overflow: hidden;
        }
        .ponos-sidebar h2 { margin: 0 0 12px; font-size: 0.95rem; color: var(--kvt-perkins-blue); flex: 0 0 auto; }
        #ponos-sidebar-content { flex: 1 1 auto; min-height: 0; overflow-y: auto; }
        .ponos-sidebar-admin { margin-bottom: 12px; }
        .ponos-btn--small { padding: 8px 12px; font-size: 0.88rem; width: 100%; }
        .ponos-group-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }
        .ponos-group-item {
            display: grid; grid-template-columns: 1fr auto; gap: 4px; align-items: center;
        }
        .ponos-group-link {
            width: 100%; text-align: left; border: 1px solid var(--kvt-line); background: #fff; border-radius: 10px;
            padding: 10px 12px; font: inherit; color: var(--kvt-text); cursor: pointer;
            display: flex; align-items: center; gap: 8px;
        }
        .ponos-group-link:hover { background: #edf6ff; }
        .ponos-group-link.is-active { border-color: var(--kvt-main-blue); background: #e8f4fc; font-weight: 700; }
        .ponos-group-link.is-pinned { background: #fef9c3; border-color: #facc15; }
        .ponos-group-link.is-pinned:hover { background: #fef08a; }
        .ponos-group-link.is-active.is-pinned { background: #fef08a; border-color: var(--kvt-main-blue); }
        .ponos-group-pin { flex: 0 0 auto; font-size: 0.85rem; line-height: 1; }
        .ponos-group-link-label { flex: 1 1 auto; min-width: 0; }
        .ponos-group-actions { display: flex; gap: 4px; }
        .ponos-icon-btn {
            border: 1px solid var(--kvt-line); background: #fff; border-radius: 8px; width: 30px; height: 30px;
            cursor: pointer; font: inherit; line-height: 1; color: var(--kvt-perkins-blue);
        }
        .ponos-icon-btn--danger { color: var(--kvt-danger); }
        .ponos-danger-overlay {
            position: fixed; inset: 0; z-index: 20000; display: grid; place-items: center;
            background: rgba(15, 23, 42, 0.55); padding: 20px;
        }
        .ponos-danger-overlay[hidden] { display: none !important; }
        .ponos-danger-dialog {
            position: relative; width: min(520px, 96vw); border-radius: 16px; overflow: hidden;
            background: #fff; box-shadow: 0 24px 60px rgba(127, 29, 29, 0.35);
            animation: ponos-danger-pulse 1.2s ease-in-out infinite;
        }
        @keyframes ponos-danger-pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.45), 0 24px 60px rgba(127, 29, 29, 0.35); }
            50% { box-shadow: 0 0 0 12px rgba(220, 38, 38, 0), 0 24px 60px rgba(185, 28, 28, 0.5); }
        }
        .ponos-warning-tape-wrap { height: 42px; overflow: hidden; }
        .ponos-warning-tape {
            width: 100%; height: 100%;
            background: linear-gradient(
                -45deg,
                #111 25%,
                #facc15 25%,
                #facc15 50%,
                #111 50%,
                #111 75%,
                #facc15 75%,
                #facc15 100%
            );
            background-size: 40px 40px;
            animation: ponos-tape-scroll 2.75s linear infinite;
        }
        @keyframes ponos-tape-scroll {
            from { background-position: 0 0; }
            to { background-position: 40px 40px; }
        }
        .ponos-danger-body { padding: 22px 24px 24px; }
        .ponos-danger-body h3 { margin: 0 0 10px; color: #b91c1c; }
        .ponos-danger-actions { display: flex; gap: 10px; margin-top: 18px; flex-wrap: wrap; }
        .ponos-danger-actions .ponos-btn--danger {
            background: linear-gradient(180deg, #ef4444 0%, #b91c1c 100%);
            border-color: #b91c1c;
        }
        .ponos-modal-overlay {
            position: fixed; inset: 0; z-index: 19000; display: grid; place-items: center;
            background: rgba(15, 23, 42, 0.45); padding: 20px;
        }
        .ponos-modal-overlay[hidden] { display: none !important; }
        .ponos-modal-dialog {
            width: min(440px, 96vw); border-radius: 16px; background: #fff;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.22); padding: 22px 24px 24px;
        }
        .ponos-modal-dialog h3 { margin: 0 0 16px; color: var(--kvt-perkins-blue); font-size: 1.1rem; }
        .ponos-modal-dialog label { display: grid; gap: 6px; font-weight: 700; color: var(--kvt-perkins-blue); font-size: 0.9rem; }
        .ponos-modal-dialog input {
            font: inherit; border-radius: 10px; border: 1px solid var(--kvt-line);
            padding: 10px 12px; width: 100%; box-sizing: border-box;
        }
        .ponos-modal-actions { display: flex; gap: 10px; margin-top: 18px; flex-wrap: wrap; }
        .ponos-main { padding: 18px; overflow: auto; background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%); }
        .ponos-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; flex: 0 0 auto; }
        .ponos-group-heading { display: flex; align-items: center; gap: 8px; }
        .ponos-pin-btn {
            border: 0; background: transparent; cursor: pointer; font-size: 1.15rem; line-height: 1;
            opacity: 0.35; padding: 2px 4px; color: var(--kvt-perkins-blue);
        }
        .ponos-pin-btn.is-pinned { opacity: 1; }
        .ponos-pin-btn:hover { opacity: 0.85; }
        .ponos-group-admin-btn {
            border: 0; background: transparent; cursor: pointer; font-size: 1.05rem; line-height: 1;
            padding: 2px 4px; color: var(--kvt-perkins-blue);
        }
        .ponos-group-stats-btn {
            border: 0; background: transparent; cursor: pointer; font-size: 1.05rem; line-height: 1;
            padding: 2px 4px; color: var(--kvt-perkins-blue);
        }
        .ponos-modal-dialog--stats { width: min(720px, 96vw); }
        .ponos-stats-summary {
            display: grid; grid-template-columns: minmax(0, 1fr) minmax(220px, 280px); gap: 20px; margin-bottom: 20px;
        }
        .ponos-stats-on-time {
            border: 1px solid var(--kvt-line); border-radius: 14px; padding: 16px 18px; background: #f7fbff;
        }
        .ponos-stats-on-time-value { font-size: 2.4rem; font-weight: 800; color: var(--kvt-perkins-blue); line-height: 1.1; }
        .ponos-stats-on-time-label { font-weight: 700; color: var(--kvt-perkins-blue); margin: 0 0 6px; }
        .ponos-stats-users-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .ponos-stats-users-table th, .ponos-stats-users-table td {
            border-bottom: 1px solid var(--kvt-line); padding: 8px 10px; text-align: left;
        }
        .ponos-stats-users-table th { color: var(--kvt-perkins-blue); font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.03em; }
        .ponos-stats-users-table td:last-child, .ponos-stats-users-table th:last-child,
        .ponos-stats-users-table td:nth-child(3), .ponos-stats-users-table th:nth-child(3) { text-align: right; }
        .ponos-stats-chart-wrap { display: grid; grid-template-columns: 200px 1fr; gap: 16px; align-items: center; }
        .ponos-stats-pie { width: 200px; height: 200px; }
        .ponos-stats-legend { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
        .ponos-stats-legend li { display: grid; grid-template-columns: 14px 1fr auto; gap: 8px; align-items: center; font-size: 0.88rem; }
        .ponos-stats-legend-swatch { width: 14px; height: 14px; border-radius: 4px; }
        .ponos-stats-section-title { margin: 0 0 10px; color: var(--kvt-perkins-blue); font-size: 1rem; }
        @media (max-width: 720px) {
            .ponos-stats-summary, .ponos-stats-chart-wrap { grid-template-columns: 1fr; }
            .ponos-stats-pie { margin: 0 auto; }
        }
        .ponos-modal-dialog--wide { width: min(520px, 96vw); }
        .ponos-category-admin-list { list-style: none; margin: 0 0 14px; padding: 0; display: grid; gap: 6px; max-height: 320px; overflow-y: auto; }
        .ponos-category-admin-item {
            display: grid; grid-template-columns: 1fr auto; gap: 6px; align-items: center;
            border: 1px solid var(--kvt-line); border-radius: 10px; padding: 8px 10px; background: #fff;
        }
        .ponos-category-swatch {
            display: inline-block; width: 14px; height: 14px; border-radius: 4px; margin-right: 8px; vertical-align: middle;
        }
        .ponos-admin-tabs { display: flex; gap: 8px; margin: 0 0 14px; flex-wrap: wrap; }
        .ponos-admin-tab {
            font: inherit; border-radius: 999px; border: 1px solid var(--kvt-line); background: #fff;
            color: var(--kvt-perkins-blue); padding: 6px 12px; cursor: pointer; font-weight: 700; font-size: 0.88rem;
        }
        .ponos-admin-tab.is-active {
            background: linear-gradient(180deg, var(--kvt-main-blue) 0%, var(--kvt-perkins-blue) 100%);
            color: #fff; border-color: var(--kvt-perkins-blue);
        }
        .ponos-admin-panel[hidden] { display: none !important; }
        .ponos-access-panel { display: grid; gap: 14px; }
        .ponos-access-checkboxes label {
            display: flex; align-items: flex-start; gap: 10px;
            font-weight: 400; color: var(--kvt-text); cursor: pointer;
        }
        .ponos-access-checkboxes input[type="checkbox"] {
            width: 1rem; height: 1rem; padding: 0; margin: 0.15em 0 0;
            flex: 0 0 auto; border-radius: 4px;
        }
        .ponos-access-checkboxes label span {
            flex: 1 1 auto; line-height: 1.45; text-align: left; font-weight: 700;
            color: var(--kvt-perkins-blue);
        }
        .ponos-access-members.is-disabled { opacity: 0.45; pointer-events: none; user-select: none; }
        .ponos-access-member-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }
        .ponos-access-member-item {
            display: grid; grid-template-columns: 1fr auto; gap: 8px; align-items: center;
            border: 1px solid var(--kvt-line); border-radius: 10px; padding: 8px 10px; background: #fff;
        }
        .ponos-access-add { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .ponos-access-add select {
            flex: 1 1 180px; font: inherit; border-radius: 10px; border: 1px solid var(--kvt-line); padding: 8px 10px;
        }
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
            display: flex; align-items: center; justify-content: space-between; gap: 8px;
        }
        .ponos-column-head-title { flex: 1 1 auto; min-width: 0; }
        .ponos-archive-btn {
            font: inherit; font-size: 0.78rem; font-weight: 700; border-radius: 8px; border: 1px solid var(--kvt-line);
            background: #fff; color: var(--kvt-perkins-blue); padding: 4px 8px; cursor: pointer; flex-shrink: 0;
        }
        .ponos-archive-btn:hover { background: #edf6ff; }
        .ponos-column-body { padding: 10px; display: grid; gap: 10px; flex: 1; align-content: start; }
        .ponos-column-body.is-drop-target { background: #edf6ff; outline: 2px dashed rgba(0,153,204,.45); }
        .ponos-card {
            border: 1px solid var(--kvt-line); border-radius: 12px; background: #fff; overflow: hidden;
            box-shadow: 0 4px 14px rgba(0,82,155,.06); cursor: pointer;
            display: flex; flex-direction: column; align-self: start; width: 100%; box-sizing: border-box;
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
        .ponos-card-body { padding: 12px 14px 14px; position: relative; display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; box-sizing: border-box; }
        .ponos-card-main { flex: 1 1 auto; min-height: 0; }
        .ponos-unread-badge {
            position: absolute; top: 10px; right: 10px; min-width: 20px; height: 20px; border-radius: 999px;
            background: #dc2626; color: #fff; font-size: 0.72rem; font-weight: 700; line-height: 20px;
            text-align: center; padding: 0 6px; box-shadow: 0 2px 6px rgba(220, 38, 38, 0.35);
        }
        .ponos-card-title { font-weight: 700; color: var(--kvt-perkins-blue); margin: 0 0 6px; }
        .ponos-card-description {
            margin: 0 0 8px; font-size: 0.88rem; color: var(--kvt-text); line-height: 1.45;
            display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
        }
        .ponos-card-meta { font-size: 0.82rem; color: var(--kvt-muted); display: grid; gap: 4px; }
        .ponos-due--week,
        .ponos-due--today {
            display: inline-block; width: fit-content; padding: 1px 7px; border-radius: 5px; font-weight: 700;
        }
        .ponos-due--week { animation: ponos-due-pulse-week 1.6s ease-in-out infinite; }
        .ponos-due--today { animation: ponos-due-pulse-today 1.2s ease-in-out infinite; }
        @keyframes ponos-due-pulse-week {
            0%, 100% { background: rgba(250, 204, 21, 0.22); color: #a16207; }
            50% { background: rgba(250, 204, 21, 0.62); color: #713f12; }
        }
        @keyframes ponos-due-pulse-today {
            0%, 100% { background: rgba(239, 68, 68, 0.2); color: #b91c1c; }
            50% { background: rgba(239, 68, 68, 0.58); color: #7f1d1d; }
        }
        .ponos-card-assignee {
            margin-top: auto; padding-top: 10px; margin-left: auto; text-align: right; font-size: 0.82rem;
            color: var(--kvt-muted); line-height: 1.35; max-width: 100%; flex-shrink: 0;
            display: flex; align-items: flex-end; justify-content: flex-end; gap: 6px; position: relative;
        }
        .ponos-card-assignee-text { text-align: right; min-width: 0; }
        .ponos-card-assignee-label {
            display: inline-flex; align-items: stretch; justify-content: flex-end; gap: 8px; min-width: 0;
        }
        .ponos-card-assignee-lines {
            display: flex; flex-direction: column; justify-content: center; gap: 1px; min-width: 0; text-align: right;
        }
        .ponos-user-avatar--assignee {
            width: 38px; height: 38px; flex: 0 0 38px; align-self: center;
        }
        .ponos-user-avatar {
            width: 30px; height: 30px; flex: 0 0 30px; display: block;
            border: 1px solid; border-radius: 4px; background: #fff;
            image-rendering: pixelated; image-rendering: crisp-edges; object-fit: none;
        }
        .ponos-user-label {
            display: inline-flex; align-items: center; gap: 8px; min-width: 0;
        }
        .ponos-user-label-text { min-width: 0; }
        .ponos-user-footer-label {
            display: flex; align-items: center; gap: 8px; min-width: 0;
        }
        .ponos-card-remind-bell {
            border: 0; background: transparent; cursor: pointer; font-size: 1rem; line-height: 1; padding: 0 2px 2px;
            color: var(--kvt-perkins-blue); opacity: 0; transition: opacity 0.15s ease; flex-shrink: 0;
        }
        .ponos-card:hover .ponos-card-remind-bell.is-available { opacity: 1; }
        .ponos-card-remind-bell.is-ringing { opacity: 1; animation: ponos-bell-ring 0.45s ease-in-out infinite; }
        .ponos-card-remind-bell.is-hidden { display: none !important; }
        @keyframes ponos-bell-ring {
            0%, 100% { transform: rotate(0deg); }
            20% { transform: rotate(16deg); }
            40% { transform: rotate(-14deg); }
            60% { transform: rotate(10deg); }
            80% { transform: rotate(-8deg); }
        }
        .ponos-remind-balloon {
            position: absolute; right: 0; bottom: calc(100% + 8px); background: var(--kvt-perkins-blue); color: #fff;
            padding: 6px 10px; border-radius: 8px; font-size: 0.78rem; white-space: nowrap; box-shadow: 0 4px 12px rgba(0,0,0,.15);
            z-index: 12; pointer-events: none;
        }
        .ponos-remind-balloon::after {
            content: ''; position: absolute; right: 12px; bottom: -6px; border: 6px solid transparent;
            border-top-color: var(--kvt-perkins-blue);
        }
        .ponos-archive-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; max-height: 420px; overflow-y: auto; }
        .ponos-archive-item {
            border: 1px solid var(--kvt-line); border-radius: 10px; padding: 10px 12px; background: #fff; cursor: pointer;
            text-align: left; width: 100%; font: inherit;
        }
        .ponos-archive-item:hover { background: #edf6ff; }
        .ponos-archive-item-title { font-weight: 700; color: var(--kvt-perkins-blue); margin: 0 0 4px; }
        .ponos-archive-item-meta { font-size: 0.82rem; color: var(--kvt-muted); }
        .ponos-archive-pagination { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 14px; }
        .ponos-archive-page-info { font-size: 0.85rem; color: var(--kvt-muted); }
        .ponos-card-assignee-name {
            display: block; font-weight: 700; color: var(--kvt-perkins-blue);
        }
        .ponos-card-assignee-email { display: block; word-break: break-all; }
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
            max-height: 100%;
            overflow: hidden;
            padding: 18px 20px 20px;
            background: linear-gradient(180deg, #f8fbff 0%, #f3f7fc 100%);
        }
        .ponos-detail-chat-title {
            margin: 0 0 12px;
            flex: 0 0 auto;
            font-size: 1rem;
            color: var(--kvt-perkins-blue);
        }
        .ponos-form { display: grid; gap: 12px; }
        .ponos-form label { display: grid; gap: 6px; font-weight: 700; color: var(--kvt-perkins-blue); font-size: 0.9rem; }
        .ponos-form input, .ponos-form select, .ponos-form textarea {
            font: inherit; border-radius: 10px; border: 1px solid var(--kvt-line); padding: 10px 12px; width: 100%; box-sizing: border-box;
        }
        .ponos-checklist { display: grid; gap: 8px; }
        .ponos-form .ponos-checklist-item {
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            gap: 10px;
            font-weight: 400;
            cursor: pointer;
        }
        .ponos-form .ponos-checklist-item input[type="checkbox"] {
            flex: 0 0 auto;
            width: 1rem;
            height: 1rem;
            margin: 0.2em 0 0;
            cursor: pointer;
        }
        .ponos-form .ponos-checklist-item span {
            flex: 1 1 auto;
            line-height: 1.45;
            font-weight: 400;
        }
        .ponos-messages {
            display: grid;
            gap: 10px;
            flex: 1 1 0;
            min-height: 0;
            overflow-y: auto;
            overflow-x: visible;
            padding: 0 4px 0 12px;
            align-content: start;
        }
        .ponos-message-row {
            display: flex;
            align-items: flex-start;
            gap: 0;
            overflow: visible;
        }
        .ponos-message-avatar-wrap {
            flex: 0 0 30px;
            margin-right: -9px;
            margin-top: 6px;
            position: relative;
            z-index: 2;
        }
        .ponos-message-row .ponos-message {
            flex: 1 1 auto;
            min-width: 0;
        }
        .ponos-message {
            border: 1px solid var(--kvt-line); border-radius: 12px; padding: 10px 12px;
        }
        .ponos-message-meta { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 6px; font-size: 0.82rem; }
        .ponos-message-email {
            display: inline-block; padding: 2px 8px; border-radius: 999px; font-weight: 700;
        }
        .ponos-stats-user-label { display: inline-flex; align-items: center; gap: 8px; }
        .ponos-access-member-label { display: inline-flex; align-items: center; gap: 8px; min-width: 0; }
        .ponos-message--system { font-style: italic; color: var(--kvt-muted); background: #f8fafc; }
        .ponos-message-compose {
            display: grid;
            gap: 8px;
            flex: 0 0 auto;
            flex-shrink: 0;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--kvt-line);
            background: linear-gradient(180deg, #f8fbff 0%, #f3f7fc 100%);
        }
        .ponos-compose-label {
            display: block;
            font-weight: 700;
            color: var(--kvt-perkins-blue);
            font-size: 0.9rem;
        }
        .ponos-message-input {
            width: 100%;
            min-height: 2.75rem;
            max-height: min(32vh, 280px);
            resize: none;
            overflow-y: hidden;
            font: inherit;
            border-radius: 10px;
            border: 1px solid var(--kvt-line);
            padding: 10px 12px;
            box-sizing: border-box;
            line-height: 1.45;
        }
        .ponos-message-compose input[type="file"] {
            width: 100%;
            box-sizing: border-box;
        }
        .ponos-attachments { display: grid; gap: 6px; font-size: 0.9rem; }
        .ponos-attachments a,
        .ponos-attachment-link {
            color: var(--kvt-main-blue); background: none; border: 0; padding: 0; font: inherit;
            text-align: left; cursor: pointer; text-decoration: underline;
        }
        .ponos-modal-dialog--preview {
            width: min(920px, 96vw); max-height: min(88vh, 900px); display: flex; flex-direction: column;
        }
        .ponos-preview-head {
            display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 12px;
        }
        .ponos-preview-title { margin: 0; color: var(--kvt-perkins-blue); font-size: 1.05rem; word-break: break-word; }
        .ponos-preview-body {
            flex: 1 1 auto; min-height: 0; overflow: auto; border: 1px solid var(--kvt-line); border-radius: 10px;
            background: #fff; padding: 12px;
        }
        .ponos-preview-body.is-loading,
        .ponos-preview-body.is-error { display: grid; place-items: center; min-height: 180px; color: var(--kvt-muted); }
        .ponos-preview-image {
            display: block; max-width: 100%; height: auto; margin: 0 auto; border-radius: 8px;
        }
        .ponos-preview-code {
            margin: 0; padding: 0; background: transparent; font-size: 0.84rem; line-height: 1.5; overflow-x: auto;
        }
        .ponos-preview-code code { display: block; padding: 0; background: transparent; }
        .ponos-preview-markdown { line-height: 1.55; color: #0f172a; }
        .ponos-preview-markdown h1,
        .ponos-preview-markdown h2,
        .ponos-preview-markdown h3 { color: var(--kvt-perkins-blue); margin: 1em 0 0.5em; }
        .ponos-preview-markdown pre {
            overflow-x: auto; background: #f8fafc; border: 1px solid var(--kvt-line); border-radius: 8px; padding: 10px;
        }
        .ponos-preview-markdown code { background: #f1f5f9; padding: 1px 4px; border-radius: 4px; }
        .ponos-preview-csv-wrap { overflow: auto; }
        .ponos-preview-csv {
            width: 100%; border-collapse: collapse; font-size: 0.84rem;
        }
        .ponos-preview-csv th,
        .ponos-preview-csv td {
            border: 1px solid var(--kvt-line); padding: 6px 8px; text-align: left; vertical-align: top;
        }
        .ponos-preview-csv tr:nth-child(even) td { background: #f8fafc; }
        .ponos-preview-text {
            margin: 0; white-space: pre-wrap; word-break: break-word; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 0.84rem; line-height: 1.5;
        }
        .ponos-user-footer {
            margin-top: auto; padding-top: 12px; border-top: 1px solid var(--kvt-line);
            display: flex; align-items: center; justify-content: space-between; gap: 10px; flex: 0 0 auto;
        }
        .ponos-user-footer-name { font-weight: 700; color: var(--kvt-perkins-blue); }
        .ponos-settings-btn {
            border: 1px solid var(--kvt-line); background: #fff; border-radius: 8px; width: 34px; height: 34px;
            cursor: pointer; font-size: 1rem; line-height: 1; color: var(--kvt-perkins-blue);
        }
        .ponos-settings-checkboxes { display: grid; gap: 10px; }
        .ponos-modal-dialog .ponos-settings-checkboxes label {
            display: flex; align-items: flex-start; gap: 10px;
            font-weight: 400; color: var(--kvt-text); cursor: pointer;
        }
        .ponos-modal-dialog .ponos-settings-checkboxes input[type="checkbox"] {
            width: 1rem; height: 1rem; padding: 0; margin: 0.15em 0 0;
            flex: 0 0 auto; border-radius: 4px;
        }
        .ponos-modal-dialog .ponos-settings-checkboxes label span {
            flex: 1 1 auto; line-height: 1.45; text-align: left;
        }
        .ponos-settings-hint { margin: 0 0 14px; font-size: 0.88rem; }
        @media (max-width: 960px) {
            .ponos-body { grid-template-columns: 1fr; }
            .ponos-sidebar { border-right: 0; border-bottom: 1px solid var(--kvt-line); max-height: 280px; }
            .ponos-board { grid-template-columns: 1fr; }
            .ponos-detail-panel { width: 100%; }
            .ponos-detail-layout { grid-template-columns: 1fr; min-height: 0; height: 100%; }
            .ponos-detail-main { border-right: 0; border-bottom: 1px solid var(--kvt-line); max-height: 40vh; }
            .ponos-detail-chat { min-height: 0; max-height: none; height: auto; flex: 1 1 0; }
            .ponos-messages { min-height: 0; }
        }
    </style>
</head>
<body>
<div class="ponos-shell">
    <header class="ponos-topbar">
        <div class="ponos-topbar-left">
            <img src="logo-website.png" alt="KVT">
            <h1 class="brand-display"><?= ponos_h(LOC('ponos.hero.title')) ?></h1>
        </div>
        <div class="ponos-topbar-tools">
            <?php renderLanguageSwitcher(); ?>
        </div>
    </header>

    <?php if ($hasAdminRole): ?>
    <label class="ponos-admin-toggle" for="ponos-dev-admin">
        <span>Admin</span>
        <input type="checkbox" id="ponos-dev-admin"<?= $isAdmin ? ' checked' : '' ?>>
    </label>
    <?php endif; ?>

    <div class="ponos-body">
        <aside class="ponos-sidebar">
            <h2><?= ponos_h(LOC('ponos.sidebar.groups')) ?></h2>
            <div id="ponos-sidebar-content" class="ponos-muted"><?= ponos_h(LOC('ponos.error.load_failed')) ?></div>
            <footer id="ponos-user-footer" class="ponos-user-footer">
                <div id="ponos-user-footer-label" class="ponos-user-footer-label">
                    <span id="ponos-user-name" class="ponos-user-footer-name"><?= ponos_h($userEmail) ?></span>
                </div>
                <button type="button" id="ponos-settings-btn" class="ponos-settings-btn" title="<?= ponos_h(LOC('ponos.settings.title')) ?>">⚙</button>
            </footer>
        </aside>

        <main class="ponos-main">
            <div id="ponos-alert" class="ponos-alert" hidden></div>
            <div class="ponos-toolbar">
                <div>
                    <div class="ponos-group-heading">
                        <strong id="ponos-project-title"></strong>
                        <button type="button" id="ponos-group-pin" class="ponos-pin-btn" hidden title="">📌</button>
                        <button type="button" id="ponos-group-stats" class="ponos-group-stats-btn" hidden title="">📊</button>
                        <button type="button" id="ponos-group-admin" class="ponos-group-admin-btn" hidden title="">⚙</button>
                    </div>
                    <div id="ponos-project-subtitle" class="ponos-muted"></div>
                </div>
                <button type="button" id="ponos-new-task" class="ponos-btn" hidden><?= ponos_h(LOC('ponos.btn.new_task')) ?></button>
            </div>
            <div id="ponos-board" class="ponos-board" hidden></div>
            <p id="ponos-empty" class="ponos-muted"><?= ponos_h(LOC('ponos.empty.select_group')) ?></p>
        </main>
    </div>
</div>

<div id="ponos-group-modal" class="ponos-modal-overlay" hidden aria-hidden="true">
    <div class="ponos-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="ponos-group-modal-title">
        <h3 id="ponos-group-modal-title"></h3>
        <form id="ponos-group-form">
            <label>
                <span><?= ponos_h(LOC('ponos.label.group')) ?></span>
                <input type="text" id="ponos-group-name" name="name" required maxlength="120" autocomplete="off">
            </label>
            <div class="ponos-modal-actions">
                <button type="submit" class="ponos-btn"><?= ponos_h(LOC('ponos.btn.save')) ?></button>
                <button type="button" id="ponos-group-cancel" class="ponos-btn ponos-btn--ghost"><?= ponos_h(LOC('ponos.btn.cancel')) ?></button>
            </div>
        </form>
    </div>
</div>

<div id="ponos-category-admin-modal" class="ponos-modal-overlay" hidden aria-hidden="true">
    <div class="ponos-modal-dialog ponos-modal-dialog--wide" role="dialog" aria-modal="true" aria-labelledby="ponos-category-admin-title">
        <h3 id="ponos-category-admin-title"><?= ponos_h(LOC('ponos.group.admin_title')) ?></h3>
        <div class="ponos-admin-tabs" role="tablist">
            <button type="button" id="ponos-admin-tab-categories" class="ponos-admin-tab is-active" role="tab" aria-selected="true"><?= ponos_h(LOC('ponos.group.tab.categories')) ?></button>
            <button type="button" id="ponos-admin-tab-access" class="ponos-admin-tab" role="tab" aria-selected="false"><?= ponos_h(LOC('ponos.group.tab.access')) ?></button>
        </div>
        <div id="ponos-admin-panel-categories" class="ponos-admin-panel" role="tabpanel">
            <div class="ponos-modal-actions" style="margin-top:0;margin-bottom:14px;">
                <button type="button" id="ponos-category-add" class="ponos-btn"><?= ponos_h(LOC('ponos.btn.new_category')) ?></button>
            </div>
            <div id="ponos-category-admin-content" class="ponos-muted"><?= ponos_h(LOC('ponos.empty.no_categories')) ?></div>
        </div>
        <div id="ponos-admin-panel-access" class="ponos-admin-panel ponos-access-panel" role="tabpanel" hidden>
            <div class="ponos-access-checkboxes ponos-access-open">
                <label>
                    <input type="checkbox" id="ponos-access-open" value="1">
                    <span><?= ponos_h(LOC('ponos.access.everyone')) ?></span>
                </label>
            </div>
            <div id="ponos-access-members" class="ponos-access-members">
                <strong><?= ponos_h(LOC('ponos.access.members')) ?></strong>
                <ul id="ponos-access-member-list" class="ponos-access-member-list"></ul>
                <div class="ponos-access-add" style="margin-top:10px;">
                    <select id="ponos-access-user-select" aria-label="<?= ponos_h(LOC('ponos.access.add_member')) ?>">
                        <option value=""><?= ponos_h(LOC('ponos.access.add_member')) ?></option>
                    </select>
                    <button type="button" id="ponos-access-add-member" class="ponos-btn"><?= ponos_h(LOC('ponos.btn.save')) ?></button>
                </div>
            </div>
        </div>
        <div class="ponos-modal-actions" style="margin-top:14px;">
            <button type="button" id="ponos-category-admin-close" class="ponos-btn ponos-btn--ghost"><?= ponos_h(LOC('ponos.btn.cancel')) ?></button>
        </div>
    </div>
</div>

<div id="ponos-category-modal" class="ponos-modal-overlay" hidden aria-hidden="true">
    <div class="ponos-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="ponos-category-modal-title">
        <h3 id="ponos-category-modal-title"></h3>
        <form id="ponos-category-form">
            <label>
                <span><?= ponos_h(LOC('ponos.label.category')) ?></span>
                <input type="text" id="ponos-category-name" name="name" required maxlength="120" autocomplete="off">
            </label>
            <div class="ponos-modal-actions">
                <button type="submit" class="ponos-btn"><?= ponos_h(LOC('ponos.btn.save')) ?></button>
                <button type="button" id="ponos-category-cancel" class="ponos-btn ponos-btn--ghost"><?= ponos_h(LOC('ponos.btn.cancel')) ?></button>
            </div>
        </form>
    </div>
</div>

<div id="ponos-reminder-modal" class="ponos-modal-overlay" hidden aria-hidden="true">
    <div class="ponos-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="ponos-reminder-modal-title">
        <h3 id="ponos-reminder-modal-title"></h3>
        <div class="ponos-modal-actions">
            <button type="button" id="ponos-reminder-yes" class="ponos-btn"><?= ponos_h(LOC('ponos.reminder.yes')) ?></button>
            <button type="button" id="ponos-reminder-yes-always" class="ponos-btn"><?= ponos_h(LOC('ponos.reminder.yes_always')) ?></button>
            <button type="button" id="ponos-reminder-no" class="ponos-btn ponos-btn--ghost"><?= ponos_h(LOC('ponos.reminder.no')) ?></button>
        </div>
    </div>
</div>

<div id="ponos-archive-modal" class="ponos-modal-overlay" hidden aria-hidden="true">
    <div class="ponos-modal-dialog ponos-modal-dialog--wide" role="dialog" aria-modal="true" aria-labelledby="ponos-archive-modal-title">
        <h3 id="ponos-archive-modal-title"><?= ponos_h(LOC('ponos.archive.title')) ?></h3>
        <div id="ponos-archive-content" class="ponos-muted"><?= ponos_h(LOC('ponos.empty.no_archived_tasks')) ?></div>
        <div class="ponos-archive-pagination">
            <button type="button" id="ponos-archive-prev" class="ponos-btn ponos-btn--ghost"><?= ponos_h(LOC('ponos.btn.prev_page')) ?></button>
            <span id="ponos-archive-page-info" class="ponos-archive-page-info"></span>
            <button type="button" id="ponos-archive-next" class="ponos-btn ponos-btn--ghost"><?= ponos_h(LOC('ponos.btn.next_page')) ?></button>
        </div>
        <div class="ponos-modal-actions" style="margin-top:14px;">
            <button type="button" id="ponos-archive-close" class="ponos-btn ponos-btn--ghost"><?= ponos_h(LOC('ponos.btn.cancel')) ?></button>
        </div>
    </div>
</div>

<div id="ponos-stats-modal" class="ponos-modal-overlay" hidden aria-hidden="true">
    <div class="ponos-modal-dialog ponos-modal-dialog--stats" role="dialog" aria-modal="true" aria-labelledby="ponos-stats-modal-title">
        <h3 id="ponos-stats-modal-title"><?= ponos_h(LOC('ponos.stats.title')) ?></h3>
        <div id="ponos-stats-content" class="ponos-muted"><?= ponos_h(LOC('ponos.error.load_failed')) ?></div>
        <div class="ponos-modal-actions" style="margin-top:14px;">
            <button type="button" id="ponos-stats-close" class="ponos-btn ponos-btn--ghost"><?= ponos_h(LOC('ponos.btn.cancel')) ?></button>
        </div>
    </div>
</div>

<div id="ponos-settings-modal" class="ponos-modal-overlay" hidden aria-hidden="true">
    <div class="ponos-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="ponos-settings-modal-title">
        <h3 id="ponos-settings-modal-title"><?= ponos_h(LOC('ponos.settings.title')) ?></h3>
        <p class="ponos-muted ponos-settings-hint"><?= ponos_h(LOC('ponos.settings.hint')) ?></p>
        <form id="ponos-settings-form" class="ponos-settings-checkboxes">
            <label><input type="checkbox" name="assigned" value="1"> <span><?= ponos_h(LOC('ponos.settings.assigned')) ?></span></label>
            <label><input type="checkbox" name="status_changed" value="1"> <span><?= ponos_h(LOC('ponos.settings.status_changed')) ?></span></label>
            <label><input type="checkbox" name="message" value="1"> <span><?= ponos_h(LOC('ponos.settings.message')) ?></span></label>
            <label><input type="checkbox" name="checklist" value="1"> <span><?= ponos_h(LOC('ponos.settings.checklist')) ?></span></label>
            <label><input type="checkbox" name="daily_reminder" value="1"> <span><?= ponos_h(LOC('ponos.settings.daily_reminder')) ?></span></label>
            <div class="ponos-modal-actions">
                <button type="submit" class="ponos-btn"><?= ponos_h(LOC('ponos.btn.save')) ?></button>
                <button type="button" id="ponos-settings-cancel" class="ponos-btn ponos-btn--ghost"><?= ponos_h(LOC('ponos.btn.cancel')) ?></button>
            </div>
        </form>
    </div>
</div>

<div id="ponos-danger-modal" class="ponos-danger-overlay" hidden aria-hidden="true">
    <div class="ponos-danger-dialog" role="alertdialog" aria-modal="true" aria-labelledby="ponos-danger-title">
        <div class="ponos-warning-tape-wrap" aria-hidden="true">
            <div class="ponos-warning-tape"></div>
        </div>
        <div class="ponos-danger-body">
            <h3 id="ponos-danger-title"><?= ponos_h(LOC('ponos.group.delete_confirm_title')) ?></h3>
            <p><?= ponos_h(LOC('ponos.group.delete_confirm_message')) ?></p>
            <div class="ponos-danger-actions">
                <button type="button" id="ponos-danger-confirm" class="ponos-btn ponos-btn--danger"><?= ponos_h(LOC('ponos.group.delete_confirm_yes')) ?></button>
                <button type="button" id="ponos-danger-cancel" class="ponos-btn ponos-btn--ghost"><?= ponos_h(LOC('ponos.group.delete_confirm_no')) ?></button>
            </div>
        </div>
    </div>
</div>

<div id="ponos-preview-modal" class="ponos-modal-overlay" hidden aria-hidden="true">
    <div class="ponos-modal-dialog ponos-modal-dialog--preview" role="dialog" aria-modal="true" aria-labelledby="ponos-preview-title">
        <div class="ponos-preview-head">
            <h3 id="ponos-preview-title" class="ponos-preview-title"></h3>
            <button type="button" id="ponos-preview-close" class="ponos-btn ponos-btn--ghost"><?= ponos_h(LOC('ponos.preview.close')) ?></button>
        </div>
        <div id="ponos-preview-body" class="ponos-preview-body is-loading"><?= ponos_h(LOC('ponos.preview.loading')) ?></div>
        <div class="ponos-modal-actions" style="margin-top:14px;">
            <a id="ponos-preview-download" class="ponos-btn" href="#" download hidden><?= ponos_h(LOC('ponos.preview.download')) ?></a>
        </div>
    </div>
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
                <button type="button" id="ponos-unarchive-task" class="ponos-btn ponos-btn--ghost" hidden><?= ponos_h(LOC('ponos.btn.unarchive')) ?></button>
                <button type="button" id="ponos-edit-task" class="ponos-btn ponos-btn--ghost"><?= ponos_h(LOC('ponos.btn.edit')) ?></button>
                <button type="button" id="ponos-close-detail" class="ponos-btn ponos-btn--ghost">✕</button>
            </div>
        </div>
        <div class="ponos-detail-body" id="ponos-detail-body"></div>
    </div>
</div>

<script>
window.PONOS_BOOT = <?= json_encode([
    'group' => $group,
    'task' => $taskId,
    'userEmail' => $userEmail,
    'isAdmin' => $isAdmin,
    'isLocalhost' => ponos_is_localhost_request(),
    'statuses' => [
        'todo' => LOC('ponos.status.todo'),
        'in_progress' => LOC('ponos.status.in_progress'),
        'done' => LOC('ponos.status.done'),
    ],
    'i18n' => $i18n,
    'dateLocale' => getDateLocale(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.9.0/build/styles/github.min.css" crossorigin="anonymous">
<script src="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.9.0/build/highlight.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/marked@12.0.2/marked.min.js" crossorigin="anonymous"></script>
<script src="ponos.js"></script>
<?php renderLanguageSwitcherScript(); ?>
</body>
</html>
