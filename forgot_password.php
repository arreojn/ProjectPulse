<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/password_resets.php';
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
            flash_set('forgot_password', 'Your request has been submitted. If the account exists, a reset request will be reviewed by the administrator.');
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
    <link rel="stylesheet" href="<?php echo escape(asset_url('assets/dist/tailwind.css')); ?>">
</head>
<body class="m-0 flex min-h-screen items-center bg-[radial-gradient(circle_at_top_left,color-mix(in_srgb,var(--accent)_18%,transparent),transparent_26%),radial-gradient(circle_at_bottom_right,color-mix(in_srgb,var(--accent-strong)_14%,transparent),transparent_24%),linear-gradient(160deg,color-mix(in_srgb,var(--bg)_72%,white)_0%,var(--bg)_55%,var(--bg-deep)_100%)] font-sans text-[var(--ink)]">
    <main class="mx-auto w-[min(1180px,calc(100%-32px))] py-5 pb-7">
        <section class="mx-auto w-full max-w-[720px]">
            <article class="rounded-[28px] border border-[var(--line)] bg-[var(--surface)] p-7 shadow-[var(--shadow)] backdrop-blur-2xl max-sm:p-5">
                <div class="mb-5">
                    <h2 class="mb-1.5 text-lg font-bold">Forgot Password</h2>
                    <p class="m-0 text-sm leading-snug text-[var(--muted)]">Enter your username or email to request a password reset.</p>
                </div>

                <?php if ($flash !== null): ?>
                    <div class="mb-4 rounded-2xl border border-[color-mix(in_srgb,var(--success)_22%,transparent)] bg-[color-mix(in_srgb,var(--success)_9%,white)] p-3 text-sm text-[var(--success)]" role="status"><?php echo escape($flash['message']); ?></div>
                <?php endif; ?>

                <?php foreach ($errors as $error): ?>
                    <div class="mb-4 rounded-2xl border border-[color-mix(in_srgb,var(--danger)_22%,transparent)] bg-[color-mix(in_srgb,var(--danger)_8%,white)] p-3 text-sm text-[var(--danger)]" role="alert"><?php echo escape($error); ?></div>
                <?php endforeach; ?>

                <form method="post" class="grid gap-3" id="forgot-password-form" data-auth-action="forgot" data-auth-endpoint="<?php echo escape(asset_url('api/auth_password.php')); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo escape(csrf_token()); ?>">

                    <label class="text-sm font-semibold" for="identity">Username or Email</label>
                    <input class="box-border w-full rounded-xl border border-[var(--line)] bg-[var(--surface-strong)] px-3.5 py-3 text-sm text-[var(--ink)] outline-none transition focus:border-[var(--accent)] focus:ring-4 focus:ring-[color-mix(in_srgb,var(--accent)_15%,transparent)]" id="identity" name="identity" type="text" value="<?php echo escape($submittedIdentity); ?>" required autofocus>

                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-full border-0 bg-[var(--accent)] px-5 py-2.5 font-semibold text-white shadow-md transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent)]">Request Reset</button>
                        <a href="<?php echo escape(route_url('index.php')); ?>" class="inline-flex min-h-11 items-center justify-center rounded-full border border-[var(--line)] bg-transparent px-5 py-2.5 font-semibold text-[var(--ink)] no-underline hover:bg-[var(--surface-strong)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--accent)]">Back to Login</a>
                    </div>
                </form>
            </article>
        </section>
    </main>
    <script type="module" src="<?php echo escape(asset_url('assets/dist/shared.js')); ?>"></script>
</body>
</html>