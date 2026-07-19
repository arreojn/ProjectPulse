<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/auth.php';

require_roles(['attendance', 'admin']);

header('Content-Type: application/json; charset=UTF-8');

$statement = database()->query(
    'SELECT
        asl.scanned_at,
        CONCAT(l.first_name, \' \', l.last_name) AS learner_name,
        l.lrn,
        CONCAT(le.grade_level, \' / \', COALESCE(s.name, \'Unassigned\')) AS grade_section,
        CONCAT(asl.slot_label, \' recorded as \', al.label) AS log_entry
     FROM attendance_scan_logs asl
     INNER JOIN learner_enrollments le ON le.id = asl.learner_enrollment_id
     INNER JOIN learners l ON l.id = le.learner_id
     LEFT JOIN sections s ON s.id = le.section_id
     INNER JOIN attendance_legends al ON al.id = asl.legend_id
     ORDER BY asl.scanned_at DESC, asl.id DESC
     LIMIT 20'
);

$rows = $statement->fetchAll();

echo json_encode([
    'success' => true,
    'logs' => array_map(
        static fn (array $row): array => [
            'log_date' => date('Y-m-d', strtotime($row['scanned_at'])),
            'log_time' => date('h:i:s A', strtotime($row['scanned_at'])),
            'learner_name' => $row['learner_name'],
            'lrn' => $row['lrn'],
            'grade_section' => $row['grade_section'],
            'log_entry' => $row['log_entry'],
        ],
        $rows
    ),
]);
