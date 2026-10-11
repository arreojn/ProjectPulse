<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/attendance_settings.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    $user = require_login();
    if (($user['role'] ?? '') !== 'admin') {
        throw new RuntimeException('Only admins can change face recognition strictness.', 403);
    }
    if (!is_post()) {
        throw new RuntimeException('Only POST requests are allowed.', 405);
    }

    $payload = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }
    $csrfToken = $payload['csrf_token'] ?? null;
    if (!is_string($csrfToken) || !verify_csrf_token($csrfToken)) {
        throw new RuntimeException('Invalid session token. Please refresh the page.', 419);
    }

    $strictness = $payload['strictness'] ?? null;
    if (!is_string($strictness) || !array_key_exists($strictness, face_recognition_strictness_options())) {
        throw new RuntimeException('Invalid face recognition strictness level.', 422);
    }

    $details = face_recognition_strictness_set($strictness);
    echo json_encode([
        'success' => true,
        'strictness' => $details,
        'message' => 'Face recognition strictness updated. It applies to all face attendance scans.',
    ]);
} catch (Throwable $exception) {
    $code = $exception->getCode();
    http_response_code(is_int($code) && $code >= 400 && $code < 600 ? $code : 500);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
}
