<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/theme_settings.php';

$user = require_login();
$dashboardPath = dashboard_path_for_role($user['role'] ?? null);
$passwordFlash = flash_get('account_password');
$errors = [];

theme_settings_bootstrap();

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

        if (strlen($newPassword) < 12) {
            $errors[] = 'New password must be at least 12 characters.';
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
    <?php echo theme_stylesheet_markup(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape(APP_NAME); ?> Change Password</title>
    <link rel="stylesheet" href="<?php echo escape(asset_url('assets/dist/tailwind.css')); ?>">
</head>
<body class="m-0 min-h-screen bg-[radial-gradient(circle_at_top_left,color-mix(in_srgb,var(--accent)_18%,transparent),transparent_26%),radial-gradient(circle_at_bottom_right,color-mix(in_srgb,var(--accent-strong)_14%,transparent),transparent_24%),linear-gradient(160deg,color-mix(in_srgb,var(--bg)_72%,white)_0%,var(--bg)_55%,var(--bg-deep)_100%)] font-sans text-[var(--ink)]">
    <main class="mx-auto w-[min(1180px,calc(100%-32px))] py-5 pb-7">
        <header class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-[28px] border border-[var(--line)] bg-[var(--surface)] p-5 shadow-[var(--shadow)] backdrop-blur-2xl max-sm:items-start">
            <div class="flex min-w-0 items-center gap-4">
                <img class="h-14 w-14 rounded-2xl object-contain" src="<?php echo escape(school_logo_url()); ?>" alt="School logo">
                <div>
                    <p class="mb-1 text-[0.68rem] font-bold uppercase tracking-[0.14em] text-[var(--accent)]">Account Security</p>
                    <h1 class="m-0 text-2xl font-bold leading-tight">Change Password</h1>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <p class="m-0 text-sm text-[var(--muted)]">Signed in as <?php echo escape($user['username']); ?></p>
                <a href="<?php echo escape(route_url($dashboardPath)); ?>" class="inline-flex min-h-10 items-center justify-center rounded-full border border-[var(--line)] bg-[var(--surface-strong)] px-4 py-2 text-sm font-semibold text-[var(--ink)] no-underline hover:brightness-95 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--accent)]">Dashboard</a>
                <a href="<?php echo escape(route_url('logout.php')); ?>" class="inline-flex min-h-10 items-center justify-center rounded-full border border-[var(--line)] bg-[var(--surface-strong)] px-4 py-2 text-sm font-semibold text-[var(--ink)] no-underline hover:brightness-95 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--accent)]">Logout</a>
            </div>
        </header>

        <section class="mx-auto w-full max-w-[720px]">
            <article class="rounded-[28px] border border-[var(--line)] bg-[var(--surface)] p-7 shadow-[var(--shadow)] backdrop-blur-2xl max-sm:p-5">
                <div class="mb-5">
                    <h2 class="mb-1.5 text-lg font-bold">Update Password</h2>
                    <p class="m-0 text-sm leading-snug text-[var(--muted)]"><?php echo escape($user['email'] ?? $user['username']); ?></p>
                </div>

                <?php if ($passwordFlash !== null): ?>
                    <div class="mb-4 rounded-2xl border border-[color-mix(in_srgb,var(--success)_22%,transparent)] bg-[color-mix(in_srgb,var(--success)_9%,white)] p-3 text-sm text-[var(--success)]" role="status"><?php echo escape($passwordFlash['message']); ?></div>
                <?php endif; ?>

                <?php foreach ($errors as $error): ?>
                    <div class="mb-4 rounded-2xl border border-[color-mix(in_srgb,var(--danger)_22%,transparent)] bg-[color-mix(in_srgb,var(--danger)_8%,white)] p-3 text-sm text-[var(--danger)]" role="alert"><?php echo escape($error); ?></div>
                <?php endforeach; ?>

                <form method="post" class="grid gap-3" id="change-password-form" data-auth-action="change" data-auth-endpoint="<?php echo escape(asset_url('api/auth_password.php')); ?>" data-redirect="<?php echo escape(route_url($dashboardPath)); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo escape(csrf_token()); ?>">

                    <label class="text-sm font-semibold" for="current_password">Current Password</label>
                    <input class="box-border w-full rounded-xl border border-[var(--line)] bg-[var(--surface-strong)] px-3.5 py-3 text-sm text-[var(--ink)] outline-none transition focus:border-[var(--accent)] focus:ring-4 focus:ring-[color-mix(in_srgb,var(--accent)_15%,transparent)]"
                        id="current_password"
                        name="current_password"
                        type="password"
                        autocomplete="current-password"
                        required
                        autofocus
                    >

                    <label class="text-sm font-semibold" for="new_password">New Password</label>
                    <input class="box-border w-full rounded-xl border border-[var(--line)] bg-[var(--surface-strong)] px-3.5 py-3 text-sm text-[var(--ink)] outline-none transition focus:border-[var(--accent)] focus:ring-4 focus:ring-[color-mix(in_srgb,var(--accent)_15%,transparent)]"
                        id="new_password"
                        name="new_password"
                        type="password"
                        autocomplete="new-password"
                        minlength="12"
                        required
                    >

                    <label class="text-sm font-semibold" for="confirm_password">Confirm New Password</label>
                    <input class="box-border w-full rounded-xl border border-[var(--line)] bg-[var(--surface-strong)] px-3.5 py-3 text-sm text-[var(--ink)] outline-none transition focus:border-[var(--accent)] focus:ring-4 focus:ring-[color-mix(in_srgb,var(--accent)_15%,transparent)]"
                        id="confirm_password"
                        name="confirm_password"
                        type="password"
                        autocomplete="new-password"
                        minlength="6"
                        required
                    >

                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-full border-0 bg-[var(--accent)] px-5 py-2.5 font-semibold text-white shadow-md transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent)]">Save Password</button>
                        <a href="<?php echo escape(route_url($dashboardPath)); ?>" class="inline-flex min-h-11 items-center justify-center rounded-full border border-[var(--line)] bg-transparent px-5 py-2.5 font-semibold text-[var(--ink)] no-underline hover:bg-[var(--surface-strong)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--accent)]">Cancel</a>
                    </div>
                </form>
            </article>
        </section>
    </main>
    <script type="module" src="<?php echo escape(asset_url('assets/dist/shared.js')); ?>"></script>
</body>
</html>
