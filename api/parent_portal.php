<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/parents.php';
require_once __DIR__ . '/../app/grades.php';
require_once __DIR__ . '/../app/announcements.php';

$user = require_roles(['parent']);

header('Content-Type: application/json; charset=UTF-8');

if (is_post()) {
    $payload = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $action = trim((string) ($payload['action'] ?? ''));
    if ($action === 'acknowledge_announcements') {
        if (!verify_csrf_token($payload['csrf_token'] ?? null)) {
            http_response_code(419);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid session token. Please refresh the page.',
            ]);
            exit;
        }

        $_SESSION['seen_parent_announcements'] = true;
        echo json_encode([
            'success' => true,
            'acknowledged' => true,
            'message' => 'Announcement acknowledgement saved.',
        ]);
        exit;
    }
}

$linkedLearners = parent_linked_learners((int) $user['id']);
$filters = parent_portal_filters($linkedLearners);
$selectedChild = parent_portal_selected_child($linkedLearners, $filters['child_id']);
$reportMonth = parent_portal_valid_month($filters['report_month']) ? $filters['report_month'] : date('Y-m');

$attendanceRows = [];
$attendanceSummary = parent_child_month_summary([]);
$gradeHistoryGroups = [];

if ($selectedChild !== null) {
    $attendanceRows = parent_child_month_attendance(
        (int) $user['id'],
        (int) $selectedChild['id'],
        $reportMonth
    );
    $attendanceSummary = parent_child_month_summary($attendanceRows);
    $gradeHistoryGroups = grade_group_history_by_level(
        grade_parent_child_history((int) $user['id'], (int) $selectedChild['id'])
    );
}

$adminAnnouncements = announcement_list(['role' => 'admin', 'is_published' => 1]);
$teacherAnnouncements = announcement_for_parent((int) $user['id']);
$allAnnouncements = array_merge($adminAnnouncements, $teacherAnnouncements);
usort($allAnnouncements, static fn ($a, $b) => strtotime($b['published_at'] ?? $b['created_at']) <=> strtotime($a['published_at'] ?? $a['created_at']));

$healthOverview = [
    'height_cm' => $selectedChild['height_cm'] ?? null,
    'weight_kg' => $selectedChild['weight_kg'] ?? null,
    'bmi' => $selectedChild['bmi'] ?? null,
    'bmi_remarks' => $selectedChild['bmi_remarks'] ?? null,
];

try {
    echo json_encode([
        'success' => true,
        'child_id' => $selectedChild['id'] ?? null,
        'report_month' => $reportMonth,
        'month_label' => parent_portal_month_label($reportMonth),
        'attendance_rows' => $attendanceRows,
        'attendance_summary' => $attendanceSummary,
        'grade_history' => $gradeHistoryGroups,
        'health_record' => $healthOverview,
        'announcements' => $allAnnouncements,
        'announcement_acknowledged' => isset($_SESSION['seen_parent_announcements']) && $_SESSION['seen_parent_announcements'] === true,
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to load parent portal data.',
    ]);
}
