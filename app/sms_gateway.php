<?php

declare(strict_types=1);

function sms_normalize_phone(string $phone): string
{
    $phone = preg_replace('/[^0-9+]/', '', trim($phone));
    if ($phone === null) {
        return '';
    }
    if (str_starts_with($phone, '+')) {
        $phone = substr($phone, 1);
    }

    if (preg_match('/^0\d{10}$/', $phone) === 1) {
        $phone = '63' . substr($phone, 1);
    } elseif (preg_match('/^9\d{9}$/', $phone) === 1) {
        $phone = '63' . $phone;
    }

    return preg_match('/^639\d{9}$/', $phone) === 1 ? $phone : '';
}

function sms_gateway_payload(string $phone, string $message): array
{
    return [
        'phoneNumbers' => ['+' . $phone],
        'textMessage' => ['text' => $message],
        'withDeliveryReport' => true,
    ];
}

function sms_test_message(): string
{
    return 'ProjectPulse SMS test message.';
}

function sms_attendance_notification_message(
    string $learnerName,
    string $slotLabel,
    string $attendanceDate,
    string $attendanceTime
): string {
    return sprintf(
        'Hello, %s, your attendance for %s on %s at %s was recorded. Thank you.',
        $learnerName,
        $slotLabel,
        $attendanceDate,
        $attendanceTime
    );
}

function sms_gateway_settings(): array
{
    require_once __DIR__ . '/sms_settings.php';

    $settings = sms_settings();
    return [
        'enabled' => (bool) ($settings['gateway_enabled'] ?? false),
        'local_address' => trim((string) ($settings['gateway_local_address'] ?? '')),
        'public_address' => trim((string) ($settings['gateway_public_address'] ?? '')),
        'username' => trim((string) ($settings['gateway_username'] ?? '')),
        'password' => trim((string) ($settings['gateway_password'] ?? '')),
        'device_id' => trim((string) ($settings['gateway_device_id'] ?? '')),
    ];
}

function sms_gateway_base_url(array $settings): string
{
    $localAddress = trim((string) ($settings['local_address'] ?? ''));
    $publicAddress = trim((string) ($settings['public_address'] ?? ''));
    $address = $localAddress !== '' ? $localAddress : $publicAddress;

    if ($address === '') {
        return '';
    }

    return rtrim($address, '/');
}

function sms_send_gateway_message(string $phone, string $message, array $settings): array
{
    if (!$settings['enabled']) {
        return ['sent' => false, 'reason' => 'SMS gateway is disabled.'];
    }
    if ($phone === '') {
        return ['sent' => false, 'reason' => 'The recipient phone number is invalid.'];
    }
    if ($settings['username'] === '' || $settings['password'] === '') {
        return ['sent' => false, 'reason' => 'SMS gateway credentials are incomplete.'];
    }

    $baseUrl = sms_gateway_base_url($settings);
    if ($baseUrl === '') {
        return ['sent' => false, 'reason' => 'SMS gateway address is not configured.'];
    }

    $url = $baseUrl . '/message';
    $payload = sms_gateway_payload($phone, $message);
    $ch = curl_init($url);

    if ($ch === false) {
        return ['sent' => false, 'reason' => 'Could not initialize SMS gateway client.'];
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $settings['username'] . ':' . $settings['password'],
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $error !== '') {
        $diagnostic = trim((string) $error);
        if ($diagnostic === '') {
            $diagnostic = 'No HTTP response received';
        }
        return [
            'sent' => false,
            'reason' => 'SMS gateway connection failed at ' . $url . ': ' . $diagnostic,
        ];
    }

    $responseData = json_decode((string) $response, true);
    if ($status < 200 || $status >= 300) {
        return [
            'sent' => false,
            'reason' => 'SMS gateway rejected the request (HTTP ' . $status . ').',
            'provider_response' => $responseData,
        ];
    }

    return [
        'sent' => true,
        'status' => $status,
        'provider_response' => $responseData,
    ];
}

function sms_send_test_message(string $phone): array
{
    $settings = sms_gateway_settings();
    return sms_send_gateway_message(
        sms_normalize_phone($phone),
        sms_test_message(),
        $settings
    );
}

function sms_send_attendance_notification(
    string $learnerName,
    string $guardianPhone,
    string $slotLabel,
    string $attendanceDate,
    string $attendanceTime
): array {
    $settings = sms_gateway_settings();
    $phone = sms_normalize_phone($guardianPhone);
    $message = sms_attendance_notification_message(
        $learnerName,
        $slotLabel,
        $attendanceDate,
        $attendanceTime
    );

    return sms_send_gateway_message($phone, $message, $settings);
}
