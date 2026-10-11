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
    <link rel="stylesheet" href="<?php echo escape(asset_url('assets/dist/tailwind.css')); ?>">
</head>
<body class="dashboard-body">
<main class="dashboard-shell fullscreen-shell">
    <header class="topbar">
        <div class="header-title-block"><img class="school-logo" src="<?php echo escape(school_logo_url()); ?>" alt="School logo"><div class="header-copy"><p class="eyebrow">Attendance Module</p><h1>Face Recognition Scanner</h1></div></div>
        <div class="topbar-actions"><p class="signed-in-as">Signed in as <?php echo escape($user['username']); ?></p><?php if (($user['role'] ?? '') === 'admin'): ?><a href="<?php echo escape(route_url('admin.php')); ?>" class="secondary-link">Admin Panel</a><?php endif; ?><a href="<?php echo escape(route_url('logout.php')); ?>" class="secondary-link">Logout</a></div>
    </header>
    <section class="grid grid-cols-[minmax(390px,0.85fr)_minmax(0,1.15fr)] items-start gap-4 max-[980px]:grid-cols-1">
        <article class="admin-module-card max-h-[calc(100vh_-_160px)] overflow-hidden max-[980px]:max-h-none">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                <div class="panel-heading compact-heading">
                    <h2>Today’s Learners</h2>
                    <p>Live time-in and time-out records.</p>
                </div>
                <div class="clock-panel min-w-[220px] flex-1 px-4 py-3 text-center sm:flex-none">
                    <p class="meta-label">Current Time</p>
                    <p id="live-time" class="clock-value text-[2rem]"><?php echo escape(date('h:i:s A')); ?></p>
                    <p id="live-date" class="date-value text-base"><?php echo escape(date('l, F j, Y')); ?></p>
                </div>
            </div>
            <div class="table-shell max-h-[calc(100vh_-_270px)] overflow-auto max-[980px]:max-h-none">
                <table class="records-table">
                    <thead><tr><th>Learner</th><th class="text-center whitespace-nowrap">AM In</th><th class="text-center whitespace-nowrap">AM Out</th><th class="text-center whitespace-nowrap">PM In</th><th class="text-center whitespace-nowrap">PM Out</th></tr></thead>
                    <tbody id="today-attendance-body"><tr><td colspan="5" class="empty-row">Loading attendance records...</td></tr></tbody>
                </table>
            </div>
        </article>
        <article class="admin-module-card grid min-w-0 justify-items-center gap-3.5">
            <section class="scan-mode-panel w-full min-w-0 justify-center p-2.5">
                <div class="relative aspect-[4/3] w-full overflow-hidden rounded-[18px] bg-black">
                    <video id="video-feed" class="absolute inset-0 h-full w-full object-cover" autoplay playsinline muted></video>
                    <canvas id="face-overlay" class="pointer-events-none absolute inset-0 h-full w-full" aria-hidden="true"></canvas>
                </div>
                <canvas id="capture-canvas" class="hidden"></canvas>
            </section>
            <div id="scan-feedback" class="alert neutral m-0 w-full max-w-full text-center">Starting camera...</div>
        </article>
    </section>
</main>
<script>window.ProjectPulse = <?php echo json_encode($faceConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR); ?>;</script>
<script type="module" src="<?php echo escape(asset_url('assets/dist/faceAttendance.js')); ?>"></script>
</body>
</html>
