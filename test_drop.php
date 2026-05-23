<?php
require_once __DIR__ . '/api/config.php';

$accountId = 100; // Replace with an active account ID if you have one online

$items = [
    "? Charge Calibur +5 20/0/0/50",
    "Celestial Armor +4",
    "Divinity Barrier +10def +5evp"
];

foreach ($items as $itemString) {
    $cmd = "on " . $accountId . " cc {$NEWSERV_COMMAND_PREFIX}item " . $itemString;
    $url = $NEWSERV_API_URL . "/y/shell-exec";
    $body = json_encode(['command' => $cmd]);
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => $body,
            'ignore_errors' => true
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ];
    $ctx = stream_context_create($opts);
    $execRes = file_get_contents($url, false, $ctx);
    echo "Item: $itemString\nResult: $execRes\n\n";
}
?>
