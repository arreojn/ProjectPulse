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
    $statement->execute(['setting_key' => 'sms_gateway_enabled', 'setting_value' => '0']);
    $statement->execute(['setting_key' => 'sms_gateway_local_address', 'setting_value' => '']);
    $statement->execute(['setting_key' => 'sms_gateway_public_address', 'setting_value' => '']);
    $statement->execute(['setting_key' => 'sms_gateway_username', 'setting_value' => '']);
    $statement->execute(['setting_key' => 'sms_gateway_password', 'setting_value' => '']);
    $statement->execute(['setting_key' => 'sms_gateway_device_id', 'setting_value' => '']);

    $bootstrapped = true;
}

function sms_settings(): array
{
    sms_settings_bootstrap();
    $statement = database()->query(
        "SELECT setting_key, setting_value FROM system_settings
         WHERE setting_key IN (
            'sms_gateway_enabled', 'sms_gateway_local_address',
            'sms_gateway_public_address', 'sms_gateway_username',
            'sms_gateway_password', 'sms_gateway_device_id'
         )"
    );

    $settings = [
        'gateway_enabled' => false,
        'gateway_local_address' => '',
        'gateway_public_address' => '',
        'gateway_username' => '',
        'gateway_password' => '',
        'gateway_device_id' => '',
    ];
    foreach ($statement->fetchAll() as $row) {
        $key = $row['setting_key'];
        $value = trim((string) $row['setting_value']);
        if ($key === 'sms_gateway_enabled') {
            $settings['gateway_enabled'] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        } elseif ($key === 'sms_gateway_local_address') {
            $settings['gateway_local_address'] = $value;
        } elseif ($key === 'sms_gateway_public_address') {
            $settings['gateway_public_address'] = $value;
        } elseif ($key === 'sms_gateway_username') {
            $settings['gateway_username'] = $value;
        } elseif ($key === 'sms_gateway_password') {
            $settings['gateway_password'] = $value;
        } elseif ($key === 'sms_gateway_device_id') {
            $settings['gateway_device_id'] = $value;
        }
    }

    return $settings;
}

function sms_settings_save(
    bool $gatewayEnabled = false,
    string $gatewayLocalAddress = '',
    string $gatewayUsername = '',
    string $gatewayPassword = '',
    string $gatewayDeviceId = '',
    string $gatewayPublicAddress = ''
): void {
    sms_settings_bootstrap();

    $statement = database()->prepare(
        'INSERT INTO system_settings (setting_key, setting_value)
         VALUES (:setting_key, :setting_value)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP'
    );
    $statement->execute(['setting_key' => 'sms_gateway_enabled', 'setting_value' => $gatewayEnabled ? '1' : '0']);
    $statement->execute(['setting_key' => 'sms_gateway_local_address', 'setting_value' => $gatewayLocalAddress]);
    $statement->execute(['setting_key' => 'sms_gateway_public_address', 'setting_value' => $gatewayPublicAddress]);
    $statement->execute(['setting_key' => 'sms_gateway_username', 'setting_value' => $gatewayUsername]);
    $statement->execute(['setting_key' => 'sms_gateway_password', 'setting_value' => $gatewayPassword]);
    $statement->execute(['setting_key' => 'sms_gateway_device_id', 'setting_value' => $gatewayDeviceId]);
}


