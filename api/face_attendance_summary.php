<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/auth.php';

require_roles(['attendance', 'admin']);

header('Content-Type: application/json; charset=UTF-8');

$statement = database()->prepare(
    'SELECT
        CONCAT(l.first_name, " ", l.last_name) AS learner_name,
        l.lrn,
        ar.am_time_in,
        ar.am_time_out,
        ar.pm_time_in,
        ar.pm_time_out,
        ar.updated_at
     FROM attendance_records ar
     INNER JOIN learner_enrollments le ON le.id = ar.learner_enrollment_id
     INNER JOIN learners l ON l.id = le.learner_id
     WHERE ar.attendance_date = CURDATE()
     ORDER BY ar.updated_at DESC, ar.id DESC
     LIMIT 40'
);
$statement->execute();

echo json_encode([
    'success' => true,
    'records' => array_map(
        static fn (array $record): array => [
            'learner_name' => $record['learner_name'],
            'lrn' => $record['lrn'],
            'am_time_in' => $record['am_time_in'] ? date('h:i A', strtotime($record['am_time_in'])) : '—',
            'am_time_out' => $record['am_time_out'] ? date('h:i A', strtotime($record['am_time_out'])) : '—',
            'pm_time_in' => $record['pm_time_in'] ? date('h:i A', strtotime($record['pm_time_in'])) : '—',
            'pm_time_out' => $record['pm_time_out'] ? date('h:i A', strtotime($record['pm_time_out'])) : '—',
        ],
        $statement->fetchAll()
    ),
]);
