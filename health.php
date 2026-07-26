<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/learners.php';
require_once __DIR__ . '/app/health.php';
require_once __DIR__ . '/app/theme_settings.php';

function health_module_url(string $module, array $params = []): string
{
    $query = http_build_query(array_merge(['module' => $module], $params));

    return route_url('health.php' . ($query !== '' ? '?' . $query : ''));
}

function health_portal_format_date(?string $value, string $format = 'M j, Y'): string
{
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? (string) $value : date($format, $timestamp);
}

function health_portal_format_metric($value, int $decimals = 2, string $suffix = ''): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    return number_format((float) $value, $decimals) . $suffix;
}

function health_portal_percent(int $value, int $total): int
{
    if ($total <= 0) {
        return 0;
    }

    return max(0, min(100, (int) round(($value / $total) * 100)));
}

function health_portal_sex_label(?string $sex): string
{
    $sex = strtolower(trim((string) $sex));

    return match ($sex) {
        'male' => 'Male',
        'female' => 'Female',
        default => '-',
    };
}

function health_portal_filter_label(array $filters, array $sectionOptions): string
{
    $parts = [];
    $gradeSectionFilter = $filters['grade_section_filter'] ?? 'all|all';

    // Find the label for the selected grade_section_filter from the full dropdown options
    foreach ($sectionOptions as $option) {
        if ($option['value'] === $gradeSectionFilter) {
            if ($option['value'] !== 'all|all') { // Only add if not the "All" default
                $parts[] = $option['label'];
                break;
            }
        }
    }

    if (($filters['keyword'] ?? '') !== '') {
        $parts[] = 'Search: ' . (string) $filters['keyword'];
    }

    if (($filters['bmi_remarks'] ?? '') !== '') {
        $parts[] = 'BMI: ' . (string) $filters['bmi_remarks'];
    }

    return $parts === [] ? 'All current learners' : implode(' - ', $parts);
}

learner_management_bootstrap();
health_portal_bootstrap();
theme_settings_bootstrap();

$user = require_roles(['health']);
$allowedModules = [
    'dashboard' => [
        'eyebrow' => 'Health Coordinator Portal',
        'title' => 'Dashboard',
        'description' => 'Track learner measurements, deworming coverage, and feeding program recipients.',
    ],
    'learner_bmi' => [
        'eyebrow' => 'Learner Health',
        'title' => 'Learner BMI',
        'description' => 'View learners by grade and section, then update height and weight records.',
    ],
    'bmi_reports' => [
        'eyebrow' => 'Learner Health',
        'title' => 'BMI Reports',
        'description' => 'Print learner BMI reports with screening remarks for the selected class or grade level.',
    ],
    'deworming' => [
        'eyebrow' => 'Programs',
        'title' => 'Deworming Module',
        'description' => 'Assign first or second deworming doses for a whole class or for individual learners.',
    ],
    'feeding_program' => [
        'eyebrow' => 'Programs',
        'title' => 'Feeding Program',
        'description' => 'Filter learners by grade and section and add selected learners as feeding program recipients.',
    ],
];

$module = (string) ($_GET['module'] ?? 'dashboard');

if (!isset($allowedModules[$module])) {
    $module = 'dashboard';
}

$healthFlash = flash_get('health_portal');
$schoolYear = current_school_year();
$filters = health_filter_defaults($_GET); // This will now parse grade_section_filter
$sectionOptions = $schoolYear !== null ? health_filter_section_options($filters['grade_level']) : []; // Still needed for some internal logic, but not for dropdown
$allSectionDropdownOptions = health_filter_section_options_for_dropdown(); // New function for the combined dropdown

$learnerRows = [];
$bmiReportRows = [];
$dewormingRows = [];
$feedingCandidates = [];
$feedingRecipients = [];
$dashboardBmiRows = [];
$dashboardDewormingCounts = [];
$dashboardFeedingCounts = [];
$bmiRemarksForSelectedFilter = [];
$dewormingStatusForSelectedFilter = [];
$dashboardStats = [
    'total_learners' => 0,
    'measured_learners' => 0,
    'first_dose_count' => 0,
    'second_dose_count' => 0,
    'feeding_count' => 0,
];

