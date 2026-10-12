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
    'deletePhotosUrl' => route_url('face_enrollment_delete.php'),
    'photoUrl' => route_url('learner_photo.php'),
    'trainUrl' => route_url('train_model.php'),
    'homeUrl' => route_url($user['role'] === 'admin' ? 'admin.php' : 'teacher.php'),
    'maxPhotos' => face_enrollment_max_photos(),
    'learners' => array_map(static fn (array $learner): array => [
        'lrn' => (string) $learner['lrn'],
        'name' => (string) ($learner['learner_name'] ?? trim(($learner['last_name'] ?? '') . ', ' . ($learner['first_name'] ?? ''))),
        'photoSlots' => learner_face_photo_slots((string) $learner['lrn']),
    ], $learners),
    'canTrain' => $user['role'] === 'admin',
];
$sidebarGroups = $user['role'] === 'teacher'
    ? [
        'Overview' => [
            ['Dashboard', route_url('teacher.php?module=dashboard')],
        ],
        'Parent Account' => [
            ['Create Parent Account', route_url('teacher.php?module=create_parent_account')],
            ['Link Parent Account', route_url('teacher.php?module=link_parent_account')],
        ],
        'Grades' => [
            ['Import Grades', route_url('teacher.php?module=grades_import')],
        ],
        'Attendance' => [
            ['Section Attendance', route_url('teacher.php?module=section_attendance')],
            ['Face Enrollment', route_url('face_enrollment.php')],
        ],
        'Learner Profile' => [
            ['Basic Profile', route_url('teacher.php?module=learner_profiles')],
        ],
        'Communication' => [
            ['Announcements', route_url('teacher.php?module=announcements')],
        ],
        'Account' => [
            ['Settings', route_url('teacher.php?module=settings')],
        ],
    ]
    : [
        'Attendance' => [
            ['Attendance Module', route_url('admin.php?module=attendance_module')],
            ['Learner Management', route_url('admin.php?module=learner_management')],
            ['Sections Management', route_url('admin.php?module=sections_management')],
            ['Teacher Management', route_url('admin.php?module=teacher_management')],
            ['Attendance Reports', route_url('admin.php?module=attendance_reports')],
            ['Face Enrollment', route_url('face_enrollment.php')],
        ],
        'System' => [
            ['Announcements', route_url('admin.php?module=announcements')],
            ['Reported Issues', route_url('admin.php?module=reported_issues')],
            ['Password Resets', route_url('admin.php?module=password_resets')],
            ['Settings', route_url('admin.php?module=settings')],
            ['Change Password', route_url('change_password.php')],
        ],
    ];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php echo theme_stylesheet_markup(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape(APP_NAME); ?> - Face Enrollment</title>
    <link rel="stylesheet" href="<?php echo escape(asset_url('assets/dist/tailwind.css')); ?>">
</head>
<body class="dashboard-body admin-dashboard">
<button
    id="sidebar-toggle"
    class="fixed left-4 top-4 z-[1003] inline-flex h-[52px] w-[52px] items-center justify-center rounded-[18px] border border-[var(--line)] bg-[var(--surface-strong)] p-0 shadow-[var(--shadow)] transition duration-[140ms] hover:-translate-y-px hover:bg-white focus-visible:outline focus-visible:outline-4 focus-visible:outline-[color-mix(in_srgb,var(--accent)_14%,transparent)]"
    type="button"
    data-sidebar-label="<?php echo escape($user['role'] === 'admin' ? 'admin menu' : 'teacher menu'); ?>"
    aria-label="Open <?php echo escape($user['role'] === 'admin' ? 'admin' : 'teacher'); ?> menu"
    aria-controls="admin-sidebar"
    aria-expanded="false"
>
    <span class="text-[28px] leading-none text-[var(--surface-dark)]" aria-hidden="true">☰</span>
</button>
<div id="sidebar-backdrop" class="pointer-events-none fixed inset-0 z-[1001] bg-[rgba(29,42,57,0.34)] opacity-0 backdrop-blur transition duration-[180ms] [body.sidebar-open_&]:pointer-events-auto [body.sidebar-open_&]:opacity-100" hidden></div>
<main class="dashboard-shell admin-shell wide-admin-shell">
    <section class="admin-layout">
        <aside id="admin-sidebar" class="admin-sidebar<?php echo $user['role'] === 'teacher' ? ' teacher-nav-sidebar' : ''; ?>">
            <div class="sidebar-profile">
                <div class="sidebar-brand">
                    <div class="sidebar-brand-logos">
                        <img class="sidebar-brand-logo pulse-logo" src="<?php echo escape(asset_url('assets/images/pulselogo.png')); ?>" alt="Project PULSE Logo">
                    </div>
                    <div class="sidebar-brand-text">
                        <span class="sidebar-brand-title">Project <span class="brand-pulse">PULSE</span></span>
                        <span class="sidebar-brand-subtitle">Monitoring Portal</span>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav" aria-label="<?php echo escape(ucfirst($user['role'])); ?> Navigation">
                <?php foreach ($sidebarGroups as $groupLabel => $items): ?>
                    <div class="menu-group">
                        <p class="menu-group-title"><?php echo escape($groupLabel); ?></p>
                        <?php foreach ($items as [$label, $url]): ?>
                            <a
                                href="<?php echo escape($url); ?>"
                                class="submenu-link<?php echo $label === 'Face Enrollment' ? ' active' : ''; ?>"
                                <?php echo $label === 'Face Enrollment' ? 'aria-current="page"' : ''; ?>
                            ><?php echo escape($label); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
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
                        <p class="eyebrow">Attendance Module</p>
                        <h2>Face Enrollment</h2>
                        <p>Capture learner photos and train the face recognition model.</p>
                    </div>
                </div>
                <div class="topbar-actions">
                    <a href="<?php echo escape(route_url($user['role'] === 'admin' ? 'admin.php' : 'teacher.php')); ?>" class="secondary-link"><?php echo $user['role'] === 'admin' ? 'Admin Panel' : 'Teacher Portal'; ?></a>
                </div>
            </header>

            <section class="grid grid-cols-1 items-start gap-3.5">
                <article class="admin-module-card">
                    <div class="face-enrollment-layout items-start">
                        <div class="grid min-w-0 justify-items-center gap-3">
                            <div class="aspect-square w-full max-w-[min(100%,88vh)] overflow-hidden rounded-[18px] bg-black">
                                <video id="video-feed" class="block h-full w-full scale-x-[-1] object-cover" autoplay playsinline muted></video>
                                <canvas id="capture-canvas" class="hidden"></canvas>
                            </div>
                            <div id="capture-feedback" class="alert neutral hidden w-full"></div>
                        </div>
                        <div class="grid min-w-0 content-start gap-5">
                            <div class="grid grid-cols-[minmax(0,1fr)_auto] items-end gap-2">
                                <div class="report-filter-field">
                                    <label for="learner_id">Select Learner</label>
                                    <select id="learner_id">
                                        <option value=""><?php echo $learners === [] ? 'No learners available in your section.' : '-- Select a learner --'; ?></option>
                                        <?php foreach ($learners as $learner): ?>
                                            <option value="<?php echo escape($learner['lrn']); ?>"><?php echo escape($learner['learner_name'] ?? trim(($learner['last_name'] ?? '') . ', ' . ($learner['first_name'] ?? ''))); ?> (<?php echo escape($learner['lrn']); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button id="reset-learner-btn" class="ghost-button min-h-[44px]" type="button" aria-label="Delete selected learner photos" title="Delete captured photos for the selected learner">↻ Reset Photos</button>
                            </div>
                            <button id="capture-btn" class="primary-button min-h-[44px] w-full" disabled>Capture Photo</button>
                            <p id="capture-progress" class="m-0 text-sm text-[var(--muted)]" aria-live="polite">Select a learner to begin.</p>
                            <section aria-labelledby="captured-photos-heading">
                                <div class="panel-heading compact-heading">
                                    <h2 id="captured-photos-heading">Captured Photos</h2>
                                    <p>Saved photos for the selected learner.</p>
                                </div>
                                <div id="captured-photos" class="grid grid-cols-2 gap-3 sm:grid-cols-3" aria-live="polite">
                                    <p class="m-0 text-sm text-[var(--muted)]">Select a learner to view captured photos.</p>
                                </div>
                            </section>
                        </div>
                    </div>
                </article>
                <?php if ($user['role'] === 'admin'): ?>
                    <article class="admin-module-card">
                        <div class="panel-heading">
                            <h2>Train Model</h2>
                            <p>After capturing new photos, retrain the model.</p>
                        </div>
                        <button id="train-btn" class="primary-button w-full">Train Face Recognition Model</button>
                        <div id="train-feedback" class="alert neutral mt-3 hidden"></div>
                        <pre id="train-log" class="mt-3 max-h-[400px] overflow-y-auto rounded-lg bg-gray-100 p-2.5 hidden"></pre>
                    </article>
                <?php endif; ?>
            </section>
        </section>
    </section>
</main>
<script>window.ProjectPulse = <?php echo json_encode($enrollmentConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR); ?>;</script>
<script type="module" src="<?php echo escape(asset_url('assets/dist/shared.js')); ?>"></script>
<script type="module" src="<?php echo escape(asset_url('assets/dist/faceEnrollment.js')); ?>"></script>
</body>
</html>
