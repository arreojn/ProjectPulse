<?php

$config = require 'config.php';

$paths = ['/message', '/docs'];

foreach ($paths as $path) {

    $ch = curl_init($config['server'] . $path);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => true,
        CURLOPT_HEADER => true
    ]);

    curl_exec($ch);

    echo $path . " => " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . "<br>";

    curl_close($ch);
}