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

function face_recognition_python(): string
{
    $configuredPython = getenv('PROJECTPULSE_FACE_PYTHON') ?: getenv('AI_ATTENDANCE_PYTHON');

    if (is_string($configuredPython) && $configuredPython !== '') {
        return $configuredPython;
    }

    // Windows' per-user Python 3.11 location provides a useful XAMPP-local
    // fallback while deployments can still configure PROJECTPULSE_FACE_PYTHON.
    $localAppData = getenv('LOCALAPPDATA');
    if (is_string($localAppData) && $localAppData !== '') {
        $python311 = rtrim($localAppData, "\\/") . '/Programs/Python/Python311/python.exe';
        if (is_file($python311)) {
            return $python311;
        }
    }

    return 'python';
}

function face_recognition_service_request(string $path, ?string $body = null): ?array
{
    if (!extension_loaded('curl')) {
        return null;
    }

    $serviceUrl = rtrim((string) (getenv('PROJECTPULSE_FACE_SERVICE_URL') ?: 'http://127.0.0.1:8765'), '/');
    $curl = curl_init($serviceUrl . '/' . ltrim($path, '/'));
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT_MS => 300,
        CURLOPT_TIMEOUT_MS => 4000,
    ]);

    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: image/jpeg', 'Content-Length: ' . strlen($body)]);
    }

    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    $payload = is_string($response) ? json_decode($response, true) : null;

    return is_array($payload) ? ['status' => $status, 'payload' => $payload] : null;
}

function face_recognition_start_service(): bool
{
    if (face_recognition_service_request('health') !== null) {
        return true;
    }

    $script = realpath(__DIR__ . '/../ai_scanner/recognition_service.py');
    if ($script === false) {
        return false;
    }

    $command = 'cmd /c start "" /B ' . escapeshellarg(face_recognition_python()) . ' ' . escapeshellarg($script) . ' --port 8765';
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    for ($attempt = 0; $attempt < 12; $attempt++) {
        usleep(150000);
        if (face_recognition_service_request('health') !== null) {
            return true;
        }
    }

    return false;
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
