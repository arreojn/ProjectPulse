<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../password_resets.php';

require_roles(['admin']);

header('Content-Type: application/json; charset=UTF-8');

if (!is_post()) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed.']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Invalid session token. Please refresh the page.']);
    exit;
}

$formAction = trim((string) ($_POST['form_action'] ?? ''));

try {
    $requestId = (int) ($_POST['request_id'] ?? 0);

    if ($formAction === 'approve_reset') {
        $temporaryPassword = approve_password_reset($requestId, (int) ($_SESSION['user']['id'] ?? 0));

        echo json_encode([
            'success' => true,
            'message' => 'Password reset approved. One-time temporary password: ' . $temporaryPassword . '. Share it securely.',
            'redirect' => route_url('admin.php?module=password_resets'),
        ]);
        exit;
    }

    if ($formAction === 'deny_reset') {
        deny_password_reset($requestId, (int) ($_SESSION['user']['id'] ?? 0));

        echo json_encode([
            'success' => true,
            'message' => 'Password reset request has been denied.',
            'redirect' => route_url('admin.php?module=password_resets'),
        ]);
        exit;
    }

    throw new RuntimeException('Unsupported password reset action.');
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
}
