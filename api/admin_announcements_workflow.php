<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/announcements.php';

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
    if ($formAction === 'save_announcement') {
        $createdById = (int) ($_SESSION['user']['id'] ?? 0);
        announcement_save($_POST, $createdById);

        echo json_encode([
            'success' => true,
            'message' => 'Announcement saved successfully.',
            'redirect' => route_url('admin.php?module=announcements'),
        ]);
        exit;
    }

    if ($formAction === 'delete_announcement') {
        announcement_delete((int) ($_POST['announcement_id'] ?? 0));

        echo json_encode([
            'success' => true,
            'message' => 'Announcement deleted successfully.',
            'redirect' => route_url('admin.php?module=announcements'),
        ]);
        exit;
    }

    throw new RuntimeException('Unsupported announcement action.');
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
}
