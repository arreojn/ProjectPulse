<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/attendance_settings.php';
require_once __DIR__ . '/app/theme_settings.php';

$user = require_roles(['attendance', 'admin']);
$scanMode = attendance_scan_mode_details();
$canManageScanMode = attendance_can_manage_scan_mode($user);
theme_settings_bootstrap();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php echo theme_stylesheet_markup(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape(APP_NAME); ?> Attendance</title>
    <link rel="stylesheet" href="<?php echo escape(asset_url('assets/css/app.css')); ?>">
</head>
<body class="dashboard-body">
    <main class="dashboard-shell fullscreen-shell">
        <header class="topbar">
            <div class="header-title-block">
                <img class="school-logo" src="<?php echo escape(school_logo_url()); ?>" alt="School logo">
                <div class="header-copy">
                    <p class="eyebrow">Attendance Module</p>
                    <h1>Attendance Scanner</h1>
                </div>
            </div>

            <div class="topbar-actions">
                <p class="signed-in-as">Signed in as <?php echo escape($user['username']); ?></p>
                <?php if (($user['role'] ?? '') === 'admin'): ?>
                    <a href="<?php echo escape(route_url('admin.php')); ?>" class="secondary-link">Admin Panel</a>
                <?php endif; ?>
                <a href="<?php echo escape(route_url('change_password.php')); ?>" class="secondary-link">Change Password</a>
                <a href="<?php echo escape(route_url('logout.php')); ?>" class="secondary-link">Logout</a>
            </div>
        </header>

        <section class="dashboard-grid expanded">
            <article class="status-panel compact">
                <div class="picture-box">
                    <img
                        id="learner-photo"
                        class="learner-photo"
                        src="<?php echo escape(asset_url('assets/images/learners/logorotate.gif')); ?>"
                        alt="Learner photo"
                    >
                </div>

                <div class="clock-panel">
                    <p class="meta-label">Current Time</p>
                    <p id="live-time" class="clock-value"><?php echo escape(date('h:i:s A')); ?></p>
                    <p id="live-date" class="date-value"><?php echo escape(date('l, F j, Y')); ?></p>
                </div>

                    <div class="legend-panel">
                        <p class="meta-label dark">Attendance Legend</p>
                        <p class="scan-mode-note">Present requires all four scans. A complete AM or PM session counts as 0.5 day; arrivals after 7:30 AM or 1:00 PM are late.</p>
                        <div class="legend-list">
                        <span class="legend-chip success">P Present</span>
                        <span class="legend-chip warning">L Late</span>
                        <span class="legend-chip danger">A Absent</span>
                        <span class="legend-chip info">E Excused</span>
                    </div>
                </div>
            </article>

            <article class="scanner-panel expanded">
                <section class="scan-head">
                    <div class="panel-heading no-gap">
                        <h2>Scan LRN</h2>
                        <p>Keep focus here while learner details stay visible.</p>
                    </div>

                    <div class="search-wrap inline-search">
                        <label for="lrn-search">LRN</label>
                        <input
                            id="lrn-search"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            placeholder="Enter or scan learner LRN"
                            maxlength="12"
                            autofocus
                        >
                    </div>
                </section>

                <section class="scan-mode-panel">
                    <div class="scan-mode-copy">
                        <p class="meta-label dark">Attendance Scan Mode</p>
                        <div class="scan-mode-summary">
                            <strong id="scan-mode-value"><?php echo escape($scanMode['label']); ?></strong>
                            <p id="scan-mode-description"><?php echo escape($scanMode['description']); ?></p>
                        </div>
                        <p class="scan-mode-note">
                            <?php if ($canManageScanMode): ?>
                                Admin control: switch modes for all attendance stations.
                            <?php else: ?>
                                Admin-controlled setting. Attendance users can view the current mode only.
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="scan-mode-toggle-wrap">
                        <label class="mode-switch<?php echo $canManageScanMode ? '' : ' is-readonly'; ?>" for="scan-mode-toggle">
                            <input
                                id="scan-mode-toggle"
                                class="mode-switch-input"
                                type="checkbox"
                                <?php echo $scanMode['key'] === 'am_pm_sequence' ? 'checked' : ''; ?>
                                <?php echo $canManageScanMode ? '' : 'disabled'; ?>
                            >
                            <span class="mode-switch-track">
                                <span class="mode-switch-thumb"></span>
                            </span>
                            <span class="mode-switch-labels">
                                <span>Strict</span>
                                <span>AM/PM</span>
                            </span>
                        </label>
                        <p id="scan-mode-feedback" class="scan-mode-feedback"></p>
                    </div>
                </section>

                <section id="learner-card" class="learner-card full-panel is-empty">
                    <div class="learner-header-row">
                        <div class="learner-summary">
                            <h3 id="learner-name">No learner selected</h3>
                            <p id="learner-lrn">Search by LRN to load learner information.</p>
                        </div>
                    </div>

                    <dl class="detail-grid wide">
                        <div>
                            <dt>Grade Level</dt>
                            <dd id="learner-grade">-</dd>
                        </div>
                        <div>
                            <dt>Section</dt>
                            <dd id="learner-section">-</dd>
                        </div>
                        <div>
                            <dt>School Year</dt>
                            <dd id="learner-school-year">-</dd>
                        </div>
                        <div>
                            <dt>Today's Status</dt>
                            <dd id="learner-status">No record yet</dd>
                        </div>
                        <div>
                            <dt>AM Time In</dt>
                            <dd id="learner-am-time-in">-</dd>
                        </div>
                        <div>
                            <dt>AM Time Out</dt>
                            <dd id="learner-am-time-out">-</dd>
                        </div>
                        <div>
                            <dt>PM Time In</dt>
                            <dd id="learner-pm-time-in">-</dd>
                        </div>
                        <div>
                            <dt>PM Time Out</dt>
                            <dd id="learner-pm-time-out">-</dd>
                        </div>
                        
                    </dl>

                    <div class="record-grid">
                        <section class="record-panel">
                            <div class="panel-heading compact-heading">
                                <h2>Recent Attendance</h2>
                                <p>The latest records remain visible after every scan.</p>
                            </div>

                            <div class="table-shell attendance-log-shell">
                                <table class="records-table attendance-log-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Learner</th>
                                            <th>LRN</th>
                                            <th>Grade / Section</th>
                                            <th>Log Entry</th>
                                        </tr>
                                    </thead>
                                    <tbody id="attendance-logs-body">
                                        <tr>
                                            <td colspan="6" class="empty-row">No attendance records to display yet.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </section>
            </article>
        </section>
    </main>

    <script>
        window.ProjectPulse = {
            csrfToken: '<?php echo escape(csrf_token()); ?>',
            lookupUrl: '<?php echo escape(route_url('api/learner_lookup.php')); ?>',
            attendanceEventUrl: '<?php echo escape(route_url('api/attendance_event.php')); ?>',
            attendanceLogsUrl: '<?php echo escape(route_url('api/attendance_logs.php')); ?>',
            scanModeUpdateUrl: '<?php echo escape(route_url('api/attendance_mode.php')); ?>',
            learnerPhotoBaseUrl: '<?php echo escape(asset_url('assets/images/learners/')); ?>',
            defaultLearnerPhotoUrl: '<?php echo escape(asset_url('assets/images/learners/logorotate.gif')); ?>',
            scanMode: {
                key: '<?php echo escape($scanMode['key']); ?>',
                label: '<?php echo escape($scanMode['label']); ?>',
                description: '<?php echo escape($scanMode['description']); ?>',
                canEdit: <?php echo $canManageScanMode ? 'true' : 'false'; ?>
            }
        };
    </script>
    <script src="<?php echo escape(asset_url('assets/js/attendance.js')); ?>"></script>
</body>
</html>
