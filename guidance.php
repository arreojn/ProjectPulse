<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/announcements.php';
require_once __DIR__ . '/app/guidance.php';
require_once __DIR__ . '/app/theme_settings.php';

try {
    announcements_bootstrap();
    theme_settings_bootstrap();
} catch (Throwable $exception) {
    // Keep the portal available even if table creation fails.
}

$user = require_roles(['guidance']);
$flash = flash_get('guidance_portal');

// Handle theme change
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'change_theme') {
    // A list of allowed themes to prevent arbitrary values.
    $allowedThemes = ['default', 'light', 'dark'];
    $selectedTheme = (string) ($_POST['theme'] ?? 'default');
    if (in_array($selectedTheme, $allowedThemes, true)) {
        $_SESSION['theme'] = $selectedTheme;
    }
    // Redirect to the same page with existing GET parameters to avoid form resubmission.
    redirect('guidance.php?' . http_build_query($_GET));
}

// Handle password change
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Invalid form token. Please refresh the page.');
        }

        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($newPassword !== $confirmPassword) {
            throw new RuntimeException('New password confirmation does not match.');
        }

        auth_change_password((int) $user['id'], $currentPassword, $newPassword);
        flash_set('guidance_portal', 'Password changed successfully.');
        redirect('guidance.php?module=settings');
    } catch (Throwable $exception) {
        $flash = ['type' => 'error', 'message' => $exception->getMessage()];
    }
}

$allowedModules = [
    'dashboard' => [
        'eyebrow' => 'Guidance Counselor Portal',
        'title' => 'Dashboard',
        'description' => 'View key metrics and recent activities.',
    ],
    'cases' => [
        'eyebrow' => 'Guidance Records',
        'title' => 'Guidance Cases',
        'description' => 'Filter, search, and manage all guidance cases.',
    ],
    'new_case' => [
        'eyebrow' => 'Guidance Records',
        'title' => 'Create New Case',
        'description' => 'Start a new guidance case for a learner.',
    ],
    'case_detail' => [
        'eyebrow' => 'Guidance Records',
        'title' => 'Update Guidance Case',
        'description' => 'Manage details, sessions, and interventions for a case.',
    ],
    'reports' => [
        'eyebrow' => 'Guidance Reports',
        'title' => 'Reporting',
        'description' => 'Generate and print case summary reports.',
    ],
    'settings' => [
        'eyebrow' => 'Portal Settings',
        'title' => 'Theme & Account',
        'description' => 'Customize the portal appearance and manage your account security.',
    ],
];

$module = trim((string) ($_GET['module'] ?? 'dashboard'));
if (!isset($allowedModules[$module])) {
    $module = 'dashboard';
}

$caseId = isset($_GET['case_id']) ? (int) $_GET['case_id'] : 0;
$editingCase = null;
if ($caseId > 0) {
    $editingCase = guidance_case_by_id($caseId);
    if ($editingCase !== null) {
        $module = 'case_detail';
    }
}

$filters = [
    'keyword' => trim((string) ($_GET['keyword'] ?? '')),
    'case_status' => trim((string) ($_GET['case_status'] ?? '')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
];

if (is_post()) {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Invalid form token. Please refresh the page.');
        }

        if (isset($_POST['save_case'])) {
            $caseId = guidance_save_case($_POST, (int) $user['id']);
            flash_set('guidance_portal', 'Guidance case saved successfully.');
            redirect('guidance.php?module=case_detail&case_id=' . urlencode((string) $caseId));
        }

        if (isset($_POST['save_session']) && $caseId > 0) {
            guidance_save_counseling_session($caseId, $_POST, (int) $user['id']);
            flash_set('guidance_portal', 'Counseling session recorded.');
            redirect('guidance.php?module=case_detail&case_id=' . urlencode((string) $caseId));
        }

        if (isset($_POST['save_referral']) && $caseId > 0) {
            guidance_save_referral($caseId, $_POST, (int) $user['id']);
            flash_set('guidance_portal', 'Referral recorded.');
            redirect('guidance.php?module=case_detail&case_id=' . urlencode((string) $caseId));
        }

        if (isset($_POST['save_intervention']) && $caseId > 0) {
            guidance_save_intervention($caseId, $_POST, (int) $user['id']);
            flash_set('guidance_portal', 'Intervention plan recorded.');
            redirect('guidance.php?module=case_detail&case_id=' . urlencode((string) $caseId));
        }
    } catch (Throwable $exception) {
        $flash = [
            'type' => 'error',
            'message' => $exception->getMessage(),
        ];
    }
}

