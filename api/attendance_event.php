<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/attendance_settings.php';

require_roles(['attendance', 'admin']);

header('Content-Type: application/json; charset=UTF-8');

if (!is_post()) {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests are allowed.',
    ]);
    exit;
}

$payload = json_decode((string) file_get_contents('php://input'), true);

if (!is_array($payload)) {
    $payload = $_POST;
}

if (!verify_csrf_token($payload['csrf_token'] ?? null)) {
    http_response_code(419);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid session token. Please refresh the page.',
    ]);
    exit;
}

$lrn = trim((string) ($payload['lrn'] ?? ''));

if ($lrn === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'LRN is required.',
    ]);
    exit;
}

$currentTime = date('H:i:s');
$attendanceDate = date('Y-m-d');
$scanMode = attendance_scan_mode();

$enrollmentStatement = database()->prepare(
    'SELECT le.id
     FROM learners l
     INNER JOIN learner_enrollments le ON le.learner_id = l.id
     INNER JOIN school_years sy ON sy.id = le.school_year_id
     WHERE l.lrn = :lrn
     ORDER BY sy.is_current DESC, sy.start_date DESC, le.id DESC
     LIMIT 1'
);
$enrollmentStatement->execute(['lrn' => $lrn]);
$enrollment = $enrollmentStatement->fetch();

if ($enrollment === false) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'No enrollment record found for this learner.',
    ]);
    exit;
}

$legendStatement = database()->prepare(
    'SELECT id FROM attendance_legends WHERE code = :code LIMIT 1'
);
$legendStatement->execute(['code' => 'P']);
$legend = $legendStatement->fetch();

if ($legend === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Attendance legend configuration is missing.',
    ]);
    exit;
}

$pdo = database();
$pdo->beginTransaction();

try {
    $recordStatement = $pdo->prepare(
        'SELECT
            id,
            legend_id,
            am_time_in,
            am_time_out,
            pm_time_in,
            pm_time_out
         FROM attendance_records
         WHERE learner_enrollment_id = :enrollment_id
           AND attendance_date = :attendance_date
         LIMIT 1
         FOR UPDATE'
    );
    $recordStatement->execute([
        'enrollment_id' => $enrollment['id'],
        'attendance_date' => $attendanceDate,
    ]);
    $record = $recordStatement->fetch();

    $slot = attendance_resolve_scan_slot(
        $scanMode,
        $currentTime,
        $record !== false ? $record : [
            'am_time_in' => null,
            'am_time_out' => null,
            'pm_time_in' => null,
            'pm_time_out' => null,
        ]
    );

    if (!$slot['success']) {
        $pdo->rollBack();
        http_response_code((int) ($slot['status'] ?? 422));
        echo json_encode([
            'success' => false,
            'message' => $slot['message'] ?? 'Unable to resolve attendance slot.',
        ]);
        exit;
    }

    $targetColumn = $slot['column'];

    if ($record !== false && $record['legend_id'] !== null) {
        $excusedCheck = $pdo->prepare(
            'SELECT al.code FROM attendance_legends al WHERE al.id = :legend_id LIMIT 1'
        );
        $excusedCheck->execute(['legend_id' => $record['legend_id']]);
        if ($excusedCheck->fetchColumn() === 'E') {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'This attendance record is marked Excused and can only be changed manually.',
            ]);
            exit;
        }
    }

    if ($record !== false && !empty($record[$targetColumn])) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => $slot['label'] . ' has already been recorded for today.',
        ]);
        exit;
    }

    $scanRecord = $record !== false ? $record : [
        'am_time_in' => null,
        'am_time_out' => null,
        'pm_time_in' => null,
        'pm_time_out' => null,
    ];
    $scanRecord[$targetColumn] = $currentTime;
    $summary = attendance_record_summary(
        $attendanceDate,
        null,
        $scanRecord['am_time_in'],
        $scanRecord['am_time_out'],
        $scanRecord['pm_time_in'],
        $scanRecord['pm_time_out']
    );
    $legendStatement->execute(['code' => $summary['code'] === 'L' ? 'L' : 'P']);
    $scanLegend = $legendStatement->fetch();

    if ($scanLegend === false) {
        throw new RuntimeException('Attendance legend configuration is missing.');
    }

    if ($record === false) {
        $insertStatement = $pdo->prepare(
            'INSERT INTO attendance_records (
                learner_enrollment_id,
                attendance_date,
                legend_id,
                am_time_in,
                am_time_out,
                pm_time_in,
                pm_time_out,
                remarks
             ) VALUES (
                :enrollment_id,
                :attendance_date,
                :legend_id,
                :am_time_in,
                :am_time_out,
                :pm_time_in,
                :pm_time_out,
                :remarks
             )'
        );
        $insertStatement->execute([
            'enrollment_id' => $enrollment['id'],
            'attendance_date' => $attendanceDate,
            'legend_id' => $scanLegend['id'],
            'am_time_in' => $targetColumn === 'am_time_in' ? $currentTime : null,
            'am_time_out' => $targetColumn === 'am_time_out' ? $currentTime : null,
            'pm_time_in' => $targetColumn === 'pm_time_in' ? $currentTime : null,
            'pm_time_out' => $targetColumn === 'pm_time_out' ? $currentTime : null,
            'remarks' => 'Recorded via attendance scanner',
        ]);
        $attendanceRecordId = (int) $pdo->lastInsertId();
    } else {
        $updateStatement = $pdo->prepare(
            "UPDATE attendance_records
             SET legend_id = :legend_id,
                 {$targetColumn} = :scan_time,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );
        $updateStatement->execute([
            'legend_id' => $scanLegend['id'],
            'scan_time' => $currentTime,
            'id' => $record['id'],
        ]);
        $attendanceRecordId = (int) $record['id'];
    }

    $logStatement = $pdo->prepare(
        'INSERT INTO attendance_scan_logs (
            attendance_record_id,
            learner_enrollment_id,
            legend_id,
            slot_key,
            slot_label,
            scanned_at
         ) VALUES (
            :attendance_record_id,
            :learner_enrollment_id,
            :legend_id,
            :slot_key,
            :slot_label,
            :scanned_at
         )'
    );
    $logStatement->execute([
        'attendance_record_id' => $attendanceRecordId,
        'learner_enrollment_id' => $enrollment['id'],
        'legend_id' => $scanLegend['id'],
        'slot_key' => $targetColumn,
        'slot_label' => $slot['label'],
        'scanned_at' => date('Y-m-d H:i:s'),
    ]);

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to save the attendance scan right now.',
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => $slot['label'] . ' recorded successfully. Current status: ' . $summary['label'] . '.',
]);
