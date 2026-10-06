<?php

$config = require 'config.php';

$url = $config['server'] . "/message";

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => "OPTIONS",
    CURLOPT_HEADER => true
]);

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

echo "<h2>Gateway Test</h2>";
echo "<p>HTTP Status: $status</p>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";