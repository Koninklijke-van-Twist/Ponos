<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/web/ponos_data.php';

ponos_test('ponos_hash_text_for_color is deterministic', function (): void {
    assert_eq(ponos_hash_text_for_color('test@example.com'), ponos_hash_text_for_color('test@example.com'));
    assert_true(ponos_hash_text_for_color('a') !== ponos_hash_text_for_color('b'));
});

ponos_test('ponos_color_from_text returns defaults for empty input', function (): void {
    $colors = ponos_color_from_text('');
    assert_eq('#cbd5e1', $colors['border']);
    assert_array_has_key('dark', $colors);
    assert_array_has_key('light', $colors);
});

ponos_test('ponos_merge_department_codes grows persistent cache', function (): void {
    $company = 'Test Company Merge';
    $path = ponos_department_cache_path($company);
    if (is_file($path)) {
        unlink($path);
    }

    $first = ponos_merge_department_codes($company, ['ENG', 'INK']);
    assert_eq(2, count($first));
    assert_array_has_key('ENG', $first);

    $second = ponos_merge_department_codes($company, ['PROD']);
    assert_eq(3, count($second));
    assert_array_has_key('ENG', $second);
    assert_array_has_key('PROD', $second);

    if (is_file($path)) {
        unlink($path);
    }
});

ponos_test('ponos_normalize_project_row maps department code', function (): void {
    $row = ponos_normalize_project_row([
        'No' => 'PRJ001',
        'Description' => 'Demo',
        'Status' => 'Open',
        'Project_Manager' => 'KVT\\USER',
        'LVS_Global_Dimension_1_Code' => 'ENG',
    ]);

    assert_eq('PRJ001', $row['no']);
    assert_eq('ENG', $row['department_code']);
});

ponos_test('ponos_group_projects_by_department groups correctly', function (): void {
    $grouped = ponos_group_projects_by_department([
        ['no' => 'A', 'department_code' => 'ENG'],
        ['no' => 'B', 'department_code' => 'INK'],
        ['no' => 'C', 'department_code' => 'ENG'],
        ['no' => 'D', 'department_code' => ''],
    ]);

    assert_eq(2, count($grouped['ENG']));
    assert_eq(1, count($grouped['INK']));
    assert_eq(1, count($grouped['_none']));
});

ponos_test('ponos_resolve_company_choice prefers requested company', function (): void {
    $companies = ['Alpha', 'Beta'];
    assert_eq('Beta', ponos_resolve_company_choice($companies, 'Beta', 'Alpha'));
    assert_eq('Alpha', ponos_resolve_company_choice($companies, '', 'Alpha'));
});

ponos_test('ponos_all_statuses contains three workflow columns', function (): void {
    assert_eq([PONOS_STATUS_TODO, PONOS_STATUS_IN_PROGRESS, PONOS_STATUS_DONE], ponos_all_statuses());
});
