<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/theme_settings.php';

try {
    theme_settings_bootstrap();
} catch (Throwable $exception) {
    // Keep the login screen available even if demo account seeding cannot run.
}

start_session();
$databaseWarning = null;

if (current_user() !== null) {
    redirect(dashboard_path_for_role(current_user()['role'] ?? null));
}

$databaseConnectionOk = true;
try {
    if (!auth_table_exists('users')) {
        $databaseWarning = 'The database schema is not imported yet. Import `database/schema.sql` into MySQL, then reload this page.';
    }
} catch (Throwable $exception) {
    $databaseConnectionOk = false;
    $databaseWarning = 'Unable to connect to the database right now. Check your XAMPP MySQL service and database import.';
}

$errorMessage = null;
$submittedIdentity = '';

if (is_post() && $databaseConnectionOk) {
    $submittedIdentity = trim((string) ($_POST['identity'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (attempt_login($submittedIdentity, $password)) {
        redirect(dashboard_path_for_role(current_user()['role'] ?? null));
    } else {
        $errorMessage = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape(APP_NAME); ?> Login</title>
    <?php echo theme_stylesheet_markup(); ?>
    <link rel="stylesheet" href="<?php echo escape(asset_url('assets/css/app.css')); ?>">
    <style>
        .auth-logo {
            width: 250px;
        }
    </style>
</head>
<body class="auth-body">
    <main class="auth-shell">
        <div class="auth-hero">
            <img src="<?php echo escape(asset_url('assets/images/pulselogo.png')); ?>" alt="<?php echo escape(APP_NAME); ?> Logo" class="auth-logo">
            <h1>Project <strong>PULSE</strong></h1>
            <p class="lead">Portal for Unified Learner Monitoring, School Records and Engagement</p>

            <?php if ($databaseConnectionOk): ?>
                <div class="auth-note">
                    <p><strong>Demo Access:</strong><br>You can log in with <code>admin@projectpulse.com</code> or <code>teacher@projectpulse.com</code>. The password for all demo accounts is <code>password</code>.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="auth-panel">
            <div class="panel-heading">
                <h2>Login to Your Account</h2>
                <p>Enter your credentials to access the portal.</p>
            </div>

             <?php if ($errorMessage !== null): ?>
                 <div class="alert error"><?php echo escape($errorMessage); ?></div>
             <?php endif; ?>
             <?php if ($databaseWarning !== null): ?>
                 <div class="alert error"><?php echo escape($databaseWarning); ?></div>
             <?php endif; ?>

            <form method="post" class="auth-form">
                <div>
                    <label for="identity">Username or Email</label>
                    <input id="identity" name="identity" type="text" value="<?php echo escape($submittedIdentity); ?>" required>
                </div>
                <div>
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" required>
                </div>

                <div class="password-form-actions">
                    <button type="submit" class="primary-button" <?php echo !$databaseConnectionOk ? 'disabled' : ''; ?>>Log In</button>
                    <a href="<?php echo escape(route_url('forgot_password.php')); ?>" class="secondary-link">Forgot password?</a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
