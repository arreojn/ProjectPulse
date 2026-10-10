<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/learners.php';
require_once __DIR__ . '/app/teachers.php';
require_once __DIR__ . '/app/theme_settings.php';
$user = require_roles(['admin', 'teacher']);
theme_settings_bootstrap();
$learners = [];
if ($user['role'] === 'admin') $learners = learner_list([]);
else { teacher_management_bootstrap(); $learners = teacher_section_learners((int) $user['id']); }
$enrollmentConfig = [
    'csrfToken' => csrf_token(),
    'enrollmentUrl' => route_url('face_enrollment_event.php'),
    'trainUrl' => route_url('train_model.php'),
    'homeUrl' => route_url($user['role'] === 'admin' ? 'admin.php' : 'teacher.php'),
    'learners' => array_map(static fn (array $learner): array => ['lrn' => (string) $learner['lrn'], 'name' => (string) ($learner['learner_name'] ?? trim(($learner['last_name'] ?? '') . ', ' . ($learner['first_name'] ?? '')))], $learners),
    'canTrain' => $user['role'] === 'admin',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php echo theme_stylesheet_markup(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape(APP_NAME); ?> - Face Enrollment</title>
    <link rel="stylesheet" href="<?php echo escape(asset_url('assets/css/app.css')); ?>">
    <style>
        #video-feed, #photo-preview { width: 100%; height: auto; border-radius: 18px; }
        #video-feed { transform: scaleX(-1); background: #000; }
        #photo-preview { display: none; }
        .enrollment-grid { display: grid; grid-template-columns: <?php echo $user['role'] === 'admin' ? 'minmax(0, 1fr) minmax(340px, 0.7fr)' : '1fr'; ?>; gap: 14px; }
        @media (max-width: 920px) { .enrollment-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body class="dashboard-body admin-dashboard">
<main class="dashboard-shell admin-shell wide-admin-shell">
    <header class="admin-page-header"><div class="admin-page-title"><img class="school-logo header-logo" src="<?php echo escape(school_logo_url()); ?>" alt="School logo"><div class="header-copy"><p class="eyebrow">Attendance Module</p><h2>Face Enrollment</h2><p>Capture learner photos and train the face recognition model.</p></div></div><div class="topbar-actions"><a href="<?php echo escape(route_url($user['role'] === 'admin' ? 'admin.php' : 'teacher.php')); ?>" class="secondary-link"><?php echo $user['role'] === 'admin' ? 'Admin Panel' : 'Teacher Portal'; ?></a><a href="<?php echo escape(route_url('logout.php')); ?>" class="secondary-link">Logout</a></div></header>
    <section class="enrollment-grid"><article class="admin-module-card"><div class="panel-heading"><h2>Enrollment Camera</h2><p>Select a learner, then capture their photo.</p></div><div class="report-filter-grid" style="align-items: center;"><div class="report-filter-field report-filter-field-wide"><label for="learner_id">Select Learner</label><select id="learner_id"><option value=""><?php echo $learners === [] ? 'No learners available in your section.' : '-- Select a learner --'; ?></option><?php foreach ($learners as $learner): ?><option value="<?php echo escape($learner['lrn']); ?>"><?php echo escape($learner['learner_name'] ?? trim(($learner['last_name'] ?? '') . ', ' . ($learner['first_name'] ?? ''))); ?> (<?php echo escape($learner['lrn']); ?>)</option><?php endforeach; ?></select></div><div class="report-actions" style="padding-top: 20px;"><button id="capture-btn" class="primary-button" disabled>Capture Photo</button></div></div><div id="capture-feedback" class="alert neutral" style="display: none; margin-top: 12px;"></div><div style="margin-top: 12px;"><video id="video-feed" autoplay playsinline muted></video><canvas id="capture-canvas" style="display:none;"></canvas><img id="photo-preview" alt="Captured photo preview"></div></article><?php if ($user['role'] === 'admin'): ?><article class="admin-module-card"><div class="panel-heading"><h2>Train Model</h2><p>After capturing new photos, retrain the model.</p></div><button id="train-btn" class="primary-button">Train Face Recognition Model</button><div id="train-feedback" class="alert neutral" style="margin-top: 12px; display: none;"></div><pre id="train-log" style="background: #f3f4f6; padding: 10px; border-radius: 8px; max-height: 400px; overflow-y: auto; display: none; margin-top: 12px;"></pre></article><?php endif; ?></section>
</main>
<script>window.ProjectPulse = <?php echo json_encode($enrollmentConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR); ?>;</script>
<script type="module" src="<?php echo escape(asset_url('assets/dist/faceEnrollment.js')); ?>"></script>
</body>
</html>
