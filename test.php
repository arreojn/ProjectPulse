<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/sms_settings.php';

require_roles(['admin']);

$response = '';
$status = '';
$settings = sms_settings();

if (is_post()) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $status = 'Invalid form token. Please refresh the page.';
    } else {
        $recipient = trim((string) ($_POST['recipient'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));

        if ($recipient === '' || $message === '') {
            $status = 'Please complete all fields.';
        } elseif ($settings['endpoint'] === '' || $settings['api_key'] === '') {
            $status = 'Configure the SMS endpoint and API key in Admin Settings first.';
        } else {
            $ch = curl_init($settings['endpoint']);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => [
                    'x-api-key: ' . $settings['api_key'],
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'recipient' => $recipient,
                    'message' => $message,
                ], JSON_THROW_ON_ERROR),
            ]);

            $rawResponse = curl_exec($ch);
            $httpStatus = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($rawResponse === false || $curlError !== '') {
                $status = 'SMS request failed. Please check the provider settings.';
            } else {
                $response = (string) $rawResponse;
                $status = $httpStatus >= 200 && $httpStatus < 300
                    ? 'Request sent successfully.'
                    : 'The SMS provider rejected the request.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>SMS API Test</title>
</head>
<body>
<main>
<h1>SMS API Test</h1>
<?php if ($status !== ''): ?><p><?php echo escape($status); ?></p><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo escape(csrf_token()); ?>">
<label>Recipient Number <input type="text" name="recipient" required></label>
<label>Message <textarea name="message" required></textarea></label>
<button type="submit">Send SMS</button>
</form>
<?php if ($response !== ''): ?><pre><?php echo escape($response); ?></pre><?php endif; ?>
</main>
</body>
</html>
