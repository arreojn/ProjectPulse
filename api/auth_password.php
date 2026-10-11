<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../password_resets.php';

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

$action = trim((string) ($payload['action'] ?? ''));

try {
    if (!verify_csrf_token($payload['csrf_token'] ?? null)) {
        http_response_code(419);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid session token. Please refresh the page.',
        ]);
        exit;
    }

    if ($action === 'forgot') {
        $identity = trim((string) ($payload['identity'] ?? ''));
        if ($identity === '') {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Username or email is required.',
            ]);
            exit;
        }

        request_password_reset($identity);
        echo json_encode([
            'success' => true,
            'message' => 'Your request has been submitted. If the account exists, a reset request will be reviewed by the administrator.',
        ]);
        exit;
    }

    if ($action === 'change') {
        $user = current_user();
        if ($user === null) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Please sign in again to change your password.',
            ]);
            exit;
        }

        $currentPassword = (string) ($payload['current_password'] ?? '');
        $newPassword = (string) ($payload['new_password'] ?? '');
        $confirmPassword = (string) ($payload['confirm_password'] ?? '');

        if ($currentPassword === '') {
            throw new RuntimeException('Current password is required.');
        }

        if (strlen($newPassword) < 12) {
            throw new RuntimeException('New password must be at least 12 characters.');
        }

        if ($newPassword !== $confirmPassword) {
            throw new RuntimeException('New password confirmation does not match.');
        }

        auth_change_password((int) $user['id'], $currentPassword, $newPassword);
        echo json_encode([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'The requested action is not supported.',
    ]);
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
}
