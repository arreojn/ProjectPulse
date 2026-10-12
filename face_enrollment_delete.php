<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/learners.php';
require_once __DIR__ . '/app/teachers.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    $user = require_roles(['admin', 'teacher']);

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

    $lrn = $payload['lrn'] ?? null;
    if (!is_string($lrn) || preg_match('/^\d{12}$/', $lrn) !== 1) {
        throw new RuntimeException('A valid 12-digit LRN is required.', 422);
    }

    if (($user['role'] ?? '') === 'teacher') {
        if (teacher_accessible_learner_by_lrn((int) $user['id'], $lrn) === null) {
            throw new RuntimeException('The selected learner is not part of your assigned section.', 403);
        }
    } else {
        learner_management_bootstrap();
        $learnerStatement = database()->prepare('SELECT 1 FROM learners WHERE lrn = :lrn LIMIT 1');
        $learnerStatement->execute(['lrn' => $lrn]);
        if ($learnerStatement->fetchColumn() === false) {
            throw new RuntimeException('The selected learner could not be found.', 404);
        }
    }

    $storageDirectory = learner_photo_storage_directory();
    $deletedCount = 0;
    $extensions = ['jpg', 'jpeg', 'png', 'webp'];
    $photoNames = [$lrn];
    for ($slot = 1; $slot <= face_enrollment_max_photos(); $slot++) {
        $photoNames[] = $lrn . '_' . $slot;
    }

    foreach ($photoNames as $photoName) {
        foreach ($extensions as $extension) {
            $photoPath = $storageDirectory . $photoName . '.' . $extension;
            if (!is_file($photoPath)) {
                continue;
            }
            if (!unlink($photoPath)) {
                throw new RuntimeException('Could not delete all captured photos. Check photo storage permissions and try again.');
            }
            $deletedCount++;
        }
    }

    echo json_encode([
        'success' => true,
        'deleted_count' => $deletedCount,
        'photo_slots' => [],
        'message' => $deletedCount > 0
            ? 'Deleted ' . $deletedCount . ' captured photo' . ($deletedCount === 1 ? '' : 's') . '. Retrain the face recognition model before scanning.'
            : 'No captured photos were found for this learner.',
    ]);
} catch (Throwable $exception) {
    $code = $exception->getCode();
    http_response_code(is_int($code) && $code >= 400 && $code < 600 ? $code : 500);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
}
