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
$attendanceConfig = [
    'csrfToken' => csrf_token(),
    'lookupUrl' => route_url('api/learner_lookup.php'),
    'attendanceEventUrl' => route_url('api/attendance_event.php'),
    'attendanceLogsUrl' => route_url('api/attendance_logs.php'),
    'scanModeUpdateUrl' => route_url('api/attendance_mode.php'),
    'learnerPhotoBaseUrl' => route_url('learner_photo.php?lrn='),
    'defaultLearnerPhotoUrl' => asset_url('assets/images/learners/logorotate.gif'),
    'scanMode' => ['key' => $scanMode['key'], 'label' => $scanMode['label'], 'description' => $scanMode['description'], 'canEdit' => $canManageScanMode],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php echo theme_stylesheet_markup(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape(APP_NAME); ?> Attendance</title>
    <link rel="stylesheet" href="<?php echo escape(asset_url('assets/css/app.css')); ?>">
    <link rel="stylesheet" href="<?php echo escape(asset_url('assets/dist/tailwind.css')); ?>">
</head>
<body class="dashboard-body">
<main class="dashboard-shell fullscreen-shell">
    <header class="topbar">
        <div class="header-title-block"><img class="school-logo" src="<?php echo escape(school_logo_url()); ?>" alt="School logo"><div class="header-copy"><p class="eyebrow">Attendance Module</p><h1>Attendance Scanner</h1></div></div>
        <div class="topbar-actions"><p class="signed-in-as">Signed in as <?php echo escape($user['username']); ?></p><?php if (($user['role'] ?? '') === 'admin'): ?><a href="<?php echo escape(route_url('admin.php')); ?>" class="secondary-link">Admin Panel</a><?php endif; ?><a href="<?php echo escape(route_url('change_password.php')); ?>" class="secondary-link">Change Password</a><a href="<?php echo escape(route_url('logout.php')); ?>" class="secondary-link">Logout</a></div>
    </header>
    <div id="attendance-app" aria-live="polite"></div>
</main>
<script>window.ProjectPulse = <?php echo json_encode($attendanceConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR); ?>;</script>
<script type="module" src="<?php echo escape(asset_url('assets/dist/attendance.js')); ?>"></script>
</body>
</html>
