<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/web/ponos_attachments.php';

ponos_test('ponos_attachment_preview_kind detects common preview types', function (): void {
    assert_eq('image', ponos_attachment_preview_kind('photo.png', 'image/png'));
    assert_eq('image', ponos_attachment_preview_kind('scan.heic', ''));
    assert_eq('markdown', ponos_attachment_preview_kind('notes.md', 'text/plain'));
    assert_eq('csv', ponos_attachment_preview_kind('data.csv', 'text/csv'));
    assert_eq('code', ponos_attachment_preview_kind('app.php', 'text/plain'));
    assert_eq('code', ponos_attachment_preview_kind('Dockerfile', ''));
    assert_eq('text', ponos_attachment_preview_kind('readme.txt', 'text/plain'));
    assert_true(ponos_attachment_preview_kind('archive.zip', 'application/zip') === null);
});

ponos_test('ponos_parse_csv_preview parses rows and headers', function (): void {
    $parsed = ponos_parse_csv_preview("name,count\nalpha,1\nbeta,2\n");
    assert_eq(['name', 'count'], $parsed['headers']);
    assert_eq(2, count($parsed['rows']));
    assert_eq('alpha', $parsed['rows'][0][0]);
    assert_eq('2', $parsed['rows'][1][1]);
});

ponos_test('ponos_attachment_code_language maps extensions', function (): void {
    assert_eq('php', ponos_attachment_code_language('index.php'));
    assert_eq('javascript', ponos_attachment_code_language('bundle.js'));
    assert_eq('dockerfile', ponos_attachment_code_language('Dockerfile'));
});
