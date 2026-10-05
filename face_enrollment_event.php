<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/teachers.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    $user = require_roles(['admin', 'teacher']);

    if (!is_post()) {
        throw new RuntimeException('Only POST requests are allowed.', 405);
    }

    $payload = json_decode((string) file_get_contents('php://input'), true);

    if (!is_array($payload)) {
        $payload = $_POST;
    }

    if (!verify_csrf_token($payload['csrf_token'] ?? null)) {
        throw new RuntimeException('Invalid session token. Please refresh the page.', 419);
    }

    $lrn = preg_replace('/\D+/', '', (string) ($payload['lrn'] ?? ''));
    $imageDataUrl = $payload['image_data'] ?? '';

    if ($lrn === '' || strlen($lrn) !== 12) {
        throw new RuntimeException('A valid 12-digit LRN is required.', 422);
    }

    if (($user['role'] ?? '') === 'teacher' && teacher_accessible_learner_by_lrn((int) $user['id'], $lrn) === null) {
        throw new RuntimeException('The selected learner is not part of your assigned section.', 403);
    }

    if (empty($imageDataUrl) || !str_starts_with($imageDataUrl, 'data:image/jpeg;base64,')) {
        throw new RuntimeException('No valid image data received.', 422);
    }

    $imgData = base64_decode(preg_replace('#^data:image/jpeg;base64,#i', '', $imageDataUrl), true);
    if ($imgData === false || strlen($imgData) > 5 * 1024 * 1024 || @getimagesizefromstring($imgData) === false) {
        throw new RuntimeException('The captured image is invalid or too large.', 422);
    }

    $storageDirectory = learner_photo_storage_directory();
    if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0700, true) && !is_dir($storageDirectory)) {
        throw new RuntimeException('The learner photo storage directory is unavailable.');
    }
    $filePath = $storageDirectory . $lrn . '.jpg';

    if (file_put_contents($filePath, $imgData) === false) {
        throw new RuntimeException('Failed to save learner photo on the server.');
    }

    echo json_encode(['success' => true, 'message' => 'Learner photo captured successfully.']);

} catch (Throwable $e) {
    http_response_code(is_int($e->getCode()) && $e->getCode() >= 400 ? $e->getCode() : 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
