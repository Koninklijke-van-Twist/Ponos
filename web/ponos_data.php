<?php

/**
 * Includes/requires
 */
require_once __DIR__ . '/localization.php';
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/odata.php';

/**
 * Constants
 */

const PONOS_DEFAULT_USER_EMAIL = 'localtester@kvt.nl';

const PONOS_PROJECTS_TTL = 3600;

const PONOS_DIMENSION_NAMES_TTL = 86400;

const PONOS_TASK_CATEGORIES = [
    'Algemeen',
    'Inkoop',
    'Engineering',
    'Productie',
    'Installatie',
    'Administratie',
];

const PONOS_STATUS_TODO = 'todo';

const PONOS_STATUS_IN_PROGRESS = 'in_progress';

const PONOS_STATUS_DONE = 'done';

/**
 * Functies
 */

function ponos_escape_odata_string(string $value): string
{
    return str_replace("'", "''", trim($value));
}

function ponos_company_entity_url(string $baseUrl, string $environment, string $company, string $entitySet, array $query): string
{
    $safeCompany = ponos_escape_odata_string($company);
    $companySegment = "Company('" . rawurlencode($safeCompany) . "')";
    $url = rtrim($baseUrl, '/') . '/' . rawurlencode($environment) . '/ODataV4/' . $companySegment . '/' . rawurlencode($entitySet);

    if ($query !== []) {
        $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    return $url;
}

function ponos_fetch_rows(string $company, string $entitySet, array $query, int $ttl = PONOS_PROJECTS_TTL): array
{
    global $baseUrl;

    $environment = auth_get_environment_for_company($company, $ttl);
    $auth = auth_get_auth_for_environment($environment);
    $url = ponos_company_entity_url($baseUrl, $environment, $company, $entitySet, $query);

    return odata_get_all($url, $auth, $ttl);
}

function ponos_try_fetch_rows(string $company, string $entitySet, array $query, int $ttl = PONOS_PROJECTS_TTL): array
{
    try {
        return ponos_fetch_rows($company, $entitySet, $query, $ttl);
    } catch (Throwable $error) {
        return [];
    }
}

function ponos_default_companies(): array
{
    return [
        'Koninklijke van Twist',
        'Hunter van Twist',
        'KVT Gas',
    ];
}

function ponos_companies_for_page(int $ttl = PONOS_PROJECTS_TTL): array
{
    try {
        $result = auth_discover_companies_across_active_environments($ttl);
        $companies = is_array($result['companies'] ?? null) ? $result['companies'] : [];
        if ($companies !== []) {
            return $companies;
        }
    } catch (Throwable $ignored) {
    }

    return ponos_default_companies();
}

function ponos_current_user_email(): string
{
    $email = strtolower(trim((string) ($_SESSION['user']['email'] ?? '')));

    return $email !== '' ? $email : PONOS_DEFAULT_USER_EMAIL;
}

function ponos_normalize_company_key(string $company): string
{
    $key = strtolower(trim($company));
    $key = preg_replace('/[^a-z0-9]+/i', '_', $key) ?? '';

    return trim($key, '_');
}

function ponos_data_dir(): string
{
    $dir = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'ponos';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    return $dir;
}

function ponos_department_cache_path(string $company): string
{
    return ponos_data_dir() . DIRECTORY_SEPARATOR . 'departments_' . ponos_normalize_company_key($company) . '.json';
}

function ponos_load_department_cache(string $company): array
{
    $path = ponos_department_cache_path($company);
    if (!is_file($path)) {
        return ['codes' => []];
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return ['codes' => []];
    }

    $codes = is_array($decoded['codes'] ?? null) ? $decoded['codes'] : [];

    return ['codes' => $codes];
}

function ponos_save_department_cache(string $company, array $cache): void
{
    $path = ponos_department_cache_path($company);
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    $payload = [
        'updated_at' => gmdate('c'),
        'codes' => is_array($cache['codes'] ?? null) ? $cache['codes'] : [],
    ];

    file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function ponos_merge_department_codes(string $company, array $codes): array
{
    $cache = ponos_load_department_cache($company);
    $stored = is_array($cache['codes'] ?? null) ? $cache['codes'] : [];
    $now = gmdate('c');

    foreach ($codes as $code) {
        $code = trim((string) $code);
        if ($code === '') {
            continue;
        }

        if (!isset($stored[$code])) {
            $stored[$code] = [
                'code' => $code,
                'first_seen' => $now,
            ];
        }
    }

    ksort($stored, SORT_NATURAL | SORT_FLAG_CASE);
    ponos_save_department_cache($company, ['codes' => $stored]);

    return $stored;
}

function ponos_normalize_project_row(array $row): array
{
    return [
        'no' => trim((string) ($row['No'] ?? '')),
        'description' => trim((string) ($row['Description'] ?? '')),
        'status' => trim((string) ($row['Status'] ?? '')),
        'department_code' => trim((string) ($row['LVS_Global_Dimension_1_Code'] ?? '')),
        'project_manager' => trim((string) ($row['Project_Manager'] ?? '')),
    ];
}

function ponos_fetch_projects_for_company(string $company, int $ttl = PONOS_PROJECTS_TTL): array
{
    $rows = ponos_try_fetch_rows($company, 'AppProjecten', [
        '$select' => 'No,Description,Status,Project_Manager,LVS_Global_Dimension_1_Code',
        '$filter' => "No ne ''",
    ], $ttl);

    $projects = [];
    $departmentCodes = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $normalized = ponos_normalize_project_row($row);
        if ($normalized['no'] === '') {
            continue;
        }

        $projects[] = $normalized;
        if ($normalized['department_code'] !== '') {
            $departmentCodes[] = $normalized['department_code'];
        }
    }

    ponos_merge_department_codes($company, $departmentCodes);

    usort($projects, static function (array $left, array $right): int {
        return strnatcasecmp($left['no'], $right['no']);
    });

    return $projects;
}

function ponos_fetch_dimension_names(string $company, int $ttl = PONOS_DIMENSION_NAMES_TTL): array
{
    $rows = ponos_try_fetch_rows($company, 'DimensionValueList', [
        '$select' => 'Dimension_Code,Code,Name',
    ], $ttl);

    $names = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $code = trim((string) ($row['Code'] ?? ''));
        $name = trim((string) ($row['Name'] ?? ''));
        if ($code === '') {
            continue;
        }

        if ($name === '' || isset($names[$code])) {
            continue;
        }

        $names[$code] = $name;
    }

    return $names;
}

