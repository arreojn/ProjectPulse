<?php

$config = require 'config.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    exit("Invalid request.");
}

$phone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
$message = trim($_POST['message'] ?? '');

if (!preg_match('/^639\d{9}$/', $phone)) {
    exit("Invalid phone number.");
}

$url = $config['server'] . "/message";

$payload = [
    "phoneNumbers" => [
        "+" . $phone
    ],
    "textMessage" => [
        "text" => $message
    ],
    "withDeliveryReport" => true
];

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_USERPWD => $config['username'] . ":" . $config['password'],
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Accept: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$error = curl_error($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

echo "<h2>SMS Result</h2>";

if ($error) {
    echo "<pre>$error</pre>";
    exit;
}

echo "<p><strong>HTTP Status:</strong> $status</p>";

echo "<h3>Request URL</h3>";
echo "<pre>$url</pre>";

echo "<h3>Payload</h3>";
echo "<pre>" . htmlspecialchars(json_encode($payload, JSON_PRETTY_PRINT)) . "</pre>";

echo "<h3>Gateway Response</h3>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";