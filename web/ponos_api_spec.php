<?php

/**
 * Machine-readable API spec for Grok/LLM bots (Forum Magnum help/spec style).
 */

function ponos_spec_field(string $name, string $type, bool $required, string $description = '', array $aliases = [], array $extra = []): array
{
    $field = array_merge([
        'name' => $name,
        'type' => $type,
        'required' => $required,
        'description' => $description,
    ], $extra);
    if ($aliases !== []) {
        $field['aliases'] = array_values($aliases);
    }

    return $field;
}

function ponos_spec_action(
    array $methods,
    string $auth,
    bool $authRequired,
    array $fields,
    array $response,
    array $errors,
    string $result,
    string $audience = 'bot'
): array {
    return [
        'methods' => array_values($methods),
        'method' => implode('|', $methods),
        'auth' => $auth,
        'auth_required' => $authRequired,
        'audience' => $audience,
        'fields' => array_values($fields),
        'response' => $response,
        'errors' => array_values($errors),
        'result' => $result,
    ];
}

function ponos_api_help(): array
{
    $authError = ['status' => 401, 'error' => 'Unauthorized.', 'when' => 'missing or invalid API key / session'];
    $missingParam = ['status' => 400, 'when' => 'required field missing'];
    $notFound = ['status' => 404, 'when' => 'group or task not found (or not visible)'];
    $forbidden = ['status' => 403, 'when' => 'user cannot edit this group'];

    $taskFields = [
        ponos_spec_field('title', 'string', true, 'Task title (max 200 chars)'),
        ponos_spec_field('description', 'string', true, 'Task notes/body. This is the Ponos notes field; there is no separate notes column.'),
        ponos_spec_field('assignee_email', 'string', false, 'Assignee; must have group access or it is stored empty'),
        ponos_spec_field('due_date', 'string', false, 'Deadline YYYY-MM-DD'),
        ponos_spec_field('category_id', 'string', false, 'Group category id. Closest thing to priority/tags; Ponos has no priority field.'),
        ponos_spec_field('checklist', 'array|json-string', false, 'Subtasks: JSON array of strings, or JSON string of that array'),
        ponos_spec_field('status', 'string', false, 'todo | in_progress | done. Create defaults to todo. Use update_status / complete_task to change.'),
        ponos_spec_field('actor_name', 'string', false, 'Optional visible actor on the created-task system message. Display-only. Empty uses the key default or the API-key owner name.'),
    ];

    return [
        'ok' => true,
        'name' => 'Ponos',
        'version' => '1',
        'spec_version' => 1,
        'purpose' => 'Work tasks per group (kanban). Same domain as the Ponos UI — do not invent a parallel task model.',
        'endpoint' => [
            'path' => 'ponos_api.php',
            'url_shape' => 'ponos_api.php?action={action}',
            'action_field' => 'action',
            'content_type' => 'application/json or application/x-www-form-urlencoded or multipart/form-data',
            'accept' => 'application/json',
            'query_or_body' => 'action and fields may be query params, JSON body, or form fields. Multipart is required when uploading attachments.',
        ],
        'task_model' => [
            'id' => 'string task id',
            'group_id' => 'string group id (required to create/list; use group=__my_tasks__ to list tasks assigned to the auth user)',
            'title' => 'string',
            'description' => 'string notes/body',
            'status' => 'todo | in_progress | done',
            'assignee_email' => 'string',
            'due_date' => 'YYYY-MM-DD or empty',
            'category_id' => 'string or empty',
            'category_label' => 'resolved category name',
            'checklist' => 'array of {id,label,done}',
            'messages' => 'discussion thread; add via add_message',
            'is_archived' => 'derived: done tasks older than the archive cutoff disappear from the board',
            'no_priority_field' => 'Ponos has no priority column. Use category and/or due_date to organize urgency.',
        ],
        'auth' => [
            'header' => 'X-API-Key',
            'or' => 'api_key JSON or form body field (not query string)',
            'bearer' => 'Authorization: Bearer <key>',
            'durable_keys' => 'Fixed Ponos API keys (prefix ponos_). Hashed at rest (sha256). Suitable for a Grok bot keystore. Not the rotating daily login analytics key.',
            'session' => 'Browser Office365 session cookie still works for the web UI. Bots should use X-API-Key and skip cookies.',
            'identity' => 'A key acts as the user_email it was minted for. Group access and assignee filters follow that user. Optional actor_name is display-only and does not change identity or privileges.',
            'actor_name' => 'Optional visible name on task messages/activity. Request field actor_name overrides the key default. Empty or omitted uses the API-key owner name. Hover in the UI shows an integration tooltip with the owner display name.',
            'mint' => [
                'ui' => 'Ponos settings panel (gear icon): create, list, and revoke keys while logged in. Primary path for humans. Plaintext is shown once in the UI.',
                'cli' => 'php web/ponos_api_key.php create EMAIL [label]',
                'cli_list' => 'php web/ponos_api_key.php list [EMAIL]',
                'cli_revoke' => 'php web/ponos_api_key.php revoke ID [EMAIL]',
                'cli_note' => 'CLI is an optional admin fallback; prefer the settings UI.',
                'api' => 'POST action=create_api_key with a browser session or an existing key of the same user. Plaintext is returned once. Do not put the key in the query string.',
                'storage' => 'web/data/ponos/ponos.sqlite table api_keys; only sha256 hash + prefix stored',
            ],
            'roles' => [
                'none' => [
                    'required' => false,
                    'how' => 'omit X-API-Key and api_key',
                    'actions' => ['help', 'spec'],
                ],
                'api_key' => [
                    'required' => true,
                    'how' => 'X-API-Key or Authorization Bearer. Optional JSON/form body field api_key. Never put the key in the query string.',
                    'actions' => 'all task/group actions below, plus whoami and api-key management for that user',
                ],
                'human_session' => [
                    'required' => true,
                    'how' => 'browser session cookie from Office365 login; not for bots',
                    'actions' => 'same as api_key; this is what the Ponos UI uses',
                ],
            ],
        ],
        'bot_actions' => [
            'help', 'spec', 'whoami',
            'navigation', 'list_tasks', 'get_task', 'create_task', 'update_task',
            'update_status', 'complete_task', 'move_task',
            'list_archived_tasks', 'unarchive_task',
            'toggle_checklist', 'add_message',
            'list_categories', 'list_api_keys', 'create_api_key', 'revoke_api_key',
        ],
        'secretary_flow' => [
            '1' => 'GET ponos_api.php?action=help (no auth) to discover this document',
            '2' => 'Store the durable key as X-API-Key',
            '3' => 'GET action=whoami to confirm user_email',
            '4' => 'GET action=navigation to list groups (id + name). Use group=__my_tasks__ for the assignee inbox',
            '5' => 'GET action=list_tasks&group={id} then get_task / create_task / update_task / update_status',
            '6' => 'Mark work done with action=complete_task or update_status status=done',
            '7' => 'Archived done tasks: list_archived_tasks / unarchive_task (archive is automatic after the cutoff; there is no manual archive action)',
        ],
        'errors' => [
            $authError,
            $missingParam,
            $forbidden,
            $notFound,
            ['status' => 400, 'when' => 'unknown action or invalid input'],
            ['status' => 400, 'error' => 'Invalid JSON body', 'when' => 'Content-Type application/json with malformed JSON or a non-object body'],
            ['status' => 409, 'when' => 'delete_group needs confirm=1'],
        ],
        'actions' => [
            'help' => ponos_spec_action(
                ['GET', 'POST'],
                'none',
                false,
                [ponos_spec_field('action', 'string', false, 'help or spec or omit; both return this document')],
                ['ok' => true, 'name' => 'Ponos', 'spec_version' => 1, 'actions' => 'object keyed by action name'],
                [],
                'This document. No auth. spec is an alias.'
            ),
            'spec' => ponos_spec_action(
                ['GET', 'POST'],
                'none',
                false,
                [ponos_spec_field('action', 'string', true, 'Must be spec; identical to help')],
                ['same_as' => 'help'],
                [],
                'Alias of help. No auth.'
            ),
            'whoami' => ponos_spec_action(
                ['GET', 'POST'],
                'api_key_or_session',
                true,
                [ponos_spec_field('action', 'string', true, 'whoami')],
                ['ok' => true, 'user_email' => 'string', 'is_admin' => 'bool', 'auth' => 'api_key|session', 'actor_name' => 'string, set only for api_key auth'],
                [$authError],
                'Identity of the current API key or session user. actor_name is the resolved display name for this request (empty when using the owner name or a browser session).'
            ),
            'navigation' => ponos_spec_action(
                ['GET', 'POST'],
                'api_key_or_session',
                true,
                [ponos_spec_field('action', 'string', true, 'navigation')],
                ['ok' => true, 'groups' => 'array', 'statuses' => 'map', 'user_email' => 'string'],
                [$authError],
                'List groups and status labels. Start here to find group ids.'
            ),
            'list_tasks' => ponos_spec_action(
                ['GET', 'POST'],
                'api_key_or_session',
                true,
                [
                    ponos_spec_field('action', 'string', true, 'list_tasks'),
                    ponos_spec_field('group', 'string', true, 'Group id, or __my_tasks__ for tasks assigned to the current user'),
                ],
                ['ok' => true, 'tasks' => 'array of board-visible tasks', 'board_revision' => 'string'],
                [$authError, $missingParam, $notFound],
                'Active (non-archived) tasks in the group or My Tasks view.'
            ),
            'get_task' => ponos_spec_action(
                ['GET', 'POST'],
                'api_key_or_session',
                true,
                [
                    ponos_spec_field('action', 'string', true, 'get_task'),
                    ponos_spec_field('group', 'string', true, 'Group id or __my_tasks__'),
                    ponos_spec_field('task', 'string', true, 'Task id'),
                ],
                ['ok' => true, 'task' => 'task including messages and checklist'],
                [$authError, $missingParam, $notFound],
                'Full task. Description is the notes field.'
            ),
            'create_task' => ponos_spec_action(
                ['POST'],
                'api_key_or_session',
                true,
                array_merge(
                    [
                        ponos_spec_field('action', 'string', true, 'create_task'),
                        ponos_spec_field('group', 'string', true, 'Target group id (not __my_tasks__)'),
                    ],
                    $taskFields
                ),
                ['ok' => true, 'task' => 'created task'],
                [$authError, $missingParam, $forbidden, $notFound],
                'Create a task. title and description are required. Cannot create in My Tasks; pick a real group.'
            ),
            'update_task' => ponos_spec_action(
                ['POST'],
                'api_key_or_session',
                true,
                [
                    ponos_spec_field('action', 'string', true, 'update_task'),
                    ponos_spec_field('group', 'string', true, 'Current group or __my_tasks__'),
                    ponos_spec_field('task', 'string', true, 'Task id'),
                    ponos_spec_field('title', 'string', false, 'New title'),
                    ponos_spec_field('description', 'string', false, 'New notes/body'),
                    ponos_spec_field('assignee_email', 'string', false, 'New assignee; empty string clears'),
                    ponos_spec_field('due_date', 'string', false, 'New deadline YYYY-MM-DD; empty string clears'),
                    ponos_spec_field('category_id', 'string', false, 'New category id'),
                    ponos_spec_field('checklist', 'array|json-string', false, 'Replace checklist labels'),
                    ponos_spec_field('target_group', 'string', false, 'Optional move to another group'),
                    ponos_spec_field('actor_name', 'string', false, 'Optional visible actor on the update system message. Display-only. Empty uses the key default or the API-key owner name.'),
                ],
                ['ok' => true, 'task' => 'updated task'],
                [$authError, $missingParam, $forbidden, $notFound],
                'Partial update of existing Ponos fields. Omitted fields stay unchanged. Status is not updated here — use update_status.'
            ),
            'update_status' => ponos_spec_action(
                ['POST'],
                'api_key_or_session',
                true,
                [
                    ponos_spec_field('action', 'string', true, 'update_status'),
                    ponos_spec_field('group', 'string', true, 'Group id or __my_tasks__'),
                    ponos_spec_field('task', 'string', true, 'Task id'),
                    ponos_spec_field('status', 'string', true, 'todo | in_progress | done'),
                    ponos_spec_field('actor_name', 'string', false, 'Optional visible actor on the status-change system message. Display-only.'),
                ],
                ['ok' => true, 'task' => 'task with new status'],
                [$authError, $missingParam, $forbidden, $notFound],
                'Move a task between kanban columns. status=done is complete.'
            ),
            'complete_task' => ponos_spec_action(
                ['POST'],
                'api_key_or_session',
                true,
                [
                    ponos_spec_field('action', 'string', true, 'complete_task'),
                    ponos_spec_field('group', 'string', true, 'Group id or __my_tasks__'),
                    ponos_spec_field('task', 'string', true, 'Task id'),
                    ponos_spec_field('actor_name', 'string', false, 'Optional visible actor on the completion system message. Display-only.'),
                ],
                ['ok' => true, 'task' => 'task with status=done'],
                [$authError, $missingParam, $forbidden, $notFound],
                'Alias of update_status with status=done.'
            ),
            'move_task' => ponos_spec_action(
                ['POST'],
                'api_key_or_session',
                true,
                [
                    ponos_spec_field('action', 'string', true, 'move_task'),
                    ponos_spec_field('group', 'string', true, 'Current group'),
                    ponos_spec_field('task', 'string', true, 'Task id'),
                    ponos_spec_field('target_group', 'string', true, 'Destination group id'),
                    ponos_spec_field('actor_name', 'string', false, 'Optional visible actor on the move system message. Display-only.'),
                ],
                ['ok' => true, 'task' => 'moved task'],
                [$authError, $missingParam, $forbidden, $notFound],
                'Move a task to another group.'
            ),
            'list_archived_tasks' => ponos_spec_action(
                ['GET', 'POST'],
                'api_key_or_session',
                true,
                [
                    ponos_spec_field('action', 'string', true, 'list_archived_tasks'),
                    ponos_spec_field('group', 'string', true, 'Group id or __my_tasks__'),
                    ponos_spec_field('page', 'integer', false, 'Page number, default 1'),
                ],
                ['ok' => true, 'tasks' => 'array', 'page' => 'int', 'total' => 'int', 'total_pages' => 'int'],
                [$authError, $missingParam, $notFound],
                'Done tasks that aged into the archive. There is no manual archive action.'
            ),
            'unarchive_task' => ponos_spec_action(
                ['POST'],
                'api_key_or_session',
                true,
                [
                    ponos_spec_field('action', 'string', true, 'unarchive_task'),
                    ponos_spec_field('group', 'string', true, 'Group id or __my_tasks__'),
                    ponos_spec_field('task', 'string', true, 'Task id'),
                    ponos_spec_field('actor_name', 'string', false, 'Optional visible actor on the unarchive system message. Display-only.'),
                ],
                ['ok' => true, 'task' => 'restored task'],
                [$authError, $missingParam, $forbidden, $notFound],
                'Bring an archived done task back onto the board for one more week.'
            ),
            'toggle_checklist' => ponos_spec_action(
                ['POST'],
                'api_key_or_session',
                true,
                [
                    ponos_spec_field('action', 'string', true, 'toggle_checklist'),
                    ponos_spec_field('group', 'string', true, 'Group id or __my_tasks__'),
                    ponos_spec_field('task', 'string', true, 'Task id'),
                    ponos_spec_field('item_id', 'integer', true, 'Checklist item id'),
                    ponos_spec_field('done', 'bool', false, 'Default true'),
                    ponos_spec_field('actor_name', 'string', false, 'Optional visible actor if this action creates activity. Display-only.'),
                ],
                ['ok' => true, 'task' => 'updated task'],
                [$authError, $missingParam, $forbidden, $notFound],
                'Check or uncheck a subtask.'
            ),
            'add_message' => ponos_spec_action(
                ['POST'],
                'api_key_or_session',
                true,
                [
                    ponos_spec_field('action', 'string', true, 'add_message'),
                    ponos_spec_field('group', 'string', true, 'Group id or __my_tasks__'),
                    ponos_spec_field('task', 'string', true, 'Task id'),
                    ponos_spec_field('text', 'string', true, 'Message body (threaded notes)'),
                    ponos_spec_field('actor_name', 'string', false, 'Optional visible sender name. Display-only; email/privileges stay with the API-key owner. Empty uses the key default or the owner name. Hover shows an integration tooltip with the owner.'),
                ],
                ['ok' => true, 'message' => 'stored message'],
                [$authError, $missingParam, $forbidden, $notFound],
                'Add a discussion note on a task.'
            ),
            'list_categories' => ponos_spec_action(
                ['GET', 'POST'],
                'api_key_or_session',
                true,
                [
                    ponos_spec_field('action', 'string', true, 'list_categories'),
                    ponos_spec_field('group', 'string', true, 'Group id'),
                ],
                ['ok' => true, 'categories' => 'array of {id,name}'],
                [$authError, $missingParam, $notFound],
                'Categories for a group. Use these instead of a priority field.'
            ),
            'create_api_key' => ponos_spec_action(
                ['POST'],
                'api_key_or_session',
                true,
                [
                    ponos_spec_field('action', 'string', true, 'create_api_key'),
                    ponos_spec_field('label', 'string', false, 'Key label, default bot. Used to identify the key in settings, not as the visible message name.'),
                    ponos_spec_field('actor_name', 'string', false, 'Optional default visible actor name for this key. Per-request actor_name overrides it. Empty = owner name.'),
                ],
                ['ok' => true, 'api_key' => 'plaintext shown once', 'key' => 'public metadata without secret'],
                [$authError],
                'Mint a durable key for the current user. Store api_key immediately; it is not stored in plaintext.'
            ),
            'list_api_keys' => ponos_spec_action(
                ['GET', 'POST'],
                'api_key_or_session',
                true,
                [ponos_spec_field('action', 'string', true, 'list_api_keys')],
                ['ok' => true, 'keys' => 'array of {id,label,actor_name,key_prefix,created_at,last_used_at} — no secrets'],
                [$authError],
                'List active keys for the current user. Only prefixes are shown.'
            ),
            'revoke_api_key' => ponos_spec_action(
                ['POST'],
                'api_key_or_session',
                true,
                [
                    ponos_spec_field('action', 'string', true, 'revoke_api_key'),
                    ponos_spec_field('id', 'integer', true, 'Key id from list_api_keys', ['key_id']),
                ],
                ['ok' => true],
                [$authError, $notFound],
                'Revoke one of the current user keys.'
            ),
        ],
    ];
}
