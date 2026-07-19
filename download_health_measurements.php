<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/learners.php';
require_once __DIR__ . '/app/health.php';

require_roles(['health']);

$mode = strtolower((string) ($_GET['mode'] ?? 'template'));
$filters = health_filter_defaults($_GET);

header('Content-Type: text/csv; charset=UTF-8');

$output = fopen('php://output', 'wb');

if ($mode === 'template') {
    header('Content-Disposition: attachment; filename="height_weight_template.csv"');
    fputcsv($output, health_measurement_template_headers());
    fclose($output);
    exit;
}

if ($mode === 'export') {
    header('Content-Disposition: attachment; filename="height_weight_export.csv"');
    fputcsv($output, [
        'lrn',
        'complete_name',
        'grade_level',
        'section',
        'height_cm',
        'weight_kg',
        'bmi',
        'bmi_remarks',
    ]);

    foreach (health_measurement_export_rows($filters) as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

fclose($output);
http_response_code(404);
echo 'Invalid measurement download mode.';
