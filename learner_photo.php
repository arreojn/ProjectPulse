<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/auth.php';

$user = require_roles(['admin', 'attendance', 'health', 'guidance', 'teacher', 'parent']);
$lrn = preg_replace('/\D+/', '', trim((string) ($_GET['lrn'] ?? ''))) ?? '';

if ($lrn === '' || strlen($lrn) !== 12) {
    http_response_code(404);
    exit;
}

$role = (string) ($user['role'] ?? '');
if ($role === 'teacher') {
    $statement = database()->prepare(
        'SELECT 1
         FROM teacher_section_assignments tsa
         INNER JOIN learner_enrollments le
            ON le.section_id = tsa.section_id AND le.school_year_id = tsa.school_year_id
         INNER JOIN learners l ON l.id = le.learner_id
         WHERE tsa.teacher_user_id = :user_id AND l.lrn = :lrn
         LIMIT 1'
    );
    $statement->execute(['user_id' => (int) $user['id'], 'lrn' => $lrn]);
    if ($statement->fetchColumn() === false) {
        http_response_code(403);
        exit;
    }
} elseif ($role === 'parent') {
    $statement = database()->prepare(
        'SELECT 1
         FROM parents p
         INNER JOIN parent_learner_links pll ON pll.parent_id = p.id
         INNER JOIN learners l ON l.id = pll.learner_id
         WHERE p.user_id = :user_id AND l.lrn = :lrn
         LIMIT 1'
    );
    $statement->execute(['user_id' => (int) $user['id'], 'lrn' => $lrn]);
    if ($statement->fetchColumn() === false) {
        http_response_code(403);
        exit;
    }
}

$photoPath = null;
foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
    $candidate = learner_photo_storage_directory() . $lrn . '.' . $extension;
    if (is_file($candidate)) {
        $photoPath = $candidate;
        break;
    }
}

if ($photoPath === null) {
    http_response_code(404);
    exit;
}

$mimeType = mime_content_type($photoPath) ?: 'application/octet-stream';
if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) filesize($photoPath));
header('Cache-Control: private, no-store');
readfile($photoPath);
