<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/health.php';
require_once __DIR__ . '/app/parents.php';
require_once __DIR__ . '/app/announcements.php';
require_once __DIR__ . '/app/teachers.php';

try {
    health_portal_bootstrap();
    parent_portal_bootstrap();
    announcements_bootstrap();
} catch (Throwable $exception) {
    // Keep the login screen available even if demo account seeding cannot run.
}

start_session();
$databaseWarning = null;

if (!auth_table_exists('users')) {
    $databaseWarning = 'The database schema is not imported yet. Import `database/schema.sql` into MySQL, then reload this page.';
}

if (current_user() !== null) {
    redirect(dashboard_path_for_role(current_user()['role'] ?? null));
}

$errorMessage = null;
$submittedIdentity = '';

if (is_post()) {
    $submittedIdentity = trim((string) ($_POST['identity'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    try {
        if (attempt_login($submittedIdentity, $password)) {
            redirect(dashboard_path_for_role(current_user()['role'] ?? null));
        }

        $errorMessage = 'Invalid username/email or password.';
    } catch (Throwable $exception) {
        $errorMessage = 'Unable to connect to the database right now. Check your XAMPP MySQL service and database import.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape(APP_NAME); ?> Login</title>
    <link rel="stylesheet" href="<?php echo escape(asset_url('assets/css/app.css')); ?>">
</head>
<body class="auth-body">
    <main class="auth-shell">
        <section class="auth-hero">
            <p class="eyebrow">Attendance Portal</p>
            <h1><?php echo escape(APP_NAME); ?></h1>
            <p class="lead">
                Sign in to access the right ProjectPulse portal for your role, whether that is
                attendance scanning, admin management, health coordination, teacher workflows, or parent attendance viewing.
            </p>

            <div class="auth-note">
                <p><strong>Demo logins</strong></p>
                <p>Attendance: <code>attendance_user</code> / <code>attendance123</code></p>
                <p>Admin: <code>portal_admin</code> / <code>admin123</code></p>
                <p>Health: <code>health_coordinator</code> / <code>health123</code></p>
                <p>Guidance: <code>guidance_counselor</code> / <code>guidance123</code></p>
                <p>Teacher: <code>teacher_mabini</code> / <code>teacher123</code></p>
                <p>Parent: <code>demo_parent</code> / <code>parent123</code></p>
            </div>
        </section>

        <section class="auth-panel">
            <div class="panel-heading">
                <h2>Sign In</h2>
                <p>After login, you will be redirected to your assigned dashboard.</p>
            </div>

            <?php if ($errorMessage !== null): ?>
                <div class="alert error"><?php echo escape($errorMessage); ?></div>
            <?php endif; ?>

            <?php if ($databaseWarning !== null): ?>
                <div class="alert error"><?php echo escape($databaseWarning); ?></div>
            <?php endif; ?>

            <form method="post" class="auth-form">
                <label for="identity">Username or Email</label>
                <input
                    id="identity"
                    name="identity"
                    type="text"
                    value="<?php echo escape($submittedIdentity); ?>"
                    autocomplete="username"
                    required
                    autofocus
                >

                <label for="password">Password</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                >

                <button type="submit" class="primary-button">Login</button>
            </form>
        </section>
    </main>
</body>
</html>
