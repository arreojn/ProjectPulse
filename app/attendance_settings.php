<?php

declare(strict_types=1);

function attendance_scan_mode_options(): array
{
    return [
        'strict_windows' => [
            'label' => 'Strict Time Windows',
            'description' => 'Follows the configured AM and PM attendance windows.',
        ],
        'am_pm_sequence' => [
            'label' => 'AM/PM Sequence',
            'description' => 'First scan is time in and second scan is time out within the current half-day.',
        ],
    ];
}

function attendance_settings_bootstrap(): void
{
    static $bootstrapped = false;

    if ($bootstrapped) {
        return;
    }

    $pdo = database();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS system_settings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )'
    );

    $statement = $pdo->prepare(
        'INSERT IGNORE INTO system_settings (setting_key, setting_value)
         VALUES (:setting_key, :setting_value)'
    );
    $statement->execute([
        'setting_key' => 'attendance_scan_mode',
        'setting_value' => 'strict_windows',
    ]);

    $bootstrapped = true;
}

function attendance_scan_mode_normalize(string $mode): string
{
    $options = attendance_scan_mode_options();

    return array_key_exists($mode, $options) ? $mode : 'strict_windows';
}

function attendance_scan_mode_details(?string $mode = null): array
{
    $options = attendance_scan_mode_options();
    $modeKey = attendance_scan_mode_normalize($mode ?? attendance_scan_mode());

    return [
        'key' => $modeKey,
        'label' => $options[$modeKey]['label'],
        'description' => $options[$modeKey]['description'],
    ];
}

function attendance_scan_mode(): string
{
    attendance_settings_bootstrap();

    $statement = database()->prepare(
        'SELECT setting_value
         FROM system_settings
         WHERE setting_key = :setting_key
         LIMIT 1'
    );
    $statement->execute(['setting_key' => 'attendance_scan_mode']);
    $row = $statement->fetch();

    return attendance_scan_mode_normalize((string) ($row['setting_value'] ?? 'strict_windows'));
}

function attendance_scan_mode_set(string $mode): array
{
    attendance_settings_bootstrap();

    $normalizedMode = attendance_scan_mode_normalize(trim($mode));

    if ($normalizedMode !== trim($mode)) {
        throw new RuntimeException('Invalid attendance scan mode.');
    }

    $statement = database()->prepare(
        'INSERT INTO system_settings (setting_key, setting_value)
         VALUES (:setting_key, :setting_value)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            updated_at = CURRENT_TIMESTAMP'
    );
    $statement->execute([
        'setting_key' => 'attendance_scan_mode',
        'setting_value' => $normalizedMode,
    ]);

    return attendance_scan_mode_details($normalizedMode);
}

function attendance_can_manage_scan_mode(array $user): bool
{
    return ($user['role'] ?? '') === 'admin';
}

function attendance_strict_scan_slot_for_time(string $currentTime): ?array
{
    if ($currentTime >= '05:00:00' && $currentTime <= '09:00:00') {
        return ['column' => 'am_time_in', 'label' => 'AM time in'];
    }

    if ($currentTime >= '11:00:00' && $currentTime <= '12:30:00') {
        return ['column' => 'am_time_out', 'label' => 'AM time out'];
    }

    if ($currentTime >= '12:31:00' && $currentTime <= '14:00:00') {
        return ['column' => 'pm_time_in', 'label' => 'PM time in'];
    }

    if ($currentTime >= '15:00:00') {
        return ['column' => 'pm_time_out', 'label' => 'PM time out'];
    }

    return null;
}

function attendance_sequence_scan_slot(string $currentTime, array $record): array
{
    if ($currentTime < '12:00:00') {
        if (empty($record['am_time_in'])) {
            return ['success' => true, 'column' => 'am_time_in', 'label' => 'AM time in'];
        }

        if (empty($record['am_time_out'])) {
            return ['success' => true, 'column' => 'am_time_out', 'label' => 'AM time out'];
        }

        return [
            'success' => false,
            'status' => 409,
            'message' => 'AM attendance has already been completed for today.',
        ];
    }

    if (empty($record['pm_time_in'])) {
        return ['success' => true, 'column' => 'pm_time_in', 'label' => 'PM time in'];
    }

    if (empty($record['pm_time_out'])) {
        return ['success' => true, 'column' => 'pm_time_out', 'label' => 'PM time out'];
    }

    return [
        'success' => false,
        'status' => 409,
        'message' => 'PM attendance has already been completed for today.',
    ];
}

function attendance_resolve_scan_slot(string $mode, string $currentTime, array $record): array
{
    $normalizedMode = attendance_scan_mode_normalize($mode);

    if ($normalizedMode === 'am_pm_sequence') {
        return attendance_sequence_scan_slot($currentTime, $record);
    }

    $slot = attendance_strict_scan_slot_for_time($currentTime);

    if ($slot === null) {
        return attendance_sequence_scan_slot($currentTime, $record);
    }

    return array_merge(['success' => true], $slot);
}
