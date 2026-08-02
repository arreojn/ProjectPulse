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
if ($user['role'] === 'admin') {
    $learners = learner_list([]);
} elseif ($user['role'] === 'teacher') {
    teacher_management_bootstrap();
    $learners = teacher_section_learners((int) $user['id']);
}
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
        #video-feed {
            width: 100%;
            height: auto;
            border-radius: 18px;
            transform: scaleX(-1);
            background: #000;
        }
        #photo-preview {
            width: 100%;
            height: auto;
            border-radius: 18px;
            display: none;
        }
        .enrollment-grid {
            display: grid;
            grid-template-columns: <?php echo $user['role'] === 'admin' ? 'minmax(0, 1fr) minmax(340px, 0.7fr)' : '1fr'; ?>;
            gap: 14px;
        }
        @media (max-width: 920px) {
            .enrollment-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="dashboard-body admin-dashboard">
    <main class="dashboard-shell admin-shell wide-admin-shell">
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
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="<?php echo escape(route_url('admin.php')); ?>" class="secondary-link">Admin Panel</a>
                <?php else: ?>
                    <a href="<?php echo escape(route_url('teacher.php')); ?>" class="secondary-link">Teacher Portal</a>
                <?php endif; ?>
                <a href="<?php echo escape(route_url('logout.php')); ?>" class="secondary-link">Logout</a>
            </div>
        </header>

        <section class="enrollment-grid">
            <article class="admin-module-card">
                <div class="panel-heading">
                    <h2>Enrollment Camera</h2>
                    <p>Select a learner, then capture their photo.</p>
                </div>

                <div class="report-filter-grid" style="align-items: center;">
                    <div class="report-filter-field report-filter-field-wide">
                        <label for="learner_id">Select Learner</label>
                        <select id="learner_id">
                            <?php if ($learners === []): ?>
                                <option value="" disabled>No learners available in your section.</option>
                            <?php else: ?>
                                <option value="">-- Select a learner --</option>
                                <?php foreach ($learners as $learner): ?>
                                    <option value="<?php echo escape($learner['lrn']); ?>">
                                        <?php echo escape($learner['learner_name'] ?? trim(($learner['last_name'] ?? '') . ', ' . ($learner['first_name'] ?? ''))); ?>
                                        (<?php echo escape($learner['lrn']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="report-actions" style="padding-top: 20px;">
                        <button id="capture-btn" class="primary-button" disabled>Capture Photo</button>
                    </div>
                </div>

                <div id="capture-feedback" class="alert neutral" style="display: none; margin-top: 12px;"></div>

                <div style="margin-top: 12px;">
                    <video id="video-feed" autoplay playsinline muted></video>
                    <canvas id="capture-canvas" style="display:none;"></canvas>
                    <img id="photo-preview" alt="Captured photo preview">
                </div>
            </article>

            <?php if ($user['role'] === 'admin'): ?>
                <article class="admin-module-card">
                    <div class="panel-heading">
                        <h2>Train Model</h2>
                        <p>After capturing new photos, retrain the model.</p>
                    </div>
                    <button id="train-btn" class="primary-button">Train Face Recognition Model</button>
                    <div id="train-feedback" class="alert neutral" style="margin-top: 12px; display: none;"></div>
                    <pre id="train-log" style="background: #f3f4f6; padding: 10px; border-radius: 8px; max-height: 400px; overflow-y: auto; display: none; margin-top: 12px;"></pre>
                </article>
            <?php endif; ?>
        </section>
    </main>

    <script>
    (function() {
        const video = document.getElementById('video-feed');
        const canvas = document.getElementById('capture-canvas');
        const context = canvas.getContext('2d');
        const learnerSelect = document.getElementById('learner_id');
        const captureBtn = document.getElementById('capture-btn');
        const captureFeedback = document.getElementById('capture-feedback');
        const photoPreview = document.getElementById('photo-preview');

        const trainBtn = document.getElementById('train-btn');
        const trainFeedback = document.getElementById('train-feedback');
        const trainLog = document.getElementById('train-log');

        const csrfToken = '<?php echo escape(csrf_token()); ?>';

        function setFeedback(el, message, type = 'neutral') {
            el.textContent = message;
            el.className = `alert ${type}`;
            el.style.display = 'block';
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
                setFeedback(captureFeedback, 'Could not access webcam. Please grant permission.', 'error');
            }
        }

        learnerSelect.addEventListener('change', () => {
            captureBtn.disabled = learnerSelect.value === '';
            photoPreview.style.display = 'none';
            video.style.display = 'block';
            captureFeedback.style.display = 'none';
        });

        captureBtn.addEventListener('click', async () => {
            captureBtn.disabled = true;
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            const imageDataUrl = canvas.toDataURL('image/jpeg');

            photoPreview.src = imageDataUrl;
            video.style.display = 'none';
            photoPreview.style.display = 'block';

            try {
                const response = await fetch('<?php echo escape(route_url('face_enrollment_event.php')); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ lrn: learnerSelect.value, image_data: imageDataUrl, csrf_token: csrfToken })
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'Failed to save photo.');
                setFeedback(captureFeedback, result.message, 'success');
            } catch (error) {
                setFeedback(captureFeedback, error.message, 'error');
            } finally {
                captureBtn.disabled = false;
            }
        });

        if (trainBtn) {
            trainBtn.addEventListener('click', async () => {
                trainBtn.disabled = true;
                trainLog.style.display = 'none';
                setFeedback(trainFeedback, 'Training in progress... This may take a moment.', 'neutral');

                try {
                    const formData = new FormData();
                    formData.append('csrf_token', csrfToken);

                    const response = await fetch('<?php echo escape(route_url('train_model.php')); ?>', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (!response.ok || !result.success) throw new Error(result.message || result.error || 'Failed to train model.');
                    
                    setFeedback(trainFeedback, 'Training completed successfully!', 'success');
                    trainLog.textContent = result.log.join('\\n');
                    trainLog.style.display = 'block';

                } catch (error) {
                    setFeedback(trainFeedback, error.message, 'error');
                } finally {
                    trainBtn.disabled = false;
                }
            });
        }

        startWebcam();
    })();
    </script>
</body>
</html>