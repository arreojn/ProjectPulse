<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/sms_gateway.php';

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
}

$normalized = sms_normalize_phone('+63 917 123 4567');
assert_same('639171234567', $normalized, 'Phone numbers should normalize to the gateway format');

$endpoint = sms_gateway_base_url([
    'local_address' => 'http://192.168.100.97:8080/',
    'public_address' => 'https://sms.example.com',
]);
assert_same('http://192.168.100.97:8080', $endpoint, 'Local Address should be preferred');

$endpoint = sms_gateway_base_url([
    'local_address' => '',
    'public_address' => 'https://sms.example.com/',
]);
assert_same('https://sms.example.com', $endpoint, 'Public Address should be used when Local Address is empty');

$payload = sms_gateway_payload('639171234567', 'Attendance notification');
assert_same(['+639171234567'], $payload['phoneNumbers'], 'The gateway payload should include an international number');
assert_same('Attendance notification', $payload['textMessage']['text'], 'The message should be preserved in the gateway payload');
assert_same(true, $payload['withDeliveryReport'], 'Delivery reporting should be enabled');

$message = sms_attendance_notification_message('Maria Santos', 'AM time in', '2026-10-06', '08:00:00');
assert_same(
    'Hello, Maria Santos, your attendance for AM time in on 2026-10-06 at 08:00:00 was recorded. Thank you.',
    $message,
    'The notification message should include learner, attendance slot, and time.'
);

assert_same('ProjectPulse SMS test message.', sms_test_message(), 'The test message should be fixed and identifiable.');

echo "SMS gateway tests passed (8 assertions).\n";
