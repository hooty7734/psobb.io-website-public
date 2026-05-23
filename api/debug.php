<?php
require_once 'config.php';
header('Content-Type: text/plain');

echo "Debug Info:\n";
echo "Target URL: " . $NEWSERV_API_URL . "/y/server\n";
echo "PHP Version: " . phpversion() . "\n";
echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'On' : 'Off') . "\n";

$options = [
    'http' => [
        'timeout' => 5,
        'ignore_errors' => true // Fetch content even on 404/500
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ]
];
$context = stream_context_create($options);

echo "\nAttempting connection to $NEWSERV_API_URL/y/server ...\n";
$data = file_get_contents($NEWSERV_API_URL . "/y/server", false, $context);

if ($data === FALSE) {
    echo "Connection FAILED.\n";
    $error = error_get_last();
    echo "Last Error: " . ($error['message'] ?? 'Unknown error') . "\n";
    
    // Try localhost if we used IP, or vice versa
    $host = parse_url($NEWSERV_API_URL, PHP_URL_HOST);
    $alt_host = ($host === '127.0.0.1') ? 'localhost' : '127.0.0.1';
    $alt_url = str_replace($host, $alt_host, $NEWSERV_API_URL);
    
    echo "\nRetrying with alternate host ($alt_host)...\n";
    $data = file_get_contents($alt_url . "/y/server", false, $context);
    
    if ($data === FALSE) {
        echo "Alternate Connection FAILED.\n";
        $error = error_get_last();
        echo "Last Error: " . ($error['message'] ?? 'Unknown error') . "\n";
    } else {
        echo "Alternate Connection SUCCESS.\n";
        echo "Use this hostname in config.php instead.\n";
        echo "Response: " . substr($data, 0, 100) . "...\n";
    }

} else {
    echo "Connection SUCCESS.\n";
    echo "Response code (headers): " . ($http_response_header[0] ?? 'Unknown') . "\n";
    echo "Response length: " . strlen($data) . "\n";
    echo "Response data: " . substr($data, 0, 200) . "...\n";
}
?>
