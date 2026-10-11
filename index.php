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
<meta name="theme-color" content="#dbeaf2">
<title><?= escape(APP_NAME) ?> | Sign in</title>

<link rel="stylesheet" href="<?= escape(asset_url('loginassets/fonts/font-awesome-4.7.0/css/font-awesome.min.css')) ?>">
<link rel="stylesheet" href="<?= escape(asset_url('assets/dist/tailwind.css')) ?>">
<style>
@media (max-width: 760px) and (orientation: portrait) {
    .login-page-background {
        background-image: linear-gradient(180deg, rgba(12, 36, 49, 0.02), rgba(12, 36, 49, 0.16)), url("<?= escape(asset_url('assets/images/bg2.jpg')) ?>") !important;
        background-position: center;
    }
}
</style>
</head>
<body>

<main class="login-page-background relative flex min-h-screen min-h-[100svh] justify-end overflow-hidden bg-cover bg-center bg-no-repeat font-login text-[#17251f] max-[760px]:flex-col max-[760px]:justify-center max-[760px]:bg-left" style="background-image: linear-gradient(90deg, rgba(12, 36, 49, 0.02), rgba(12, 36, 49, 0.16)), url('<?= escape(asset_url('assets/images/bg.jpg')) ?>')">
<section class="relative flex min-h-screen min-h-[100svh] w-full max-w-[510px] items-center justify-center border-l border-white/45 bg-white/50 px-10 pt-12 pb-[82px] shadow-[-18px_0_60px_rgba(14,38,48,0.14)] backdrop-blur-[18px] max-[760px]:min-h-[calc(100svh-54px)] max-[760px]:max-w-none max-[760px]:border-l-0 max-[760px]:bg-white/55 max-[760px]:px-[22px] max-[760px]:py-7 max-[380px]:px-4 max-[380px]:py-5" aria-labelledby="login-title">
<div class="w-full max-w-[370px]">
<a class="mb-[27px] block text-center text-[32px] font-semibold leading-[1.25] text-[#17364b] no-underline hover:text-[#17364b] hover:no-underline focus:text-[#17364b] focus:no-underline max-[760px]:mb-5 max-[380px]:text-[27px] max-[380px]:mb-4" href="<?= escape(route_url('index.php')) ?>" aria-label="ProjectPulse home">
<span class="mr-2 text-[#45ad53]">Project</span><span>Pulse</span>
</a>

<form method="post" class="box-border rounded-2xl border border-white/70 bg-white/45 px-7 pt-7 pb-7 shadow-[0_12px_36px_rgba(20,51,61,0.12)] backdrop-blur-xl max-[760px]:px-6 max-[760px]:pt-6 max-[760px]:pb-6 max-[380px]:px-5 max-[380px]:pt-5 max-[380px]:pb-5">
<input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">

<h1 class="mb-0.5 text-[21px] font-semibold leading-[1.4] text-[#17364b]" id="login-title">Welcome back</h1>
<p class="mb-[17px] text-[13px] leading-[1.6] text-[#53645e]">Sign in to access your account.</p>

<?php if ($errorMessage): ?>
<div class="mb-[14px] rounded border border-[#efc5c5] bg-[#fff4f3] px-3 py-2.5 text-xs leading-[1.5] text-[#8f2929]" role="alert"><?= escape($errorMessage) ?></div>
<?php endif; ?>

<?php if ($databaseWarning): ?>
<div class="mb-[14px] rounded border border-[#efc5c5] bg-[#fff4f3] px-3 py-2.5 text-xs leading-[1.5] text-[#8f2929]" role="alert"><?= escape($databaseWarning) ?></div>
<?php endif; ?>

<label class="mb-[5px] block text-xs font-semibold text-[#344840]" for="identity">Username or email</label>
<div class="relative mb-[14px]">
<i class="fa fa-user pointer-events-none absolute top-1/2 left-[14px] -translate-y-1/2 text-sm text-[#71817a]" aria-hidden="true"></i>
<input class="box-border block h-[49px] w-full rounded-lg border border-white/80 bg-white/65 pr-[42px] pl-[39px] font-[inherit] text-[13px] text-[#1c2420] outline-none transition-[border-color,box-shadow] duration-150 placeholder:text-[#727e78] focus:border-[#45ad53] focus:shadow-[0_0_0_3px_rgba(69,173,83,0.2)]" type="text" id="identity" name="identity" autocomplete="username"
placeholder="Enter your username or email" value="<?= escape($submittedIdentity) ?>" required>
</div>

<label class="mb-[5px] block text-xs font-semibold text-[#344840]" for="password">Password</label>
<div class="relative mb-[14px]">
<i class="fa fa-lock pointer-events-none absolute top-1/2 left-[14px] -translate-y-1/2 text-sm text-[#71817a]" aria-hidden="true"></i>
<input class="box-border block h-[49px] w-full rounded-lg border border-white/80 bg-white/65 pr-[42px] pl-[39px] font-[inherit] text-[13px] text-[#1c2420] outline-none transition-[border-color,box-shadow] duration-150 placeholder:text-[#727e78] focus:border-[#45ad53] focus:shadow-[0_0_0_3px_rgba(69,173,83,0.2)]" type="password" id="password" name="password" autocomplete="current-password"
placeholder="Enter your password" required>
<button class="absolute top-0 right-0.5 grid h-[49px] w-10 place-items-center border-0 bg-transparent text-[15px] text-[#53645e] focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-[#45ad53]/35 focus-visible:outline-offset-2" type="button" aria-label="Show password" aria-pressed="false">
<i class="fa fa-eye-slash" aria-hidden="true"></i>
</button>
</div>

<button class="mt-[7px] flex min-h-[45px] w-full items-center justify-center gap-2.5 rounded border-0 bg-[#45ad53] px-4 py-2.5 font-[inherit] text-[13px] font-semibold uppercase text-white shadow-[0_2px_4px_rgba(31,88,40,0.22)] transition duration-150 enabled:hover:-translate-y-px enabled:hover:bg-[#2d8c3b] disabled:cursor-not-allowed disabled:opacity-[0.58] focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-[#45ad53]/35 focus-visible:outline-offset-2" type="submit" <?= !$databaseConnectionOk ? 'disabled' : '' ?>>
<span>Log in</span><i class="fa fa-arrow-right text-[13px]" aria-hidden="true"></i>
</button>

<a class="mt-4 block text-center text-[11px] leading-[1.6] text-[#344840] no-underline focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-[#45ad53]/35 focus-visible:outline-offset-2" href="<?= escape(route_url('forgot_password.php')) ?>">Forgot password? <span class="text-[#247d34] hover:underline">Reset here</span></a>

</form>
</div>
</section>

<footer class="absolute right-0 bottom-0 w-full max-w-[510px] border-t border-white/45 bg-white/35 px-5 py-4 text-center text-[10px] leading-[1.6] text-[#40534b] backdrop-blur-md max-[760px]:static max-[760px]:max-w-none max-[760px]:px-4 max-[760px]:py-[15px]">&copy; <?= date('Y') ?> ProjectPulse <span class="px-1 text-[#78877f]" aria-hidden="true">&middot;</span> Learner monitoring portal</footer>
</main>

<script type="module" src="<?= escape(asset_url('assets/dist/login.js')) ?>"></script>

</body>
</html>
