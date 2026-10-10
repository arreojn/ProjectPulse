<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/issues.php';

require_roles(['admin']);

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

$issueId = filter_var($payload['issue_id'] ?? null, FILTER_VALIDATE_INT);
$status = trim((string) ($payload['status'] ?? ''));

if ($issueId === false || $issueId === null || $issueId < 1) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'A valid issue ID is required.',
    ]);
    exit;
}

try {
    if (issue_find($issueId) === null) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'The reported issue could not be found.',
        ]);
        exit;
    }

    issue_update_status($issueId, $status);

    echo json_encode([
        'success' => true,
        'status' => $status,
        'message' => 'Issue status updated successfully.',
    ]);
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
}
