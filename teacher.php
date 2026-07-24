<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/learners.php';
require_once __DIR__ . '/app/parents.php';
require_once __DIR__ . '/app/teachers.php';
require_once __DIR__ . '/app/grades.php';
require_once __DIR__ . '/app/health.php';
require_once __DIR__ . '/app/announcements.php';
require_once __DIR__ . '/app/issues.php';
require_once __DIR__ . '/app/theme_settings.php';

function teacher_module_url(string $module, array $params = []): string
{
    $query = http_build_query(array_merge(['module' => $module], $params));

    return route_url('teacher.php' . ($query !== '' ? '?' . $query : ''));
}

function teacher_find_section_learner(array $learners, int $learnerId): ?array
{
    foreach ($learners as $learner) {
        if ((int) $learner['id'] === $learnerId) {
            return $learner;
        }
    }

    return null;
}

function teacher_profile_is_complete(array $learner): bool
{
    return trim((string) ($learner['birthdate'] ?? '')) !== ''
        && trim((string) ($learner['mother_tongue'] ?? '')) !== ''
        && trim((string) ($learner['religion'] ?? '')) !== ''
        && trim((string) ($learner['address_barangay'] ?? '')) !== '';
}

function teacher_section_learner_sex_counts(array $learners): array
{
    $counts = [
        'male' => 0,
        'female' => 0,
        'unspecified' => 0,
    ];

    foreach ($learners as $learner) {
        $sex = strtolower(trim((string) ($learner['sex'] ?? '')));

        if ($sex === 'male' || $sex === 'female') {
            $counts[$sex]++;
            continue;
        }

        $counts['unspecified']++;
    }

    return $counts;
}

function teacher_percentage(int $value, int $total): float
{
    if ($total <= 0) {
        return 0.0;
    }

    return round(($value / $total) * 100, 1);
}

function teacher_profile_address(array $learner): string
{
    $parts = array_filter([
        trim((string) ($learner['address_house_number'] ?? '')),
        trim((string) ($learner['address_barangay'] ?? '')),
        trim((string) ($learner['address_city_municipality'] ?? '')),
        trim((string) ($learner['address_province'] ?? '')),
    ], static fn ($value): bool => $value !== '');

    return $parts === [] ? '-' : implode(', ', $parts);
}

function teacher_format_date(?string $value, string $format = 'M j, Y'): string
{
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? (string) $value : date($format, $timestamp);
}

function teacher_section_bmi_remarks_rows(array $learners): array
{
    $colors = [
        'Underweight' => '#dd6b20',
        'Normal' => '#17663a',
        'Overweight' => '#b45309',
        'Obese' => '#a12b2b',
        'Not measured' => '#52606d',
    ];
    $counts = array_fill_keys(array_keys($colors), 0);

    foreach ($learners as $learner) {
        $bmi = health_calculate_bmi($learner['height_cm'] ?? null, $learner['weight_kg'] ?? null);
        $remark = $bmi === null ? 'Not measured' : health_bmi_remarks($bmi);
        $counts[$remark]++;
    }

    return array_map(
        static fn (string $label): array => ['label' => $label, 'total' => $counts[$label], 'color' => $colors[$label]],
        array_keys($colors)
    );
}

function teacher_bmi_pie_gradient(array $rows): string
{
    $total = max(1, array_sum(array_column($rows, 'total')));
    $start = 0.0;
    $segments = [];

    foreach ($rows as $row) {
        $end = $start + ((int) $row['total'] / $total * 360);
        $segments[] = $row['color'] . ' ' . number_format($start, 2, '.', '') . 'deg ' . number_format($end, 2, '.', '') . 'deg';
        $start = $end;
    }

    return 'conic-gradient(' . implode(', ', $segments) . ')';
}

function teacher_dashboard_learner_health(int $teacherUserId, int $learnerId): ?array
{
    $statement = database()->prepare(
        'SELECT hm.height_cm, hm.weight_kg, hm.recorded_on
         FROM teacher_section_assignments tsa
         INNER JOIN learner_enrollments le
            ON le.section_id = tsa.section_id
           AND le.school_year_id = tsa.school_year_id
         LEFT JOIN learner_health_measurements hm ON hm.learner_enrollment_id = le.id
         WHERE tsa.teacher_user_id = :teacher_user_id
           AND le.learner_id = :learner_id
         LIMIT 1'
    );
    $statement->execute(['teacher_user_id' => $teacherUserId, 'learner_id' => $learnerId]);
    $row = $statement->fetch();

    return $row === false ? null : $row;
}

learner_management_bootstrap();
teacher_management_bootstrap();
parent_portal_bootstrap();
grade_book_bootstrap();
health_portal_bootstrap();

$user = require_roles(['teacher']);
$allowedModules = [
    'dashboard' => [
        'eyebrow' => 'Teacher Portal',
        'title' => 'Dashboard',
        'description' => 'View your section analytics, learner gender breakdown, and advisory roster.',
    ],
    'create_parent_account' => [
        'eyebrow' => 'Parent Account',
        'title' => 'Create Parent Account',
        'description' => 'Create a parent account, link it to a learner, or bulk import accounts from a file.',
    ],
    'link_parent_account' => [
        'eyebrow' => 'Parent Account',
        'title' => 'Link Parent Account',
        'description' => 'Attach an existing parent account to one learner in your assigned section.',
    ],
    'grades_import' => [
        'eyebrow' => 'Grades',
        'title' => 'Import Grades',
        'description' => 'Upload subject grades for learners inside your assigned section and review imported records.',
    ],
    'learner_profiles' => [
        'eyebrow' => 'Learner Profile',
        'title' => 'Learner Basic Profile',
        'description' => 'Import or update birthdate, age basis, mother tongue, religion, and address details.',
    ],
    'learner_details' => [
        'eyebrow' => 'Learner Profile',
        'title' => 'Learner Information',
        'description' => 'Review the selected learner’s profile, health data, parent links, and grades.',
    ],
    'announcements' => [
        'eyebrow' => 'Communication',
        'title' => 'Announcements',
        'description' => 'Post announcements for parents of your advisory learners.',
    ],
    'settings' => [
        'eyebrow' => 'Account',
        'title' => 'Settings',
        'description' => 'Manage your portal and account settings.',
    ],
];

$module = (string) ($_GET['module'] ?? 'dashboard');

if (!isset($allowedModules[$module])) {
    $module = 'dashboard';
}

$teacherFlash = flash_get('teacher_dashboard');
$newParentForm = parent_account_form_defaults([
    'relationship' => 'Parent/Guardian',
]);
$existingParentForm = parent_account_form_defaults([
    'relationship' => 'Parent/Guardian',
]);
$profileForm = learner_profile_form_defaults();
$announcementForm = ['id' => null, 'title' => '', 'content' => '', 'is_published' => 0];
$announcementRows = [];
$adminAnnouncements = [];
$settingsFlash = flash_get('teacher_settings');
$activeThemeKey = 'default';
$issueForm = issue_form_defaults();
$announcementEditId = isset($_GET['edit_announcement_id']) ? (int) $_GET['edit_announcement_id'] : null;
$profileFormFromPost = false;
$section = teacher_assigned_section((int) $user['id']);

if (is_post()) {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Invalid form token. Please refresh the page.');
        }

        $formAction = (string) ($_POST['form_action'] ?? '');

        if ($section === null && $formAction !== 'save_theme') {
            throw new RuntimeException('Your teacher account is not assigned to a section yet.');
        }

        if ($formAction === 'create_parent_and_link') {
            $newParentForm = parent_account_normalize_payload($_POST);
            $learner = teacher_accessible_learner((int) $user['id'], (int) $newParentForm['learner_id']);

            if ($learner === null) {
                throw new RuntimeException('The selected learner is not part of your assigned section.');
            }

            parent_create_account_and_link($newParentForm, (int) $learner['id']);
            flash_set('teacher_dashboard', 'Parent account created and linked successfully.');
            redirect('teacher.php?module=create_parent_account');
        }

        if ($formAction === 'link_existing_parent') {
            $existingParentForm = parent_account_normalize_payload($_POST);
            $learner = teacher_accessible_learner((int) $user['id'], (int) $existingParentForm['learner_id']);

            if ($learner === null) {
                throw new RuntimeException('The selected learner is not part of your assigned section.');
            }

            $parentAccount = parent_find_account_by_identity($existingParentForm['identity']);

            if ($parentAccount === null) {
                throw new RuntimeException('No parent account matched the provided username or email.');
            }

            parent_link_learner(
                (int) $parentAccount['id'],
                (int) $learner['id'],
                $existingParentForm['relationship'],
                $existingParentForm['is_primary_contact'] === '1'
            );

            flash_set('teacher_dashboard', 'Existing parent account linked successfully.');
            redirect('teacher.php?module=link_parent_account');
        }

        if ($formAction === 'import_parent_accounts') {
            $importedCount = teacher_import_parent_accounts((int) $user['id'], $_FILES['parent_import_file'] ?? []);
            flash_set('teacher_dashboard', 'Imported and linked ' . $importedCount . ' parent account(s) successfully.');
            redirect('teacher.php?module=create_parent_account');
        }

        if ($formAction === 'import_grades') {
            $importedCount = grade_import_file_for_teacher((int) $user['id'], $_FILES['grade_import_file'] ?? []);
            flash_set('teacher_dashboard', 'Imported ' . $importedCount . ' grade record(s) successfully.');
            redirect('teacher.php?module=grades_import');
        }

        if ($formAction === 'save_learner_profile') {
            $profileForm = learner_profile_normalize_payload($_POST);
            $profileFormFromPost = true;
            teacher_update_learner_profile((int) $user['id'], $profileForm);

            flash_set('teacher_dashboard', 'Learner basic profile updated successfully.');
            redirect('teacher.php?module=learner_profiles&profile_learner_id=' . urlencode($profileForm['learner_id']));
        }

        if ($formAction === 'import_learner_profiles') {
            $importedCount = teacher_import_learner_profiles((int) $user['id'], $_FILES['learner_profile_file'] ?? []);
            flash_set('teacher_dashboard', 'Imported ' . $importedCount . ' learner basic profile row(s) successfully.');
            redirect('teacher.php?module=learner_profiles');
        }

        if ($formAction === 'save_announcement') {
            announcement_save($_POST, (int) $user['id']);
            flash_set('teacher_dashboard', 'Announcement saved successfully.');
            redirect('teacher.php?module=announcements');
        }

        if ($formAction === 'delete_announcement') {
            announcement_delete((int) ($_POST['announcement_id'] ?? 0));
            flash_set('teacher_dashboard', 'Announcement deleted successfully.');
            redirect('teacher.php?module=announcements');
        }

        if ($formAction === 'save_theme') {
            theme_colors_save((string) ($_POST['theme_key'] ?? ''));
            flash_set('teacher_settings', 'Theme saved successfully.');
            redirect('teacher.php?module=settings');
        }

        if ($formAction === 'report_issue') {
            $issueForm = issue_normalize_payload($_POST);
            issue_report_for_teacher((int) $user['id'], $issueForm);
            flash_set('teacher_settings', 'Issue reported successfully. Thank you for your feedback!');
            redirect('teacher.php?module=settings');
        }
    } catch (Throwable $exception) {
        $teacherFlash = [
            'type' => 'error',
            'message' => $exception->getMessage(),
        ];
    }
}

