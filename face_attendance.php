<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/attendance_settings.php';
require_once __DIR__ . '/app/theme_settings.php';
$user = require_roles(['attendance', 'admin']);
theme_settings_bootstrap();
$faceConfig = [
    'csrfToken' => csrf_token(),
    'faceApiUrl' => route_url('face_attendance_event.php'),
    'attendanceSummaryUrl' => route_url('api/face_attendance_summary.php'),
    'adminUrl' => route_url('admin.php'),
    'logoutUrl' => route_url('logout.php'),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php echo theme_stylesheet_markup(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape(APP_NAME); ?> Face Attendance</title>
    <link rel="stylesheet" href="<?php echo escape(asset_url('assets/css/app.css')); ?>">
    <style>
        #video-feed { width: 100%; max-width: 620px; aspect-ratio: 4 / 3; object-fit: cover; border-radius: 18px; transform: scaleX(-1); background: #000; }
        .face-attendance-layout { display: grid; grid-template-columns: minmax(390px, 0.85fr) minmax(0, 1.15fr); gap: 16px; align-items: start; }
        .today-attendance-panel { max-height: calc(100vh - 160px); overflow: hidden; }
        .today-attendance-panel .table-shell { max-height: calc(100vh - 270px); overflow: auto; }
        .camera-station { display: grid; gap: 14px; justify-items: center; }
        .camera-station .scan-head, .camera-station .scan-mode-panel, .camera-station #scan-feedback { width: min(100%, 620px); }
        .camera-station .scan-head { align-items: center; }
        .camera-station .clock-panel { min-width: 210px; padding: 12px 16px; }
        .camera-station .clock-value { font-size: 1.45rem; }
        .camera-station .scan-mode-panel { justify-content: center; padding: 10px; }
        .attendance-time-table th:not(:first-child), .attendance-time-table td:not(:first-child) { text-align: center; white-space: nowrap; }
        .learner-name-cell strong, .learner-name-cell small { display: block; }
        .learner-name-cell small { color: var(--muted); margin-top: 3px; }
        @media (max-width: 980px) { .face-attendance-layout { grid-template-columns: 1fr; } .today-attendance-panel, .today-attendance-panel .table-shell { max-height: none; } }
    </style>
</head>
<body class="dashboard-body">
<main class="dashboard-shell fullscreen-shell">
    <header class="topbar">
        <div class="header-title-block"><img class="school-logo" src="<?php echo escape(school_logo_url()); ?>" alt="School logo"><div class="header-copy"><p class="eyebrow">Attendance Module</p><h1>Face Recognition Scanner</h1></div></div>
        <div class="topbar-actions"><p class="signed-in-as">Signed in as <?php echo escape($user['username']); ?></p><?php if (($user['role'] ?? '') === 'admin'): ?><a href="<?php echo escape(route_url('admin.php')); ?>" class="secondary-link">Admin Panel</a><?php endif; ?><a href="<?php echo escape(route_url('logout.php')); ?>" class="secondary-link">Logout</a></div>
    </header>
    <section class="face-attendance-layout">
        <article class="admin-module-card today-attendance-panel"><div class="panel-heading compact-heading"><h2>Today’s Learners</h2><p>Live time-in and time-out records.</p></div><div class="table-shell"><table class="records-table attendance-time-table"><thead><tr><th>Learner</th><th>AM In</th><th>AM Out</th><th>PM In</th><th>PM Out</th></tr></thead><tbody id="today-attendance-body"><tr><td colspan="5" class="empty-row">Loading attendance records...</td></tr></tbody></table></div></article>
        <article class="admin-module-card camera-station"><section class="scan-head"><div class="panel-heading no-gap"><h2>Live Camera Feed</h2><p>Position face in the frame to log attendance automatically.</p></div><div class="clock-panel"><p class="meta-label">Current Time</p><p id="live-time" class="clock-value"><?php echo escape(date('h:i:s A')); ?></p><p id="live-date" class="date-value"><?php echo escape(date('l, F j, Y')); ?></p></div></section><section class="scan-mode-panel"><video id="video-feed" autoplay playsinline muted></video><canvas id="capture-canvas" style="display:none;"></canvas></section><div id="scan-feedback" class="alert neutral" style="margin: 0; text-align: center;">Starting camera...</div></article>
    </section>
</main>
<script>window.ProjectPulse = <?php echo json_encode($faceConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR); ?>;</script>
<script type="module" src="<?php echo escape(asset_url('assets/dist/faceAttendance.js')); ?>"></script>
</body>
</html>
