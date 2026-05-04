<?php
require_once 'config.php';
header('Content-Type: application/json');

$url = $NEWSERV_API_URL . "/y/server";
$data_json = @file_get_contents($url);

$config_url = $NEWSERV_API_URL . "/y/config";
$config_data_json = @file_get_contents($config_url);

if ($data_json === FALSE) {
    echo json_encode([
        'ServerName' => 'Offline',
        'Uptime' => 'Offline',
        'ClientCount' => 'Offline',
        'GameCount' => 'Offline',
        'EXP' => '1',
        'Drop' => '1'
    ]);
} else {
    $data = json_decode($data_json, true);
    if ($config_data_json !== FALSE) {
        $config = json_decode($config_data_json, true);
        $data["BBGlobalEXPMultiplier"] = $config["BBGlobalEXPMultiplier"] ?? 1;
        $data["ServerGlobalDropRateMultiplier"] = $config["ServerGlobalDropRateMultiplier"] ?? 1.0;
    }
    echo json_encode($data);
}
?>