$stats = guidance_dashboard_stats();
$upcomingFollowups = guidance_upcoming_followups();
$cases = in_array($module, ['cases', 'reports']) ? guidance_case_rows($filters) : guidance_case_rows();
$learnerOptions = guidance_learner_options();
$caseSessions = [];
$caseReferrals = [];
$caseInterventions = [];
if ($editingCase !== null) {
    $caseSessions = guidance_session_rows((int) $editingCase['id']);
    $caseReferrals = guidance_referral_rows((int) $editingCase['id']);
    $caseInterventions = guidance_intervention_rows((int) $editingCase['id']);
}

$pageMeta = $allowedModules[$module];
if ($module === 'case_detail' && $editingCase === null) {
    $flash = [
        'type' => 'error',
        'message' => 'The requested guidance case could not be found.',
    ];
    $pageMeta = $allowedModules['cases'];
    $module = 'cases';
    $cases = guidance_case_rows($filters);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape(APP_NAME); ?> Guidance Counselor Portal</title>
    <?php echo theme_stylesheet_markup(); ?>
    <link rel="stylesheet" href="<?php echo escape(asset_url('assets/css/app.css')); ?>">
    <style>
        .sidebar-theme-form {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            margin-bottom: 1rem;
        }
        .sidebar-theme-form label {
            color: var(--text-muted, #6c757d);
            font-size: 0.9rem;
        }
        .sidebar-theme-form select {
            border-radius: 4px;
        }
    </style>
</head>
<body class="dashboard-body admin-dashboard">
    <button
        id="sidebar-toggle"
        class="sidebar-toggle-button"
        type="button"
        data-sidebar-label="guidance menu"
        aria-label="Open guidance menu"
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
                    <p class="eyebrow">Guidance Counselor</p>
                    <h1>Guidance Portal</h1>
                    <p class="sidebar-user"><?php echo escape($user['first_name'] . ' ' . $user['last_name']); ?></p>
                    <p class="sidebar-email"><?php echo escape($user['email']); ?></p>
                </div>
                <nav class="sidebar-nav" aria-label="Guidance Navigation">
                    <div class="menu-group">
                        <p class="menu-group-title">Overview</p>
                        <a href="<?php echo escape(route_url('guidance.php?module=dashboard')); ?>" class="submenu-link<?php echo $module === 'dashboard' ? ' active' : ''; ?>">Dashboard</a>
                    </div>
                    <div class="menu-group">
                        <p class="menu-group-title">Cases</p>
                        <a href="<?php echo escape(route_url('guidance.php?module=cases')); ?>" class="submenu-link<?php echo in_array($module, ['cases', 'case_detail']) ? ' active' : ''; ?>">All Cases</a>
                        <a href="<?php echo escape(route_url('guidance.php?module=new_case')); ?>" class="submenu-link<?php echo $module === 'new_case' ? ' active' : ''; ?>">New Case</a>
                    </div>
                    <div class="menu-group">
                        <p class="menu-group-title">Reporting</p>
                        <a href="<?php echo escape(route_url('guidance.php?module=reports')); ?>" class="submenu-link<?php echo $module === 'reports' ? ' active' : ''; ?>">Reports</a>
                    </div>
                </nav>
                <div class="sidebar-footer">
                    <a href="<?php echo escape(route_url('guidance.php?module=settings')); ?>" class="secondary-link full-width-link">Settings</a>
                    <a href="<?php echo escape(route_url('logout.php')); ?>" class="secondary-link full-width-link">Sign Out</a>
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
                </header>
            <?php if ($flash !== null): ?>
                <div class="alert <?php echo escape($flash['type']); ?>"><?php echo escape($flash['message']); ?></div>
            <?php else: ?>
                <article class="teacher-panel-card">
                    <div class="panel-heading">
                        <h2>Guidance Portal</h2>
                    </div>
                    <p>Use the navigation to open the dashboard, review guidance cases, or create a new case.</p>
                </article>
            <?php endif; ?>

            <?php if ($module === 'dashboard'): ?>
                <section class="teacher-summary-grid">
                        <article class="summary-card">
                            <p class="eyebrow">Active Cases</p>
                            <h3><?php echo escape((string) $stats['active_cases']); ?></h3>
                        </article>
                        <article class="summary-card">
                            <p class="eyebrow">Pending Referrals</p>
                            <h3><?php echo escape((string) $stats['pending_referrals']); ?></h3>
                        </article>
                        <article class="summary-card">
                            <p class="eyebrow">Completed Interventions</p>
                            <h3><?php echo escape((string) $stats['completed_interventions']); ?></h3>
                        </article>
                        <article class="summary-card">
                            <p class="eyebrow">Scheduled Sessions</p>
                            <h3><?php echo escape((string) $stats['scheduled_sessions']); ?></h3>
                        </article>
                        <article class="summary-card">
                            <p class="eyebrow">Follow-Up Cases</p>
                            <h3><?php echo escape((string) $stats['follow_up_cases']); ?></h3>
                        </article>
                </section>

                <section class="teacher-overview-grid">
                    <article class="teacher-panel-card">
                        <div class="panel-heading compact-heading">
                            <h2>Upcoming Follow-ups</h2>
                            <p>Scheduled follow-up dates for active cases.</p>
                        </div>

                        <div class="table-shell">
                            <table class="records-table">
                                <thead>
                                    <tr>
                                        <th>Follow-up Date</th>
                                        <th>Case #</th>
                                        <th>Learner</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($upcomingFollowups === []): ?>
                                        <tr>
                                            <td colspan="4" class="empty-row">No upcoming follow-ups are scheduled.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($upcomingFollowups as $followup): ?>
                                            <tr>
                                                <td><?php echo escape((string) ($followup['follow_up_schedule'] ?? '')); ?></td>
                                                <td><?php echo escape((string) ($followup['case_number'] ?? '')); ?></td>
                                                <td><?php echo escape(guidance_full_name($followup)); ?></td>
                                                <td>
                                                    <a href="<?php echo escape(route_url('guidance.php?module=case_detail&case_id=' . urlencode((string) $followup['id']))); ?>" class="table-inline-link">Open Case</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </article>

                    <article class="teacher-panel-card">
                        <div class="panel-heading compact-heading">
                            <h2>Recent Guidance Cases</h2>
                            <p>The 5 most recently opened cases.</p>
                        </div>

                    <div class="table-shell">
                            <table class="records-table">
                            <thead>
                                <tr>
                                    <th>Case #</th>
                                    <th>Learner</th>
                                    <th>Status</th>
                                    <th>Open Date</th>
                                    <th>Follow-up</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($cases)): ?>
                                    <tr>
                                        <td colspan="6" class="empty-row">No recent guidance cases found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach (array_slice($cases, 0, 5) as $case): ?>
                                        <tr>
                                            <td><?php echo escape((string) ($case['case_number'] ?? '')); ?></td>
                                            <td><?php echo escape(guidance_full_name($case)); ?></td>
                                            <td><?php echo escape((string) ($case['case_status'] ?? 'Open')); ?></td>
                                            <td><?php echo escape((string) ($case['date_opened'] ?? '')); ?></td>
                                            <td><?php echo escape((string) ($case['follow_up_schedule'] ?? '-')); ?></td>
                                            <td>
                                                <a href="<?php echo escape(route_url('guidance.php?module=case_detail&case_id=' . urlencode((string) $case['id']))); ?>" class="table-inline-link">Open Case</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    </article>
                </section>
            <?php elseif ($module === 'new_case' || $module === 'case_detail'): ?>
                <article class="teacher-panel-card">
                    <div class="panel-heading">
                        <h2><?php echo $editingCase !== null ? 'Update Guidance Case' : 'Create Guidance Case'; ?></h2>
                        <p>Fields for managing a learner guidance case file.</p>
                    </div>
                    <form method="post" class="teacher-form-grid">
                        <input type="hidden" name="csrf_token" value="<?php echo escape(csrf_token()); ?>">
                        <input type="hidden" name="save_case" value="1">
                        <?php if ($editingCase !== null): ?>
                            <input type="hidden" name="id" value="<?php echo escape((string) $editingCase['id']); ?>">
                        <?php endif; ?>

                            <div class="teacher-form-grid-full">
                                <label for="learner_id">Learner</label>
                                <select name="learner_id" required>
                                    <option value="">Select learner</option>
                                    <?php foreach ($learnerOptions as $learner): ?>
                                        <option value="<?php echo escape((string) $learner['id']); ?>" <?php echo ($editingCase !== null && (int) $editingCase['learner_id'] === (int) $learner['id']) ? 'selected' : ''; ?>><?php echo escape(guidance_full_name($learner)); ?> (<?php echo escape((string) ($learner['learner_number'] ?? '')); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if ($editingCase !== null): ?>
                                <div class="teacher-readonly-field">
                                    <span class="meta-label">Grade and Section</span>
                                    <?php echo escape(($editingCase['grade_level'] ?? 'N/A') . ' - ' . ($editingCase['section_name'] ?? 'N/A')); ?>
                                </div>
                            <?php endif; ?>

                            <div>
                                <label for="case_number">Case Number</label>
                                <input type="text" name="case_number" value="<?php echo escape((string) ($editingCase['case_number'] ?? '')); ?>" required>
                            </div>
                            <div>
                                <label for="date_opened">Date Opened</label>
                                <input type="date" name="date_opened" value="<?php echo escape((string) ($editingCase['date_opened'] ?? date('Y-m-d'))); ?>" required>
                            </div>
                            <div>
                                <label for="case_status">Case Status</label>
                                <select name="case_status">
                                    <?php foreach (guidance_case_status_options() as $value => $label): ?>
                                        <option value="<?php echo escape($value); ?>" <?php echo (($editingCase['case_status'] ?? 'Open') === $value) ? 'selected' : ''; ?>><?php echo escape($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="referral_source">Referral Source</label>
                                <input type="text" name="referral_source" value="<?php echo escape((string) ($editingCase['referral_source'] ?? '')); ?>">
                            </div>
                            <div>
                                <label for="counseling_type">Counseling Type</label>
                                <select name="counseling_type">
                                    <option value="">Select</option>
                                    <?php foreach (guidance_counseling_type_options() as $value => $label): ?>
                                        <option value="<?php echo escape($value); ?>" <?php echo (($editingCase['counseling_type'] ?? '') === $value) ? 'selected' : ''; ?>><?php echo escape($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="counseling_date">Counseling Date</label>
                                <input type="date" name="counseling_date" value="<?php echo escape((string) ($editingCase['counseling_date'] ?? '')); ?>">
                            </div>
                            <div>
                                <label for="follow_up_schedule">Follow-up Schedule</label>
                                <input type="date" name="follow_up_schedule" value="<?php echo escape((string) ($editingCase['follow_up_schedule'] ?? '')); ?>">
                            </div>
                            <div>
                                <label for="parent_conference">Parent Conference</label>
                                <input type="text" name="parent_conference" value="<?php echo escape((string) ($editingCase['parent_conference'] ?? '')); ?>">
                            </div>
                            <div>
                                <label for="date_closed">Date Closed</label>
                                <input type="date" name="date_closed" value="<?php echo escape((string) ($editingCase['date_closed'] ?? '')); ?>">
                            </div>

                        <div class="teacher-form-grid-full">
                            <label for="case_referral_reason">Initial Referral Reason</label>
                            <textarea id="case_referral_reason" name="referral_reason" rows="3"><?php echo escape((string) ($editingCase['referral_reason'] ?? '')); ?></textarea>
                        </div>
                        <div class="teacher-form-grid-full">
                            <label for="case_presenting_concern">Presenting Concern</label>
                            <textarea id="case_presenting_concern" name="presenting_concern" rows="3"><?php echo escape((string) ($editingCase['presenting_concern'] ?? '')); ?></textarea>
                        </div>
                        <div class="teacher-form-grid-full">
                            <label for="case_intervention_plan">Overall Intervention Plan</label>
                            <textarea id="case_intervention_plan" name="intervention_plan" rows="3"><?php echo escape((string) ($editingCase['intervention_plan'] ?? '')); ?></textarea>
                        </div>
                        <div class="teacher-form-grid-full">
                            <label for="case_remarks">Remarks</label>
                            <textarea id="case_remarks" name="remarks" rows="3"><?php echo escape((string) ($editingCase['remarks'] ?? '')); ?></textarea>
                        </div>

                        <div class="learner-form-actions teacher-form-grid-full">
                            <button type="submit" class="primary-button">Save Case</button>
                            <a href="<?php echo escape(route_url('guidance.php?module=dashboard')); ?>" class="secondary-link">Cancel</a>
                        </div>
                    </form>
                </article>

                <?php if ($editingCase !== null): ?>
                    <article class="teacher-panel-card">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">Record</p>
                                <h2>Add Counseling Session</h2>
                            </div>
                        </div>
                        <form method="post" class="report-filter-grid">
                            <input type="hidden" name="csrf_token" value="<?php echo escape(csrf_token()); ?>">
                            <input type="hidden" name="save_session" value="1">
                            <input type="hidden" name="case_id" value="<?php echo escape((string) $editingCase['id']); ?>">
                            <div class="report-filter-field">
                                <label for="session_date">Session Date</label>
                                    <input type="date" name="session_date" required>
                            </div>
                            <div class="report-filter-field">
                                <label for="session_type">Session Type</label>
                                    <input type="text" name="session_type">
                            </div>
                            <div class="report-filter-field">
                                <label for="follow_up_required">
                                    Follow-up Required
                                    <input type="checkbox" name="follow_up_required" value="1">
                                </label>
                            </div>
                            <div class="report-filter-field report-filter-field-wide">
                                <label for="session_notes">Notes</label>
                                <textarea id="session_notes" name="notes" rows="3"></textarea>
                            </div>
                            <div class="report-actions">
                                <button type="submit" class="primary-button">Record Session</button>
                            </div>
                        </form>
                    </article>

                    <section class="teacher-overview-grid">
                    <article class="teacher-panel-card">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">Record</p>
                                <h2>Add Referral</h2>
                            </div>
                        </div>
                        <form method="post" class="teacher-form-grid">
                            <input type="hidden" name="csrf_token" value="<?php echo escape(csrf_token()); ?>">
                            <input type="hidden" name="save_referral" value="1">
                            <input type="hidden" name="case_id" value="<?php echo escape((string) $editingCase['id']); ?>">
                                <div>
                                <label for="source_role">Source Role</label>
                                    <input type="text" name="source_role">
                                </div>
                                <div>
                                <label for="status">Status</label>
                                    <select name="status">
                                        <?php foreach (guidance_referral_status_options() as $value => $label): ?>
                                            <option value="<?php echo escape($value); ?>"><?php echo escape($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                <label for="referred_on">Referred On</label>
                                    <input type="date" name="referred_on" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            <div class="teacher-form-grid-full">
                                <label for="referral_form_reason">Reason for this Referral</label>
                                <textarea id="referral_form_reason" name="referral_reason" rows="3"></textarea>
                            </div>
                            <div class="teacher-form-grid-full">
                                <label for="referral_action_taken">Action Taken</label>
                                <textarea id="referral_action_taken" name="action_taken" rows="3"></textarea>
                            </div>
                            <div class="teacher-form-grid-full">
                                <label for="referral_outcome">Outcome / Recommendation</label>
                                <textarea id="referral_outcome" name="outcome_recommendation" rows="3"></textarea>
                            </div>
                            <div class="learner-form-actions teacher-form-grid-full">
                                <button type="submit" class="primary-button">Save Referral</button>
                            </div>
                        </form>
                    </article>

                    <article class="teacher-panel-card">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">Record</p>
                                <h2>Add Intervention Plan</h2>
                            </div>
                        </div>
                        <form method="post" class="teacher-form-grid">
                            <input type="hidden" name="csrf_token" value="<?php echo escape(csrf_token()); ?>">
                            <input type="hidden" name="save_intervention" value="1">
                            <input type="hidden" name="case_id" value="<?php echo escape((string) $editingCase['id']); ?>">
                                <div>
                                <label for="intervention_title">Intervention Title</label>
                                    <input type="text" name="intervention_title" required>
                                </div>
                                <div>
                                <label for="intervention_type">Intervention Type</label>
                                    <input type="text" name="intervention_type">
                                </div>
                                <div>
                                <label for="scheduled_on">Scheduled On</label>
                                    <input type="date" name="scheduled_on">
                                </div>
                                <div>
                                <label for="status">Status</label>
                                    <select name="status">
                                        <?php foreach (guidance_intervention_status_options() as $value => $label): ?>
                                            <option value="<?php echo escape($value); ?>"><?php echo escape($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                <label for="completion_date">Completion Date</label>
                                    <input type="date" name="completion_date">
                                </div>
                            <div class="teacher-form-grid-full">
                                <label for="intervention_notes">Notes</label>
                                <textarea id="intervention_notes" name="notes" rows="3"></textarea>
                            </div>
                            <div class="learner-form-actions teacher-form-grid-full">
                                <button type="submit" class="primary-button">Save Intervention</button>
                            </div>
                        </form>
                    </article>
                    </section>

                    <article class="teacher-panel-card">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">Case Detail</p>
                                <h2>Existing Records</h2>
                            </div>
                        </div>
                        <div class="table-shell">
                            <h3>Counseling Sessions</h3>
                            <table class="records-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Follow-up</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($caseSessions === []): ?>
                                        <tr>
                                            <td colspan="4" class="empty-row">No counseling sessions recorded for this case.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($caseSessions as $session): ?>
                                        <tr>
                                            <td><?php echo escape((string) ($session['session_date'] ?? '')); ?></td>
                                            <td><?php echo escape((string) ($session['session_type'] ?? '')); ?></td>
                                            <td><?php echo !empty($session['follow_up_required']) ? 'Yes' : 'No'; ?></td>
                                            <td><?php echo escape((string) ($session['notes'] ?? '')); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-shell">
                            <h3>Referrals</h3>
                            <table class="records-table">
                                <thead>
                                    <tr>
                                        <th>Referred On</th>
                                        <th>Source</th>
                                        <th>Status</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($caseReferrals === []): ?>
                                        <tr>
                                            <td colspan="4" class="empty-row">No referrals recorded for this case.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($caseReferrals as $referral): ?>
                                        <tr>
                                            <td><?php echo escape((string) ($referral['referred_on'] ?? '')); ?></td>
                                            <td><?php echo escape((string) ($referral['source_role'] ?? '')); ?></td>
                                            <td><?php echo escape((string) ($referral['status'] ?? 'Pending')); ?></td>
                                            <td><?php echo escape((string) ($referral['referral_reason'] ?? '')); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-shell">
                            <h3>Interventions</h3>
                            <table class="records-table">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Type</th>
                                        <th>Scheduled</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($caseInterventions === []): ?>
                                        <tr>
                                            <td colspan="4" class="empty-row">No interventions recorded for this case.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($caseInterventions as $intervention): ?>
                                        <tr>
                                            <td><?php echo escape((string) ($intervention['intervention_title'] ?? '')); ?></td>
                                            <td><?php echo escape((string) ($intervention['intervention_type'] ?? '')); ?></td>
                                            <td><?php echo escape((string) ($intervention['scheduled_on'] ?? '')); ?></td>
                                            <td><?php echo escape((string) ($intervention['status'] ?? 'Planned')); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </article>
                <?php endif; ?>
            <?php elseif ($module === 'cases'): ?>
                <article class="teacher-panel-card">
                    <div class="panel-heading">
                        <h2>Filter Cases</h2>
                        <p>Search by keyword, status, or date range.</p>
                    </div>
                    <form method="get" class="report-filter-grid">
                        <input type="hidden" name="module" value="cases">
                        <div class="report-filter-field">
                            <label for="keyword">Keyword</label>
                            <input type="text" name="keyword" id="keyword" value="<?php echo escape($filters['keyword']); ?>" placeholder="Case #, Learner Name">
                        </div>
                        <div class="report-filter-field">
                            <label for="case_status">Case Status</label>
                            <select name="case_status" id="case_status">
                                <option value="">All Statuses</option>
                                <?php foreach (guidance_case_status_options() as $value => $label): ?>
                                    <option value="<?php echo escape($value); ?>" <?php echo ($filters['case_status'] === $value) ? 'selected' : ''; ?>><?php echo escape($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="report-filter-field">
                            <label for="date_from">Date From</label>
                            <input type="date" name="date_from" id="date_from" value="<?php echo escape($filters['date_from']); ?>">
                        </div>
                        <div class="report-filter-field">
                            <label for="date_to">Date To</label>
                            <input type="date" name="date_to" id="date_to" value="<?php echo escape($filters['date_to']); ?>">
                        </div>
                        <div class="report-actions">
                            <button type="submit" class="primary-button">Filter Cases</button>
                            <a href="<?php echo escape(route_url('guidance.php?module=cases')); ?>" class="secondary-link">Reset</a>
                        </div>
                    </form>
                </article>
                <article class="teacher-panel-card">
                    <div class="table-shell">
                        <table class="records-table">
                            <thead>
                                <tr>
                                    <th>Case #</th>
                                    <th>Learner</th>
                                    <th>Status</th>
                                    <th>Open Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($cases === []): ?>
                                    <tr>
                                        <td colspan="5" class="empty-row">No guidance cases match the current filters.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($cases as $case): ?>
                                    <tr>
                                        <td><?php echo escape((string) ($case['case_number'] ?? '')); ?></td>
                                        <td><?php echo escape(guidance_full_name($case)); ?></td>
                                        <td><?php echo escape((string) ($case['case_status'] ?? 'Open')); ?></td>
                                        <td><?php echo escape((string) ($case['date_opened'] ?? '')); ?></td>
                                        <td>
                                            <a href="<?php echo escape(route_url('guidance.php?module=case_detail&case_id=' . urlencode((string) $case['id']))); ?>" class="table-inline-link">Open</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            <?php elseif ($module === 'reports'): ?>
                <article class="teacher-panel-card no-print">
                    <div class="panel-heading">
                        <h2>Report Filters</h2>
                        <p>Select filters to generate the Case Summary Report.</p>
                    </div>
                    <form method="get" class="report-filter-grid">
                        <input type="hidden" name="module" value="reports">
                        <div class="report-filter-field">
                            <label for="keyword">Keyword</label>
                            <input type="text" name="keyword" id="keyword" value="<?php echo escape($filters['keyword']); ?>" placeholder="Case #, Learner Name">
                        </div>
                        <div class="report-filter-field">
                            <label for="case_status">Case Status</label>
                            <select name="case_status" id="case_status">
                                <option value="">All Statuses</option>
                                <?php foreach (guidance_case_status_options() as $value => $label): ?>
                                    <option value="<?php echo escape($value); ?>" <?php echo ($filters['case_status'] === $value) ? 'selected' : ''; ?>><?php echo escape($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="report-filter-field">
                            <label for="date_from">Date From</label>
                            <input type="date" name="date_from" id="date_from" value="<?php echo escape($filters['date_from']); ?>">
                        </div>
                        <div class="report-filter-field">
                            <label for="date_to">Date To</label>
                            <input type="date" name="date_to" id="date_to" value="<?php echo escape($filters['date_to']); ?>">
                        </div>
                        <div class="report-actions">
                            <button type="submit" class="primary-button">Generate Report</button>
                            <button type="button" class="ghost-button" onclick="window.print()">Print Report</button>
                            <a href="<?php echo escape(route_url('guidance.php?module=reports')); ?>" class="secondary-link">Reset</a>
                        </div>
                    </form>
                </article>

                <article class="teacher-panel-card report-print-area">
                    <div class="report-print-header">
                        <div class="admin-page-title">
                            <img class="school-logo header-logo" src="<?php echo escape(school_logo_url()); ?>" alt="School logo">
                            <div class="header-copy">
                                <h2>Case Summary Report</h2>
                                <p>Generated on <?php echo date('F j, Y'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="table-shell">
                        <table class="records-table">
                            <thead>
                                <tr>
                                    <th>Case #</th>
                                    <th>Learner</th>
                                    <th>Status</th>
                                    <th>Open Date</th>
                                    <th>Counseling Type</th>
                                    <th>Follow-up</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($cases === []): ?>
                                    <tr>
                                        <td colspan="6" class="empty-row">No guidance cases match the report filters.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($cases as $case): ?>
                                    <tr>
                                        <td><?php echo escape((string) ($case['case_number'] ?? '')); ?></td>
                                        <td><?php echo escape(guidance_full_name($case)); ?></td>
                                        <td><?php echo escape((string) ($case['case_status'] ?? 'Open')); ?></td>
                                        <td><?php echo escape((string) ($case['date_opened'] ?? '')); ?></td>
                                        <td><?php echo escape((string) ($case['counseling_type'] ?? '-')); ?></td>
                                        <td><?php echo escape((string) ($case['follow_up_schedule'] ?? '-')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            <?php elseif ($module === 'settings'): ?>
                <section class="teacher-overview-grid">
                    <article class="teacher-panel-card">
                        <div class="panel-heading">
                            <h2>Theme Selection</h2>
                            <p>Choose a visual theme for the portal.</p>
                        </div>
                        <form method="post">
                            <input type="hidden" name="action" value="change_theme">
                            <div class="teacher-form-grid">
                                <div class="teacher-form-grid-full">
                                    <label for="theme-select">Theme</label>
                                    <select name="theme" id="theme-select" onchange="this.form.submit()" class="form-input">
                                        <?php
                                        $availableThemes = ['default', 'light', 'dark'];
                                        $currentTheme = $_SESSION['theme'] ?? 'default';
                                        foreach ($availableThemes as $theme) : ?>
                                            <option value="<?php echo escape($theme); ?>" <?php echo $theme === $currentTheme ? 'selected' : ''; ?>>
                                                <?php echo escape(ucfirst($theme)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </form>
                    </article>

                    <article class="teacher-panel-card">
                        <div class="panel-heading">
                            <h2>Change Password</h2>
                            <p>Update your account password.</p>
                        </div>
                        <form method="post" class="auth-form">
                            <input type="hidden" name="action" value="change_password">
                            <input type="hidden" name="csrf_token" value="<?php echo escape(csrf_token()); ?>">

                            <label for="current_password">Current Password</label>
                            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>

                            <label for="new_password">New Password</label>
                            <input id="new_password" name="new_password" type="password" autocomplete="new-password" minlength="6" required>

                            <label for="confirm_password">Confirm New Password</label>
                            <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="6" required>

                            <div class="password-form-actions">
                                <button type="submit" class="primary-button">Save Password</button>
                            </div>
                        </form>
                    </article>
                </section>
            <?php else: ?>
                <article class="teacher-panel-card">
                    <div class="panel-heading">
                        <h2>Guidance Portal</h2>
                    </div>
                    <p>Use the navigation to open the dashboard, review guidance cases, or create a new case.</p>
                </article>
            <?php endif; ?>
            </section>
        </section>
    </main>
    <script src="<?php echo escape(asset_url('assets/js/admin.js')); ?>"></script>
</body>
</html>
