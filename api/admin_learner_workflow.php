<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/learners.php';

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
    if ($formAction === 'save_learner') {
        $payload = learner_normalize_payload($_POST);
        learner_save($payload);

        echo json_encode([
            'success' => true,
            'message' => $payload['id'] === null ? 'Learner created successfully.' : 'Learner updated successfully.',
            'redirect' => route_url('admin.php?module=learner_management'),
        ]);
        exit;
    }

    if ($formAction === 'delete_learner') {
        learner_delete((int) ($_POST['learner_id'] ?? 0));

        echo json_encode([
            'success' => true,
            'message' => 'Learner deleted successfully.',
            'redirect' => route_url('admin.php?module=learner_management'),
        ]);
        exit;
    }

    if ($formAction === 'import_learners') {
        $importCount = learner_import_file($_FILES['import_file'] ?? []);

        echo json_encode([
            'success' => true,
            'message' => 'Imported ' . $importCount . ' learner(s) successfully.',
            'redirect' => route_url('admin.php?module=learner_management'),
        ]);
        exit;
    }

    throw new RuntimeException('Unsupported learner action.');
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
}
