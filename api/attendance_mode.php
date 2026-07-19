<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/attendance_settings.php';

$user = require_login();

header('Content-Type: application/json; charset=UTF-8');

if (($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Only admins can change the attendance scan mode.',
    ]);
    exit;
}

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

$mode = trim((string) ($payload['mode'] ?? ''));

try {
    $details = attendance_scan_mode_set($mode);

    echo json_encode([
        'success' => true,
        'mode' => $details['key'],
        'label' => $details['label'],
        'description' => $details['description'],
        'message' => 'Attendance scan mode updated successfully.',
    ]);
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
}
