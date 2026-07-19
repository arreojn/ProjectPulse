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

learner_management_bootstrap();
teacher_management_bootstrap();
parent_portal_bootstrap();
grade_book_bootstrap();

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
        'description' => 'Create a parent account and link it directly to a learner in your section.',
    ],
    'parent_accounts_import' => [
        'eyebrow' => 'Parent Account',
        'title' => 'Import Parent Accounts',
        'description' => 'Bulk import parent accounts and link them by learner LRN.',
    ],
    'link_parent_account' => [
        'eyebrow' => 'Parent Account',
        'title' => 'Link Parent Account',
        'description' => 'Attach an existing parent account to one learner in your assigned section.',
    ],
    'parent_links' => [
        'eyebrow' => 'Parent Account',
        'title' => 'Parents Link In The Section',
        'description' => 'Review the parent accounts already connected to your advisory learners.',
    ],
    'grades_import' => [
        'eyebrow' => 'Grades',
        'title' => 'Import Grades',
        'description' => 'Upload subject grades for learners inside your assigned section and review imported records.',
    ],
    'imported_grades' => [
        'eyebrow' => 'Grades',
        'title' => 'Imported Grades',
        'description' => 'Inspect imported grade details for one learner at a time.',
    ],
    'learner_profiles' => [
        'eyebrow' => 'Learner Profile',
        'title' => 'Learner Basic Profile',
        'description' => 'Import or update birthdate, age basis, mother tongue, religion, and address details.',
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
$profileFormFromPost = false;
$section = teacher_assigned_section((int) $user['id']);

if (is_post()) {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Invalid form token. Please refresh the page.');
        }

        if ($section === null) {
            throw new RuntimeException('Your teacher account is not assigned to a section yet.');
        }

        $formAction = (string) ($_POST['form_action'] ?? '');

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
            redirect('teacher.php?module=parent_accounts_import');
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
    } catch (Throwable $exception) {
        $teacherFlash = [
            'type' => 'error',
            'message' => $exception->getMessage(),
        ];
    }
}

$sectionLearners = $section === null ? [] : teacher_section_learners((int) $user['id']);
$parentLinks = $section === null ? [] : teacher_section_parent_links((int) $user['id']);
$gradeRows = $section === null ? [] : grade_teacher_section_rows((int) $user['id']);
$usesSeniorGradeLayout = $section !== null && grade_is_senior_high((string) $section['grade_level']);
$linkedLearnerCount = count(array_filter(
    $sectionLearners,
    static fn (array $learner): bool => (int) $learner['linked_parent_count'] > 0
));
$profileCompletedCount = count(array_filter(
    $sectionLearners,
    static fn (array $learner): bool => teacher_profile_is_complete($learner)
));
$gradeLearnerIds = array_values(array_unique(array_map(
    static fn (array $row): int => (int) $row['learner_id'],
    $gradeRows
)));
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
$genderBarMax = max(1, $maleLearnerCount, $femaleLearnerCount);
$maleBarHeight = $maleLearnerCount > 0 ? round(($maleLearnerCount / $genderBarMax) * 100, 2) : 0.0;
$femaleBarHeight = $femaleLearnerCount > 0 ? round(($femaleLearnerCount / $genderBarMax) * 100, 2) : 0.0;

$referenceYear = $section !== null && !empty($section['school_year_start_date'])
    ? (int) substr((string) $section['school_year_start_date'], 0, 4)
    : (int) date('Y');
$ageReferenceDate = learner_first_friday_of_june($referenceYear);
$ageReferenceLabel = teacher_format_date($ageReferenceDate, 'F j, Y');

$selectedGradeLearnerId = isset($_GET['grade_learner_id']) ? (int) $_GET['grade_learner_id'] : 0;
if ($selectedGradeLearnerId <= 0 && $gradeLearnerIds !== []) {
    $selectedGradeLearnerId = $gradeLearnerIds[0];
}
$selectedGradeLearner = $selectedGradeLearnerId > 0 ? teacher_accessible_learner((int) $user['id'], $selectedGradeLearnerId) : null;
$selectedGradeRows = $selectedGradeLearner === null ? [] : grade_teacher_learner_rows((int) $user['id'], (int) $selectedGradeLearner['id']);