if ($module === 'announcements') {
    announcements_bootstrap();
    $announcementRows = announcement_list(['user_id' => (int) $user['id']]);

    if ($announcementEditId !== null) {
        $existingAnnouncement = announcement_find($announcementEditId);
        if ($existingAnnouncement !== null && (int) $existingAnnouncement['created_by_user_id'] === (int) $user['id']) {
            $announcementForm = $existingAnnouncement;
        }
    }
}

if ($module === 'settings') {
    theme_settings_bootstrap();
    $activeThemeKey = theme_active_key();
}

$historicalSchoolYears = teacher_historical_school_years((int) $user['id']);
$selectedGradeSchoolYearId = isset($_GET['grades_sy_id']) ? (int) $_GET['grades_sy_id'] : 0;

if ($selectedGradeSchoolYearId === 0 && !empty($historicalSchoolYears)) {
    $currentSectionSyLabel = $section['school_year_label'] ?? null;
    $currentSyId = null;
    if ($currentSectionSyLabel !== null) {
        foreach ($historicalSchoolYears as $sy) {
            if ($sy['label'] === $currentSectionSyLabel) {
                $currentSyId = (int) $sy['id'];
                break;
            }
        }
    }
    $selectedGradeSchoolYearId = $currentSyId ?? (int) ($historicalSchoolYears[0]['id'] ?? 0);
}

$sectionLearners = $section === null ? [] : teacher_section_learners((int) $user['id']);
$parentLinks = $section === null ? [] : teacher_section_parent_links((int) $user['id']);
$gradeRows = $selectedGradeSchoolYearId > 0 ? grade_teacher_rows_for_school_year((int) $user['id'], $selectedGradeSchoolYearId) : [];
$bmiRemarkRows = teacher_section_bmi_remarks_rows($sectionLearners);
$bmiMeasuredCount = array_sum(array_map(
    static fn (array $row): int => $row['label'] === 'Not measured' ? 0 : (int) $row['total'],
    $bmiRemarkRows
));
$selectedDashboardLearnerId = isset($_GET['learner_id']) ? (int) $_GET['learner_id'] : 0;
$selectedDashboardLearner = $module === 'learner_details'
    ? teacher_find_section_learner($sectionLearners, $selectedDashboardLearnerId)
    : null;
$selectedLearnerDetailSyId = isset($_GET['learner_sy_id']) ? (int) $_GET['learner_sy_id'] : 0;
$learnerSchoolYears = [];
$selectedDashboardGrades = $selectedDashboardLearner === null
    ? []
    : grade_teacher_learner_rows((int) $user['id'], $selectedDashboardLearnerId, $selectedLearnerDetailSyId > 0 ? $selectedLearnerDetailSyId : null);
$gradeHistoryGroups = [];
if ($selectedDashboardGrades !== []) {
    $gradeHistoryGroups = grade_group_history_by_level($selectedDashboardGrades);
}
if ($selectedDashboardLearner !== null) {
    $learnerSchoolYears = learner_school_years((int) $selectedDashboardLearner['id']);
}
$selectedDashboardHealth = $selectedDashboardLearner === null
    ? null
    : teacher_dashboard_learner_health((int) $user['id'], $selectedDashboardLearnerId);
$selectedDashboardParents = $selectedDashboardLearner === null
    ? []
    : array_values(array_filter($parentLinks, static fn (array $link): bool => (int) $link['learner_id'] === $selectedDashboardLearnerId));
$usesSeniorGradeLayout = $section !== null && grade_is_senior_high((string) $section['grade_level']);
$parentAccountOptions = in_array($module, ['create_parent_account', 'link_parent_account'], true) ? parent_account_options() : [];
$linkedLearnerCount = count(array_filter(
    $sectionLearners,
    static fn (array $learner): bool => (int) $learner['linked_parent_count'] > 0
));
$profileCompletedCount = count(array_filter(
    $sectionLearners,
    static fn (array $learner): bool => teacher_profile_is_complete($learner)
));
$gradeLearners = [];
$seenGradeLearnerIds = [];
foreach ($gradeRows as $gradeRow) {
    if (!in_array((int) $gradeRow['learner_id'], $seenGradeLearnerIds, true)) {
        $gradeLearners[] = [
            'id' => $gradeRow['learner_id'],
            'learner_name' => $gradeRow['learner_name'],
            'lrn' => $gradeRow['lrn'],
        ];
        $seenGradeLearnerIds[] = (int) $gradeRow['learner_id'];
    }
}
$gradeLearnerIds = $seenGradeLearnerIds;

$gradeLearnerCount = count($gradeLearnerIds);
$gradeRecordCount = count($gradeRows);
$sectionLearnerCount = count($sectionLearners);
$sexCounts = teacher_section_learner_sex_counts($sectionLearners);
$maleLearnerCount = $sexCounts['male'];
$femaleLearnerCount = $sexCounts['female'];
$unspecifiedSexCount = $sexCounts['unspecified'];
$maleLearnerShare = teacher_percentage($maleLearnerCount, $sectionLearnerCount);
$femaleLearnerShare = teacher_percentage($femaleLearnerCount, $sectionLearnerCount);
$unspecifiedSexShare = teacher_percentage($unspecifiedSexCount, $sectionLearnerCount);
$genderChartTotal = max(1, $sectionLearnerCount);
$maleChartEnd = round(($maleLearnerCount / $genderChartTotal) * 360, 2);
$femaleChartEnd = round($maleChartEnd + (($femaleLearnerCount / $genderChartTotal) * 360), 2);

$referenceYear = $section !== null && !empty($section['school_year_start_date'])
    ? (int) substr((string) $section['school_year_start_date'], 0, 4)
    : (int) date('Y');
$ageReferenceDate = learner_first_friday_of_june($referenceYear);

if ($module === 'dashboard') {
    announcements_bootstrap();
    $adminAnnouncements = announcement_list(['role' => 'admin', 'is_published' => 1]);
}
$ageReferenceLabel = teacher_format_date($ageReferenceDate, 'F j, Y');

$selectedGradeLearnerId = isset($_GET['grade_learner_id']) ? (int) $_GET['grade_learner_id'] : 0;
if ($selectedGradeLearnerId <= 0 && $gradeLearnerIds !== []) {
    $selectedGradeLearnerId = $gradeLearnerIds[0];
}

$selectedGradeLearner = null;
if ($selectedGradeLearnerId > 0) {
    foreach ($gradeLearners as $learner) {
        if ((int) $learner['id'] === $selectedGradeLearnerId) {
            $selectedGradeLearner = $learner;
            break;
        }
    }
}

$selectedGradeRows = [];
if ($selectedGradeLearner !== null) {
    $selectedGradeRows = array_values(array_filter($gradeRows, static fn (array $row): bool => (int) $row['learner_id'] === (int) $selectedGradeLearner['id']));
}

$selectedProfileLearnerId = isset($_GET['profile_learner_id']) ? (int) $_GET['profile_learner_id'] : 0;
if ($selectedProfileLearnerId <= 0 && $sectionLearners !== []) {
    $selectedProfileLearnerId = (int) $sectionLearners[0]['id'];
}
$selectedProfileLearner = teacher_find_section_learner($sectionLearners, $selectedProfileLearnerId);

$profileLearnerGrades = [];
$profileLearnerGradeHistory = [];
if ($module === 'learner_profiles' && $selectedProfileLearner !== null) {
    $profileLearnerGrades = grade_teacher_learner_rows((int) $user['id'], (int) $selectedProfileLearner['id']);
    if ($profileLearnerGrades !== []) {
        $profileLearnerGradeHistory = grade_group_history_by_level($profileLearnerGrades);
    }
}

