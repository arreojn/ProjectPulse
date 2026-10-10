<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/learners.php';
require_once __DIR__ . '/../app/parents.php';
require_once __DIR__ . '/../app/teachers.php';
require_once __DIR__ . '/../app/grades.php';
require_once __DIR__ . '/../app/announcements.php';
require_once __DIR__ . '/../app/issues.php';
require_once __DIR__ . '/../app/theme_settings.php';

function teacher_workflow_redirect_url(string $module, array $params = []): string
{
    $query = http_build_query(array_merge(['module' => $module], $params));

    return route_url('teacher.php' . ($query !== '' ? '?' . $query : ''));
}

$user = require_roles(['teacher']);
header('Content-Type: application/json; charset=UTF-8');

if (!is_post()) {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests are allowed.',
    ]);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(419);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid session token. Please refresh the page.',
    ]);
    exit;
}

$section = teacher_assigned_section((int) $user['id']);
$formAction = trim((string) ($_POST['form_action'] ?? ''));

try {
    if ($section === null && $formAction !== 'save_theme') {
        throw new RuntimeException('Your teacher account is not assigned to a section yet.');
    }

    switch ($formAction) {
        case 'create_parent_and_link': {
            $payload = parent_account_normalize_payload($_POST);
            $learner = teacher_accessible_learner((int) $user['id'], (int) $payload['learner_id']);

            if ($learner === null) {
                throw new RuntimeException('The selected learner is not part of your assigned section.');
            }

            parent_create_account_and_link($payload, (int) $learner['id']);
            flash_set('teacher_dashboard', 'Parent account created and linked successfully.');
            echo json_encode([
                'success' => true,
                'message' => 'Parent account created and linked successfully.',
                'redirect' => teacher_workflow_redirect_url('create_parent_account'),
            ]);
            exit;
        }

        case 'link_existing_parent': {
            $payload = parent_account_normalize_payload($_POST);
            $learner = teacher_accessible_learner((int) $user['id'], (int) $payload['learner_id']);

            if ($learner === null) {
                throw new RuntimeException('The selected learner is not part of your assigned section.');
            }

            $parentAccount = parent_find_account_by_identity($payload['identity']);
            if ($parentAccount === null) {
                throw new RuntimeException('No parent account matched the provided username or email.');
            }

            parent_link_learner(
                (int) $parentAccount['id'],
                (int) $learner['id'],
                $payload['relationship'],
                $payload['is_primary_contact'] === '1'
            );
            flash_set('teacher_dashboard', 'Existing parent account linked successfully.');
            echo json_encode([
                'success' => true,
                'message' => 'Existing parent account linked successfully.',
                'redirect' => teacher_workflow_redirect_url('link_parent_account'),
            ]);
            exit;
        }

        case 'import_parent_accounts': {
            $importedCount = teacher_import_parent_accounts((int) $user['id'], $_FILES['parent_import_file'] ?? []);
            flash_set('teacher_dashboard', 'Imported and linked ' . $importedCount . ' parent account(s) successfully.');
            echo json_encode([
                'success' => true,
                'message' => 'Imported and linked ' . $importedCount . ' parent account(s) successfully.',
                'redirect' => teacher_workflow_redirect_url('create_parent_account'),
            ]);
            exit;
        }

        case 'import_grades': {
            $importedCount = grade_import_file_for_teacher((int) $user['id'], $_FILES['grade_import_file'] ?? []);
            flash_set('teacher_dashboard', 'Imported ' . $importedCount . ' grade record(s) successfully.');
            echo json_encode([
                'success' => true,
                'message' => 'Imported ' . $importedCount . ' grade record(s) successfully.',
                'redirect' => teacher_workflow_redirect_url('grades_import'),
            ]);
            exit;
        }

        case 'save_learner_profile': {
            $payload = learner_profile_normalize_payload($_POST);
            teacher_update_learner_profile((int) $user['id'], $payload);
            flash_set('teacher_dashboard', 'Learner basic profile updated successfully.');
            echo json_encode([
                'success' => true,
                'message' => 'Learner basic profile updated successfully.',
                'redirect' => teacher_workflow_redirect_url('learner_profiles', ['profile_learner_id' => (string) $payload['learner_id']]),
            ]);
            exit;
        }

        case 'import_learner_profiles': {
            $importedCount = teacher_import_learner_profiles((int) $user['id'], $_FILES['learner_profile_file'] ?? []);
            flash_set('teacher_dashboard', 'Imported ' . $importedCount . ' learner basic profile row(s) successfully.');
            echo json_encode([
                'success' => true,
                'message' => 'Imported ' . $importedCount . ' learner basic profile row(s) successfully.',
                'redirect' => teacher_workflow_redirect_url('learner_profiles'),
            ]);
            exit;
        }

        case 'save_announcement': {
            announcement_save($_POST, (int) $user['id']);
            flash_set('teacher_dashboard', 'Announcement saved successfully.');
            echo json_encode([
                'success' => true,
                'message' => 'Announcement saved successfully.',
                'redirect' => teacher_workflow_redirect_url('announcements'),
            ]);
            exit;
        }

        case 'delete_announcement': {
            $announcementId = (int) ($_POST['announcement_id'] ?? 0);
            $existingAnnouncement = announcement_find($announcementId);

            if ($existingAnnouncement === null || (int) $existingAnnouncement['created_by_user_id'] !== (int) $user['id']) {
                throw new RuntimeException('You can only delete your own announcements.');
            }

            announcement_delete($announcementId);
            flash_set('teacher_dashboard', 'Announcement deleted successfully.');
            echo json_encode([
                'success' => true,
                'message' => 'Announcement deleted successfully.',
                'redirect' => teacher_workflow_redirect_url('announcements'),
            ]);
            exit;
        }

        case 'save_theme': {
            $themeKey = (string) ($_POST['theme_key'] ?? '');
            theme_colors_save($themeKey);
            flash_set('teacher_settings', 'Theme saved successfully.');
            echo json_encode([
                'success' => true,
                'message' => 'Theme saved successfully.',
                'redirect' => teacher_workflow_redirect_url('settings'),
            ]);
            exit;
        }

        case 'report_issue': {
            $issueForm = issue_normalize_payload($_POST);
            issue_report_for_teacher((int) $user['id'], $issueForm);
            flash_set('teacher_settings', 'Issue reported successfully. Thank you for your feedback!');
            echo json_encode([
                'success' => true,
                'message' => 'Issue reported successfully. Thank you for your feedback!',
                'redirect' => teacher_workflow_redirect_url('settings'),
            ]);
            exit;
        }

        default:
            throw new RuntimeException('Unsupported teacher workflow action.');
    }
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
    exit;
}
