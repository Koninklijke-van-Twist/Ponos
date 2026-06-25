<?php

/**
 * Includes/requires
 */
require_once __DIR__ . '/localization.php';

/**
 * Constants
 */

const PONOS_DEFAULT_USER_EMAIL = 'localtester@kvt.nl';

const PONOS_GROUP_MY_TASKS = '__my_tasks__';

const PONOS_STATUS_TODO = 'todo';

const PONOS_STATUS_IN_PROGRESS = 'in_progress';

const PONOS_STATUS_DONE = 'done';

/**
 * Functies
 */

function ponos_current_user_email(): string
{
    $email = strtolower(trim((string) ($_SESSION['user']['email'] ?? '')));

    return $email !== '' ? $email : PONOS_DEFAULT_USER_EMAIL;
}

function ponos_current_user_is_admin(): bool
{
    if (function_exists('is_trusted_requester') && is_trusted_requester()) {
        return true;
    }

    return !empty($_SESSION['user']['admin']);
}

function ponos_data_dir(): string
{
    $dir = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'ponos';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    return $dir;
}

function ponos_load_user_navigation_prefs(string $email): array
{
    if (!function_exists('loadUserPrefs')) {
        return [];
    }

    $prefs = loadUserPrefs($email);

    return [
        'group' => trim((string) ($prefs['ponos_group'] ?? '')),
    ];
}

function ponos_save_user_navigation_prefs(string $email, string $group): void
{
    if (!function_exists('saveUserPref')) {
        return;
    }

    saveUserPref($email, 'ponos_group', $group);
}

function ponos_hash_text_for_color(string $text): int
{
    $hash = 0;
    $length = strlen($text);
    for ($index = 0; $index < $length; $index++) {
        $hash = (int) (ord($text[$index]) + (($hash << 5) - $hash));
        $hash &= 0x7FFFFFFF;
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

function ponos_url(array $params = []): array
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

function ponos_new_id(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);

    return substr($hex, 0, 8) . '-'
        . substr($hex, 8, 4) . '-'
        . substr($hex, 12, 4) . '-'
        . substr($hex, 16, 4) . '-'
        . substr($hex, 20, 12);
}
