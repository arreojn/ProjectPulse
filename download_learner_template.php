<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/auth.php';

require_roles(['admin']);

$format = strtolower((string) ($_GET['format'] ?? 'csv'));
$headers = [
    'lrn',
    'first_name',
    'middle_name',
    'last_name',
    'birthdate',
    'age',
    'mother_tongue',
    'religion',
    'address_house_number',
    'address_barangay',
    'address_city_municipality',
    'address_province',
    'has_disability',
    'disability_basis',
    'disability_type',
    'sex',
    'current_status',
    'grade_level',
    'section_name',
];

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="learner_template.csv"');

    $output = fopen('php://output', 'wb');
    fputcsv($output, $headers);
    fclose($output);
    exit;
}

if ($format === 'xls') {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="learner_template.xls"');

    echo '<?xml version="1.0"?>';
    ?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
    xmlns:o="urn:schemas-microsoft-com:office:office"
    xmlns:x="urn:schemas-microsoft-com:office:excel"
    xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
    <Worksheet ss:Name="Learners">
        <Table>
            <Row>
                <?php foreach ($headers as $header): ?>
                    <Cell><Data ss:Type="String"><?php echo escape($header); ?></Data></Cell>
                <?php endforeach; ?>
            </Row>
        </Table>
    </Worksheet>
</Workbook>
<?php
    exit;
}

http_response_code(404);
echo 'Invalid template format.';