if (is_post()) {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Invalid form token. Please refresh the page.');
        }

        $postFilters = health_filter_defaults($_POST);
        $redirectModule = (string) ($_POST['redirect_module'] ?? $module);

        if (!isset($allowedModules[$redirectModule])) {
            $redirectModule = $module; // Fallback to current module
        }

        $formAction = (string) ($_POST['form_action'] ?? '');

        if ($formAction === 'save_measurement') {
            health_save_measurement(
                (int) ($_POST['learner_enrollment_id'] ?? 0),
                $_POST['height_cm'] ?? null,
                $_POST['weight_kg'] ?? null
            );
            health_update_learner_disability(
                (int) ($_POST['learner_enrollment_id'] ?? 0),
                $_POST['has_disability'] ?? '0',
                $_POST['disability_basis'] ?? '',
                $_POST['disability_type'] ?? ''
            );
            flash_set('health_portal', 'Learner health and disability information updated successfully.');
            redirect('health.php?' . http_build_query(array_merge(['module' => $redirectModule], $postFilters)));
        }

        if ($formAction === 'import_measurements') {
            $importedCount = health_import_measurement_file($_FILES['measurement_file'] ?? []);
            flash_set('health_portal', 'Imported height and weight for ' . $importedCount . ' learner(s).');
            redirect('health.php?' . http_build_query(array_merge(['module' => $redirectModule], $postFilters)));
        }

        if ($formAction === 'assign_deworming_class') {
            $updatedCount = health_assign_deworming_class(
                $postFilters,
                (int) ($_POST['dose_number'] ?? 0),
                trim((string) ($_POST['administered_on'] ?? '')),
                (int) $user['id']
            );
            flash_set('health_portal', 'Assigned deworming dose to ' . $updatedCount . ' learner(s).');
            redirect('health.php?' . http_build_query(array_merge(['module' => $redirectModule], $postFilters)));
        }

        if ($formAction === 'assign_deworming_individual') {
            health_assign_deworming_individual(
                (int) ($_POST['learner_enrollment_id'] ?? 0),
                (int) ($_POST['dose_number'] ?? 0),
                trim((string) ($_POST['administered_on'] ?? '')),
                (int) $user['id']
            );
            flash_set('health_portal', 'Individual deworming record updated successfully.');
            redirect('health.php?' . http_build_query(array_merge(['module' => $redirectModule], $postFilters)));
        }

        if ($formAction === 'clear_deworming_selected') {
            $clearedCount = health_clear_deworming_records(
                $_POST['learner_enrollment_ids'] ?? [],
                (int) ($_POST['dose_number'] ?? 0)
            );
            flash_set('health_portal', 'Cleared deworming dose for ' . $clearedCount . ' learner(s).');
            redirect('health.php?' . http_build_query(array_merge(['module' => $redirectModule], $postFilters)));
        }

        if ($formAction === 'add_feeding_recipients') {
            $addedCount = health_add_feeding_recipients($_POST['learner_enrollment_ids'] ?? [], (int) $user['id']);
            $message = $addedCount > 0
                ? 'Added ' . $addedCount . ' learner(s) to the feeding program.'
                : 'All selected learners are already listed as feeding program recipients.';
            flash_set('health_portal', $message);
            redirect('health.php?' . http_build_query(array_merge(['module' => $redirectModule], $postFilters)));
        }

        if ($formAction === 'remove_feeding_recipient') {
            health_remove_feeding_recipient((int) ($_POST['learner_enrollment_id'] ?? 0));
            flash_set('health_portal', 'Learner removed from the feeding program list.');
            redirect('health.php?' . http_build_query(array_merge(['module' => $redirectModule], $postFilters)));
        }
    } catch (Throwable $exception) {
        $healthFlash = [
            'type' => 'error',
            'message' => $exception->getMessage(),
        ];
    }
}

if ($schoolYear !== null) {
    try {
        $dashboardStats = health_dashboard_stats();
        $dashboardBmiRows = health_dashboard_bmi_remarks_rows();
        $dashboardDewormingCounts = health_deworming_status_counts();
        $dashboardFeedingCounts = health_feeding_program_status_counts();

        if ($module === 'learner_bmi') {
            $learnerRows = health_learner_rows($filters);
            $bmiRemarksForSelectedFilter = health_dashboard_bmi_remarks_rows($filters);
        } elseif ($module === 'bmi_reports') {
            $bmiReportRows = health_learner_rows($filters);
        } elseif ($module === 'deworming') {
            $dewormingRows = health_deworming_rows($filters);
            $dewormingStatusForSelectedFilter = health_deworming_status_counts($filters);
        } elseif ($module === 'feeding_program') {
            $feedingCandidates = health_feeding_candidate_rows($filters);
            // The feeding_recipient_rows function already filters by the provided filters
            $feedingRecipients = health_feeding_recipient_rows($filters);
        }
    } catch (Throwable $exception) {
        $healthFlash = [
            'type' => 'error',
            'message' => $exception->getMessage(),
        ];
    }
}

$pageMeta = $allowedModules[$module];
$filterLabel = health_portal_filter_label($filters, $allSectionDropdownOptions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape(APP_NAME); ?> Health Coordinator Portal</title>
    <?php echo theme_stylesheet_markup(); ?>
    <style>
        .chart-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: space-around;
            margin-top: 20px;
        }
        .chart-card {
            background: var(--surface-strong);
            border: 1px solid var(--line);
            border-radius: 28px;
            box-shadow: var(--shadow);
            padding: 20px;
            flex: 1;
            min-width: 300px;
            max-width: 45%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .chart-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 15px;
            color: var(--ink);
        }
        .pie-chart {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: conic-gradient(
                var(--success) 0% var(--slice1),
                var(--warning) var(--slice1) var(--slice2),
                var(--danger) var(--slice2) var(--slice3),
                var(--info) var(--slice3) var(--slice4),
                var(--muted) var(--slice4) 100%
            );
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: var(--surface-strong);
            font-weight: 600;
            position: relative;
        }
        .pie-chart::before {
            content: '';
            position: absolute;
            background: var(--surface-strong);
            border-radius: 50%;
            width: 60%;
            height: 60%;
        }
        .pie-chart span {
            position: relative;
            z-index: 1;
            color: var(--ink);
        }
        .chart-legend {
            margin-top: 20px;
            width: 100%;
        }
        .chart-legend-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 0.9rem;
        }
        .chart-legend-item span:first-child {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .chart-legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }
        .bar-chart-container {
            width: 100%;
            height: 150px;
            display: flex;
            align-items: flex-end;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--line);
        }
        .bar-chart-bar {
            flex: 1;
            background-color: var(--accent);
            border-radius: 5px 5px 0 0;
            position: relative;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            color: var(--surface-strong);
            font-size: 0.8rem;
            font-weight: bold;
            transition: height 0.5s ease-out;
        }
        .bar-chart-bar span {
            position: absolute;
            top: -20px;
            color: var(--ink);
            font-size: 0.75rem;
            font-weight: normal;
        }
        .bar-chart-label {
            text-align: center;
            font-size: 0.8rem;
            color: var(--muted);
            margin-top: 5px;
        }
    </style>
    <link rel="stylesheet" href="<?php echo escape(asset_url('assets/css/app.css')); ?>">
