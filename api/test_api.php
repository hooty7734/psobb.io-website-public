<?php
require_once __DIR__ . '/config.php';

header("Content-Type: application/json");

$options = [
    'http' => [
        'header' => "Content-type: application/json\r\n",
        'method' => 'GET',
        'timeout' => 5
    ]
];
$context = stream_context_create($options);

$out = [];

$clients_raw = @file_get_contents($NEWSERV_API_URL . '/y/clients', false, $context);
$out['endpoint_y_clients'] = $clients_raw ? json_decode($clients_raw, true) : "Failed to fetch /y/clients";

$summary_raw = @file_get_contents($NEWSERV_API_URL . '/y/summary', false, $context);
$out['endpoint_y_summary'] = $summary_raw ? json_decode($summary_raw, true) : "Failed to fetch /y/summary";

echo json_encode($out, JSON_PRETTY_PRINT);
?>
