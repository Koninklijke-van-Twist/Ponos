<?php

/**
 * Includes/requires
 */

/**
 * Constants
 */

const PONOS_ATTACHMENT_PREVIEW_TEXT_MAX_BYTES = 524288;

const PONOS_ATTACHMENT_PREVIEW_IMAGE_MAX_BYTES = 15728640;

/**
 * Functies
 */

function ponos_attachment_extension(string $filename): string
{
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    return $extension !== '' ? $extension : strtolower((string) pathinfo($filename, PATHINFO_FILENAME));
}

function ponos_attachment_preview_kind(string $filename, string $mime = ''): ?string
{
    $filename = trim($filename);
    $mime = strtolower(trim($mime));
    $extension = ponos_attachment_extension($filename);
    $basename = strtolower(basename($filename));

    $imageExtensions = [
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'svg', 'ico', 'avif', 'tif', 'tiff', 'heic', 'heif', 'apng',
    ];
    if (str_starts_with($mime, 'image/') || in_array($extension, $imageExtensions, true)) {
        return 'image';
    }

    if (in_array($extension, ['md', 'markdown'], true)) {
        return 'markdown';
    }

    if (in_array($extension, ['csv', 'tsv'], true)) {
        return 'csv';
    }

    $codeExtensions = [
        'js', 'ts', 'jsx', 'tsx', 'mjs', 'cjs', 'php', 'py', 'rb', 'go', 'rs', 'java', 'kt', 'kts', 'cs', 'cpp',
        'cc', 'cxx', 'c', 'h', 'hpp', 'hh', 'css', 'scss', 'sass', 'less', 'html', 'htm', 'xml', 'xsl', 'xslt',
        'sql', 'sh', 'bash', 'zsh', 'fish', 'ps1', 'json', 'jsonc', 'yaml', 'yml', 'toml', 'ini', 'conf', 'cfg',
        'env', 'vue', 'svelte', 'swift', 'dart', 'lua', 'r', 'pl', 'pm', 'asm', 'zig', 'vb', 'fs', 'fsx', 'clj',
        'ex', 'exs', 'erl', 'hrl', 'gradle', 'groovy', 'tf', 'tfvars', 'dockerfile', 'makefile', 'cmake', 'bat',
        'cmd', 'reg', 'properties', 'gitignore', 'editorconfig',
    ];
    if (
        in_array($extension, $codeExtensions, true)
        || in_array($basename, ['dockerfile', 'makefile', 'gemfile', 'rakefile', 'vagrantfile'], true)
    ) {
        return 'code';
    }

    $textExtensions = ['txt', 'log', 'text', 'rst', 'rtf', 'nfo'];
    if (in_array($extension, $textExtensions, true) || str_starts_with($mime, 'text/')) {
        return 'text';
    }

    if (in_array($mime, ['application/json', 'application/xml', 'application/javascript'], true)) {
        return 'code';
    }

    return null;
}

function ponos_attachment_code_language(string $filename): string
{
    $extension = ponos_attachment_extension($filename);
    $basename = strtolower(basename($filename));

    $map = [
        'js' => 'javascript', 'mjs' => 'javascript', 'cjs' => 'javascript', 'jsx' => 'javascript',
        'ts' => 'typescript', 'tsx' => 'typescript',
        'py' => 'python', 'rb' => 'ruby', 'php' => 'php', 'cs' => 'csharp', 'cpp' => 'cpp', 'cc' => 'cpp',
        'cxx' => 'cpp', 'h' => 'cpp', 'hpp' => 'cpp', 'hh' => 'cpp', 'java' => 'java', 'kt' => 'kotlin',
        'kts' => 'kotlin', 'go' => 'go', 'rs' => 'rust', 'sql' => 'sql', 'sh' => 'bash', 'bash' => 'bash',
        'zsh' => 'bash', 'fish' => 'bash', 'ps1' => 'powershell', 'html' => 'xml', 'htm' => 'xml', 'xml' => 'xml',
        'css' => 'css', 'scss' => 'scss', 'sass' => 'scss', 'less' => 'less', 'json' => 'json', 'jsonc' => 'json',
        'yaml' => 'yaml', 'yml' => 'yaml', 'toml' => 'ini', 'ini' => 'ini', 'dockerfile' => 'dockerfile',
        'makefile' => 'makefile', 'vue' => 'xml', 'svelte' => 'xml', 'swift' => 'swift', 'dart' => 'dart',
        'lua' => 'lua', 'r' => 'r', 'pl' => 'perl', 'pm' => 'perl', 'zig' => 'zig', 'vb' => 'vbnet', 'gradle' => 'groovy',
        'groovy' => 'groovy', 'tf' => 'hcl', 'tfvars' => 'hcl', 'cmake' => 'cmake', 'bat' => 'dos', 'cmd' => 'dos',
    ];

    if (isset($map[$extension])) {
        return $map[$extension];
    }

    if ($basename === 'dockerfile') {
        return 'dockerfile';
    }

    if ($basename === 'makefile') {
        return 'makefile';
    }

    return $extension !== '' ? $extension : 'plaintext';
}

