<?php

$config = require 'config.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    exit("Invalid request.");
}

$phone = trim($_POST["phone"] ?? "");
$message = trim($_POST["message"] ?? "");

if ($phone === "" || $message === "") {
    exit("Phone number and message are required.");
}

// Remove spaces, dashes, etc.
$phone = preg_replace('/[^0-9+]/', '', $phone);

if (str_starts_with($phone, '+')) {
    $phone = substr($phone, 1);
}

if (!preg_match('/^639\d{9}$/', $phone)) {
    exit("Invalid Philippine mobile number.");
}

$url = $config['server'] . "/message";

$payload = [
    "phoneNumbers" => [$phone],
    "textMessage" => [
        "text" => $message
    ],
    "withDeliveryReport" => true,
];

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_USERPWD => $config['username'] . ":" . $config['password'],
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$error = curl_error($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>SMS Result</title>
<style>
body{font-family:Arial;background:#f5f5f5;padding:40px;}
.card{max-width:700px;margin:auto;background:#fff;padding:25px;border-radius:10px;box-shadow:0 5px 15px rgba(0,0,0,.1);}
.ok{color:green;}
.bad{color:red;}
pre{background:#eee;padding:15px;border-radius:8px;overflow:auto;}
a{display:inline-block;margin-top:20px;}
</style>
</head>
<body>

<div class="card">

<h2>SMS Result</h2>

<?php if($error): ?>

<p class="bad"><strong>Connection Error:</strong> <?= htmlspecialchars($error) ?></p>

<?php else: ?>

<p><strong>HTTP Status:</strong> <?= $status ?></p>

<?php if($status >= 200 && $status < 300): ?>
<p class="ok">Message submitted successfully.</p>
<?php else: ?>
<p class="bad">Message submission failed.</p>
<?php endif; ?>

<h3>Gateway Response</h3>

<pre><?= htmlspecialchars($response) ?></pre>

<?php endif; ?>

<a href="index.php">← Back</a>

</div>

</body>
</html>