if (!$profileFormFromPost && $selectedProfileLearner !== null) {
    $profileForm = learner_profile_form_defaults([
        'learner_id' => (string) $selectedProfileLearner['id'],
        'lrn' => (string) $selectedProfileLearner['lrn'],
        'learner_name' => (string) $selectedProfileLearner['learner_name'],
        'birthdate' => (string) ($selectedProfileLearner['birthdate'] ?? ''),
        'mother_tongue' => (string) ($selectedProfileLearner['mother_tongue'] ?? ''),
        'religion' => (string) ($selectedProfileLearner['religion'] ?? ''),
        'address_house_number' => (string) ($selectedProfileLearner['address_house_number'] ?? ''),
        'address_barangay' => (string) ($selectedProfileLearner['address_barangay'] ?? ''),
        'address_city_municipality' => (string) (($selectedProfileLearner['address_city_municipality'] ?? '') !== '' ? $selectedProfileLearner['address_city_municipality'] : learner_default_city_municipality()),
        'address_province' => (string) (($selectedProfileLearner['address_province'] ?? '') !== '' ? $selectedProfileLearner['address_province'] : learner_default_province()),
    ]);
}

$pageMeta = $allowedModules[$module];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php echo theme_stylesheet_markup(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape(APP_NAME); ?> Teacher Portal</title>
    <link rel="stylesheet" href="<?php echo escape(asset_url('assets/css/app.css')); ?>">
</head>
<body class="dashboard-body admin-dashboard">
    <button
        id="sidebar-toggle"
        class="sidebar-toggle-button"
        type="button"
        data-sidebar-label="teacher menu"
        aria-label="Open teacher menu"
        aria-controls="admin-sidebar"
        aria-expanded="false"
    >
        <span></span>
        <span></span>
        <span></span>
    </button>
    <div id="sidebar-backdrop" class="sidebar-backdrop" hidden></div>

    <main class="dashboard-shell admin-shell wide-admin-shell">
        <section class="admin-layout">
            <aside id="admin-sidebar" class="admin-sidebar teacher-nav-sidebar">
                <div class="sidebar-profile">
                    <p class="eyebrow">Teacher Profile</p>
                    <h1>Teacher Portal</h1>
                    <p class="sidebar-user"><?php echo escape($user['username']); ?></p>
                    <p class="sidebar-email"><?php echo escape($user['email']); ?></p>
                </div>

                <nav class="sidebar-nav" aria-label="Teacher Navigation">
                    <div class="menu-group">
                        <p class="menu-group-title">Overview</p>
                        <a href="<?php echo escape(teacher_module_url('dashboard')); ?>" class="submenu-link<?php echo $module === 'dashboard' ? ' active' : ''; ?>">Dashboard</a>
                    </div>

                    <div class="menu-group">
                        <p class="menu-group-title">Parent Account</p>
                        <a href="<?php echo escape(teacher_module_url('create_parent_account')); ?>" class="submenu-link<?php echo $module === 'create_parent_account' ? ' active' : ''; ?>">Create Parent Account</a>
                        <a href="<?php echo escape(teacher_module_url('link_parent_account')); ?>" class="submenu-link<?php echo $module === 'link_parent_account' ? ' active' : ''; ?>">Link Parent Account</a>
                    </div>

                    <div class="menu-group">
                        <p class="menu-group-title">Grades</p>
                        <a href="<?php echo escape(teacher_module_url('grades_import')); ?>" class="submenu-link<?php echo $module === 'grades_import' ? ' active' : ''; ?>">Import Grades</a>
                    </div>

                    <div class="menu-group">
                        <p class="menu-group-title">Learner Profile</p>
                        <a href="<?php echo escape(teacher_module_url('learner_profiles')); ?>" class="submenu-link<?php echo $module === 'learner_profiles' ? ' active' : ''; ?>">Basic Profile</a>
                    </div>
                    <div class="menu-group">
                        <p class="menu-group-title">Communication</p>
                        <a href="<?php echo escape(teacher_module_url('announcements')); ?>" class="submenu-link<?php echo $module === 'announcements' ? ' active' : ''; ?>">Announcements</a>
                    </div>
                    <div class="menu-group">
                        <p class="menu-group-title">Account</p>
                        <a href="<?php echo escape(teacher_module_url('settings')); ?>" class="submenu-link<?php echo $module === 'settings' ? ' active' : ''; ?>">Settings</a>
                    </div>
                </nav>

                <div class="sidebar-footer">
                    <a href="<?php echo escape(route_url('logout.php')); ?>" class="secondary-link full-width-link">Logout</a>
                </div>
            </aside>

            <section class="admin-main-panel teacher-main-panel">
                <header class="admin-page-header">
                    <div class="admin-page-title">
                        <img class="school-logo header-logo" src="<?php echo escape(school_logo_url()); ?>" alt="School logo">
                        <div class="header-copy">
                            <p class="eyebrow"><?php echo escape($pageMeta['eyebrow']); ?></p>
                            <h2><?php echo escape($pageMeta['title']); ?></h2>
                            <p><?php echo escape($pageMeta['description']); ?></p>
                        </div>
                    </div>

                    <?php if ($section !== null): ?>
                        <div class="teacher-header-chip">
                            <strong><?php echo escape($section['grade_level'] . ' - ' . $section['name']); ?></strong>
                            <span><?php echo escape($section['school_year_label']); ?></span>
                        </div>
                    <?php endif; ?>
                </header>

                <?php if ($teacherFlash !== null): ?>
                    <div class="alert <?php echo escape($teacherFlash['type']); ?>"><?php echo escape($teacherFlash['message']); ?></div>
                <?php endif; ?>

                <?php if ($section === null && $module !== 'settings'): ?>
                    <article class="teacher-panel-card">
                        <div class="panel-heading">
                            <h2>No Section Assignment Yet</h2>
                            <p>Your teacher tools will appear after an admin assigns your grade and section.</p>
                        </div>

                        <div class="alert neutral">Ask the admin to assign your teacher account to a section in the teacher management module.</div>
                    </article>
                <?php elseif ($module === 'dashboard'): ?>
                    <section class="teacher-summary-grid">
                        <article class="summary-card">
                            <span class="summary-code">Learners</span>
                            <strong><?php echo escape((string) $sectionLearnerCount); ?></strong>
                            <small>Assigned to your section</small>
                        </article>

                        <article class="summary-card">
                            <span class="summary-code">Parent Links</span>
                            <strong><?php echo escape((string) count($parentLinks)); ?></strong>
                            <small>Total connected accounts</small>
                        </article>

                        <article class="summary-card">
                            <span class="summary-code">Grade Rows</span>
                            <strong><?php echo escape((string) $gradeRecordCount); ?></strong>
                            <small>Imported subject records</small>
                        </article>

                        <article class="summary-card">
                            <span class="summary-code">Profiles Ready</span>
                            <strong><?php echo escape((string) $profileCompletedCount); ?></strong>
                            <small>With core basic profile data</small>
                        </article>
                    </section>

                    <!-- Assigned Section Card - Moved to its own section above charts -->
                    <section>
                        <article class="teacher-panel-card">
                            <div class="panel-heading">
                                <h2>Assigned Section</h2>
                                <p>Your access is limited to this advisory class.</p>
                            </div>

                            <dl class="detail-grid wide">
                                <div>
                                    <dt>Grade Level</dt>
                                    <dd><?php echo escape($section['grade_level']); ?></dd>
                                </div>
                                <div>
                                    <dt>Section</dt>
                                    <dd><?php echo escape($section['name']); ?></dd>
                                </div>
                                <div>
                                    <dt>School Year</dt>
                                    <dd><?php echo escape($section['school_year_label']); ?></dd>
                                </div>
                                <div>
                                    <dt>Age Basis</dt>
                                    <dd><?php echo escape($ageReferenceLabel); ?></dd>
                                </div>
                            </dl>
                        </article>
                    </section>

                    <!-- Charts Section - Now using teacher-chart-overview-grid for stretching -->
                    <section class="teacher-overview-grid">
                        <article class="teacher-panel-card">
                            <div class="panel-heading compact-heading">
                                <h2>BMI Remarks</h2>
                                <p>Health-measurement overview for your advisory section.</p>
                            </div>

                            <div class="teacher-dashboard-chart-grid">
                                <div class="teacher-bmi-donut" style="background: <?php echo escape(teacher_bmi_pie_gradient($bmiRemarkRows)); ?>;" aria-label="BMI remarks distribution">
                                    <div class="teacher-gender-donut-center">
                                        <strong><?php echo escape((string) $bmiMeasuredCount); ?></strong>
                                        <span>measured learners</span>
                                    </div>
                                </div>

                                <div class="teacher-chart-legend">
                                    <?php foreach ($bmiRemarkRows as $bmiRow): ?>
                                        <div class="teacher-chart-stat">
                                            <div class="teacher-chart-stat-copy">
                                                <span class="teacher-chart-swatch" style="background: <?php echo escape($bmiRow['color']); ?>;"></span>
                                                <strong><?php echo escape($bmiRow['label']); ?></strong>
                                            </div>
                                            <strong><?php echo escape((string) $bmiRow['total']); ?></strong>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </article>

                        <article class="teacher-panel-card">
                            <div class="panel-heading compact-heading">
                                <h2>Learner Gender Distribution</h2>
                                <p>Recorded male and female counts for your advisory section.</p>
                            </div>

                            <?php if ($sectionLearners === []): ?>
                                <div class="alert neutral">No learners are assigned to your section yet.</div>
                            <?php else: ?>
                                <div class="teacher-dashboard-chart-grid">
                                    <div
                                        class="teacher-gender-donut"
                                        style="background: conic-gradient(#2563eb 0deg <?php echo escape(number_format($maleChartEnd, 2, '.', '')); ?>deg, #ec4899 <?php echo escape(number_format($maleChartEnd, 2, '.', '')); ?>deg <?php echo escape(number_format($femaleChartEnd, 2, '.', '')); ?>deg, #94a3b8 <?php echo escape(number_format($femaleChartEnd, 2, '.', '')); ?>deg 360deg);"
                                        aria-label="Learner gender distribution"
                                    >
                                        <div class="teacher-gender-donut-center">
                                            <strong><?php echo escape((string) $sectionLearnerCount); ?></strong>
                                            <span>Total learners</span>
                                        </div>
                                    </div>

                                    <div class="teacher-chart-legend">
                                        <div class="teacher-chart-stat">
                                            <div class="teacher-chart-stat-copy">
                                                <span class="teacher-chart-swatch teacher-chart-swatch-male"></span>
                                                <div>
                                                    <strong>Male</strong>
                                                    <small><?php echo escape(number_format($maleLearnerShare, 1)); ?>% of section</small>
                                                </div>
                                            </div>
                                            <strong><?php echo escape((string) $maleLearnerCount); ?></strong>
                                        </div>

                                        <div class="teacher-chart-stat">
                                            <div class="teacher-chart-stat-copy">
                                                <span class="teacher-chart-swatch teacher-chart-swatch-female"></span>
                                                <div>
                                                    <strong>Female</strong>
                                                    <small><?php echo escape(number_format($femaleLearnerShare, 1)); ?>% of section</small>
                                                </div>
                                            </div>
                                            <strong><?php echo escape((string) $femaleLearnerCount); ?></strong>
                                        </div>

                                        <div class="teacher-chart-stat subdued">
                                            <div class="teacher-chart-stat-copy">
                                                <span class="teacher-chart-swatch teacher-chart-swatch-unspecified"></span>
                                                <div>
                                                    <strong>Not yet tagged</strong>
                                                    <small><?php echo escape(number_format($unspecifiedSexShare, 1)); ?>% of section</small>
                                                </div>
                                            </div>
                                            <strong><?php echo escape((string) $unspecifiedSexCount); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </article>
                    </section>

                    <!-- Section Learners Card - Now in its own section -->
                    <section>
        <article class="teacher-panel-card">
            <div class="panel-heading compact-heading">
                <h2>Section Learners</h2>
                <p>Click a learner name to open the complete learner information.</p>
            </div>

            <div class="table-shell">
                <table class="records-table learner-table">
                    <thead>
                        <tr>
                            <th>Learner No.</th>
                            <th>LRN</th>
                            <th>Name</th>
                            <th>Grade</th>
                            <th>Linked Parents</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($sectionLearners === []): ?>
                            <tr>
                                <td colspan="5" class="empty-row">No learners are assigned to your section yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sectionLearners as $learner): ?>
                                <tr>
                                    <td><?php echo escape($learner['learner_number']); ?></td>
                                    <td><?php echo escape($learner['lrn']); ?></td>
                                    <td>
                                        <a class="table-inline-link" href="<?php echo escape(teacher_module_url('learner_details', ['learner_id' => (string) $learner['id']])); ?>">
                                            <?php echo escape($learner['learner_name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo escape($learner['grade_level']); ?></td>
                                    <td><?php echo escape((string) $learner['linked_parent_count']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </section>
                <?php elseif ($module === 'learner_details'): ?>
                    <?php if ($selectedDashboardLearner === null): ?>
                        <article class="teacher-panel-card">
                            <div class="alert error">The requested learner is not part of your assigned section.</div>
                            <div class="template-actions">
                                <a href="<?php echo escape(teacher_module_url('dashboard')); ?>" class="secondary-link">Back to Dashboard</a>
                            </div>
                        </article>
                    <?php else: ?>
                        <?php
                        $detailBmi = health_calculate_bmi($selectedDashboardHealth['height_cm'] ?? null, $selectedDashboardHealth['weight_kg'] ?? null);
                        $detailBmiRemark = $detailBmi === null ? 'Not measured' : health_bmi_remarks($detailBmi);
                        $detailAge = learner_age_on_reference_date((string) ($selectedDashboardLearner['birthdate'] ?? ''), $ageReferenceDate);
                        ?>
                        <article class="teacher-panel-card">
                            <div class="panel-heading compact-heading">
                                <h2><?php echo escape($selectedDashboardLearner['learner_name']); ?></h2>
                                <p>Complete information for this learner in your advisory section.</p>
                            </div>
                            <div class="template-actions">
                                <a href="<?php echo escape(teacher_module_url('dashboard')); ?>" class="secondary-link">Back to Dashboard</a>
                                <a href="<?php echo escape(teacher_module_url('learner_profiles', ['profile_learner_id' => (string) $selectedDashboardLearner['id']])); ?>" class="primary-button">Edit Basic Profile</a>
                            </div>

                            <div class="teacher-profile-identity teacher-detail-identity">
                                <div class="teacher-profile-photo-frame">
                                    <img class="teacher-profile-photo" src="<?php echo escape(learner_photo_url($selectedDashboardLearner['lrn'])); ?>" alt="<?php echo escape($selectedDashboardLearner['learner_name']); ?> photo">
                                </div>
                                <div class="teacher-profile-identity-copy">
                                    <p class="meta-label dark">Learner</p>
                                    <div class="teacher-readonly-field"><?php echo escape($selectedDashboardLearner['learner_name']); ?></div>
                                    <div class="teacher-profile-meta">
                                        <span>LRN: <?php echo escape($selectedDashboardLearner['lrn']); ?></span>
                                        <span><?php echo escape($selectedDashboardLearner['grade_level'] . ' - ' . $selectedDashboardLearner['section_name']); ?></span>
                                        <span><?php echo escape(ucfirst((string) ($selectedDashboardLearner['current_status'] ?? 'active'))); ?></span>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <section class="teacher-overview-grid">
                            <article class="teacher-panel-card">
                                <div class="panel-heading compact-heading"><h2>Basic Profile</h2></div>
                                <dl class="detail-grid wide">
                                    <div><dt>Learner Number</dt><dd><?php echo escape($selectedDashboardLearner['learner_number']); ?></dd></div>
                                    <div><dt>Sex</dt><dd><?php echo escape(ucfirst((string) ($selectedDashboardLearner['sex'] ?? 'unspecified'))); ?></dd></div>
                                    <div><dt>Birthdate</dt><dd><?php echo escape(teacher_format_date($selectedDashboardLearner['birthdate'] ?? null)); ?></dd></div>
                                    <div><dt>Age</dt><dd><?php echo escape($detailAge === null ? '-' : (string) $detailAge); ?></dd></div>
                                    <div><dt>Mother Tongue</dt><dd><?php echo escape($selectedDashboardLearner['mother_tongue'] ?: '-'); ?></dd></div>
                                    <div><dt>Religion</dt><dd><?php echo escape($selectedDashboardLearner['religion'] ?: '-'); ?></dd></div>
                                    <div class="teacher-detail-wide"><dt>Address</dt><dd><?php echo escape(teacher_profile_address($selectedDashboardLearner)); ?></dd></div>
                                </dl>
                            </article>

                            <article class="teacher-panel-card">
                                <div class="panel-heading compact-heading"><h2>Health Information</h2></div>
                                <dl class="detail-grid">
                                    <div><dt>Height</dt><dd><?php echo escape(($selectedDashboardHealth['height_cm'] ?? null) !== null ? $selectedDashboardHealth['height_cm'] . ' cm' : '-'); ?></dd></div>
                                    <div><dt>Weight</dt><dd><?php echo escape(($selectedDashboardHealth['weight_kg'] ?? null) !== null ? $selectedDashboardHealth['weight_kg'] . ' kg' : '-'); ?></dd></div>
                                    <div><dt>BMI</dt><dd><?php echo escape($detailBmi === null ? '-' : (string) $detailBmi); ?></dd></div>
                                    <div><dt>BMI Remark</dt><dd><?php echo escape($detailBmiRemark); ?></dd></div>
                                    <div><dt>Recorded On</dt><dd><?php echo escape(teacher_format_date($selectedDashboardHealth['recorded_on'] ?? null)); ?></dd></div>
                                </dl>
                            </article>
                        </section>

                        <section class="teacher-overview-grid">
                            <article class="teacher-panel-card">
                                <div class="panel-heading compact-heading"><h2>Linked Parents / Guardians</h2></div>
                                <?php if ($selectedDashboardParents === []): ?>
                                    <div class="alert neutral">No parent or guardian account is linked yet.</div>
                                <?php else: ?>
                                    <div class="teacher-link-stack">
                                        <?php foreach ($selectedDashboardParents as $parentLink): ?>
                                            <div class="teacher-metric-row"><span><?php echo escape($parentLink['parent_name'] . ' · ' . $parentLink['relationship']); ?></span><strong><?php echo escape($parentLink['parent_email']); ?></strong></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </article>
                        </section>

                        <article class="teacher-panel-card">
                            <div class="panel-heading compact-heading">
                                <h2>Grades</h2>
                            </div>

                            <form method="get" class="report-filter-grid" style="margin-bottom: 1.5rem;">
                                <input type="hidden" name="module" value="learner_details">
                                <input type="hidden" name="learner_id" value="<?php echo escape((string) $selectedDashboardLearner['id']); ?>">

                                <div class="report-filter-field report-filter-field-wide">
                                    <label for="learner_sy_id">School Year</label>
                                    <select id="learner_sy_id" name="learner_sy_id">
                                        <option value="0">All School Years</option>
                                        <?php if ($learnerSchoolYears === []): ?>
                                            <option value="" disabled>No school years with grades</option>
                                        <?php else: ?>
                                            <?php foreach ($learnerSchoolYears as $sy): ?>
                                                <option value="<?php echo escape((string) $sy['id']); ?>"<?php echo $selectedLearnerDetailSyId === (int) $sy['id'] ? ' selected' : ''; ?>>
                                                    <?php echo escape($sy['label']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div class="report-actions">
                                    <button type="submit" class="primary-button">View Grades</button>
                                </div>
                            </form>

                            <?php if ($selectedDashboardGrades === []): ?>
                                <div class="alert neutral">No grade records are available for the selected school year.</div>
                            <?php else: ?>
                                <div class="table-shell">
                                    <table class="records-table report-table">
                                        <thead>
                                            <tr>
                                                <th>Subject</th>
                                                <th>Q1</th>
                                                <th>Q2</th>
                                                <?php if ($usesSeniorGradeLayout): ?>
                                                    <th>1st Sem Avg</th>
                                                <?php endif; ?>
                                                <th>Q3</th>
                                                <th>Q4</th>
                                                <?php if ($usesSeniorGradeLayout): ?>
                                                    <th>2nd Sem Avg</th>
                                                <?php else: ?>
                                                    <th>Average</th>
                                                <?php endif; ?>
                                                <th>Final Avg</th>
                                                <th>Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($selectedDashboardGrades as $gradeRow): ?>
                                                <tr>
                                                    <td><?php echo escape($gradeRow['subject_name']); ?></td>
                                                    <td><?php echo escape($gradeRow['quarter_1_grade'] ?? '-'); ?></td>
                                                    <td><?php echo escape($gradeRow['quarter_2_grade'] ?? '-'); ?></td>
                                                    <?php if ($usesSeniorGradeLayout): ?>
                                                        <td><?php echo escape($gradeRow['first_semester_average'] ?? '-'); ?></td>
                                                    <?php endif; ?>
                                                    <td><?php echo escape($gradeRow['quarter_3_grade'] ?? '-'); ?></td>
                                                    <td><?php echo escape($gradeRow['quarter_4_grade'] ?? '-'); ?></td>
                                                    <?php if ($usesSeniorGradeLayout): ?>
                                                        <td><?php echo escape($gradeRow['second_semester_average'] ?? '-'); ?></td>
                                                    <?php else: ?>
                                                        <?php $quarterAverage = grade_quarter_average($gradeRow); ?>
                                                        <td><?php echo escape($quarterAverage !== null ? (string) $quarterAverage : '-'); ?></td>
                                                    <?php endif; ?>
                                                    <td><?php echo escape($gradeRow['final_average'] ?? '-'); ?></td>
                                                    <td><?php echo escape($gradeRow['remarks'] ?? '-'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endif; ?>
                <?php elseif ($module === 'create_parent_account'): ?>
                    <article class="teacher-panel-card">
                        <div class="panel-heading">
                            <h2>Create Parent Account</h2>
                            <p>Create a new parent account and immediately link it to one learner in your section.</p>
                        </div>

                        <form method="post" class="teacher-form-grid">
                            <input type="hidden" name="csrf_token" value="<?php echo escape(csrf_token()); ?>">
                            <input type="hidden" name="form_action" value="create_parent_and_link">

                            <div>
                                <label for="create_learner_id">Learner</label>
                                <select id="create_learner_id" name="learner_id" required>
                                    <option value="">Select learner</option>
                                    <?php foreach ($sectionLearners as $learner): ?>
                                        <option value="<?php echo escape((string) $learner['id']); ?>"<?php echo $newParentForm['learner_id'] === (string) $learner['id'] ? ' selected' : ''; ?>>
                                            <?php echo escape($learner['learner_name'] . ' [' . $learner['lrn'] . ']'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label for="create_relationship">Relationship</label>
                                <input id="create_relationship" name="relationship" type="text" value="<?php echo escape($newParentForm['relationship']); ?>" placeholder="Mother, Father, Guardian" required>
                            </div>

                            <div>
                                <label for="create_username">Username</label>
                                <input id="create_username" name="username" type="text" value="<?php echo escape($newParentForm['username']); ?>" required>
                            </div>

                            <div>
                                <label for="create_email">Email</label>
                                <input id="create_email" name="email" type="email" value="<?php echo escape($newParentForm['email']); ?>" required>
                            </div>

                            <div>
                                <label for="create_password">Password</label>
                                <input id="create_password" name="password" type="password" value="<?php echo escape($newParentForm['password']); ?>" required>
                            </div>

                            <div>
                                <label for="create_contact_number">Contact Number</label>
                                <input id="create_contact_number" name="contact_number" type="text" value="<?php echo escape($newParentForm['contact_number']); ?>">
                            </div>

                            <div>
                                <label for="create_first_name">First Name</label>
                                <input id="create_first_name" name="first_name" type="text" value="<?php echo escape($newParentForm['first_name']); ?>" required>
                            </div>

                            <div>
                                <label for="create_middle_name">Middle Name</label>
                                <input id="create_middle_name" name="middle_name" type="text" value="<?php echo escape($newParentForm['middle_name']); ?>">
                            </div>

                            <div>
                                <label for="create_last_name">Last Name</label>
                                <input id="create_last_name" name="last_name" type="text" value="<?php echo escape($newParentForm['last_name']); ?>" required>
                            </div>

                            <div class="teacher-form-grid-full">
                                <label for="create_address">Address</label>
                                <input id="create_address" name="address" type="text" value="<?php echo escape($newParentForm['address']); ?>">
                            </div>

                            <div class="teacher-inline-check teacher-form-grid-full">
                                <label>
                                    <input type="checkbox" name="is_primary_contact" value="1"<?php echo $newParentForm['is_primary_contact'] === '1' ? ' checked' : ''; ?>>
                                    Mark as primary contact for this learner
                                </label>
                            </div>

                            <div class="learner-form-actions teacher-form-grid-full">
                                <button type="submit" class="primary-button">Create and Link Parent</button>
                            </div>
                        </form>
                    </article>

                    <article class="teacher-panel-card">
                        <div class="panel-heading">
                            <h2>Import Parent Accounts</h2>
                            <p>Upload parent accounts in bulk and link each row by learner LRN.</p>
                        </div>

                        <div class="template-actions">
                            <a href="<?php echo escape(route_url('download_parent_template.php?format=csv')); ?>" class="secondary-link">Download CSV Template</a>
                            <a href="<?php echo escape(route_url('download_parent_template.php?format=xls')); ?>" class="secondary-link">Download XLS Template</a>
                        </div>

                        <form method="post" enctype="multipart/form-data" class="import-form">
                            <input type="hidden" name="csrf_token" value="<?php echo escape(csrf_token()); ?>">
                            <input type="hidden" name="form_action" value="import_parent_accounts">

                            <div>
                                <label for="parent_import_file">Parent Account File</label>
                                <input id="parent_import_file" name="parent_import_file" type="file" accept=".csv,.xls" required>
                            </div>

                            <p class="import-note">Columns include learner LRN, parent login details, contact information, relationship, and primary contact flag.</p>
                            <button type="submit" class="primary-button">Import Parent Accounts</button>
                        </form>
                    </article>
                <?php elseif ($module === 'link_parent_account'): ?>
                    <article class="teacher-panel-card">
                        <div class="panel-heading">
                            <h2>Link Existing Parent Account</h2>
                            <p>Use an existing parent username or email and attach it to a learner in your section.</p>
                        </div>

                        <form method="post" class="report-filter-grid">
                            <input type="hidden" name="csrf_token" value="<?php echo escape(csrf_token()); ?>">
                            <input type="hidden" name="form_action" value="link_existing_parent">

                            <div class="report-filter-field report-filter-field-wide">
                                <label for="existing_identity">Parent Username or Email</label>
                                <select id="existing_identity" name="identity" required>
                                    <option value="">Select from existing parent accounts</option>
                                    <?php if ($parentAccountOptions === []): ?>
                                        <option value="" disabled>No parent accounts exist yet</option>
                                    <?php else: ?>
                                        <?php foreach ($parentAccountOptions as $parent): ?>
                                            <?php
                                            $parentName = trim(($parent['last_name'] ?? '') . ', ' . ($parent['first_name'] ?? ''));
                                            $parentLabel = $parentName !== ',' ? $parentName : ($parent['username'] ?? '');
                                            ?>
                                            <option value="<?php echo escape($parent['username']); ?>"<?php echo $existingParentForm['identity'] === $parent['username'] ? ' selected' : ''; ?>>
                                                <?php echo escape($parentLabel . ' (' . $parent['username'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="report-filter-field">
                                <label for="existing_learner_id">Learner</label>
                                <select id="existing_learner_id" name="learner_id" required>
                                    <option value="">Select learner</option>
                                    <?php foreach ($sectionLearners as $learner): ?>
                                        <option value="<?php echo escape((string) $learner['id']); ?>"<?php echo $existingParentForm['learner_id'] === (string) $learner['id'] ? ' selected' : ''; ?>>
                                            <?php echo escape($learner['learner_name'] . ' [' . $learner['lrn'] . ']'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="report-filter-field">
                                <label for="existing_relationship">Relationship</label>
                                <input id="existing_relationship" name="relationship" type="text" value="<?php echo escape($existingParentForm['relationship']); ?>" placeholder="Mother, Father, Guardian" required>
                            </div>

                            <div class="teacher-inline-check report-actions">
                                <label>
                                    <input type="checkbox" name="is_primary_contact" value="1"<?php echo $existingParentForm['is_primary_contact'] === '1' ? ' checked' : ''; ?>>
                                    Mark as primary contact
                                </label>
                                <button type="submit" class="primary-button">Link Existing Parent</button>
                            </div>
                        </form>
                    </article>

                    <article class="teacher-panel-card">
                        <div class="panel-heading compact-heading">
                            <h2>Parent Links in Your Section</h2>
                            <p>Review the parent accounts already connected to your learners.</p>
                        </div>

                        <div class="table-shell">
                            <table class="records-table">
                                <thead>
                                <tr>
                                    <th>Learner</th>
                                    <th>LRN</th>
                                    <th>Parent</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Relationship</th>
                                    <th>Primary</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if ($parentLinks === []): ?>
                                    <tr>
                                        <td colspan="7" class="empty-row">No parent accounts are linked to this section yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($parentLinks as $link): ?>
                                        <tr>
                                            <td><?php echo escape($link['learner_name']); ?></td>
                                            <td><?php echo escape($link['lrn']); ?></td>
                                            <td><?php echo escape($link['parent_name']); ?></td>
                                            <td><?php echo escape($link['parent_username']); ?></td>
                                            <td><?php echo escape($link['parent_email']); ?></td>
                                            <td><?php echo escape($link['relationship']); ?></td>
                                            <td><?php echo (int) $link['is_primary_contact'] === 1 ? 'Yes' : 'No'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </article>
                <?php elseif ($module === 'grades_import'): ?>
                    <article class="teacher-panel-card">
                        <div class="panel-heading">
                            <h2>Import Grades</h2>
                            <p>Upload subject grades for learners in your assigned section.</p>
                        </div>

                        <div class="template-actions">
                            <a href="<?php echo escape(route_url('download_grade_template.php?format=csv')); ?>" class="secondary-link">Download CSV Template</a>
                            <a href="<?php echo escape(route_url('download_grade_template.php?format=xls')); ?>" class="secondary-link">Download XLS Template</a>
                        </div>

                        <form method="post" enctype="multipart/form-data" class="import-form">
                            <input type="hidden" name="csrf_token" value="<?php echo escape(csrf_token()); ?>">
                            <input type="hidden" name="form_action" value="import_grades">

                            <div>
                                <label for="grade_import_file">Grade File</label>
                                <input id="grade_import_file" name="grade_import_file" type="file" accept=".csv,.xls" required>
                            </div>

                            <p class="import-note">Each row includes LRN, school year, and grade level. Use quarters 1-4 for regular subjects. For senior high semester subjects, leave unused quarters blank or use #, then provide the semester average.</p>
                            <button type="submit" class="primary-button">Import Grades</button>
                        </form>
                    </article>

                    <article class="teacher-panel-card">
                        <div class="panel-heading compact-heading">
                            <h2>Imported Grades</h2>
                            <p>Review the grade records currently stored for your assigned learners.</p>
                        </div>

                        <form method="get" class="report-filter-grid" style="margin-bottom: 1.5rem;">
                            <input type="hidden" name="module" value="grades_import">

                            <div class="report-filter-field report-filter-field-wide">
                                <label for="grades_sy_id">School Year</label>
                                <select id="grades_sy_id" name="grades_sy_id">
                                    <?php if ($historicalSchoolYears === []): ?>
                                        <option value="">No historical data found</option>
                                    <?php else: ?>
                                        <?php foreach ($historicalSchoolYears as $sy): ?>
                                            <option value="<?php echo escape((string) $sy['id']); ?>"<?php echo $selectedGradeSchoolYearId === (int) $sy['id'] ? ' selected' : ''; ?>>
                                                <?php echo escape($sy['label']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="report-actions">
                                <button type="submit" class="primary-button">View Grades</button>
                            </div>
                        </form>

                        <div class="table-shell">
                            <table class="records-table report-table">
                                <thead>
                                    <tr>
                                        <th>LRN</th>
                                        <th>Learner</th>
                                        <th>School Year</th>
                                        <th>Grade</th>
                                        <th>Subject</th>
                                        <th>Q1</th>
                                        <th>Q2</th>
                                        <?php if ($usesSeniorGradeLayout): ?>
                                            <th>1st Sem Avg</th>
                                        <?php endif; ?>
                                        <th>Q3</th>
                                        <th>Q4</th>
                                        <?php if ($usesSeniorGradeLayout): ?>
                                            <th>2nd Sem Avg</th>
                                        <?php else: ?>
                                            <th>Average</th>
                                        <?php endif; ?>
                                        <th>Final Avg</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($gradeRows === []): ?>
                                        <tr>
                                            <td colspan="<?php echo $usesSeniorGradeLayout ? '12' : '11'; ?>" class="empty-row">No grade records have been imported for this section yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($gradeRows as $gradeRow): ?>
                                            <tr>
                                                <td><?php echo escape($gradeRow['lrn']); ?></td>
                                                <td><?php echo escape($gradeRow['learner_name']); ?></td>
                                                <td><?php echo escape($gradeRow['school_year_label']); ?></td>
                                                <td><?php echo escape($gradeRow['grade_level']); ?></td>
                                                <td><?php echo escape($gradeRow['subject_name']); ?></td>
                                                <td><?php echo escape($gradeRow['quarter_1_grade'] ?? '-'); ?></td>
                                                <td><?php echo escape($gradeRow['quarter_2_grade'] ?? '-'); ?></td>
                                                <?php if ($usesSeniorGradeLayout): ?>
                                                    <td><?php echo escape($gradeRow['first_semester_average'] ?? '-'); ?></td>
                                                <?php endif; ?>
                                                <td><?php echo escape($gradeRow['quarter_3_grade'] ?? '-'); ?></td>
                                                <td><?php echo escape($gradeRow['quarter_4_grade'] ?? '-'); ?></td>
                                                <?php if ($usesSeniorGradeLayout): ?>
                                                    <td><?php echo escape($gradeRow['second_semester_average'] ?? '-'); ?></td>
                                                <?php else: ?>
                                                    <?php $quarterAverage = grade_quarter_average($gradeRow); ?>
                                                    <td><?php echo escape($quarterAverage !== null ? (string) $quarterAverage : '-'); ?></td>
                                                <?php endif; ?>
                                                <td><?php echo escape($gradeRow['final_average'] ?? '-'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </article>

                    <article class="teacher-panel-card">
                        <div class="panel-heading compact-heading">
                            <h2>Select Learner</h2>
                            <p>Open one learner to view the detailed imported grades.</p>
                        </div>

                        <form method="get" class="report-filter-grid">
                            <input type="hidden" name="module" value="grades_import">

                            <div class="report-filter-field report-filter-field-wide">
                                <label for="grade_learner_id">Learner</label>
                                <select id="grade_learner_id" name="grade_learner_id">
                                    <?php foreach ($gradeLearners as $learner): ?>
                                        <option value="<?php echo escape((string) $learner['id']); ?>"<?php echo $selectedGradeLearnerId === (int) $learner['id'] ? ' selected' : ''; ?>>
                                            <?php echo escape($learner['learner_name'] . ' [' . $learner['lrn'] . ']'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="report-actions">
                                <button type="submit" class="primary-button">View Grades</button>
                            </div>
                        </form>
                    </article>

                    <article class="teacher-panel-card">
                        <div class="panel-heading compact-heading">
                            <h2>Learner Grade Detail</h2>
                            <p>
                                <?php if ($selectedGradeLearner !== null): ?>
                                    <?php echo escape($selectedGradeLearner['learner_name'] . ' [' . $selectedGradeLearner['lrn'] . ']'); ?>
                                <?php else: ?>
                                    Select a learner to display grade details.
                                <?php endif; ?>
                            </p>
                        </div>

                        <?php if ($selectedGradeLearner === null): ?>
                            <div class="alert neutral">No learner selected.</div>
                        <?php elseif ($selectedGradeRows === []): ?>
                            <div class="alert neutral">No grade records are available for this learner yet.</div>
                        <?php else: ?>
                            <div class="monthly-report-info">
                                <p><strong>School Year:</strong> <?php echo escape($selectedGradeRows[0]['school_year_label']); ?></p>
                                <p><strong>Grade:</strong> <?php echo escape($selectedGradeRows[0]['grade_level']); ?></p>
                                <p><strong>Section:</strong> <?php echo escape($selectedGradeRows[0]['section_name']); ?></p>
                                <p><strong>Grand Average:</strong> <?php $selectedGrandAverage = grade_average(array_column($selectedGradeRows, 'final_average')); echo escape($selectedGrandAverage !== null ? (string) $selectedGrandAverage : '-'); ?></p>
                            </div>

                            <div class="table-shell">
                                <table class="records-table report-table">
                                    <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Q1</th>
                                        <th>Q2</th>
                                        <?php if ($usesSeniorGradeLayout): ?>
                                            <th>1st Sem Avg</th>
                                        <?php endif; ?>
                                        <th>Q3</th>
                                        <th>Q4</th>
                                        <?php if ($usesSeniorGradeLayout): ?>
                                            <th>2nd Sem Avg</th>
                                        <?php else: ?>
                                            <th>Average</th>
                                        <?php endif; ?>
                                        <th>Final Avg</th>
                                        <th>Remarks</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($selectedGradeRows as $gradeRow): ?>
                                        <tr>
                                            <td><?php echo escape($gradeRow['subject_name']); ?></td>
                                            <td><?php echo escape($gradeRow['quarter_1_grade'] ?? '-'); ?></td>
                                            <td><?php echo escape($gradeRow['quarter_2_grade'] ?? '-'); ?></td>
                                            <?php if ($usesSeniorGradeLayout): ?>
                                                <td><?php echo escape($gradeRow['first_semester_average'] ?? '-'); ?></td>
                                            <?php endif; ?>
                                            <td><?php echo escape($gradeRow['quarter_3_grade'] ?? '-'); ?></td>
                                            <td><?php echo escape($gradeRow['quarter_4_grade'] ?? '-'); ?></td>
                                            <?php if ($usesSeniorGradeLayout): ?>
                                                <td><?php echo escape($gradeRow['second_semester_average'] ?? '-'); ?></td>
                                            <?php else: ?>
                                                <?php $quarterAverage = grade_quarter_average($gradeRow); ?>
                                                <td><?php echo escape($quarterAverage !== null ? (string) $quarterAverage : '-'); ?></td>
                                            <?php endif; ?>
                                            <td><?php echo escape($gradeRow['final_average'] ?? '-'); ?></td>
                                            <td><?php echo escape($gradeRow['remarks'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php elseif ($module === 'learner_profiles'): ?>
                    <article class="teacher-panel-card">
                        <div class="panel-heading compact-heading">
                            <h2>Import Learner Basic Profiles</h2>
                            <p>Upload birthdate, mother tongue, religion, and address fields by learner LRN.</p>
                        </div>

                        <div class="template-actions">
                            <a href="<?php echo escape(route_url('download_learner_profile_template.php?format=csv')); ?>" class="secondary-link">Download CSV Template</a>
                            <a href="<?php echo escape(route_url('download_learner_profile_template.php?format=xls')); ?>" class="secondary-link">Download XLS Template</a>
                        </div>

                        <form method="post" enctype="multipart/form-data" class="import-form">
                            <input type="hidden" name="csrf_token" value="<?php echo escape(csrf_token()); ?>">
                            <input type="hidden" name="form_action" value="import_learner_profiles">

                            <div>
                                <label for="learner_profile_file">Learner Profile File</label>
                                <input id="learner_profile_file" name="learner_profile_file" type="file" accept=".csv,.xls" required>
                            </div>

                            <p class="import-note">Age is computed automatically using the first Friday of June, which is <?php echo escape($ageReferenceLabel); ?> for this school year.</p>
                            <button type="submit" class="primary-button">Import Learner Profiles</button>
                        </form>
                    </article>

                    <article class="teacher-panel-card">
                        <div class="panel-heading compact-heading">
                            <h2>Select Learner</h2>
                            <p>Choose a learner to review or update the basic profile.</p>
                        </div>

                        <form method="get" class="report-filter-grid">
                            <input type="hidden" name="module" value="learner_profiles">

                            <div class="report-filter-field report-filter-field-wide">
                                <label for="profile_learner_id">Learner</label>
                                <select id="profile_learner_id" name="profile_learner_id">
                                    <?php foreach ($sectionLearners as $learner): ?>
                                        <option value="<?php echo escape((string) $learner['id']); ?>"<?php echo $selectedProfileLearner !== null && (int) $selectedProfileLearner['id'] === (int) $learner['id'] ? ' selected' : ''; ?>>
                                            <?php echo escape($learner['learner_name'] . ' [' . $learner['lrn'] . ']'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="report-actions">
                                <button type="submit" class="primary-button">Open Profile</button>
                            </div>
                        </form>
                    </article>

                    <article class="teacher-panel-card">
                        <div class="panel-heading">
                            <h2>Update Learner Basic Profile</h2>
                            <p>Age is shown automatically based on the first Friday of June for the active school year.</p>
                        </div>

                        <?php if ($selectedProfileLearner === null): ?>
                            <div class="alert neutral">No learner selected.</div>
                        <?php else: ?>
                            <?php $profileAge = learner_age_on_reference_date($profileForm['birthdate'], $ageReferenceDate); ?>
                            <form method="post" class="teacher-form-grid">
                                <input type="hidden" name="csrf_token" value="<?php echo escape(csrf_token()); ?>">
                                <input type="hidden" name="form_action" value="save_learner_profile">
                                <input type="hidden" name="learner_id" value="<?php echo escape($profileForm['learner_id']); ?>">
                                <input type="hidden" name="lrn" value="<?php echo escape($profileForm['lrn']); ?>">
                                <input type="hidden" name="learner_name" value="<?php echo escape($profileForm['learner_name']); ?>">

                                <div class="teacher-form-grid-full teacher-profile-identity">
                                    <div class="teacher-profile-photo-frame">
                                        <img
                                            class="teacher-profile-photo"
                                            src="<?php echo escape(learner_photo_url($profileForm['lrn'])); ?>"
                                            alt="<?php echo escape($profileForm['learner_name']); ?> photo"
                                        >
                                    </div>

                                    <div class="teacher-profile-identity-copy">
                                        <p class="meta-label dark">Learner</p>
                                        <div class="teacher-readonly-field"><?php echo escape($profileForm['learner_name'] . ' [' . $profileForm['lrn'] . ']'); ?></div>
                                        <div class="teacher-profile-meta">
                                            <span><?php echo escape((string) ($selectedProfileLearner['grade_level'] ?? '-')); ?></span>
                                            <span><?php echo escape((string) ($selectedProfileLearner['section_name'] ?? '-')); ?></span>
                                            <span><?php echo escape(ucfirst((string) ($selectedProfileLearner['sex'] ?? 'unspecified'))); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label for="profile_birthdate">Birthdate</label>
                                    <input
                                        id="profile_birthdate"
                                        name="birthdate"
                                        type="date"
                                        value="<?php echo escape($profileForm['birthdate']); ?>"
                                        data-age-target="profile_age_display"
                                        data-age-reference-date="<?php echo escape($ageReferenceDate); ?>"
                                    >
                                </div>

                                <div>
                                    <label>Age as of <?php echo escape($ageReferenceLabel); ?></label>
                                    <div id="profile_age_display" class="teacher-readonly-field"><?php echo escape($profileAge !== null ? (string) $profileAge : '-'); ?></div>
                                </div>

                                <div>
                                    <label for="profile_mother_tongue">Mother Tongue</label>
                                    <input id="profile_mother_tongue" name="mother_tongue" type="text" value="<?php echo escape($profileForm['mother_tongue']); ?>">
                                </div>

                                <div>
                                    <label for="profile_religion">Religion</label>
                                    <select id="profile_religion" name="religion">
                                        <option value="">Select religion</option>
                                        <?php foreach (learner_religion_options_with_selected($profileForm['religion']) as $option): ?>
                                            <option value="<?php echo escape($option); ?>"<?php echo $profileForm['religion'] === $option ? ' selected' : ''; ?>><?php echo escape($option); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label for="profile_address_house_number">House Number</label>
                                    <input id="profile_address_house_number" name="address_house_number" type="text" value="<?php echo escape($profileForm['address_house_number']); ?>">
                                </div>

                                <div>
                                    <label for="profile_address_barangay">Barangay</label>
                                    <input id="profile_address_barangay" name="address_barangay" type="text" value="<?php echo escape($profileForm['address_barangay']); ?>">
                                </div>

                                <div>
                                    <label for="profile_address_city_municipality">City / Municipality</label>
                                    <input id="profile_address_city_municipality" name="address_city_municipality" type="text" value="<?php echo escape($profileForm['address_city_municipality']); ?>">
                                </div>

                                <div>
                                    <label for="profile_address_province">Province</label>
                                    <input id="profile_address_province" name="address_province" type="text" value="<?php echo escape($profileForm['address_province']); ?>">
                                </div>

                                <div class="learner-form-actions teacher-form-grid-full">
                                    <button type="submit" class="primary-button">Save Basic Profile</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </article>

                    <?php if ($selectedProfileLearner !== null): ?>
                        <article class="teacher-panel-card">
                            <div class="panel-heading compact-heading">
                                <h2>Grade History</h2>
                                <p>Complete grade history for <?php echo escape($selectedProfileLearner['learner_name']); ?>.</p>
                            </div>

                            <?php if ($profileLearnerGradeHistory === []): ?>
                                <div class="alert neutral">No grade records are available for this learner yet.</div>
                            <?php else: ?>
                                <div class="grade-history-stack">
                                    <?php foreach ($profileLearnerGradeHistory as $gradeGroup): ?>
                                        <?php $isSeniorHigh = grade_is_senior_high((string) $gradeGroup['grade_level']); ?>
                                        <?php $usesSeniorGradeLayout = $isSeniorHigh; ?>
                                        <section class="grade-history-section">
                                            <div class="monthly-report-info large-report-info">
                                                <p><strong>School Year:</strong> <?php echo escape($gradeGroup['school_year_label']); ?></p>
                                                <p><strong>Grade:</strong> <?php echo escape($gradeGroup['grade_level']); ?></p>
                                                <p><strong>Section:</strong> <?php echo escape($gradeGroup['section_name']); ?></p>
                                                <p><strong>Grand Average:</strong> <?php echo escape($gradeGroup['grand_average'] !== null ? (string) $gradeGroup['grand_average'] : '-'); ?></p>
                                            </div>

                                            <div class="table-shell">
                                                <?php if ($usesSeniorGradeLayout): ?>
                                                    <table class="records-table report-table">
                                                        <thead>
                                                        <tr>
                                                            <th colspan="4">First Semester</th>
                                                        </tr>
                                                        <tr>
                                                            <th>Subject</th>
                                                            <th>Q1</th>
                                                            <th>Q2</th>
                                                            <th>1st Sem Avg</th>
                                                        </tr>
                                                        </thead>
                                                        <tbody>
                                                        <?php foreach ($gradeGroup['rows'] as $gradeRow): ?>
                                                            <tr>
                                                                <td><?php echo escape($gradeRow['subject_name']); ?></td>
                                                                <td><?php echo escape($gradeRow['quarter_1_grade'] ?? '-'); ?></td>
                                                                <td><?php echo escape($gradeRow['quarter_2_grade'] ?? '-'); ?></td>
                                                                <td><?php echo escape($gradeRow['first_semester_average'] ?? '-'); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                    <table class="records-table report-table grade-semester-table">
                                                        <thead>
                                                        <tr>
                                                            <th colspan="6">Second Semester</th>
                                                        </tr>
                                                        <tr>
                                                            <th>Subject</th>
                                                            <th>Q3</th>
                                                            <th>Q4</th>
                                                            <th>2nd Sem Avg</th>
                                                            <th>Final Avg</th>
                                                            <th>Remarks</th>
                                                        </tr>
                                                        </thead>
                                                        <tbody>
                                                        <?php foreach ($gradeGroup['rows'] as $gradeRow): ?>
                                                            <tr>
                                                                <td><?php echo escape($gradeRow['subject_name']); ?></td>
                                                                <td><?php echo escape($gradeRow['quarter_3_grade'] ?? '-'); ?></td>
                                                                <td><?php echo escape($gradeRow['quarter_4_grade'] ?? '-'); ?></td>
                                                                <td><?php echo escape($gradeRow['second_semester_average'] ?? '-'); ?></td>
                                                                <td><?php echo escape($gradeRow['final_average'] ?? '-'); ?></td>
                                                                <td><?php echo escape($gradeRow['remarks'] ?? '-'); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                <?php else: ?>
                                                    <table class="records-table report-table">
                                                        <thead>
                                                        <tr>
                                                            <th>Subject</th>
                                                            <th>Q1</th>
                                                            <th>Q2</th>
                                                            <th>Q3</th>
                                                            <th>Q4</th>
                                                            <th>Average</th>
                                                            <th>Final Avg</th>
                                                            <th>Remarks</th>
                                                        </tr>
                                                        </thead>
                                                        <tbody>
                                                        <?php foreach ($gradeGroup['rows'] as $gradeRow): ?>
                                                            <tr>
                                                                <td><?php echo escape($gradeRow['subject_name']); ?></td>
                                                                <td><?php echo escape($gradeRow['quarter_1_grade'] ?? '-'); ?></td>
                                                                <td><?php echo escape($gradeRow['quarter_2_grade'] ?? '-'); ?></td>
                                                                <td><?php echo escape($gradeRow['quarter_3_grade'] ?? '-'); ?></td>
                                                                <td><?php echo escape($gradeRow['quarter_4_grade'] ?? '-'); ?></td>
                                                                <?php $quarterAverage = grade_quarter_average($gradeRow); ?>
                                                                <td><?php echo escape($quarterAverage !== null ? (string) $quarterAverage : '-'); ?></td>
                                                                <td><?php echo escape($gradeRow['final_average'] ?? '-'); ?></td>
                                                                <td><?php echo escape($gradeRow['remarks'] ?? '-'); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                <?php endif; ?>
                                            </div>
                                        </section>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endif; ?>
                <?php elseif ($module === 'announcements'): ?>
                    <section>
                        <article class="admin-module-card">
                            <div class="panel-heading compact-heading">
                                <h2><?php echo $announcementForm['id'] === null ? 'Create Announcement' : 'Edit Announcement'; ?></h2>
                                <p>Published announcements will be visible to parents of your advisory learners.</p>
                            </div>

                            <form method="post" class="learner-form-grid">
                                <input type="hidden" name="csrf_token" value="<?php echo escape(csrf_token()); ?>">
                                <input type="hidden" name="form_action" value="save_announcement">
                                <input type="hidden" name="id" value="<?php echo escape((string) ($announcementForm['id'] ?? '')); ?>">

                                <div class="teacher-form-grid-full">
                                    <label for="announcement_title">Title</label>
                                    <input id="announcement_title" name="title" type="text" value="<?php echo escape($announcementForm['title']); ?>" required>
                                </div>

                                <div class="teacher-form-grid-full">
                                    <label for="announcement_content">Content</label>
                                    <textarea id="announcement_content" name="content" rows="5" required><?php echo escape($announcementForm['content']); ?></textarea>
                                </div>

                                <div class="teacher-inline-check teacher-form-grid-full">
                                    <label>
                                        <input type="checkbox" name="is_published" value="1"<?php echo !empty($announcementForm['is_published']) ? ' checked' : ''; ?>>
                                        Publish this announcement
                                    </label>
                                </div>

                                <div class="learner-form-actions">
                                    <button type="submit" class="primary-button"><?php echo $announcementForm['id'] === null ? 'Save Announcement' : 'Update Announcement'; ?></button>
                                    <?php if ($announcementForm['id'] !== null): ?>
                                        <a href="<?php echo escape(teacher_module_url('announcements')); ?>" class="secondary-link">Cancel Edit</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </article>
                    </section>

                    <section class="admin-module-card">
                        <div class="panel-heading compact-heading">
                            <h2>Your Announcements</h2>
                            <p>Review and manage your announcements.</p>
                        </div>

                        <div class="table-shell">
                            <table class="records-table learner-table">
                                <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Published On</th>
                                    <th>Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if ($announcementRows === []): ?>
                                    <tr>
                                        <td colspan="4" class="empty-row">You have not created any announcements yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($announcementRows as $announcement): ?>
                                        <tr>
                                            <td><?php echo escape($announcement['title']); ?></td>
                                            <td><span class="table-status"><?php echo !empty($announcement['is_published']) ? 'Published' : 'Draft'; ?></span></td>
                                            <td><?php echo escape($announcement['published_at'] !== null ? date('M j, Y', strtotime($announcement['published_at'])) : '-'); ?></td>
                                            <td>
                                                <div class="table-actions">
                                                    <a href="<?php echo escape(teacher_module_url('announcements', ['edit_announcement_id' => $announcement['id']])); ?>" class="secondary-link small-link">Edit</a>
                                                    <form method="post" class="inline-form" onsubmit="return confirm('Delete this announcement?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo escape(csrf_token()); ?>">
                                                        <input type="hidden" name="form_action" value="delete_announcement">
                                                        <input type="hidden" name="announcement_id" value="<?php echo escape((string) $announcement['id']); ?>">
                                                        <button type="submit" class="danger-button">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                <?php elseif ($module === 'settings'): ?>
                    <section>
                        <article class="teacher-panel-card">
                            <div class="panel-heading">
                                <h2>Portal Theme</h2>
                                <p>Select a predefined theme for the portal.</p>
                            </div>

                            <?php if ($settingsFlash !== null): ?>
                                <div class="alert <?php echo escape($settingsFlash['type']); ?>"><?php echo escape($settingsFlash['message']); ?></div>
                            <?php endif; ?>

                            <form method="post" class="learner-form-grid">
                                <input type="hidden" name="csrf_token" value="<?php echo escape(csrf_token()); ?>">
                                <input type="hidden" name="form_action" value="save_theme">

                                <div class="teacher-form-grid-full">
                                    <label>Select Theme</label>
                                    <div class="option-checklist">
                                        <?php foreach (theme_predefined_sets() as $key => $theme): ?>
                                            <label class="check-card">
                                                <input type="radio" name="theme_key" value="<?php echo escape($key); ?>"<?php echo $activeThemeKey === $key ? ' checked' : ''; ?>>
                                                <span class="theme-choice-copy">
                                                    <strong><?php echo escape($theme['name']); ?></strong>
                                                    <small>Accent, background, and interface colors</small>
                                                    <span class="theme-swatch-row" aria-hidden="true">
                                                        <i class="theme-swatch" style="--swatch-color: <?php echo escape($theme['colors']['theme_color_accent']); ?>;"></i>
                                                        <i class="theme-swatch" style="--swatch-color: <?php echo escape($theme['colors']['theme_color_bg']); ?>;"></i>
                                                        <i class="theme-swatch" style="--swatch-color: <?php echo escape($theme['colors']['theme_color_surface_strong']); ?>;"></i>
                                                    </span>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="learner-form-actions">
                                    <button type="submit" class="primary-button">Save Theme</button>
                                </div>
                            </form>
                        </article>
                        <article class="teacher-panel-card">
                            <div class="panel-heading compact-heading">
                                <h2>Account Security</h2>
                                <p>Change your login password.</p>
                            </div>
                            <div class="template-actions">
                                 <a href="<?php echo escape(route_url('change_password.php')); ?>" class="secondary-link">Change Password</a>
                            </div>
                        </article>
                        <article class="teacher-panel-card">
                            <div class="panel-heading compact-heading">
                                <h2>Report an Issue</h2>
                                <p>Encountered a problem? Let us know!</p>
                            </div>
                            <form method="post" class="learner-form-grid">
                                <input type="hidden" name="csrf_token" value="<?php echo escape(csrf_token()); ?>">
                                <input type="hidden" name="form_action" value="report_issue">

                                <div class="teacher-form-grid-full">
                                    <label for="issue_subject">Subject</label>
                                    <input
                                        id="issue_subject"
                                        name="subject"
                                        type="text"
                                        value="<?php echo escape($issueForm['subject']); ?>"
                                        required
                                    >
                                </div>
                                <div class="teacher-form-grid-full">
                                    <label for="issue_description">Description</label>
                                    <textarea id="issue_description" name="description" rows="5" required><?php echo escape($issueForm['description']); ?></textarea>
                                </div>

                                <div class="learner-form-actions teacher-form-grid-full">
                                    <button type="submit" class="primary-button">Submit Issue</button>
                                </div>
                            </form>
                        </article>
                    </section>
                <?php endif; ?>
            </section>
        </section>
    </main>

    <?php if ($module === 'dashboard' && $adminAnnouncements !== [] && !isset($_SESSION['seen_admin_announcements'])): ?>
        <div id="announcement-modal" class="modal-backdrop is-open">
            <div class="modal-panel">
                <div class="panel-heading">
                    <h2>Admin Announcements</h2>
                    <p>Important updates from the portal administrator.</p>
                </div>
                <div class="teacher-link-stack">
                    <?php foreach ($adminAnnouncements as $announcement): ?>
                        <article class="admin-module-card">
                            <div class="panel-heading compact-heading">
                                <h3><?php echo escape($announcement['title']); ?></h3>
                                <p>
                                    Published by <?php echo escape($announcement['username'] ?? 'Admin'); ?>
                                    on <?php echo escape(teacher_format_date($announcement['published_at'])); ?>
                                </p>
                            </div>
                            <p><?php echo escape($announcement['content']); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="modal-actions">
                    <button id="close-announcement-modal" type="button" class="primary-button">Close</button>
                </div>
            </div>
        </div>
        <?php $_SESSION['seen_admin_announcements'] = true; ?>
    <?php endif; ?>

    <script src="<?php echo escape(asset_url('assets/js/admin.js')); ?>"></script>
</body>
</html>
