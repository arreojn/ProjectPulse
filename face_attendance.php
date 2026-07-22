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
        #video-feed {
            width: 100%;
            height: auto;
            border-radius: 18px;
            transform: scaleX(-1); /* Mirror view for a more natural feel */
            background: #000;
        }
        .scanner-panel.expanded {
            grid-template-rows: auto minmax(0, 1fr);
        }
    </style>
</head>
<body class="dashboard-body">
    <main class="dashboard-shell fullscreen-shell">
        <header class="topbar">
            <div class="header-title-block">
                <img class="school-logo" src="<?php echo escape(school_logo_url()); ?>" alt="School logo">
                <div class="header-copy">
                    <p class="eyebrow">Attendance Module</p>
                    <h1>Face Recognition Scanner</h1>
                </div>
            </div>

            <div class="topbar-actions">
                <p class="signed-in-as">Signed in as <?php echo escape($user['username']); ?></p>
                <?php if (($user['role'] ?? '') === 'admin'): ?>
                    <a href="<?php echo escape(route_url('admin.php')); ?>" class="secondary-link">Admin Panel</a>
                <?php endif; ?>
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

                <div id="scan-feedback" class="alert neutral" style="margin: 0; text-align: center;">Awaiting scan...</div>
            </article>

            <article class="scanner-panel expanded">
                <section class="scan-head">
                    <div class="panel-heading no-gap">
                        <h2>Live Camera Feed</h2>
                        <p>Position face in the frame to log attendance automatically.</p>
                    </div>
                </section>

                <section class="scan-mode-panel">
                     <video id="video-feed" autoplay playsinline muted></video>
                     <canvas id="capture-canvas" style="display:none;"></canvas>
                </section>

                <section id="learner-card" class="learner-card full-panel is-empty">
                    <div class="learner-header-row">
                        <div class="learner-summary">
                            <h3 id="learner-name">No learner selected</h3>
                            <p id="learner-lrn">System is ready to recognize faces.</p>
                        </div>
                    </div>

                    <dl class="detail-grid wide">
                        <div><dt>Grade Level</dt><dd id="learner-grade">-</dd></div>
                        <div><dt>Section</dt><dd id="learner-section">-</dd></div>
                        <div><dt>Today's Status</dt><dd id="learner-status">No record yet</dd></div>
                        <div><dt>Last Scan</dt><dd id="learner-last-scan">-</dd></div>
                    </dl>

                    <div class="record-grid">
                        <section class="record-panel">
                            <div class="panel-heading compact-heading">
                                <h2>Recent Attendance Logs</h2>
                                <p>The latest records from all stations.</p>
                            </div>
                            <div class="table-shell">
                                <table class="records-table">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Learner</th>
                                            <th>Log Entry</th>
                                        </tr>
                                    </thead>
                                    <tbody id="attendance-logs-body">
                                        <tr><td colspan="3" class="empty-row">No attendance records to display yet.</td></tr>
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
            faceApiUrl: '<?php echo escape(route_url('face_attendance_event.php')); ?>',
            attendanceLogsUrl: '<?php echo escape(route_url('api/attendance_logs.php')); ?>',
            learnerPhotoBaseUrl: '<?php echo escape(asset_url('assets/images/learners/')); ?>',
            defaultLearnerPhotoUrl: '<?php echo escape(asset_url('assets/images/learners/logorotate.gif')); ?>'
        };
    </script>
    <script>
    (function () {
        const video = document.getElementById('video-feed');
        const canvas = document.getElementById('capture-canvas');
        const context = canvas.getContext('2d');
        const feedbackEl = document.getElementById('scan-feedback');
        const learnerPhotoEl = document.getElementById('learner-photo');
        const learnerNameEl = document.getElementById('learner-name');
        const learnerLrnEl = document.getElementById('learner-lrn');
        const learnerGradeEl = document.getElementById('learner-grade');
        const learnerSectionEl = document.getElementById('learner-section');
        const learnerStatusEl = document.getElementById('learner-status');
        const learnerLastScanEl = document.getElementById('learner-last-scan');
        const logsBody = document.getElementById('attendance-logs-body');
        let isProcessing = false;

        function setFeedback(message, type = 'neutral') {
            feedbackEl.textContent = message;
            feedbackEl.className = `alert ${type}`;
        }

        function escapeHtml(unsafe) {
            return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        async function startWebcam() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 }, audio: false });
                video.srcObject = stream;
                video.onloadedmetadata = () => {
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                };
            } catch (err) {
                setFeedback('Could not access webcam. Please grant permission.', 'error');
            }
        }

        async function processFrame() {
            if (isProcessing || video.paused || video.ended || !video.srcObject) return;
            isProcessing = true;

            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            const imageDataUrl = canvas.toDataURL('image/jpeg');

            try {
                const response = await fetch(window.ProjectPulse.faceApiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ image_data: imageDataUrl, csrf_token: window.ProjectPulse.csrfToken })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    setFeedback(result.message, 'success');
                    const learner = result.learner;
                    learnerNameEl.textContent = learner.name;
                    learnerLrnEl.textContent = `LRN: ${learner.lrn}`;
                    learnerGradeEl.textContent = learner.grade_level;
                    learnerSectionEl.textContent = learner.section_name;
                    learnerStatusEl.textContent = result.slot_label;
                    learnerLastScanEl.textContent = new Date().toLocaleTimeString();
                    // Note: This assumes the learner photo is a .jpg. The system supports other formats, but this requires a more complex lookup.
                    learnerPhotoEl.src = `${window.ProjectPulse.learnerPhotoBaseUrl}${learner.lrn}.jpg`; 
                } else if (response.status !== 404) { // 404 is "not found", which is normal
                    setFeedback(result.message, 'error');
                } else {
                    setFeedback('Searching for a recognized face...', 'neutral');
                }
            } catch (error) {
                setFeedback('API connection error.', 'error');
            } finally {
                updateLogs();
                setTimeout(() => { isProcessing = false; }, 2000); // Cooldown period
            }
        }

        function renderLogs(logs) {
            if (logs.length === 0) {
                logsBody.innerHTML = '<tr><td colspan="3" class="empty-row">No attendance records to display yet.</td></tr>';
                return;
            }

            const rowsHtml = logs.map(log => `
                <tr>
                    <td>${escapeHtml(log.log_time)}</td>
                    <td>${escapeHtml(log.learner_name)}</td>
                    <td><span class="table-status">${escapeHtml(log.log_entry)}</span></td>
                </tr>
            `).join('');

            logsBody.innerHTML = rowsHtml;
        }

        function updateLogs() {
            fetch(window.ProjectPulse.attendanceLogsUrl)
                .then(response => response.json())
                .then(data => {
                    if (data.success && Array.isArray(data.logs)) {
                        renderLogs(data.logs);
                    }
                })
                .catch(() => { /* Ignore log update errors */ });
        }

        startWebcam();
        updateLogs();
        setInterval(processFrame, 2500); // Attempt recognition every 2.5 seconds
    })();
    </script>
</body>
</html>