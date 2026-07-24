<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/password_resets.php';
require_once __DIR__ . '/app/theme_settings.php';

theme_settings_bootstrap();
password_resets_bootstrap();

$flash = flash_get('forgot_password');
$errors = [];
$submittedIdentity = '';

if (is_post()) {
    $submittedIdentity = trim((string) ($_POST['identity'] ?? ''));

    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Invalid form token. Please refresh the page.');
        }

        if ($submittedIdentity === '') {
            $errors[] = 'Username or email is required.';
        }

        if ($errors === []) {
            request_password_reset($submittedIdentity);
            flash_set('forgot_password', 'If an account with that identity exists, a password reset request has been sent for admin approval.');
            redirect('forgot_password.php');
        }
    } catch (Throwable $exception) {
        $errors[] = 'An unexpected error occurred. Please try again later.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php echo theme_stylesheet_markup(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape(APP_NAME); ?> - Forgot Password</title>
    <link rel="stylesheet" href="<?php echo escape(asset_url('assets/css/app.css')); ?>">
</head>
<body class="dashboard-body">
    <main class="dashboard-shell">
        <section class="password-utility-grid">
            <article class="password-utility-panel">
                <div class="panel-heading">
                    <h2>Forgot Password</h2>
                    <p>Enter your username or email to request a password reset.</p>
                </div>

                <?php if ($flash !== null): ?>
                    <div class="alert <?php echo escape($flash['type']); ?>"><?php echo escape($flash['message']); ?></div>
                <?php endif; ?>

                <?php foreach ($errors as $error): ?>
                    <div class="alert error"><?php echo escape($error); ?></div>
                <?php endforeach; ?>

                <form method="post" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?php echo escape(csrf_token()); ?>">

                    <label for="identity">Username or Email</label>
                    <input id="identity" name="identity" type="text" value="<?php echo escape($submittedIdentity); ?>" required autofocus>

                    <div class="password-form-actions">
                        <button type="submit" class="primary-button">Request Reset</button>
                        <a href="<?php echo escape(route_url('index.php')); ?>" class="secondary-link">Back to Login</a>
                    </div>
                </form>
            </article>
        </section>
    </main>
</body>
</html>