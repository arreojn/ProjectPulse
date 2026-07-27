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

if (is_post() && $databaseConnectionOk) {
    $submittedIdentity = trim((string)($_POST['identity'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (attempt_login($submittedIdentity, $password)) {
        redirect(dashboard_path_for_role(current_user()['role'] ?? null));
    }

    $errorMessage = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= escape(APP_NAME) ?></title>

<link rel="stylesheet" href="<?= escape(asset_url('vendor/bootstrap/css/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= escape(asset_url('loginassets/fonts/font-awesome-4.7.0/css/font-awesome.min.css')) ?>">
<link rel="stylesheet" href="<?= escape(asset_url('loginassets/vendor/animate/animate.css')) ?>">
<link rel="stylesheet" href="<?= escape(asset_url('loginassets/vendor/css-hamburgers/hamburgers.min.css')) ?>">
<link rel="stylesheet" href="<?= escape(asset_url('loginassets/vendor/select2/select2.min.css')) ?>">
<link rel="stylesheet" href="<?= escape(asset_url('loginassets/css/util.css')) ?>">
<link rel="stylesheet" href="<?= escape(asset_url('loginassets/css/main.css')) ?>">

<style>
.auth-logo{width:180px;margin-bottom:20px}
.project-title{font-size:48px;font-weight:700;color:#2E2A47}
.project-subtitle{font-size:17px;color:#666;margin-bottom:25px}
.about-card{margin-top:25px;padding:20px;background:#fafafa;border:1px solid #ddd;border-radius:12px}
.about-card h4{margin-bottom:10px}
.about-card p{font-size:14px;line-height:1.7;color:#555}
.login-subtitle{text-align:center;margin-top:-20px;margin-bottom:30px;color:#666}
.alert.error{background:#fdecea;border:1px solid #f5c2c7;color:#842029;padding:10px;border-radius:6px;margin-bottom:15px}
.forgot-link{display:block;text-align:center;margin-top:20px}
</style>
</head>
<body>

<div class="limiter">
<div class="container-login100">
<div class="wrap-login100">

<div class="login100-pic js-tilt" data-tilt>
<img src="<?= escape(asset_url('assets/images/pulselogo.png')) ?>" class="auth-logo" alt="Project PULSE">

<h1 class="project-title">Project PULSE</h1>

<p class="project-subtitle">
Portal for Unified Learner Monitoring,<br>
School Records and Engagement
</p>

<div class="about-card">
<h4>About Project PULSE</h4>
<p>
Project PULSE is an integrated school information system that centralizes learner records,
attendance, academic performance, health monitoring, and stakeholder engagement through
secure role-based access for administrators, teachers, parents, guidance counselors,
and health coordinators.
</p>
</div>

</div>

<form method="post" class="login100-form validate-form">

<span class="login100-form-title">Login to Your Account</span>

<p class="login-subtitle">Enter your credentials to access the portal.</p>

<?php if ($errorMessage): ?>
<div class="alert error"><?= escape($errorMessage) ?></div>
<?php endif; ?>

<?php if ($databaseWarning): ?>
<div class="alert error"><?= escape($databaseWarning) ?></div>
<?php endif; ?>

<div class="wrap-input100">
<input class="input100" type="text" id="identity" name="identity"
placeholder="Username or Email"
value="<?= escape($submittedIdentity) ?>" required>
<span class="focus-input100"></span>
<span class="symbol-input100"><i class="fa fa-user"></i></span>
</div>

<div class="wrap-input100">
<input class="input100" type="password" id="password" name="password"
placeholder="Password" required>
<span class="focus-input100"></span>
<span class="symbol-input100"><i class="fa fa-lock"></i></span>
</div>

<div class="container-login100-form-btn">
<button class="login100-form-btn" type="submit" <?= !$databaseConnectionOk ? 'disabled' : '' ?>>
Log In
</button>
</div>

<div class="text-center p-t-12">
<a class="txt2" href="<?= escape(route_url('forgot_password.php')) ?>">
Forgot Password?
</a>
</div>

</form>

</div>
</div>
</div>

<script src="<?= escape(asset_url('loginassets/vendor/jquery/jquery-3.2.1.min.js')) ?>"></script>
<script src="<?= escape(asset_url('loginassets/vendor/bootstrap/js/popper.js')) ?>"></script>
<script src="<?= escape(asset_url('loginassets/vendor/bootstrap/js/bootstrap.min.js')) ?>"></script>
<script src="<?= escape(asset_url('loginassets/vendor/select2/select2.min.js')) ?>"></script>
<script src="<?= escape(asset_url('loginassets/vendor/tilt/tilt.jquery.min.js')) ?>"></script>
<script>
$('.js-tilt').tilt({scale:1.05});
</script>

</body>
</html>
