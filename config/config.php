<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

function projectpulse_normalize_path(string $path): string
{
    return str_replace('\\', '/', rtrim($path, "\\/"));
}

function projectpulse_detect_base_path(): string
{
    $defaultBasePath = '/ProjectPulse';
    $projectRoot = realpath(__DIR__ . '/..');
    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;

    if ($projectRoot === false || $documentRoot === false) {
        return $defaultBasePath;
    }

    $normalizedProjectRoot = projectpulse_normalize_path($projectRoot);
    $normalizedDocumentRoot = projectpulse_normalize_path($documentRoot);

    if ($normalizedProjectRoot === $normalizedDocumentRoot) {
        return '';
    }

    $documentRootPrefix = $normalizedDocumentRoot . '/';

    if (strpos($normalizedProjectRoot, $documentRootPrefix) !== 0) {
        return $defaultBasePath;
    }

    $relativePath = substr($normalizedProjectRoot, strlen($normalizedDocumentRoot));
    $relativePath = $relativePath === false ? '' : trim(str_replace('\\', '/', $relativePath));

    if ($relativePath === '') {
        return '';
    }

    return '/' . trim($relativePath, '/');
}

function projectpulse_detect_scheme(): string
{
    $httpsValue = strtolower((string) ($_SERVER['HTTPS'] ?? ''));

    if ($httpsValue !== '' && $httpsValue !== 'off' && $httpsValue !== '0') {
        return 'https';
    }

    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));

    if ($forwardedProto !== '') {
        return $forwardedProto;
    }

    return 'http';
}

function projectpulse_detect_host(): string
{
    $forwardedHost = trim((string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''));

    if ($forwardedHost !== '') {
        return $forwardedHost;
    }

    $httpHost = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));

    if ($httpHost !== '') {
        return $httpHost;
    }

    $serverName = trim((string) ($_SERVER['SERVER_NAME'] ?? ''));
    $serverPort = trim((string) ($_SERVER['SERVER_PORT'] ?? ''));

    if ($serverName === '') {
        return 'localhost';
    }

    if ($serverPort === '' || in_array($serverPort, ['80', '443'], true)) {
        return $serverName;
    }

    return $serverName . ':' . $serverPort;
}

function projectpulse_detect_base_url(): string
{
    return projectpulse_detect_scheme() . '://' . projectpulse_detect_host() . projectpulse_detect_base_path();
}

define('APP_NAME', 'ProjectPulse');
define('APP_BASE_PATH', projectpulse_detect_base_path());
define('BASE_URL', projectpulse_detect_base_url());
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'project_pulse');
define('DB_USER', 'root');
define('DB_PASS', '');
