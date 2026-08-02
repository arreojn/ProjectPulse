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
            max-width: 620px;
            aspect-ratio: 4 / 3;
            object-fit: cover;
            border-radius: 18px;
            transform: scaleX(-1); /* Mirror view for a more natural feel */
            background: #000;
        }
        .face-attendance-layout {
            display: grid;
            grid-template-columns: minmax(390px, 0.85fr) minmax(0, 1.15fr);
            gap: 16px;
            align-items: start;
        }
        .today-attendance-panel {
            max-height: calc(100vh - 160px);
            overflow: hidden;
        }
        .today-attendance-panel .table-shell {
            max-height: calc(100vh - 270px);
            overflow: auto;
        }
        .camera-station {
            display: grid;
            gap: 14px;
            justify-items: center;
        }
        .camera-station .scan-head,
        .camera-station .scan-mode-panel,
        .camera-station #scan-feedback {
            width: min(100%, 620px);
        }
        .camera-station .scan-head {
            align-items: center;
        }
        .camera-station .clock-panel {
            min-width: 210px;
            padding: 12px 16px;
        }
        .camera-station .clock-value {
            font-size: 1.45rem;
        }
        .camera-station .scan-mode-panel {
            justify-content: center;
            padding: 10px;
        }
        .attendance-time-table th:not(:first-child),
        .attendance-time-table td:not(:first-child) {
            text-align: center;
            white-space: nowrap;
        }
        .learner-name-cell strong,
        .learner-name-cell small {
            display: block;
        }
        .learner-name-cell small { color: var(--muted); margin-top: 3px; }
        @media (max-width: 980px) {
            .face-attendance-layout { grid-template-columns: 1fr; }
            .today-attendance-panel, .today-attendance-panel .table-shell { max-height: none; }
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

        <section class="face-attendance-layout">
            <article class="admin-module-card today-attendance-panel">
                <div class="panel-heading compact-heading">
                    <h2>Today’s Learners</h2>
                    <p>Live time-in and time-out records.</p>
                </div>
                <div class="table-shell">
                    <table class="records-table attendance-time-table">
                        <thead>
                            <tr>
                                <th>Learner</th>
                                <th>AM In</th>
                                <th>AM Out</th>
                                <th>PM In</th>
                                <th>PM Out</th>
                            </tr>
                        </thead>
                        <tbody id="today-attendance-body">
                            <tr><td colspan="5" class="empty-row">No attendance records for today yet.</td></tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="admin-module-card camera-station">
                <section class="scan-head">
                    <div class="panel-heading no-gap">
                        <h2>Live Camera Feed</h2>
                        <p>Position face in the frame to log attendance automatically.</p>
                    </div>
                    <div class="clock-panel">
                        <p class="meta-label">Current Time</p>
                        <p id="live-time" class="clock-value"><?php echo escape(date('h:i:s A')); ?></p>
                        <p id="live-date" class="date-value"><?php echo escape(date('l, F j, Y')); ?></p>
                    </div>
                </section>

                <section class="scan-mode-panel">
                    <video id="video-feed" autoplay playsinline muted></video>
                    <canvas id="capture-canvas" style="display:none;"></canvas>
                </section>

                <div id="scan-feedback" class="alert neutral" style="margin: 0; text-align: center;">Awaiting scan...</div>
            </article>
        </section>
    </main>

    <script>
        window.ProjectPulse = {
            csrfToken: '<?php echo escape(csrf_token()); ?>',
            faceApiUrl: '<?php echo escape(route_url('face_attendance_event.php')); ?>',
            attendanceSummaryUrl: '<?php echo escape(route_url('api/face_attendance_summary.php')); ?>'
        };
    </script>
    <script>
    (function () {
        const video = document.getElementById('video-feed');
        const canvas = document.getElementById('capture-canvas');
        const context = canvas.getContext('2d');
        const feedbackEl = document.getElementById('scan-feedback');
        const attendanceBody = document.getElementById('today-attendance-body');
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
            const imageDataUrl = canvas.toDataURL('image/jpeg', 0.72);

            try {
                const response = await fetch(window.ProjectPulse.faceApiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ image_data: imageDataUrl, csrf_token: window.ProjectPulse.csrfToken })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    setFeedback(result.message, 'success');
                } else if (response.status !== 404) { // 404 is "not found", which is normal
                    setFeedback(result.message, 'error');
                } else {
                    setFeedback('Searching for a recognized face...', 'neutral');
                }
            } catch (error) {
                setFeedback('API connection error.', 'error');
            } finally {
                updateAttendanceList();
                setTimeout(() => { isProcessing = false; }, 700); // Service is kept warm between frames.
            }
        }

        function renderAttendanceList(records) {
            if (records.length === 0) {
                attendanceBody.innerHTML = '<tr><td colspan="5" class="empty-row">No attendance records for today yet.</td></tr>';
                return;
            }

            const rowsHtml = records.map(record => `
                <tr>
                    <td class="learner-name-cell"><strong>${escapeHtml(record.learner_name)}</strong><small>LRN ${escapeHtml(record.lrn)}</small></td>
                    <td>${escapeHtml(record.am_time_in)}</td>
                    <td>${escapeHtml(record.am_time_out)}</td>
                    <td>${escapeHtml(record.pm_time_in)}</td>
                    <td>${escapeHtml(record.pm_time_out)}</td>
                </tr>
            `).join('');

            attendanceBody.innerHTML = rowsHtml;
        }

        function updateAttendanceList() {
            fetch(window.ProjectPulse.attendanceSummaryUrl)
                .then(response => response.json())
                .then(data => {
                    if (data.success && Array.isArray(data.records)) {
                        renderAttendanceList(data.records);
                    }
                })
                .catch(() => { /* Keep the most recently rendered list. */ });
        }

        function updateClock() {
            const now = new Date();
            document.getElementById('live-time').textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            document.getElementById('live-date').textContent = now.toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
        }

        startWebcam();
        updateClock();
        updateAttendanceList();
        setInterval(updateClock, 1000);
        setInterval(updateAttendanceList, 15000);
        setInterval(processFrame, 1200); // Fast worker supports more responsive scans.
    })();
    </script>
</body>
</html>
