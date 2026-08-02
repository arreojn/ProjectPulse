<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/attendance_settings.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    $user = require_roles(['attendance', 'admin']);

    if (!is_post()) {
        throw new RuntimeException('Only POST requests are allowed.', 405);
    }

    $payload = json_decode((string) file_get_contents('php://input'), true);

    if (!is_array($payload)) {
        $payload = $_POST;
    }

    if (!verify_csrf_token($payload['csrf_token'] ?? null)) {
        throw new RuntimeException('Invalid session token. Please refresh the page.', 419);
    }

    $imageDataUrl = $payload['image_data'] ?? '';
    if (empty($imageDataUrl)) {
        throw new RuntimeException('No image data received.', 422);
    }

    if (!is_string($imageDataUrl) || !preg_match('#^data:image/(?:jpeg|jpg|png);base64,([A-Za-z0-9+/=]+)$#', $imageDataUrl, $matches)) {
        throw new RuntimeException('A JPEG or PNG camera frame is required.', 422);
    }

    $imageData = base64_decode($matches[1], true);
    if ($imageData === false || strlen($imageData) > 5 * 1024 * 1024) {
        throw new RuntimeException('The camera frame is invalid or too large.', 422);
    }

    if (!face_recognition_start_service()) {
        throw new RuntimeException('Fast recognition service could not be started. Train the face model and try again.', 503);
    }

    $serviceResponse = face_recognition_service_request('recognize', $imageData);
    $recognition = $serviceResponse['payload'] ?? null;
    if (!is_array($recognition) || !($recognition['ok'] ?? false)) {
        throw new RuntimeException(is_array($recognition) ? (string) ($recognition['error'] ?? 'Recognition process failed.') : 'Recognition service is unavailable.', 503);
    }

    $matches = is_array($recognition['matches'] ?? null) ? $recognition['matches'] : [];
    if ($matches === []) {
        throw new RuntimeException('No registered face was recognized.', 404);
    }

    // The lowest LBPH confidence is the strongest match. Recording stays one learner per
    // scan so the existing AM/PM slot rules remain unambiguous.
    $lrn = (string) ($matches[0]['lrn'] ?? '');

    // --- Attendance Logic (adapted from api/attendance_event.php) ---
    $currentTime = date('H:i:s');
    $attendanceDate = date('Y-m-d');
    $scanMode = attendance_scan_mode();
    $pdo = database();

    $enrollmentStmt = $pdo->prepare(
        'SELECT le.id, l.first_name, l.last_name, le.grade_level, s.name as section_name
         FROM learners l
         INNER JOIN learner_enrollments le ON le.learner_id = l.id
         INNER JOIN school_years sy ON sy.id = le.school_year_id
         LEFT JOIN sections s ON s.id = le.section_id
         WHERE l.lrn = :lrn
         ORDER BY sy.is_current DESC, sy.start_date DESC, le.id DESC
         LIMIT 1'
    );
    $enrollmentStmt->execute(['lrn' => $lrn]);
    $enrollment = $enrollmentStmt->fetch();

    if ($enrollment === false) {
        throw new RuntimeException('No active enrollment found for the recognized learner.', 404);
    }

    $pdo->beginTransaction();

    $recordStmt = $pdo->prepare('SELECT * FROM attendance_records WHERE learner_enrollment_id = :id AND attendance_date = :date LIMIT 1 FOR UPDATE');
    $recordStmt->execute(['id' => $enrollment['id'], 'date' => $attendanceDate]);
    $record = $recordStmt->fetch();

    $slot = attendance_resolve_scan_slot($scanMode, $currentTime, $record ?: []);

    if (!$slot['success']) {
        $pdo->rollBack();
        throw new RuntimeException($slot['message'] ?? 'Unable to resolve attendance slot.', $slot['status'] ?? 422);
    }

    $targetColumn = $slot['column'];
    if ($record !== false && !empty($record[$targetColumn])) {
        $pdo->rollBack();
        throw new RuntimeException($slot['label'] . ' has already been recorded.', 409);
    }

    $legendStmt = $pdo->prepare('SELECT id FROM attendance_legends WHERE code = :code LIMIT 1');
    $legendStmt->execute(['code' => 'P']);
    $presentLegend = $legendStmt->fetch();

    if ($record === false) {
        $insertStmt = $pdo->prepare(
            "INSERT INTO attendance_records (learner_enrollment_id, attendance_date, legend_id, {$targetColumn})
             VALUES (:enroll_id, :date, :legend_id, :time)"
        );
        $insertStmt->execute([
            'enroll_id' => $enrollment['id'], 'date' => $attendanceDate,
            'legend_id' => $presentLegend['id'], 'time' => $currentTime
        ]);
        $attendanceRecordId = (int) $pdo->lastInsertId();
    } else {
        $updateStmt = $pdo->prepare("UPDATE attendance_records SET legend_id = :legend_id, {$targetColumn} = :time WHERE id = :id");
        $updateStmt->execute(['legend_id' => $presentLegend['id'], 'time' => $currentTime, 'id' => $record['id']]);
        $attendanceRecordId = (int) $record['id'];
    }

    $logStmt = $pdo->prepare(
        'INSERT INTO attendance_scan_logs (attendance_record_id, learner_enrollment_id, legend_id, slot_key, slot_label, scanned_at)
         VALUES (:rec_id, :enroll_id, :legend_id, :key, :label, :at)'
    );
    $logStmt->execute([
        'rec_id' => $attendanceRecordId, 'enroll_id' => $enrollment['id'], 'legend_id' => $presentLegend['id'],
        'key' => $targetColumn, 'label' => $slot['label'], 'at' => date('Y-m-d H:i:s')
    ]);

    $pdo->commit();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $slot['label'] . ' recorded!',
        'slot_label' => $slot['label'],
        'learner' => [
            'name' => $enrollment['first_name'] . ' ' . $enrollment['last_name'],
            'lrn' => $lrn,
            'grade_level' => $enrollment['grade_level'],
            'section_name' => $enrollment['section_name'],
        ]
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $code = $e->getCode();
    http_response_code(is_int($code) && $code >= 400 && $code < 600 ? $code : 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
