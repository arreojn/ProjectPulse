<?php

declare(strict_types=1);

function escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function projectpulse_app_path_prefix(): string
{
    return APP_BASE_PATH === '' ? '' : rtrim(APP_BASE_PATH, '/');
}

function asset_url(string $path): string
{
    return projectpulse_app_path_prefix() . '/' . ltrim($path, '/');
}

function school_logo_url(): string
{
    $preferredPath = __DIR__ . '/../assets/images/logo.png';

    return asset_url(file_exists($preferredPath) ? 'assets/images/logo.png' : 'assets/images/logo.png.png');
}

function learner_photo_url(?string $lrn, string $defaultAsset = 'assets/images/learners/default.jpg'): string
{
    $normalizedLrn = preg_replace('/\D+/', '', trim((string) $lrn)) ?? '';
    $photoDirectory = __DIR__ . '/../assets/images/learners/';

    if ($normalizedLrn !== '') {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
            $candidatePath = $photoDirectory . $normalizedLrn . '.' . $extension;

            if (is_file($candidatePath)) {
                return asset_url('assets/images/learners/' . $normalizedLrn . '.' . $extension);
            }
        }
    }

    $defaultPath = __DIR__ . '/../' . ltrim($defaultAsset, '/');

    return asset_url(is_file($defaultPath) ? $defaultAsset : 'assets/images/learners/logorotate.gif');
}

function route_url(string $path): string
{
    return projectpulse_app_path_prefix() . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . route_url($path));
    exit;
}

function start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function is_post(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function csrf_token(): string
{
    start_session();

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool
{
    start_session();

    return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

function flash_set(string $key, string $message, string $type = 'success'): void
{
    start_session();
    $_SESSION['flash_messages'][$key] = [
        'message' => $message,
        'type' => $type,
    ];
}

function theme_stylesheet_markup(): string
{
    require_once __DIR__ . '/theme_settings.php';

    return theme_inline_styles();
}

function flash_get(string $key): ?array
{
    start_session();

    if (!isset($_SESSION['flash_messages'][$key]) || !is_array($_SESSION['flash_messages'][$key])) {
        return null;
    }

    $flash = $_SESSION['flash_messages'][$key];
    unset($_SESSION['flash_messages'][$key]);

    return $flash;
}