function ponos_attachment_looks_like_text(string $content): bool
{
    if ($content === '') {
        return true;
    }

    if (str_contains($content, "\0")) {
        return false;
    }

    if (!mb_check_encoding($content, 'UTF-8')) {
        return false;
    }

    return true;
}

function ponos_parse_csv_preview(string $content, string $delimiter = ','): array
{
    $lines = preg_split('/\R/', rtrim($content, "\r\n")) ?: [];
    $rows = [];
    foreach ($lines as $line) {
        if ($line === '') {
            continue;
        }
        $rows[] = str_getcsv($line, $delimiter);
    }

    if ($rows === []) {
        return ['headers' => [], 'rows' => []];
    }

    $headers = array_shift($rows);

    return [
        'headers' => array_map(static fn($value): string => (string) $value, $headers),
        'rows' => array_map(
            static fn(array $row): array => array_map(static fn($value): string => (string) $value, $row),
            $rows
        ),
    ];
}

function ponos_attachment_preview_payload(array $attachment): array
{
    $kind = ponos_attachment_preview_kind((string) $attachment['filename'], (string) $attachment['mime']);
    if ($kind === null) {
        return ['ok' => false, 'error' => LOC('ponos.error.attachment_preview_unsupported')];
    }

    $path = (string) ($attachment['path'] ?? '');
    if ($path === '' || !is_file($path)) {
        return ['ok' => false, 'error' => LOC('ponos.error.attachment_not_found')];
    }

    $size = (int) filesize($path);
    $filename = (string) $attachment['filename'];
    $mime = (string) ($attachment['mime'] ?? 'application/octet-stream');

    if ($kind === 'image') {
        if ($size > PONOS_ATTACHMENT_PREVIEW_IMAGE_MAX_BYTES) {
            return ['ok' => false, 'error' => LOC('ponos.error.attachment_preview_too_large')];
        }

        return [
            'ok' => true,
            'preview_kind' => 'image',
            'filename' => $filename,
            'mime' => $mime,
            'size_bytes' => $size,
        ];
    }

    if ($size > PONOS_ATTACHMENT_PREVIEW_TEXT_MAX_BYTES) {
        return ['ok' => false, 'error' => LOC('ponos.error.attachment_preview_too_large')];
    }

    $content = file_get_contents($path);
    if ($content === false || !ponos_attachment_looks_like_text($content)) {
        return ['ok' => false, 'error' => LOC('ponos.error.attachment_preview_unsupported')];
    }

    $payload = [
        'ok' => true,
        'preview_kind' => $kind,
        'filename' => $filename,
        'mime' => $mime,
        'size_bytes' => $size,
        'content' => $content,
    ];

    if ($kind === 'code') {
        $payload['language'] = ponos_attachment_code_language($filename);
    }

    if ($kind === 'csv') {
        $delimiter = ponos_attachment_extension($filename) === 'tsv' ? "\t" : ',';
        $payload = array_merge($payload, ponos_parse_csv_preview($content, $delimiter));
        unset($payload['content']);
    }

    return $payload;
}

function ponos_api_resolve_attachment_group(string $groupId, string $taskId = ''): string
{
    if ($taskId !== '' && ponos_is_my_tasks_group($groupId)) {
        $location = ponos_find_task_location($taskId);
        if ($location !== null) {
            return (string) $location['group_id'];
        }
    }

    return $groupId;
}

function ponos_api_attachment_urls(string $groupId, int $attachmentId, string $taskId = ''): array
{
    $params = [
        'group' => $groupId,
        'attachment_id' => (string) $attachmentId,
    ];
    if ($taskId !== '') {
        $params['task'] = $taskId;
    }

    $download = 'ponos_api.php?' . http_build_query(array_merge($params, ['action' => 'download_attachment']));
    $inline = 'ponos_api.php?' . http_build_query(array_merge($params, ['action' => 'view_attachment']));

    return [
        'download_url' => $download,
        'inline_url' => $inline,
    ];
}
