<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/theme_settings.php';

try { theme_settings_bootstrap(); } catch (Throwable $e) {}

start_session();

if (current_user() !== null) {
    redirect(dashboard_path_for_role(current_user()['role'] ?? null));
}

$databaseWarning = null;
$databaseConnectionOk = true;

try {
    if (!auth_table_exists('users')) {
        $databaseWarning = 'Database schema is not imported yet.';
    }
} catch (Throwable $e) {
    $databaseConnectionOk = false;
    $databaseWarning = 'Unable to connect to the database.';
}

$errorMessage = null;
$submittedIdentity = '';
$csrfToken = csrf_token();

if (is_post() && $databaseConnectionOk) {
    $submittedIdentity = trim((string)($_POST['identity'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'Invalid login session token. Please refresh the page and try again.';
    } elseif (attempt_login($submittedIdentity, $password)) {
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
<meta name="theme-color" content="#f7f8f6">
<title><?= escape(APP_NAME) ?> | Sign in</title>

<link rel="stylesheet" href="<?= escape(asset_url('loginassets/fonts/font-awesome-4.7.0/css/font-awesome.min.css')) ?>">
<link rel="stylesheet" href="<?= escape(asset_url('assets/css/login.css')) ?>">
</head>
<body>

<main class="login-screen">
<section class="login-main" aria-labelledby="login-title">
<div class="login-content">
<a class="wordmark" href="<?= escape(route_url('index.php')) ?>" aria-label="ProjectPulse home">
<span class="wordmark-project">Project</span><span class="wordmark-pulse">Pulse</span>
</a>

<form method="post" class="login-form">
<input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">

<h1 id="login-title">Welcome back</h1>
<p class="form-intro">Sign in to access your account.</p>

<?php if ($errorMessage): ?>
<div class="login-alert" role="alert"><?= escape($errorMessage) ?></div>
<?php endif; ?>

<?php if ($databaseWarning): ?>
<div class="login-alert" role="alert"><?= escape($databaseWarning) ?></div>
<?php endif; ?>

<label class="field-label" for="identity">Username or email</label>
<div class="login-field">
<i class="fa fa-user field-icon" aria-hidden="true"></i>
<input type="text" id="identity" name="identity" autocomplete="username"
placeholder="Enter your username or email" value="<?= escape($submittedIdentity) ?>" required>
</div>

<label class="field-label" for="password">Password</label>
<div class="login-field">
<i class="fa fa-lock field-icon" aria-hidden="true"></i>
<input type="password" id="password" name="password" autocomplete="current-password"
placeholder="Enter your password" required>
<button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false">
<i class="fa fa-eye-slash" aria-hidden="true"></i>
</button>
</div>

<button class="login-button" type="submit" <?= !$databaseConnectionOk ? 'disabled' : '' ?>>
<span>Log in</span><i class="fa fa-arrow-right" aria-hidden="true"></i>
</button>

<a class="forgot-link" href="<?= escape(route_url('forgot_password.php')) ?>">Forgot password? <span>Reset here</span></a>

</form>
</div>
</section>

<aside class="brand-panel" aria-label="ProjectPulse learner monitoring portal">
<div class="brand-art">
<img src="<?= escape(asset_url('assets/images/pulselogo.png')) ?>" alt="ProjectPulse monitoring portal logo">
<p class="brand-tagline">Data. Insight. Engagement. Success.</p>
</div>
</aside>

<footer class="login-footer">&copy; <?= date('Y') ?> ProjectPulse <span aria-hidden="true">&middot;</span> Learner monitoring portal</footer>
</main>

<script>
const passwordField = document.getElementById('password');
const passwordToggle = document.querySelector('.password-toggle');

passwordToggle.addEventListener('click', () => {
    const isVisible = passwordField.type === 'text';
    passwordField.type = isVisible ? 'password' : 'text';
    passwordToggle.setAttribute('aria-pressed', String(!isVisible));
    passwordToggle.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
    passwordToggle.querySelector('i').className = isVisible ? 'fa fa-eye-slash' : 'fa fa-eye';
});
</script>

</body>
</html>
