<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/attendance_settings.php';

header('Content-Type: application/json; charset=UTF-8');

$recognizedLearner = null;
$recognizedFaceBoxes = [];

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

    $strictness = face_recognition_strictness();
    $serviceHealth = face_recognition_service_request('health');
    $supportedStrictness = $serviceHealth['payload']['strictness_levels'] ?? null;
    if (!is_array($supportedStrictness) || !in_array($strictness, $supportedStrictness, true)) {
        throw new RuntimeException('The face recognition service must be restarted before configurable strictness can be used.', 503);
    }

    $serviceResponse = face_recognition_service_request(
        'recognize',
        $imageData,
        ['X-Face-Strictness: ' . $strictness]
    );
    $recognition = $serviceResponse['payload'] ?? null;
    if (!is_array($recognition) || !($recognition['ok'] ?? false)) {
        $serviceStatus = (int) ($serviceResponse['status'] ?? 503);
        throw new RuntimeException(
            is_array($recognition) ? (string) ($recognition['error'] ?? 'Recognition process failed.') : 'Recognition service is unavailable.',
            $serviceStatus >= 400 ? $serviceStatus : 503
        );
    }

    $matches = is_array($recognition['matches'] ?? null) ? $recognition['matches'] : [];
    $recognizedFaceBoxes = is_array($recognition['faces'] ?? null) ? $recognition['faces'] : [];
    if ($matches === []) {
        $pending = (bool) ($recognition['pending'] ?? false);
        $faceCount = (int) ($recognition['face_count'] ?? 0);
        http_response_code($pending ? 202 : 404);
        echo json_encode([
            'success' => false,
            'code' => $pending
                ? 'confirmation_pending'
                : ($faceCount === 0 ? 'face_not_detected' : 'face_not_recognized'),
            'face_count' => $faceCount,
            'face_boxes' => $recognizedFaceBoxes,
            'message' => $pending
                ? 'Possible learner match. Hold still while we confirm each identity.'
                : ($faceCount === 0
                    ? 'No face detected. Move closer and face the camera.'
                    : 'No detected face matched the trained learner photos. Check enrollment photos and retrain the model.'),
        ]);
        exit;
    }

    $pdo = database();
    $enrollmentStmt = $pdo->prepare(
        'SELECT le.id, l.first_name, l.last_name, le.grade_level, s.name as section_name,
                l.parent_guardian_contact_number
         FROM learners l
         INNER JOIN learner_enrollments le ON le.learner_id = l.id
         INNER JOIN school_years sy ON sy.id = le.school_year_id
         LEFT JOIN sections s ON s.id = le.section_id
         WHERE l.lrn = :lrn
         ORDER BY sy.is_current DESC, sy.start_date DESC, le.id DESC
         LIMIT 1'
    );
    $recognizedCandidates = [];
    $learnerNames = [];
    foreach ($matches as $match) {
        $lrn = (string) ($match['lrn'] ?? '');
        if ($lrn === '') {
            continue;
        }
        $enrollmentStmt->execute(['lrn' => $lrn]);
        $candidateEnrollment = $enrollmentStmt->fetch();
        if ($candidateEnrollment === false) {
            continue;
        }

        $learnerName = $candidateEnrollment['first_name'] . ' ' . $candidateEnrollment['last_name'];
        $learnerNames[$lrn] = $learnerName;
        $recognizedCandidates[] = [
            'lrn' => $lrn,
            'confidence' => $match['confidence'] ?? null,
            'face_box' => $match['face_box'] ?? null,
            'enrollment' => $candidateEnrollment,
            'learner' => [
                'name' => $learnerName,
                'lrn' => $lrn,
                'grade_level' => $candidateEnrollment['grade_level'],
                'section_name' => $candidateEnrollment['section_name'],
            ],
        ];
    }

    foreach ($recognizedFaceBoxes as &$faceBox) {
        $boxLrn = (string) ($faceBox['lrn'] ?? '');
        if (($faceBox['status'] ?? '') === 'recognized' && isset($learnerNames[$boxLrn])) {
            $faceBox['name'] = $learnerNames[$boxLrn];
        }
    }
    unset($faceBox);

    if ($recognizedCandidates === []) {
        throw new RuntimeException('No active enrollment found for the recognized learners.', 404);
    }

    $currentTime = date('H:i:s');
    $attendanceDate = date('Y-m-d');
    $scanMode = attendance_scan_mode();
    $lastSkipMessage = 'No confirmed learner could be recorded.';
    $lastSkipStatus = 409;
    $selectedCandidate = null;
    $slot = null;

    foreach ($recognizedCandidates as $candidate) {
        $enrollment = $candidate['enrollment'];
        $pdo->beginTransaction();
        try {
            $recordStmt = $pdo->prepare('SELECT * FROM attendance_records WHERE learner_enrollment_id = :id AND attendance_date = :date LIMIT 1 FOR UPDATE');
            $recordStmt->execute(['id' => $enrollment['id'], 'date' => $attendanceDate]);
            $record = $recordStmt->fetch();

            $candidateSlot = attendance_resolve_scan_slot($scanMode, $currentTime, $record ?: []);
            if (!$candidateSlot['success']) {
                $pdo->rollBack();
                $lastSkipMessage = $candidateSlot['message'] ?? 'Unable to resolve attendance slot.';
                $lastSkipStatus = $candidateSlot['status'] ?? 422;
                continue;
            }

            $targetColumn = $candidateSlot['column'];
            if ($record !== false && !empty($record[$targetColumn])) {
                $pdo->rollBack();
                $lastSkipMessage = $candidateSlot['label'] . ' has already been recorded for ' . $candidate['learner']['name'] . '.';
                $lastSkipStatus = 409;
                continue;
            }

            $legendStmt = $pdo->prepare('SELECT id FROM attendance_legends WHERE code = :code LIMIT 1');
            $legendStmt->execute(['code' => 'P']);
            $presentLegend = $legendStmt->fetch();
            if ($presentLegend === false) {
                throw new RuntimeException('The Present attendance legend is unavailable.');
            }

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
                'key' => $targetColumn, 'label' => $candidateSlot['label'], 'at' => date('Y-m-d H:i:s')
            ]);

            $pdo->commit();
            $selectedCandidate = $candidate;
            $slot = $candidateSlot;
            $recognizedLearner = $candidate['learner'];
            break;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    if ($selectedCandidate === null || $slot === null) {
        http_response_code($lastSkipStatus);
        echo json_encode([
            'success' => false,
            'code' => 'attendance_unavailable',
            'message' => $lastSkipMessage,
            'recognized_learners' => array_column(array_column($recognizedCandidates, 'learner'), 'name'),
            'face_boxes' => $recognizedFaceBoxes,
        ]);
        exit;
    }

    $enrollment = $selectedCandidate['enrollment'];

    require_once __DIR__ . '/app/sms_gateway.php';
    if (!empty($enrollment['parent_guardian_contact_number'])) {
        sms_send_attendance_notification(
            $enrollment['first_name'] . ' ' . $enrollment['last_name'],
            $enrollment['parent_guardian_contact_number'],
            $slot['label'],
            $attendanceDate,
            $currentTime
        );
    }

    http_response_code(200);
    $otherRecognizedLearners = array_values(array_filter(
        array_column(array_column($recognizedCandidates, 'learner'), 'name'),
        static fn (string $name): bool => $name !== $recognizedLearner['name']
    ));
    $message = $slot['label'] . ' recorded for ' . $recognizedLearner['name'] . '.';
    if ($otherRecognizedLearners !== []) {
        $message .= ' Also recognized: ' . implode(', ', $otherRecognizedLearners) . '. One attendance is recorded per scan.';
    }
    echo json_encode([
        'success' => true,
        'message' => $message,
        'slot_label' => $slot['label'],
        'learner' => $recognizedLearner,
        'recognized_learners' => array_column(array_column($recognizedCandidates, 'learner'), 'name'),
        'face_boxes' => $recognizedFaceBoxes,
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $code = $e->getCode();
    http_response_code(is_int($code) && $code >= 400 && $code < 600 ? $code : 500);
    $response = ['success' => false, 'message' => $e->getMessage()];
    if (is_array($recognizedLearner)) {
        $response['learner'] = $recognizedLearner;
        $response['face_boxes'] = $recognizedFaceBoxes;
    }
    echo json_encode($response);
}
