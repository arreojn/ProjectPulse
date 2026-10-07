<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/sms_gateway.php';

require_roles(['admin']);

$response = '';
$status = '';

if (is_post()) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $status = 'Invalid form token. Please refresh the page.';
    } else {
        $recipient = sms_normalize_phone((string) ($_POST['recipient'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));

        if ($recipient === '' || $message === '') {
            $status = 'Please complete all fields.';
        } else {
            $result = sms_send_gateway_message(
                $recipient,
                $message,
                sms_gateway_settings()
            );
            if ($result['sent']) {
                $status = 'Request sent successfully.';
                $response = json_encode($result['provider_response'], JSON_PRETTY_PRINT);
            } else {
                $status = $result['reason'];
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
