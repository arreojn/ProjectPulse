<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/import_security.php';

function test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function test_throws(callable $callback): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        return;
    }

    throw new RuntimeException('Expected the operation to reject invalid input.');
}

import_validate_headers(['lrn', 'school_year', 'grade_level'], ['lrn', 'school_year']);
test_throws(static function (): void {
    import_validate_headers(['lrn', 'lrn'], ['lrn']);
});
test_throws(static function (): void {
    import_assert_row_limit(PROJECTPULSE_IMPORT_MAX_ROWS + 1);
});
test_assert(csv_safe_value('=SUM(A1:A2)') === "'=SUM(A1:A2)", 'CSV formula values must be escaped.');
test_assert(csv_safe_value('ordinary text') === 'ordinary text', 'Normal CSV values must remain unchanged.');

$gradesSource = file_get_contents(__DIR__ . '/../app/grades.php');
$authSource = file_get_contents(__DIR__ . '/../app/auth.php');
$resetSource = file_get_contents(__DIR__ . '/../password_resets.php');
$faceSource = file_get_contents(__DIR__ . '/../face_enrollment_event.php');

test_assert(is_string($gradesSource) && str_contains($gradesSource, 'tsa.teacher_user_id'), 'Grade imports must enforce teacher assignment ownership.');
test_assert(is_string($authSource) && str_contains($authSource, 'auth_version'), 'Sessions must use an authorization version.');
test_assert(is_string($resetSource) && str_contains($resetSource, 'must_change_password = 1'), 'Reset passwords must force a password change.');
test_assert(is_string($faceSource) && str_contains($faceSource, 'verify_csrf_token'), 'Face enrollment must remain CSRF protected.');

fwrite(STDOUT, "Security regression checks passed.\n");
