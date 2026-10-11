<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/learners.php';
require_once __DIR__ . '/../app/sections.php';

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
    if ($formAction === 'save_section') {
        $payload = section_normalize_payload($_POST);
        section_save($payload);

        echo json_encode([
            'success' => true,
            'message' => $payload['id'] === null ? 'Section created successfully.' : 'Section updated successfully.',
            'redirect' => route_url('admin.php?module=sections_management'),
        ]);
        exit;
    }

    if ($formAction === 'delete_section') {
        section_delete((int) ($_POST['section_id'] ?? 0));

        echo json_encode([
            'success' => true,
            'message' => 'Section deleted successfully.',
            'redirect' => route_url('admin.php?module=sections_management'),
        ]);
        exit;
    }

    throw new RuntimeException('Unsupported section action.');
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
}
