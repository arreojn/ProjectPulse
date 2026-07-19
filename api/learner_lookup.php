<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/auth.php';

require_roles(['attendance', 'admin']);

header('Content-Type: application/json; charset=UTF-8');

$lrn = trim((string) ($_GET['lrn'] ?? ''));
$attendanceDate = date('Y-m-d');

if ($lrn === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please enter an LRN.',
    ]);
    exit;
}

$learnerStatement = database()->prepare(
    'SELECT id, lrn, first_name, middle_name, last_name
     FROM learners
     WHERE lrn = :lrn
     LIMIT 1'
);
$learnerStatement->execute(['lrn' => $lrn]);
$learner = $learnerStatement->fetch();

if ($learner === false) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'No learner found for that LRN.',
    ]);
    exit;
}

$enrollmentStatement = database()->prepare(
    'SELECT
        le.id AS enrollment_id,
        le.grade_level,
        COALESCE(s.name, \'Unassigned\') AS section_name,
        sy.label AS school_year
     FROM learner_enrollments le
     INNER JOIN school_years sy ON sy.id = le.school_year_id
     LEFT JOIN sections s ON s.id = le.section_id
     WHERE le.learner_id = :learner_id
     ORDER BY sy.is_current DESC, sy.start_date DESC, le.id DESC
     LIMIT 1'
);
$enrollmentStatement->execute(['learner_id' => $learner['id']]);
$enrollment = $enrollmentStatement->fetch();

if ($enrollment === false) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Learner found, but no enrollment record is available yet.',
    ]);
    exit;
}

$attendanceStatement = database()->prepare(
    'SELECT
        al.label AS attendance_label,
        ar.am_time_in,
        ar.am_time_out,
        ar.pm_time_in,
        ar.pm_time_out
     FROM attendance_records ar
     INNER JOIN attendance_legends al ON al.id = ar.legend_id
     WHERE ar.learner_enrollment_id = :enrollment_id
       AND ar.attendance_date = :attendance_date
     LIMIT 1'
);
$attendanceStatement->execute([
    'enrollment_id' => $enrollment['enrollment_id'],
    'attendance_date' => $attendanceDate,
]);
$attendance = $attendanceStatement->fetch() ?: null;

$fullName = trim(implode(' ', array_filter([
    $learner['first_name'],
    $learner['middle_name'],
    $learner['last_name'],
])));

echo json_encode([
    'success' => true,
    'learner' => [
        'lrn' => $learner['lrn'],
        'name' => $fullName,
        'grade_level' => $enrollment['grade_level'],
        'section' => $enrollment['section_name'],
        'school_year' => $enrollment['school_year'],
        'attendance_status' => $attendance['attendance_label'] ?? 'No record yet',
        'am_time_in' => $attendance['am_time_in'] ?? null,
        'am_time_out' => $attendance['am_time_out'] ?? null,
        'pm_time_in' => $attendance['pm_time_in'] ?? null,
        'pm_time_out' => $attendance['pm_time_out'] ?? null,
    ],
]);
