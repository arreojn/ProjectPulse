<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/auth.php';

$user = require_login();
$dashboardPath = dashboard_path_for_role($user['role'] ?? null);
$passwordFlash = flash_get('account_password');
$errors = [];

if (is_post()) {
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Invalid form token. Please refresh the page.');
        }

        if ($currentPassword === '') {
            $errors[] = 'Current password is required.';
        }

        if (strlen($newPassword) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        }

        if ($newPassword !== $confirmPassword) {
            $errors[] = 'New password confirmation does not match.';
        }

        if ($errors === []) {
            auth_change_password((int) $user['id'], $currentPassword, $newPassword);
            flash_set('account_password', 'Password changed successfully.');
            redirect('change_password.php');
        }
    } catch (RuntimeException $exception) {
        $errors[] = $exception->getMessage();
    } catch (Throwable $exception) {
        $errors[] = 'Unable to change your password right now.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape(APP_NAME); ?> Change Password</title>
    <link rel="stylesheet" href="<?php echo escape(asset_url('assets/css/app.css')); ?>">
</head>
<body class="dashboard-body">
    <main class="dashboard-shell">
        <header class="topbar">
            <div class="header-title-block">
                <img class="school-logo" src="<?php echo escape(school_logo_url()); ?>" alt="School logo">
                <div class="header-copy">
                    <p class="eyebrow">Account Security</p>
                    <h1>Change Password</h1>
                </div>
            </div>

            <div class="topbar-actions">
                <p class="signed-in-as">Signed in as <?php echo escape($user['username']); ?></p>
                <a href="<?php echo escape(route_url($dashboardPath)); ?>" class="secondary-link">Dashboard</a>
                <a href="<?php echo escape(route_url('logout.php')); ?>" class="secondary-link">Logout</a>
            </div>
        </header>

        <section class="password-utility-grid">
            <article class="password-utility-panel">
                <div class="panel-heading">
                    <h2>Update Password</h2>
                    <p><?php echo escape($user['email'] ?? $user['username']); ?></p>
                </div>

                <?php if ($passwordFlash !== null): ?>
                    <div class="alert <?php echo escape($passwordFlash['type']); ?>"><?php echo escape($passwordFlash['message']); ?></div>
                <?php endif; ?>

                <?php foreach ($errors as $error): ?>
                    <div class="alert error"><?php echo escape($error); ?></div>
                <?php endforeach; ?>

                <form method="post" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?php echo escape(csrf_token()); ?>">

                    <label for="current_password">Current Password</label>
                    <input
                        id="current_password"
                        name="current_password"
                        type="password"
                        autocomplete="current-password"
                        required
                        autofocus
                    >

                    <label for="new_password">New Password</label>
                    <input
                        id="new_password"
                        name="new_password"
                        type="password"
                        autocomplete="new-password"
                        minlength="6"
                        required
                    >

                    <label for="confirm_password">Confirm New Password</label>
                    <input
                        id="confirm_password"
                        name="confirm_password"
                        type="password"
                        autocomplete="new-password"
                        minlength="6"
                        required
                    >

                    <div class="password-form-actions">
                        <button type="submit" class="primary-button">Save Password</button>
                        <a href="<?php echo escape(route_url($dashboardPath)); ?>" class="secondary-link">Cancel</a>
                    </div>
                </form>
            </article>
        </section>
    </main>
</body>
</html>
