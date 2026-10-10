<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/guidance.php';

$user = require_roles(['guidance']);

header('Content-Type: application/json; charset=UTF-8');

if (!is_post()) {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests are allowed.',
    ]);
    exit;
}

$payload = $_POST;
if ($payload === []) {
    $rawPayload = file_get_contents('php://input');
    if (is_string($rawPayload) && $rawPayload !== '') {
        $decoded = json_decode($rawPayload, true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }
}

if (!verify_csrf_token($payload['csrf_token'] ?? null)) {
    http_response_code(419);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid session token. Please refresh the page.',
    ]);
    exit;
}

$caseId = isset($payload['case_id']) ? (int) $payload['case_id'] : 0;
if ($caseId <= 0) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'A valid guidance case is required.',
    ]);
    exit;
}

try {
    guidance_save_counseling_session($caseId, $payload, (int) $user['id']);
    flash_set('guidance_portal', 'Counseling session recorded.');

    echo json_encode([
        'success' => true,
        'redirect' => route_url('guidance.php?module=case_detail&case_id=' . urlencode((string) $caseId)),
        'message' => 'Counseling session recorded.',
    ]);
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
}
