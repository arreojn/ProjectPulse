<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/theme_settings.php';
require_once __DIR__ . '/../app/sms_settings.php';
require_once __DIR__ . '/../app/sms_gateway.php';

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
    if ($formAction === 'save_theme') {
        theme_colors_save((string) ($_POST['theme_key'] ?? ''));

        echo json_encode([
            'success' => true,
            'message' => 'Portal theme saved successfully.',
            'redirect' => route_url('admin.php?module=settings'),
        ]);
        exit;
    }

    if ($formAction === 'reset_theme') {
        theme_colors_reset();

        echo json_encode([
            'success' => true,
            'message' => 'Theme colors have been reset to default.',
            'redirect' => route_url('admin.php?module=settings'),
        ]);
        exit;
    }

    if ($formAction === 'save_sms_settings') {
        sms_settings_save(
            (bool) (($_POST['sms_gateway_enabled'] ?? '') === '1'),
            (string) ($_POST['sms_gateway_local_address'] ?? ''),
            (string) ($_POST['sms_gateway_username'] ?? ''),
            (string) ($_POST['sms_gateway_password'] ?? ''),
            (string) ($_POST['sms_gateway_device_id'] ?? ''),
            (string) ($_POST['sms_gateway_public_address'] ?? '')
        );

        echo json_encode([
            'success' => true,
            'message' => 'SMS settings saved successfully.',
            'redirect' => route_url('admin.php?module=settings'),
        ]);
        exit;
    }

    if ($formAction === 'test_sms_settings') {
        $result = sms_send_test_message((string) ($_POST['sms_test_phone'] ?? ''));

        if ($result['sent']) {
            echo json_encode([
                'success' => true,
                'message' => 'Test SMS sent successfully to the selected number.',
                'redirect' => route_url('admin.php?module=settings'),
            ]);
        } else {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => $result['reason'],
            ]);
        }
        exit;
    }

    throw new RuntimeException('Unsupported settings action.');
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
}
