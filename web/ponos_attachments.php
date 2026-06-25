<?php

/**
 * Constants
 */

const PONOS_ATTACHMENT_PREVIEW_MAX_BYTES = 5242880;

/**
 * Functies
 */

function ponos_attachment_extension(string $filename): string
{
    $filename = strtolower(trim($filename));
    $position = strrpos($filename, '.');

    return $position === false ? '' : substr($filename, $position + 1);
}

function ponos_attachment_image_extensions(): array
{
    return [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'avif', 'ico', 'tif', 'tiff', 'heic', 'heif',
    ];
}

function ponos_attachment_code_extensions(): array
{
    return [
        'js', 'mjs', 'cjs', 'ts', 'tsx', 'jsx', 'php', 'py', 'rb', 'go', 'rs', 'java', 'c', 'cpp', 'h',
        'hpp', 'cs', 'css', 'scss', 'less', 'html', 'htm', 'xml', 'json', 'yaml', 'yml', 'sql', 'sh',
        'bash', 'bat', 'ps1', 'vue', 'svelte', 'kt', 'swift', 'lua', 'pl', 'r', 'dart', 'zig',
    ];
}

function ponos_attachment_text_extensions(): array
{
    return ['txt', 'log', 'ini', 'cfg', 'conf', 'env', 'mdown'];
}

function ponos_attachment_language_from_filename(string $filename): string
{
    return match (ponos_attachment_extension($filename)) {
        'js', 'mjs', 'cjs' => 'javascript',
        'ts', 'tsx' => 'typescript',
        'jsx' => 'jsx',
        'php' => 'php',
        'py' => 'python',
        'rb' => 'ruby',
        'go' => 'go',
        'rs' => 'rust',
        'java' => 'java',
        'c', 'h' => 'c',
        'cpp', 'hpp' => 'cpp',
        'cs' => 'csharp',
        'css', 'scss', 'less' => 'css',
        'html', 'htm' => 'html',
        'xml' => 'xml',
        'json' => 'json',
        'yaml', 'yml' => 'yaml',
        'sql' => 'sql',
        'sh', 'bash' => 'bash',
        'bat' => 'dos',
        'ps1' => 'powershell',
        'vue' => 'vue',
        'svelte' => 'svelte',
        'kt' => 'kotlin',
        'swift' => 'swift',
        'lua' => 'lua',
        'pl' => 'perl',
        'r' => 'r',
        'dart' => 'dart',
        'zig' => 'zig',
        'md', 'markdown' => 'markdown',
        'csv' => 'csv',
        default => 'plaintext',
    };
}

function ponos_attachment_preview_kind(string $filename, string $mime): ?string
{
    $extension = ponos_attachment_extension($filename);
    $mime = strtolower(trim($mime));

    if ($mime !== '' && str_starts_with($mime, 'image/')) {
        return 'image';
    }

    if (in_array($extension, ponos_attachment_image_extensions(), true)) {
        return 'image';
    }

    if (in_array($extension, ['md', 'markdown'], true)) {
        return 'markdown';
    }

    if ($extension === 'csv' || $mime === 'text/csv') {
        return 'csv';
    }

    if (in_array($extension, ponos_attachment_code_extensions(), true)) {
        return 'code';
    }

    if (in_array($extension, ponos_attachment_text_extensions(), true)) {
        return 'text';
    }

    if ($mime !== '' && (str_starts_with($mime, 'text/') || $mime === 'application/json' || $mime === 'application/xml')) {
        return str_contains($mime, 'json') || str_contains($mime, 'xml') ? 'code' : 'text';
    }

    return null;
}

function ponos_attachment_read_preview_content(string $path): ?string
{
    if (!is_file($path)) {
        return null;
    }

    $size = filesize($path);
    if ($size === false || $size > PONOS_ATTACHMENT_PREVIEW_MAX_BYTES) {
        return null;
    }

    $content = file_get_contents($path);
    if ($content === false) {
        return null;
    }

    if (!mb_check_encoding($content, 'UTF-8')) {
        $converted = mb_convert_encoding($content, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        if (is_string($converted)) {
            return $converted;
        }
    }

    return $content;
}

function ponos_attachment_parse_csv(string $content): array
{
    $handle = fopen('php://memory', 'rb+');
    if ($handle === false) {
        return [];
    }

    fwrite($handle, $content);
    rewind($handle);

    $rows = [];
    while (($row = fgetcsv($handle)) !== false) {
        $rows[] = array_map(static fn($value): string => (string) $value, $row);
    }

    fclose($handle);

    return $rows;
}

function ponos_attachment_resolve_group_for_download(string $groupId, string $taskId): string
{
    if ($taskId !== '' && ponos_is_my_tasks_group($groupId)) {
        $location = ponos_find_task_location($taskId);
        if ($location !== null) {
            return (string) $location['group_id'];
        }
    }

    return $groupId;
}
