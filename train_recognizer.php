<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/helpers.php';

function train_face_recognizer(): array
{
    $outputLog = [];

    try {
        $script = realpath(__DIR__ . '/ai_scanner/identify_face.py');
        if ($script === false) {
            throw new RuntimeException('Recognition script is missing.');
        }

        $python = face_recognition_python();
        $command = escapeshellarg($python) . ' ' . escapeshellarg($script) . ' --rebuild-cache 2>&1';
        $commandOutput = [];
        $exitCode = 0;
        exec($command, $commandOutput, $exitCode);
        $result = json_decode(implode("\n", $commandOutput), true);

        if ($exitCode !== 0 || !is_array($result) || !($result['ok'] ?? false)) {
            throw new RuntimeException(is_array($result) ? ($result['error'] ?? 'Recognition cache could not be built.') : 'Recognition process failed. Install the Python scanner requirements.');
        }

        $outputLog[] = 'Face-encoding cache rebuilt.';
        $outputLog[] = 'Usable learner registrations: ' . (string) ($result['registered'] ?? 0);
        if (($result['registered'] ?? 0) === 0) {
            throw new RuntimeException('No usable learner face registrations were found. Capture a clear learner photo first.');
        }
        if (face_recognition_start_service()) {
            face_recognition_service_request('reload', '');
            $outputLog[] = 'Fast recognition service is ready.';
        }
        return ['success' => true, 'log' => $outputLog];
    } catch (Throwable $e) {
        $outputLog[] = "\nAn error occurred: " . $e->getMessage();
        return ['success' => false, 'log' => $outputLog, 'error' => $e->getMessage()];
    }
}

if (php_sapi_name() === 'cli') {
    echo "Starting face recognizer training...\n";
    $result = train_face_recognizer();
    echo implode("\n", $result['log']);
    echo "\n";
    exit($result['success'] ? 0 : 1);
}
