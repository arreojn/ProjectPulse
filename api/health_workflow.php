<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/health.php';

require_roles(['health']);

header('Content-Type: application/json; charset=UTF-8');

if (!is_post()) {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests are allowed.',
    ]);
    exit;
}

$user = current_user();
if ($user === null) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Your session is no longer valid. Please sign in again.',
    ]);
    exit;
}

$payload = [];
if (str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
    $decoded = json_decode((string) file_get_contents('php://input'), true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

if ($payload === []) {
    $payload = $_POST;
}

$formAction = (string) ($payload['form_action'] ?? '');

if (!verify_csrf_token($payload['csrf_token'] ?? null)) {
    http_response_code(419);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid form token. Please refresh the page.',
    ]);
    exit;
}

try {
    if ($formAction === 'save_measurement') {
        $learnerEnrollmentId = (int) ($payload['learner_enrollment_id'] ?? 0);
        $heightCm = $payload['height_cm'] ?? null;
        $weightKg = $payload['weight_kg'] ?? null;

        health_save_measurement($learnerEnrollmentId, $heightCm, $weightKg);
        health_update_learner_disability(
            $learnerEnrollmentId,
            $payload['has_disability'] ?? '0',
            $payload['disability_basis'] ?? '',
            $payload['disability_type'] ?? ''
        );

        $bmi = null;
        if ($heightCm !== null && $weightKg !== null && trim((string) $heightCm) !== '' && trim((string) $weightKg) !== '') {
            $bmi = health_calculate_bmi($heightCm, $weightKg);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Learner health and disability information updated successfully.',
            'bmi' => $bmi !== null ? number_format((float) $bmi, 2, '.', '') : null,
        ]);
        exit;
    }

    if ($formAction === 'import_measurements') {
        $importedCount = health_import_measurement_file($_FILES['measurement_file'] ?? []);

        echo json_encode([
            'success' => true,
            'message' => 'Imported height and weight for ' . $importedCount . ' learner(s).',
            'count' => $importedCount,
        ]);
        exit;
    }

    if ($formAction === 'assign_deworming_class') {
        $filters = health_filter_defaults($payload);
        $doseNumber = (int) ($payload['dose_number'] ?? 0);
        $administeredOn = trim((string) ($payload['administered_on'] ?? ''));
        $updatedCount = health_assign_deworming_class($filters, $doseNumber, $administeredOn, (int) $user['id']);

        echo json_encode([
            'success' => true,
            'message' => 'Assigned deworming dose to ' . $updatedCount . ' learner(s).',
            'count' => $updatedCount,
        ]);
        exit;
    }

    if ($formAction === 'assign_deworming_individual') {
        $learnerEnrollmentId = (int) ($payload['learner_enrollment_id'] ?? 0);
        $doseNumber = (int) ($payload['dose_number'] ?? 0);
        $administeredOn = trim((string) ($payload['administered_on'] ?? ''));
        health_assign_deworming_individual($learnerEnrollmentId, $doseNumber, $administeredOn, (int) $user['id']);

        echo json_encode([
            'success' => true,
            'message' => 'Individual deworming record updated successfully.',
        ]);
        exit;
    }

    if ($formAction === 'clear_deworming_selected') {
        $clearedCount = health_clear_deworming_records(
            (array) ($payload['learner_enrollment_ids'] ?? []),
            (int) ($payload['dose_number'] ?? 0)
        );

        echo json_encode([
            'success' => true,
            'message' => 'Cleared deworming dose for ' . $clearedCount . ' learner(s).',
            'count' => $clearedCount,
        ]);
        exit;
    }

    if ($formAction === 'add_feeding_recipients') {
        $addedCount = health_add_feeding_recipients((array) ($payload['learner_enrollment_ids'] ?? []), (int) $user['id']);
        $message = $addedCount > 0
            ? 'Added ' . $addedCount . ' learner(s) to the feeding program.'
            : 'All selected learners are already listed as feeding program recipients.';

        echo json_encode([
            'success' => true,
            'message' => $message,
            'count' => $addedCount,
        ]);
        exit;
    }

    if ($formAction === 'remove_feeding_recipient') {
        health_remove_feeding_recipient((int) ($payload['learner_enrollment_id'] ?? 0));

        echo json_encode([
            'success' => true,
            'message' => 'Learner removed from the feeding program list.',
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Unsupported health action.',
    ]);
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
}
