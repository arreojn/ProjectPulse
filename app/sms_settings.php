<?php

declare(strict_types=1);

function sms_settings_bootstrap(): void
{
    static $bootstrapped = false;

    if ($bootstrapped) {
        return;
    }

    database()->exec(
        'CREATE TABLE IF NOT EXISTS system_settings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )'
    );

    $statement = database()->prepare(
        'INSERT IGNORE INTO system_settings (setting_key, setting_value)
         VALUES (:setting_key, :setting_value)'
    );
    $statement->execute(['setting_key' => 'sms_api_endpoint', 'setting_value' => 'https://smsapiph.onrender.com/api/v1/send/sms']);
    $statement->execute(['setting_key' => 'sms_api_key', 'setting_value' => '']);

    $bootstrapped = true;
}

function sms_settings(): array
{
    sms_settings_bootstrap();
    $statement = database()->query(
        "SELECT setting_key, setting_value FROM system_settings
         WHERE setting_key IN ('sms_api_endpoint', 'sms_api_key')"
    );

    $settings = ['endpoint' => '', 'api_key' => ''];
    foreach ($statement->fetchAll() as $row) {
        if ($row['setting_key'] === 'sms_api_endpoint') {
            $settings['endpoint'] = trim((string) $row['setting_value']);
        } elseif ($row['setting_key'] === 'sms_api_key') {
            $settings['api_key'] = trim((string) $row['setting_value']);
        }
    }

    return $settings;
}

function sms_settings_save(string $endpoint, string $apiKey): void
{
    sms_settings_bootstrap();
    $endpoint = trim($endpoint);
    $apiKey = trim($apiKey);

    if (!filter_var($endpoint, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($endpoint), 'https://')) {
        throw new RuntimeException('SMS API endpoint must be a valid HTTPS URL.');
    }

    $current = sms_settings();
    if ($apiKey === '') {
        $apiKey = $current['api_key'];
    }
    if ($apiKey === '') {
        throw new RuntimeException('Enter an SMS API key before saving.');
    }

    $statement = database()->prepare(
        'INSERT INTO system_settings (setting_key, setting_value)
         VALUES (:setting_key, :setting_value)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP'
    );
    $statement->execute(['setting_key' => 'sms_api_endpoint', 'setting_value' => $endpoint]);
    $statement->execute(['setting_key' => 'sms_api_key', 'setting_value' => $apiKey]);
}