$selectedProfileLearnerId = isset($_GET['profile_learner_id']) ? (int) $_GET['profile_learner_id'] : 0;
if ($selectedProfileLearnerId <= 0 && $sectionLearners !== []) {
    $selectedProfileLearnerId = (int) $sectionLearners[0]['id'];
}
$selectedProfileLearner = teacher_find_section_learner($sectionLearners, $selectedProfileLearnerId);

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
                        <a href="<?php echo escape(teacher_module_url('parent_accounts_import')); ?>" class="submenu-link<?php echo $module === 'parent_accounts_import' ? ' active' : ''; ?>">Import</a>
                        <a href="<?php echo escape(teacher_module_url('link_parent_account')); ?>" class="submenu-link<?php echo $module === 'link_parent_account' ? ' active' : ''; ?>">Link Parent Account</a>
                        <a href="<?php echo escape(teacher_module_url('parent_links')); ?>" class="submenu-link<?php echo $module === 'parent_links' ? ' active' : ''; ?>">Parents Link In The Section</a>
                    </div>

                    <div class="menu-group">
                        <p class="menu-group-title">Grades</p>
                        <a href="<?php echo escape(teacher_module_url('grades_import')); ?>" class="submenu-link<?php echo $module === 'grades_import' ? ' active' : ''; ?>">Import Grades</a>
                        <a href="<?php echo escape(teacher_module_url('imported_grades')); ?>" class="submenu-link<?php echo $module === 'imported_grades' ? ' active' : ''; ?>">Imported Grades</a>
                    </div>

                    <div class="menu-group">
                        <p class="menu-group-title">Learner Profile</p>
                        <a href="<?php echo escape(teacher_module_url('learner_profiles')); ?>" class="submenu-link<?php echo $module === 'learner_profiles' ? ' active' : ''; ?>">Basic Profile</a>
                    </div>
                </nav>

                <div class="sidebar-footer">
                    <a href="<?php echo escape(route_url('change_password.php')); ?>" class="secondary-link full-width-link">Change Password</a>
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

                <?php if ($section === null): ?>
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

                    <section class="teacher-overview-grid">
                        <article class="teacher-panel-card">
                            <div class="panel-heading">
                                <h2>Assigned Section</h2>
                                <p>Your access is limited to this advisory class.</p>
                            </div>

                            <dl class="detail-grid">
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

                    <section class="teacher-overview-grid">
                        <article class="teacher-panel-card">
                            <div class="panel-heading compact-heading">
                                <h2>Male vs Female Comparison</h2>
                                <p>Bar chart view of the learner sex data recorded in your section.</p>
                            </div>

                            <?php if ($sectionLearners === []): ?>
                                <div class="alert neutral">No learners are assigned to your section yet.</div>
                            <?php else: ?>
                                <div class="teacher-comparison-chart">
                                    <div class="teacher-comparison-bar">
                                        <div class="teacher-comparison-bar-meta">
                                            <strong><?php echo escape((string) $maleLearnerCount); ?></strong>
                                            <span>Male learners</span>
                                        </div>
                                        <div class="teacher-comparison-bar-track">
                                            <div
                                                class="teacher-comparison-bar-fill male<?php echo $maleLearnerCount === 0 ? ' is-empty' : ''; ?>"
                                                style="height: <?php echo escape(number_format($maleBarHeight, 2, '.', '')); ?>%;"
                                            ></div>
                                        </div>
                                    </div>

                                    <div class="teacher-comparison-bar">
                                        <div class="teacher-comparison-bar-meta">
                                            <strong><?php echo escape((string) $femaleLearnerCount); ?></strong>
                                            <span>Female learners</span>
                                        </div>
                                        <div class="teacher-comparison-bar-track">
                                            <div
                                                class="teacher-comparison-bar-fill female<?php echo $femaleLearnerCount === 0 ? ' is-empty' : ''; ?>"
                                                style="height: <?php echo escape(number_format($femaleBarHeight, 2, '.', '')); ?>%;"
                                            ></div>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($unspecifiedSexCount > 0): ?>
                                    <p class="teacher-chart-footnote"><?php echo escape((string) $unspecifiedSexCount); ?> learner(s) still have no recorded sex.</p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </article>

                        <article class="teacher-panel-card">
                            <div class="panel-heading compact-heading">
                                <h2>Section Learners</h2>
                                <p>Click a learner name to open the grade details page.</p>
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
                                                        <a class="table-inline-link" href="<?php echo escape(teacher_module_url('imported_grades', ['grade_learner_id' => (string) $learner['id']])); ?>">
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
                <?php elseif ($module === 'parent_accounts_import'): ?>
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
                                <input id="existing_identity" name="identity" type="text" value="<?php echo escape($existingParentForm['identity']); ?>" required>
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
                <?php elseif ($module === 'parent_links'): ?>
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
                <?php elseif ($module === 'imported_grades'): ?>
                    <article class="teacher-panel-card">
                        <div class="panel-heading compact-heading">
                            <h2>Select Learner</h2>
                            <p>Open one learner to view the detailed imported grades.</p>
                        </div>

                        <form method="get" class="report-filter-grid">
                            <input type="hidden" name="module" value="imported_grades">

                            <div class="report-filter-field report-filter-field-wide">
                                <label for="grade_learner_id">Learner</label>
                                <select id="grade_learner_id" name="grade_learner_id">
                                    <?php foreach ($sectionLearners as $learner): ?>
                                        <option value="<?php echo escape((string) $learner['id']); ?>"<?php echo $selectedGradeLearner !== null && (int) $selectedGradeLearner['id'] === (int) $learner['id'] ? ' selected' : ''; ?>>
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

                    <article class="teacher-panel-card">
                        <div class="panel-heading compact-heading">
                            <h2>Section Learner Profiles</h2>
                            <p>Age below is auto-computed using <?php echo escape($ageReferenceLabel); ?>.</p>
                        </div>

                        <div class="table-shell">
                            <table class="records-table learner-table">
                                <thead>
                                    <tr>
                                        <th>Learner</th>
                                        <th>LRN</th>
                                        <th>Birthdate</th>
                                        <th>Age</th>
                                        <th>Mother Tongue</th>
                                        <th>Religion</th>
                                        <th>Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($sectionLearners === []): ?>
                                        <tr>
                                            <td colspan="7" class="empty-row">No learners are assigned to your section yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($sectionLearners as $learner): ?>
                                            <?php $learnerAge = learner_age_on_reference_date((string) ($learner['birthdate'] ?? ''), $ageReferenceDate); ?>
                                            <tr>
                                                <td>
                                                    <a class="table-inline-link" href="<?php echo escape(teacher_module_url('learner_profiles', ['profile_learner_id' => (string) $learner['id']])); ?>">
                                                        <?php echo escape($learner['learner_name']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo escape($learner['lrn']); ?></td>
                                                <td><?php echo escape(teacher_format_date($learner['birthdate'] ?? null)); ?></td>
                                                <td><?php echo escape($learnerAge !== null ? (string) $learnerAge : '-'); ?></td>
                                                <td><?php echo escape($learner['mother_tongue'] !== '' ? $learner['mother_tongue'] : '-'); ?></td>
                                                <td><?php echo escape($learner['religion'] !== '' ? $learner['religion'] : '-'); ?></td>
                                                <td><?php echo escape(teacher_profile_address($learner)); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </article>
                <?php endif; ?>
            </section>
        </section>
    </main>

    <script src="<?php echo escape(asset_url('assets/js/admin.js')); ?>"></script>
</body>
</html>