function ponos_department_label(string $code, array $dimensionNames): string
{
    $code = trim($code);
    if ($code === '') {
        return '';
    }

    $name = trim((string) ($dimensionNames[$code] ?? ''));
    if ($name !== '') {
        return $name;
    }

    return $code;
}

function ponos_build_departments_payload(string $company, array $projects, array $dimensionNames): array
{
    $cache = ponos_load_department_cache($company);
    $codes = is_array($cache['codes'] ?? null) ? $cache['codes'] : [];

    $departments = [];
    foreach ($codes as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $code = trim((string) ($entry['code'] ?? ''));
        if ($code === '') {
            continue;
        }

        $departments[] = [
            'code' => $code,
            'label' => ponos_department_label($code, $dimensionNames),
            'first_seen' => (string) ($entry['first_seen'] ?? ''),
        ];
    }

    usort($departments, static function (array $left, array $right): int {
        return strnatcasecmp($left['label'], $right['label']);
    });

    $projectsByDepartment = [];
    foreach ($projects as $project) {
        $dept = trim((string) ($project['department_code'] ?? ''));
        if ($dept === '') {
            $dept = '_none';
        }

        if (!isset($projectsByDepartment[$dept])) {
            $projectsByDepartment[$dept] = [];
        }

        $projectsByDepartment[$dept][] = $project;
    }

    return [
        'departments' => $departments,
        'projects_by_department' => $projectsByDepartment,
    ];
}

