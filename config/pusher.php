<?php
require_once __DIR__ . '/../vendor/autoload.php';

$pusher = new Pusher\Pusher(
    '196af0f1dd479f60be9c',      // Replace with your key
    '75ff8d8b9b97053d1388',   // Replace with your secret
    '2119726',   // Replace with your app id
    [
        'cluster' => 'ap1', // e.g., 'ap1'
        'useTLS' => true
    ]
);

return $pusher;
?>

<!-- app_id = "2119726"
key = "196af0f1dd479f60be9c"
secret = "75ff8d8b9b97053d1388"
cluster = "ap1" -->