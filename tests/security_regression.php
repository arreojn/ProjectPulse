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
$faceAttendanceSource = file_get_contents(__DIR__ . '/../face_attendance.php');
$faceAttendanceScript = file_get_contents(__DIR__ . '/../src/attendance/face.js');
$adminSettingsSource = file_get_contents(__DIR__ . '/../admin.php');
$faceTrainerSource = file_get_contents(__DIR__ . '/../ai_scanner/identify_face.py');
$faceServiceSource = file_get_contents(__DIR__ . '/../ai_scanner/recognition_service.py');
$faceSettingsSource = file_get_contents(__DIR__ . '/../app/attendance_settings.php');
$faceStrictnessApiSource = file_get_contents(__DIR__ . '/../api/face_recognition_strictness.php');
$faceAttendanceEventSource = file_get_contents(__DIR__ . '/../face_attendance_event.php');
$faceEnrollmentEventSource = file_get_contents(__DIR__ . '/../face_enrollment_event.php');
$faceEnrollmentScript = file_get_contents(__DIR__ . '/../src/attendance/enrollment.js');
$faceHelpersSource = file_get_contents(__DIR__ . '/../app/helpers.php');
$trainModelSource = file_get_contents(__DIR__ . '/../train_model.php');

test_assert(is_string($gradesSource) && str_contains($gradesSource, 'tsa.teacher_user_id'), 'Grade imports must enforce teacher assignment ownership.');
test_assert(is_string($authSource) && str_contains($authSource, 'auth_version'), 'Sessions must use an authorization version.');
test_assert(is_string($resetSource) && str_contains($resetSource, 'must_change_password = 1'), 'Reset passwords must force a password change.');
test_assert(is_string($faceSource) && str_contains($faceSource, 'verify_csrf_token'), 'Face enrollment must remain CSRF protected.');
test_assert(is_string($faceSource) && str_contains($faceSource, "'/app/learners.php'"), 'Face enrollment must load learner bootstrap before checking a teacher assignment.');
test_assert(is_string($faceEnrollmentEventSource) && str_contains($faceEnrollmentEventSource, '$imageNumber > $maxPhotos'), 'Face enrollment must validate the configured photo limit.');
test_assert(is_string($faceHelpersSource) && str_contains($faceHelpersSource, 'function face_enrollment_max_photos(): int') && str_contains($faceHelpersSource, 'return 5;'), 'Face enrollment must allow up to five photos and detect all saved slots.');
test_assert(is_string($faceEnrollmentScript) && str_contains($faceEnrollmentScript, 'config.maxPhotos'), 'Face enrollment controls must use the configured photo limit.');
test_assert(is_string($faceTrainerSource) && str_contains($faceTrainerSource, 'PHOTO_NUMBER_LIMIT = 5'), 'Face model training must include photo slots through five.');
test_assert(is_string($faceTrainerSource) && str_contains($faceTrainerSource, 'learner_labels[learner_lrn]'), 'Face training must group multiple photos under the same learner identity.');
test_assert(is_string($faceServiceSource) && str_contains($faceServiceSource, 'model_is_current()'), 'Face attendance must reject stale models after enrollment photos change.');
test_assert(is_string($faceServiceSource) && str_contains($faceServiceSource, 'MATCH_CONFIDENCE_LIMIT = 25.0'), 'Face attendance must reject uncertain LBPH matches.');
test_assert(is_string($faceServiceSource) && str_contains($faceServiceSource, '"very_strict": 20.0') && str_contains($faceServiceSource, '"strict": MATCH_CONFIDENCE_LIMIT') && str_contains($faceServiceSource, '"balanced": 35.0') && str_contains($faceServiceSource, '"relaxed": 45.0') && str_contains($faceServiceSource, '"very_relaxed": 55.0'), 'Face attendance strictness levels must use explicit worker-side confidence thresholds.');
test_assert(is_string($faceSettingsSource) && str_contains($faceSettingsSource, "'very_strict'") && str_contains($faceSettingsSource, "'very_relaxed'"), 'Admins must be offered both added strictness presets.');
test_assert(is_string($faceServiceSource) && str_contains($faceServiceSource, 'X-Face-Strictness'), 'The recognition worker must receive the selected strictness level.');
test_assert(is_string($faceSettingsSource) && str_contains($faceSettingsSource, 'face_recognition_strictness_set'), 'Face recognition strictness must be persisted in system settings.');
test_assert(is_string($adminSettingsSource) && str_contains($adminSettingsSource, 'api/face_recognition_strictness.php') && str_contains($adminSettingsSource, 'Recognition Strictness'), 'Face recognition strictness must be managed from Admin Settings.');
test_assert(is_string($adminSettingsSource) && str_contains($adminSettingsSource, 'Retrain Face Recognition Model') && str_contains($adminSettingsSource, "route_url('train_model.php')"), 'Admins must be able to retrain the face model from Admin Settings.');
test_assert(is_string($trainModelSource) && str_contains($trainModelSource, "require_roles(['admin'])") && str_contains($trainModelSource, 'verify_csrf_token') && str_contains($trainModelSource, 'Face recognition model retrained successfully.'), 'Face model retraining must stay admin-only, CSRF-protected, and provide a clear success message.');
test_assert(is_string($faceAttendanceSource) && !str_contains($faceAttendanceSource, 'face-strictness'), 'The scanner page must not contain the recognition strictness control.');
test_assert(is_string($faceStrictnessApiSource) && str_contains($faceStrictnessApiSource, "(\$user['role'] ?? '') !== 'admin'") && str_contains($faceStrictnessApiSource, 'verify_csrf_token'), 'Changing face recognition strictness must be admin-only and CSRF protected.');
test_assert(is_string($faceAttendanceEventSource) && str_contains($faceAttendanceEventSource, 'X-Face-Strictness: ') && str_contains($faceAttendanceEventSource, '$strictness'), 'Attendance scans must use the persisted strictness setting.');
test_assert(is_string($faceAttendanceEventSource) && str_contains($faceAttendanceEventSource, 'strictness_levels'), 'Attendance scans must reject outdated workers that cannot apply configurable strictness.');
test_assert(is_string($faceServiceSource) && str_contains($faceServiceSource, 'CONFIRMATION_SCANS = 3'), 'Face attendance must confirm the same identity over multiple scans.');
test_assert(is_string($faceServiceSource) && str_contains($faceServiceSource, 'CONFIRMATION_MISS_GRACE_SECONDS = 1.25') && str_contains($faceServiceSource, '"last_seen_at"'), 'Face attendance must preserve multi-scan confirmations across brief detection gaps.');
test_assert(is_string($faceAttendanceScript) && str_contains($faceAttendanceScript, 'SCAN_INTERVAL_MS = 150') && str_contains($faceAttendanceScript, 'if (this.processing'), 'Face scans must run responsively without overlapping recognition requests.');
test_assert(is_string($faceServiceSource) && str_contains($faceServiceSource, 'for x, y, width, height in faces') && str_contains($faceServiceSource, 'self.confirmations = {}'), 'Face attendance must recognize and confirm faces independently in multi-face frames.');
test_assert(is_string($faceAttendanceEventSource) && str_contains($faceAttendanceEventSource, 'foreach ($recognizedCandidates as $candidate)') && str_contains($faceAttendanceEventSource, 'break;'), 'Face attendance must record at most one eligible learner per scan.');
test_assert(is_string($faceAttendanceSource) && str_contains($faceAttendanceSource, 'id="face-overlay"'), 'Face attendance must include a camera overlay for detected faces.');
test_assert(is_string($faceServiceSource) && str_contains($faceServiceSource, '"face_box": face_box'), 'Face recognition must return each matched face box.');
test_assert(is_string($faceAttendanceScript) && str_contains($faceAttendanceScript, 'box.name') && str_contains($faceAttendanceScript, "box.status === 'recognized'"), 'Face attendance must label each confirmed face with its learner name.');

fwrite(STDOUT, "Security regression checks passed.\n");