function ponos_group_projects_by_department(array $projects): array
{
    $grouped = [];
    foreach ($projects as $project) {
        $dept = trim((string) ($project['department_code'] ?? ''));
        if ($dept === '') {
            $dept = '_none';
        }

        if (!isset($grouped[$dept])) {
            $grouped[$dept] = [];
        }

        $grouped[$dept][] = $project;
    }

    return $grouped;
}

function ponos_resolve_company_choice(array $companies, string $requested, string $saved): string
{
    $requested = trim($requested);
    if ($requested !== '') {
        foreach ($companies as $company) {
            if (strcasecmp($company, $requested) === 0) {
                return $company;
            }
        }
    }

    $saved = trim($saved);
    if ($saved !== '') {
        foreach ($companies as $company) {
            if (strcasecmp($company, $saved) === 0) {
                return $company;
            }
        }
    }

    return (string) ($companies[0] ?? '');
}

function ponos_load_user_navigation_prefs(string $email): array
{
    if (!function_exists('loadUserPrefs')) {
        return [];
    }

    $prefs = loadUserPrefs($email);

    return [
        'company' => trim((string) ($prefs['ponos_company'] ?? '')),
        'department' => trim((string) ($prefs['ponos_department'] ?? '')),
    ];
}

function ponos_save_user_navigation_prefs(string $email, string $company, string $department): void
{
    if (!function_exists('saveUserPref')) {
        return;
    }

    if ($company !== '') {
        saveUserPref($email, 'ponos_company', $company);
    }

    if ($department !== '') {
        saveUserPref($email, 'ponos_department', $department);
    }
}

function ponos_hash_text_for_color(string $text): int
{
    $hash = 0;
    $length = strlen($text);
    for ($index = 0; $index < $length; $index++) {
        $hash = ord($text[$index]) + (($hash << 5) - $hash);
    }

    return $hash;
}

function ponos_color_from_text(string $text): array
{
    $normalized = strtolower(trim($text));
    if ($normalized === '') {
        return [
            'border' => '#cbd5e1',
            'dark' => '#64748b',
            'light' => '#94a3b8',
            'chipBackground' => '#e2e8f0',
            'cardBackground' => '#ffffff',
            'chipTextColor' => '#334155',
        ];
    }

    $hash = ponos_hash_text_for_color($normalized);
    $hue = abs($hash) % 360;
    $saturation = 72 + (abs($hash >> 8) % 14);
    $lightness = 56 + (abs($hash >> 16) % 10);
    $borderLightness = max($lightness - 6, 48);
    $darkLightness = max($lightness - 18, 32);
    $chipTextColor = $lightness >= 58 ? '#1e293b' : '#ffffff';

    return [
        'border' => "hsl({$hue}, {$saturation}%, {$borderLightness}%)",
        'dark' => "hsl({$hue}, {$saturation}%, {$darkLightness}%)",
        'light' => "hsl({$hue}, {$saturation}%, {$lightness}%)",
        'chipBackground' => "hsl({$hue}, {$saturation}%, {$lightness}%)",
        'cardBackground' => 'hsl(' . $hue . ', ' . min($saturation, 48) . '%, 96%)',
        'chipTextColor' => $chipTextColor,
    ];
}

function ponos_status_label(string $status): string
{
    return match ($status) {
        PONOS_STATUS_IN_PROGRESS => LOC('ponos.status.in_progress'),
        PONOS_STATUS_DONE => LOC('ponos.status.done'),
        default => LOC('ponos.status.todo'),
    };
}

function ponos_all_statuses(): array
{
    return [PONOS_STATUS_TODO, PONOS_STATUS_IN_PROGRESS, PONOS_STATUS_DONE];
}

function ponos_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ponos_url(array $params = []): string
{
    $query = $_GET;
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
            continue;
        }
        $query[$key] = $value;
    }

    unset($query['lang']);
    $path = strtok((string) ($_SERVER['REQUEST_URI'] ?? 'index.php'), '?') ?: 'index.php';
    $query['lang'] = getCurrentLanguage();

    return $path . '?' . http_build_query($query);
}