</head>
<body class="dashboard-body admin-dashboard">
    <button
        id="sidebar-toggle"
        class="sidebar-toggle-button"
        type="button"
        data-sidebar-label="health menu"
        aria-label="Open health menu"
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
                    <p class="eyebrow">Health Coordinator</p>
                    <h1>Health Portal</h1>
                    <p class="sidebar-user"><?php echo escape($user['username']); ?></p>
                    <p class="sidebar-email"><?php echo escape($user['email']); ?></p>
                </div>

                <nav class="sidebar-nav" aria-label="Health Navigation">
                    <div class="menu-group">
                        <p class="menu-group-title">Overview</p>
                        <a href="<?php echo escape(health_module_url('dashboard')); ?>" class="submenu-link<?php echo $module === 'dashboard' ? ' active' : ''; ?>">Dashboard</a>
                    </div>

                    <div class="menu-group">
                        <p class="menu-group-title">Learner Health</p>
                        <a href="<?php echo escape(health_module_url('learner_bmi')); ?>" class="submenu-link<?php echo $module === 'learner_bmi' ? ' active' : ''; ?>">Learner BMI</a>
                        <a href="<?php echo escape(health_module_url('bmi_reports')); ?>" class="submenu-link<?php echo $module === 'bmi_reports' ? ' active' : ''; ?>">BMI Reports</a>
                    </div>

                    <div class="menu-group">
                        <p class="menu-group-title">Programs</p>
                        <a href="<?php echo escape(health_module_url('deworming')); ?>" class="submenu-link<?php echo $module === 'deworming' ? ' active' : ''; ?>">Deworming</a>
                        <a href="<?php echo escape(health_module_url('feeding_program')); ?>" class="submenu-link<?php echo $module === 'feeding_program' ? ' active' : ''; ?>">Feeding Program</a>
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

                    <?php if ($schoolYear !== null): ?>
                        <div class="teacher-header-chip">
                            <strong><?php echo escape($schoolYear['label']); ?></strong>
                            <span>Current school year</span>
                        </div>
                    <?php endif; ?>
                </header>

                <?php if ($healthFlash !== null): ?>
                    <div class="alert <?php echo escape($healthFlash['type']); ?>"><?php echo escape($healthFlash['message']); ?></div>
                <?php endif; ?>

                <?php if ($schoolYear === null): ?>
                    <article class="teacher-panel-card">
                        <div class="panel-heading">
                            <h2>No Active School Year Yet</h2>
                            <p>Create or mark a current school year first before using the health coordinator modules.</p>
                        </div>

                        <div class="alert neutral">Health records, deworming, and feeding program tools depend on the active school year.</div>
                    </article>
                <?php elseif ($module === 'dashboard'): ?>
                    <section class="teacher-summary-grid">
                        <article class="summary-card">
                            <span class="summary-code">Learners</span>
                            <strong><?php echo escape((string) $dashboardStats['total_learners']); ?></strong>
                            <small>Current school year enrollment</small>
                        </article>

                        <article class="summary-card">
                            <span class="summary-code">Measured</span>
                            <strong><?php echo escape((string) $dashboardStats['measured_learners']); ?></strong>
                            <small>With height and weight data</small>
                        </article>

                        <article class="summary-card">
                            <span class="summary-code">1st Dose</span>
                            <strong><?php echo escape((string) $dashboardStats['first_dose_count']); ?></strong>
                            <small>Deworming records saved</small>
                        </article>

                        <article class="summary-card">
                            <span class="summary-code">Feeding</span>
                            <strong><?php echo escape((string) $dashboardStats['feeding_count']); ?></strong>
                            <small>Program recipients</small>
                        </article>
                    </section>

                    <section class="teacher-overview-grid">
                        <article class="teacher-panel-card">
                            <div class="panel-heading">
                                <h2>School-Year Health Snapshot</h2>
                                <p>Use the modules on the left to maintain learner measurements and program coverage.</p>
                            </div>

                            <dl class="detail-grid">
                                <div>
                                    <dt>School Year</dt>
                                    <dd><?php echo escape($schoolYear['label']); ?></dd>
                                </div>
                                <div>
                                    <dt>Total Learners</dt>
                                    <dd><?php echo escape((string) $dashboardStats['total_learners']); ?></dd>
                                </div>
                                <div>
                                    <dt>Measured Learners</dt>
                                    <dd><?php echo escape((string) $dashboardStats['measured_learners']); ?></dd>
                                </div>
                                <div>
                                    <dt>Second Dose Records</dt>
                                    <dd><?php echo escape((string) $dashboardDewormingCounts['second_dose_count']); ?></dd>
                                </div>
                            </dl>
                        </article>

                        <!-- Removed Quick Actions Card as per request -->
                    </section>

                    <section class="chart-container">
                        <article class="chart-card">
                            <h3 class="chart-title">BMI Remarks Distribution</h3>
                            <?php
                                $bmiTotal = array_sum(array_column($dashboardBmiRows, 'total'));
                                $slice1 = health_portal_percent($dashboardBmiRows[0]['total'] ?? 0, $bmiTotal);
                                $slice2 = $slice1 + health_portal_percent($dashboardBmiRows[1]['total'] ?? 0, $bmiTotal);
                                $slice3 = $slice2 + health_portal_percent($dashboardBmiRows[2]['total'] ?? 0, $bmiTotal);
                                $slice4 = $slice3 + health_portal_percent($dashboardBmiRows[3]['total'] ?? 0, $bmiTotal);
                            ?>
                            <div class="pie-chart" style="
                                --slice1: <?php echo escape((string) $slice1); ?>%;
                                --slice2: <?php echo escape((string) $slice2); ?>%;
                                --slice3: <?php echo escape((string) $slice3); ?>%;
                                --slice4: <?php echo escape((string) $slice4); ?>%;
                            ">
                                <span><?php echo escape((string) $bmiTotal); ?> Learners</span>
                            </div>
                            <div class="chart-legend">
                                <?php foreach ($dashboardBmiRows as $bmiRow): ?>
                                    <div class="chart-legend-item">
                                        <span>
                                            <span class="chart-legend-color" style="background-color: <?php echo escape($bmiRow['color']); ?>;"></span>
                                            <?php echo escape($bmiRow['label']); ?>
                                        </span>
                                        <strong><?php echo escape((string) $bmiRow['total']); ?> (<?php echo escape((string) health_portal_percent($bmiRow['total'], $bmiTotal)); ?>%)</strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </article>

                        <article class="chart-card">
                            <h3 class="chart-title">Deworming Status</h3>
                            <?php
                                $dewormingTotal = $dashboardDewormingCounts['total_learners'];
                                $firstDosePercent = health_portal_percent($dashboardDewormingCounts['first_dose_count'], $dewormingTotal);
                                $secondDosePercent = health_portal_percent($dashboardDewormingCounts['second_dose_count'], $dewormingTotal);
                                $noDosePercent = health_portal_percent($dashboardDewormingCounts['no_dose_count'], $dewormingTotal);
                            ?>
                            <div class="bar-chart-container">
                                <div class="bar-chart-bar" style="height: <?php echo escape((string) $firstDosePercent); ?>%; background-color: var(--success);">
                                    <span><?php echo escape((string) $dashboardDewormingCounts['first_dose_count']); ?></span>
                                </div>
                                <div class="bar-chart-bar" style="height: <?php echo escape((string) $secondDosePercent); ?>%; background-color: var(--info);">
                                    <span><?php echo escape((string) $dashboardDewormingCounts['second_dose_count']); ?></span>
                                </div>
                                <div class="bar-chart-bar" style="height: <?php echo escape((string) $noDosePercent); ?>%; background-color: var(--muted);">
                                    <span><?php echo escape((string) $dashboardDewormingCounts['no_dose_count']); ?></span>
                                </div>
                            </div>
                            <div style="display: flex; justify-content: space-around; width: 100%; margin-top: 5px;">
                                <div class="bar-chart-label">1st Dose</div>
                                <div class="bar-chart-label">2nd Dose</div>
                                <div class="bar-chart-label">No Dose</div>
                            </div>
                            <div class="chart-legend">
                                <div class="chart-legend-item">
                                    <span><span class="chart-legend-color" style="background-color: var(--success);"></span>1st Dose</span>
                                    <strong><?php echo escape((string) $dashboardDewormingCounts['first_dose_count']); ?> (<?php echo escape((string) $firstDosePercent); ?>%)</strong>
                                </div>
                                <div class="chart-legend-item">
                                    <span><span class="chart-legend-color" style="background-color: var(--info);"></span>2nd Dose</span>
                                    <strong><?php echo escape((string) $dashboardDewormingCounts['second_dose_count']); ?> (<?php echo escape((string) $secondDosePercent); ?>%)</strong>
                                </div>
                                <div class="chart-legend-item">
                                    <span><span class="chart-legend-color" style="background-color: var(--muted);"></span>No Dose</span>
                                    <strong><?php echo escape((string) $dashboardDewormingCounts['no_dose_count']); ?> (<?php echo escape((string) $noDosePercent); ?>%)</strong>
                                </div>
                            </div>
                        </article>

                        <article class="chart-card">
                            <h3 class="chart-title">Feeding Program Status</h3>
                            <?php
                                $feedingTotal = $dashboardFeedingCounts['total_learners'];
                                $recipientPercent = health_portal_percent($dashboardFeedingCounts['recipient_count'], $feedingTotal);
                                $nonRecipientPercent = health_portal_percent($dashboardFeedingCounts['non_recipient_count'], $feedingTotal);
                            ?>
                            <div class="pie-chart" style="
                                --slice1: <?php echo escape((string) $recipientPercent); ?>%;
                                background: conic-gradient(var(--accent) 0% var(--slice1), var(--muted) var(--slice1) 100%);
                            ">
                                <span><?php echo escape((string) $feedingTotal); ?> Learners</span>
                            </div>
                            <div class="chart-legend">
                                <div class="chart-legend-item">
                                    <span><span class="chart-legend-color" style="background-color: var(--accent);"></span>Recipients</span>
                                    <strong><?php echo escape((string) $dashboardFeedingCounts['recipient_count']); ?> (<?php echo escape((string) $recipientPercent); ?>%)</strong>
                                </div>
                                <div class="chart-legend-item">
                                    <span><span class="chart-legend-color" style="background-color: var(--muted);"></span>Non-Recipients</span>
                                    <strong><?php echo escape((string) $dashboardFeedingCounts['non_recipient_count']); ?> (<?php echo escape((string) $nonRecipientPercent); ?>%)</strong>
                                </div>
                            </div>
                        </article>
                    </section>
                <?php elseif ($module === 'learner_bmi'): ?>
                    <article class="teacher-panel-card no-print">
                        <div class="panel-heading">
                            <h2>Filter Learners</h2>
                            <p>Choose a grade level and section to focus the learner BMI list.</p>
                        </div>

                        <form method="get" class="report-filter-grid">
                            <input type="hidden" name="module" value="learner_bmi">

                            <div class="report-filter-field report-filter-field-wide">
                                <label for="grade_section_filter">Grade Level and Section</label>
                                <select id="grade_section_filter" name="grade_section_filter">
                                    <?php foreach ($allSectionDropdownOptions as $option): ?>
                                        <option value="<?php echo escape($option['value']); ?>"<?php echo $filters['grade_section_filter'] === $option['value'] ? ' selected' : ''; ?>>
                                            <?php echo escape($option['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="report-actions">
                                <button type="submit" class="primary-button">View Learners</button>
                                <a href="<?php echo escape(health_module_url('learner_bmi')); ?>" class="secondary-link">Reset</a>
                            </div>
                        </form>
                    </article>

                    <article class="teacher-panel-card">
                        <div class="panel-heading compact-heading">
                            <h2>Learner BMI List</h2>
                            <p>Leave both fields blank and save to clear an existing measurement.</p>
                        </div>

                        <?php if ($filters['section_id'] !== '' && $bmiRemarksForSelectedFilter['total_learners'] > 0): ?>
                            <div class="chart-card" style="width: 100%; max-width: none; margin-bottom: 20px;">
                                <h3 class="chart-title">BMI Remarks for Selected Section</h3>
                                <?php
                                    $bmiTotalFiltered = array_sum(array_column($bmiRemarksForSelectedFilter, 'total'));
                                    $slice1Filtered = health_portal_percent($bmiRemarksForSelectedFilter[0]['total'] ?? 0, $bmiTotalFiltered);
                                    $slice2Filtered = $slice1Filtered + health_portal_percent($bmiRemarksForSelectedFilter[1]['total'] ?? 0, $bmiTotalFiltered);
                                    $slice3Filtered = $slice2Filtered + health_portal_percent($bmiRemarksForSelectedFilter[2]['total'] ?? 0, $bmiTotalFiltered);
                                    $slice4Filtered = $slice3Filtered + health_portal_percent($bmiRemarksForSelectedFilter[3]['total'] ?? 0, $bmiTotalFiltered);
                                ?>
                                <div class="pie-chart" style="
                                    --slice1: <?php echo escape((string) $slice1Filtered); ?>%;
                                    --slice2: <?php echo escape((string) $slice2Filtered); ?>%;
                                    --slice3: <?php echo escape((string) $slice3Filtered); ?>%;
                                    --slice4: <?php echo escape((string) $slice4Filtered); ?>%;
                                ">
                                    <span><?php echo escape((string) $bmiTotalFiltered); ?> Learners</span>
                                </div>
                                <div class="chart-legend">
                                    <?php foreach ($bmiRemarksForSelectedFilter as $bmiRow): ?>
                                        <div class="chart-legend-item">
                                            <span>
                                                <span class="chart-legend-color" style="background-color: <?php echo escape($bmiRow['color']); ?>;"></span>
                                                <?php echo escape($bmiRow['label']); ?>
                                            </span>
                                            <strong><?php echo escape((string) $bmiRow['total']); ?> (<?php echo escape((string) health_portal_percent($bmiRow['total'], $bmiTotalFiltered)); ?>%)</strong>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php elseif ($filters['grade_level'] !== '' && $bmiRemarksForSelectedFilter['total_learners'] > 0): ?>
                            <div class="alert neutral" style="margin-bottom: 20px;">
                                Select a specific section to view BMI remarks distribution for that section.
                            </div>
                        <?php else: ?>
                            <!-- No BMI remarks chart if no specific section or grade level is selected -->
                        <?php endif; ?>

                        <p class="import-note">BMI remarks use standard BMI bands as a quick screening view inside the portal.</p>

                        <div class="table-shell">
                            <table class="records-table learner-table">
                                <thead>
                                    <tr>
                                        <th>LRN</th>
                                        <th>Complete Name</th>
                                        <th>Grade and Section</th>
                                        <th>Sex</th>
                                        <th>Disability information</th>
                                        <th>Height (cm)</th>
                                        <th>Weight (kg)</th>
                                        <th>BMI</th>
                                        <th>BMI Remarks</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($learnerRows === []): ?>
                                        <tr>
                                            <td colspan="10" class="empty-row">No learners matched the selected filters.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($learnerRows as $learner): ?>
                                            <?php $formId = 'measurement-form-' . (int) $learner['learner_enrollment_id']; ?>
                                            <tr>
                                                <td><?php echo escape($learner['lrn']); ?></td>
                                                <td><?php echo escape($learner['complete_name']); ?></td>
                                                <td><?php echo escape($learner['grade_level'] . ' - ' . $learner['section_name']); ?></td>
                                                <td><?php echo escape(health_portal_sex_label($learner['sex'] ?? null)); ?></td>
                                                <td>
                                                    <select form="<?php echo escape($formId); ?>" name="has_disability" class="table-input-slim">
                                                        <option value="0"<?php echo (int) ($learner['has_disability'] ?? 0) !== 1 ? ' selected' : ''; ?>>No</option>
                                                        <option value="1"<?php echo (int) ($learner['has_disability'] ?? 0) === 1 ? ' selected' : ''; ?>>Yes</option>
                                                    </select>
                                                    <select form="<?php echo escape($formId); ?>" name="disability_basis" class="table-input-slim">
                                                        <option value="">Basis</option>
                                                        <option value="diagnosis"<?php echo ($learner['disability_basis'] ?? '') === 'diagnosis' ? ' selected' : ''; ?>>Diagnosis</option>
                                                        <option value="manifestation"<?php echo ($learner['disability_basis'] ?? '') === 'manifestation' ? ' selected' : ''; ?>>Manifestation</option>
                                                    </select>
                                                    <input form="<?php echo escape($formId); ?>" name="disability_type" type="text" maxlength="255" value="<?php echo escape((string) ($learner['disability_type'] ?? '')); ?>" placeholder="Type / details" class="table-input-slim">
                                                </td>
                                                <td>
                                                    <input form="<?php echo escape($formId); ?>" name="height_cm" type="number" min="30" max="250" step="0.01" value="<?php echo escape($learner['height_cm'] !== null ? (string) $learner['height_cm'] : ''); ?>" class="table-input-slim">
                                                </td>
                                                <td>
                                                    <input form="<?php echo escape($formId); ?>" name="weight_kg" type="number" min="1" max="300" step="0.01" value="<?php echo escape($learner['weight_kg'] !== null ? (string) $learner['weight_kg'] : ''); ?>" class="table-input-slim">
                                                </td>
                                                <td><?php echo escape($learner['bmi'] !== null ? number_format((float) $learner['bmi'], 2) : '-'); ?></td>
                                                <td><span class="table-status"><?php echo escape($learner['bmi_remarks']); ?></span></td>
                                                <td>
                                                    <form id="<?php echo escape($formId); ?>" method="post" class="inline-form">
                                                        <input type="hidden" name="csrf_token" value="<?php echo escape(csrf_token()); ?>">
                                                        <input type="hidden" name="form_action" value="save_measurement">
                                                        <input type="hidden" name="redirect_module" value="learner_bmi">
                                                        <input type="hidden" name="learner_enrollment_id" value="<?php echo escape((string) $learner['learner_enrollment_id']); ?>">
                                                        <input type="hidden" name="grade_level" value="<?php echo escape($filters['grade_level']); ?>">
                                                        <input type="hidden" name="section_id" value="<?php echo escape($filters['section_id']); ?>">
                                                    </form>
                                                    <button type="submit" form="<?php echo escape($formId); ?>" class="primary-button small-link">Save</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </article>
                <?php elseif ($module === 'bmi_reports'): ?>
                    <article class="teacher-panel-card no-print">
                        <div class="panel-heading">
                            <h2>Report Filters</h2>
                            <p>Select a grade and section, then print the BMI report for the current school year.</p>
                        </div>

                        <form method="get" class="report-filter-grid">
                            <input type="hidden" name="module" value="bmi_reports">

                            <div class="report-filter-field report-filter-field-wide">
                                <label for="report_grade_section_filter">Grade Level and Section</label>
                                <select id="report_grade_section_filter" name="grade_section_filter">
                                    <?php foreach ($allSectionDropdownOptions as $option): ?>
                                        <option value="<?php echo escape($option['value']); ?>"<?php echo $filters['grade_section_filter'] === $option['value'] ? ' selected' : ''; ?>>
                                            <?php echo escape($option['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="report-actions">
                                <button type="submit" class="primary-button">Load Report</button>
                                <button type="button" class="ghost-button" onclick="window.print()">Print Report</button>
                                <a href="<?php echo escape(health_module_url('bmi_reports')); ?>" class="secondary-link">Reset</a>
                            </div>
                        </form>
                    </article>

                    <article class="teacher-panel-card report-print-area">
                        <div class="report-print-header">
                            <div class="admin-page-title">
                                <img class="school-logo header-logo" src="<?php echo escape(school_logo_url()); ?>" alt="School logo">
                                <div class="header-copy">
                                    <h2>Learner BMI Report</h2>
                                    <p>Filtered view for <?php echo escape($schoolYear['label']); ?>.</p>
                                </div>
                            </div>

                            <div class="report-print-meta">
                                <p><strong>School Year:</strong> <?php echo escape($schoolYear['label']); ?></p>
                                <p><strong>Filter:</strong> <?php echo escape($filterLabel); ?></p>
                                <p><strong>Total Learners:</strong> <?php echo escape((string) count($bmiReportRows)); ?></p>
                            </div>
                        </div>

                        <div class="report-filter-summary">
                            <div class="report-filter-chip">
                                <span>Grade / Section</span>
                                <strong><?php echo escape($filterLabel); ?></strong>
                            </div>
                            <div class="report-filter-chip">
                                <span>Generated</span>
                                <strong><?php echo escape(date('F j, Y')); ?></strong>
                            </div>
                        </div>

                        <p class="report-summary-note">BMI remarks are shown as a quick screening reference based on the stored BMI value.</p>

                        <div class="table-shell">
                            <table class="records-table learner-table">
                                <thead>
                                    <tr>
                                        <th>LRN</th>
                                        <th>Complete Name</th>
                                        <th>Grade and Section</th>
                                        <th>Sex</th>
                                        <th>Disability information</th>
                                        <th>Height</th>
                                        <th>Weight</th>
                                        <th>BMI</th>
                                        <th>BMI Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($bmiReportRows === []): ?>
                                        <tr>
                                            <td colspan="9" class="empty-row">No learners matched the selected filters.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($bmiReportRows as $learner): ?>
                                            <tr>
                                                <td><?php echo escape($learner['lrn']); ?></td>
                                                <td><?php echo escape($learner['complete_name']); ?></td>
                                                <td><?php echo escape($learner['grade_level'] . ' - ' . $learner['section_name']); ?></td>
                                                <td><?php echo escape(health_portal_sex_label($learner['sex'] ?? null)); ?></td>
                                                <td><?php echo (int) ($learner['has_disability'] ?? 0) === 1 ? escape(ucfirst((string) $learner['disability_basis']) . ': ' . (string) $learner['disability_type']) : 'No'; ?></td>
                                                <td><?php echo escape(health_portal_format_metric($learner['height_cm'], 2, ' cm')); ?></td>
                                                <td><?php echo escape(health_portal_format_metric($learner['weight_kg'], 2, ' kg')); ?></td>
                                                <td><?php echo escape($learner['bmi'] !== null ? number_format((float) $learner['bmi'], 2) : '-'); ?></td>
                                                <td><span class="table-status"><?php echo escape($learner['bmi_remarks']); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </article>
                <?php elseif ($module === 'deworming'): ?>
                    <article class="teacher-panel-card no-print">
                        <div class="panel-heading">
                            <h2>Filter Class</h2>
                            <p>Choose the grade and section before assigning deworming doses.</p>
                        </div>

                        <form method="get" class="report-filter-grid">
                            <input type="hidden" name="module" value="deworming">

                            <div class="report-filter-field report-filter-field-wide">
                                <label for="deworm_grade_section_filter">Grade Level and Section</label>
                                <select id="deworm_grade_section_filter" name="grade_section_filter">
                                    <?php foreach ($allSectionDropdownOptions as $option): ?>
                                        <option value="<?php echo escape($option['value']); ?>"<?php echo $filters['grade_section_filter'] === $option['value'] ? ' selected' : ''; ?>>
                                            <?php echo escape($option['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="report-actions">
                                <button type="submit" class="primary-button">View Class</button>
                                <a href="<?php echo escape(health_module_url('deworming')); ?>" class="secondary-link">Reset</a>
                            </div>
                        </form>
                    </article>

                    <article class="teacher-panel-card no-print">
                        <div class="panel-heading compact-heading">
                            <h2>Assign Whole Class</h2>
                            <p>Apply the selected dose and date to every learner in the filtered list below.</p>
                        </div>

                        <form method="post" class="teacher-form-grid">
                            <input type="hidden" name="csrf_token" value="<?php echo escape(csrf_token()); ?>">
                            <input type="hidden" name="form_action" value="assign_deworming_class">
                            <input type="hidden" name="redirect_module" value="deworming"> <!-- Keep redirect_module -->
                            <input type="hidden" name="grade_section_filter" value="<?php echo escape($filters['grade_section_filter']); ?>">

                            <div>
                                <label for="dose_number">Dose</label>
                                <select id="dose_number" name="dose_number" required>
                                    <?php foreach (health_deworming_dose_options() as $value => $label): ?>
                                        <option value="<?php echo escape((string) $value); ?>"><?php echo escape($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label for="administered_on">Date Given</label>
                                <input id="administered_on" name="administered_on" type="date" value="<?php echo escape(date('Y-m-d')); ?>" required>
                            </div>

                            <div class="learner-form-actions">
                                <button type="submit" class="primary-button">Assign Dose To Filtered Learners</button>
                            </div>
                        </form>
                    </article>

                    <?php if ($dewormingStatusForSelectedFilter['total_learners'] > 0): ?>
                        <article class="chart-card" style="width: 100%; max-width: none; margin-bottom: 20px;">
                            <h3 class="chart-title">Deworming Status for Filtered Learners</h3>
                            <?php
                                $dewormingTotalFiltered = $dewormingStatusForSelectedFilter['total_learners'];
                                $firstDosePercentFiltered = health_portal_percent($dewormingStatusForSelectedFilter['first_dose_count'], $dewormingTotalFiltered);
                                $secondDosePercentFiltered = health_portal_percent($dewormingStatusForSelectedFilter['second_dose_count'], $dewormingTotalFiltered);
                                $noDosePercentFiltered = health_portal_percent($dewormingStatusForSelectedFilter['no_dose_count'], $dewormingTotalFiltered);
                            ?>
                            <div class="bar-chart-container">
                                <div class="bar-chart-bar" style="height: <?php echo escape((string) $firstDosePercentFiltered); ?>%; background-color: var(--success);">
                                    <span><?php echo escape((string) $dewormingStatusForSelectedFilter['first_dose_count']); ?></span>
                                </div>
                                <div class="bar-chart-bar" style="height: <?php echo escape((string) $secondDosePercentFiltered); ?>%; background-color: var(--info);">
                                    <span><?php echo escape((string) $dewormingStatusForSelectedFilter['second_dose_count']); ?></span>
                                </div>
                                <div class="bar-chart-bar" style="height: <?php echo escape((string) $noDosePercentFiltered); ?>%; background-color: var(--muted);">
                                    <span><?php echo escape((string) $dewormingStatusForSelectedFilter['no_dose_count']); ?></span>
                                </div>
                            </div>
                            <div style="display: flex; justify-content: space-around; width: 100%; margin-top: 5px;">
                                <div class="bar-chart-label">1st Dose</div>
                                <div class="bar-chart-label">2nd Dose</div>
                                <div class="bar-chart-label">No Dose</div>
                            </div>
                            <div class="chart-legend">
                                <div class="chart-legend-item"><span><span class="chart-legend-color" style="background-color: var(--success);"></span>1st Dose</span><strong><?php echo escape((string) $firstDosePercentFiltered); ?> (<?php echo escape((string) $firstDosePercentFiltered); ?>%)</strong></div>
                                <div class="chart-legend-item"><span><span class="chart-legend-color" style="background-color: var(--info);"></span>2nd Dose</span><strong><?php echo escape((string) $secondDosePercentFiltered); ?> (<?php echo escape((string) $secondDosePercentFiltered); ?>%)</strong></div>
                                <div class="chart-legend-item"><span><span class="chart-legend-color" style="background-color: var(--muted);"></span>No Dose</span><strong><?php echo escape((string) $noDosePercentFiltered); ?> (<?php echo escape((string) $noDosePercentFiltered); ?>%)</strong></div>
                            </div>
                        </article>
                    <?php else: ?>
                        <div class="alert neutral" style="margin-bottom: 20px;">
                            No learners found for the selected filters to display deworming status.
                        </div>
                    <?php endif; ?>

                    <article class="teacher-panel-card">
                        <div class="panel-heading compact-heading">
                            <h2>Deworming Records</h2>
                            <p>Assign doses per learner when the whole-class action is not appropriate.</p>
                        </div>

                        <div class="table-shell">
                            <table class="records-table learner-table">
                                <thead>
                                    <tr>
                                        <th>LRN</th>
                                        <th>Complete Name</th>
                                        <th>Grade and Section</th>
                                        <th>First Dose</th>
                                        <th>Second Dose</th>
                                        <th>Individual Assignment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($dewormingRows === []): ?>
                                        <tr>
                                            <td colspan="6" class="empty-row">No learners matched the selected filters.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($dewormingRows as $learner): ?>
                                            <tr>
                                                <td><?php echo escape($learner['lrn']); ?></td>
                                                <td><?php echo escape($learner['complete_name']); ?></td>
                                                <td><?php echo escape($learner['grade_level'] . ' - ' . $learner['section_name']); ?></td>
                                                <td><?php echo escape(health_portal_format_date($learner['first_dose_date'] ?? null)); ?></td>
                                                <td><?php echo escape(health_portal_format_date($learner['second_dose_date'] ?? null)); ?></td>
                                                <td>
                                                    <form method="post" class="table-form-stack">
                                                        <input type="hidden" name="csrf_token" value="<?php echo escape(csrf_token()); ?>">
                                                        <input type="hidden" name="form_action" value="assign_deworming_individual">
                                                        <input type="hidden" name="redirect_module" value="deworming">
                                                        <input type="hidden" name="learner_enrollment_id" value="<?php echo escape((string) $learner['learner_enrollment_id']); ?>">
                                                        <input type="hidden" name="grade_level" value="<?php echo escape($filters['grade_level']); ?>">
                                                        <input type="hidden" name="section_id" value="<?php echo escape($filters['section_id']); ?>">

                                                        <select name="dose_number" class="table-input-slim">
                                                            <?php foreach (health_deworming_dose_options() as $value => $label): ?>
                                                                <option value="<?php echo escape((string) $value); ?>"><?php echo escape($label); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>

                                                        <input name="administered_on" type="date" value="<?php echo escape(date('Y-m-d')); ?>" class="table-input-slim" required>
                                                        <button type="submit" class="primary-button small-link">Assign</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </article>
                <?php else: ?>
                    <article class="teacher-panel-card no-print">
                        <div class="panel-heading">
                            <h2>Filter Learners</h2>
                            <p>Choose the grade and section to build the feeding program list.</p>
                        </div>

                        <form method="get" class="report-filter-grid">
                            <input type="hidden" name="module" value="feeding_program">

                            <div class="report-filter-field report-filter-field-wide">
                                <label for="feeding_grade_section_filter">Grade Level and Section</label>
                                <select id="feeding_grade_section_filter" name="grade_section_filter">
                                    <?php foreach ($allSectionDropdownOptions as $option): ?>
                                        <option value="<?php echo escape($option['value']); ?>"<?php echo $filters['grade_section_filter'] === $option['value'] ? ' selected' : ''; ?>>
                                            <?php echo escape($option['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="report-actions">
                                <button type="submit" class="primary-button">View Learners</button>
                                <a href="<?php echo escape(health_module_url('feeding_program')); ?>" class="secondary-link">Reset</a>
                            </div>
                        </form>
                    </article>

                    <article class="teacher-panel-card no-print">
                        <div class="panel-heading compact-heading">
                            <h2>Select Feeding Recipients</h2>
                            <p>Check the learners to add them to the feeding program list for the current school year.</p>
                        </div>

                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo escape(csrf_token()); ?>">
                            <input type="hidden" name="form_action" value="add_feeding_recipients">
                            <input type="hidden" name="redirect_module" value="feeding_program">
                            <input type="hidden" name="grade_section_filter" value="<?php echo escape($filters['grade_section_filter']); ?>">

                            <div class="table-shell">
                                <table class="records-table learner-table">
                                    <thead>
                                        <tr>
                                            <th>Select</th>
                                            <th>LRN</th>
                                            <th>Complete Name</th>
                                            <th>Grade and Section</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($feedingCandidates === []): ?>
                                            <tr>
                                                <td colspan="5" class="empty-row">No non-recipient learners matched the selected filters.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($feedingCandidates as $learner): ?>
                                                <tr>
                                                    <td>
                                                        <label class="table-checkbox-label">
                                                            <input
                                                                type="checkbox"
                                                                name="learner_enrollment_ids[]"
                                                                value="<?php echo escape((string) $learner['learner_enrollment_id']); ?>"
                                                                class="table-checkbox"
                                                                <?php echo $learner['is_recipient'] ? ' disabled' : ''; ?>
                                                            >
                                                            <span><?php echo $learner['is_recipient'] ? 'Added' : ''; ?></span>
                                                        </label>
                                                    </td>
                                                    <td><?php echo escape($learner['lrn']); ?></td>
                                                    <td><?php echo escape($learner['complete_name']); ?></td>
                                                    <td><?php echo escape($learner['grade_level'] . ' - ' . $learner['section_name']); ?></td>
                                                    <td><span class="table-status"><?php echo $learner['is_recipient'] ? 'Already Recipient' : 'Available'; ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="report-actions">
                                <button type="submit" class="primary-button">Add Selected Learners</button>
                            </div>
                        </form>
                    </article>

                    <article class="teacher-panel-card">
                        <div class="panel-heading compact-heading">
                            <h2>Feeding Program Recipients</h2>
                            <p>Current recipient list for <?php echo escape($schoolYear['label']); ?> based on the selected filters.</p>
                        </div>

                        <div class="table-shell">
                            <table class="records-table learner-table">
                                <thead>
                                    <tr>
                                        <th>LRN</th>
                                        <th>Complete Name</th>
                                        <th>Grade and Section</th>
                                        <th>Enrolled On</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($feedingRecipients === []): ?>
                                        <tr>
                                            <td colspan="5" class="empty-row">No feeding program recipients matched the selected filters.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($feedingRecipients as $learner): ?>
                                            <tr>
                                                <td><?php echo escape($learner['lrn']); ?></td>
                                                <td><?php echo escape($learner['complete_name']); ?></td>
                                                <td><?php echo escape($learner['grade_level'] . ' - ' . $learner['section_name']); ?></td>
                                                <td><?php echo escape(health_portal_format_date($learner['enrolled_on'] ?? null)); ?></td>
                                                <td>
                                                    <form method="post" class="inline-form" onsubmit="return confirm('Remove this learner from the feeding program list?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo escape(csrf_token()); ?>">
                                                        <input type="hidden" name="form_action" value="remove_feeding_recipient">
                                                        <input type="hidden" name="redirect_module" value="feeding_program">
                                                        <input type="hidden" name="learner_enrollment_id" value="<?php echo escape((string) $learner['learner_enrollment_id']); ?>">
                                                        <input type="hidden" name="grade_level" value="<?php echo escape($filters['grade_level']); ?>">
                                                        <input type="hidden" name="section_id" value="<?php echo escape($filters['section_id']); ?>">
                                                        <button type="submit" class="danger-button">Remove</button>
                                                    </form>
                                                </td>
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
