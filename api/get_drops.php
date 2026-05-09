<?php
require_once 'config.php';
header('Content-Type: application/json');

$endpoint = '/y/data/rare-table/rare-table-v4';
$url = $NEWSERV_API_URL . $endpoint;

$cache_dir = __DIR__ . '/../scratch';
if (!is_dir($cache_dir)) {
    @mkdir($cache_dir, 0777, true);
}
$cache_file = $cache_dir . '/rare_table_cache.json';
$cache_ttl = 86400; // 24 hours

$data_json = false;

// Check cache first
if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_ttl) {
    $cached = file_get_contents($cache_file);
    $decoded_cache = @json_decode($cached, true);
    if ($decoded_cache !== null) {
        if (!isset($decoded_cache['success'])) {
            $data_json = json_encode(["success" => true, "data" => $decoded_cache]);
            @file_put_contents($cache_file, $data_json); // Update cache with wrapped version
        } else {
            $data_json = $cached;
        }
    }
}

if ($data_json === false) {
    // Attempt to fetch from Newserv
    // Increase timeout since user mentioned it's slow
    $ctx = stream_context_create(['http' => ['timeout' => 15]]);
    $res = @file_get_contents($url, false, $ctx);
    
    if ($res !== false) {
        // Fix non-standard hex syntax: Newserv's JSON might contain unquoted hex values like 0x010203
        // Standard json_decode() will fail on these, so we wrap them in quotes first.
        $clean_json = preg_replace('/(?<=:|,|\s|\[)(0x[a-fA-F0-9]+)(?=,|\]|\}|\s)/', '"$1"', $res);
        
        $decoded = @json_decode($clean_json, true);
        if ($decoded !== null) {
            // Newserv returns a raw array. Wrap it in our standard API response.
            if (!isset($decoded['success'])) {
                $data_json = json_encode(["success" => true, "data" => $decoded]);
            } else {
                $data_json = $clean_json;
            }
            // Save to cache
            @file_put_contents($cache_file, $data_json);
        } else {
            // Log if it still fails to parse
            error_log("[Drops API] Failed to parse JSON even after hex cleanup. JSON Error: " . json_last_error_msg());
        }
    }
}

if ($data_json !== false) {
    echo $data_json;
    exit;
}

// Fallback Mock Data
$mock_drops = [
    ["episode" => 1, "difficulty" => "Ultimate", "section_id" => "Skyly", "monster" => "Hildebear", "item" => "Sealed J-Sword", "rate" => "1/12604", "rate_percent" => 0.0079],
    ["episode" => 1, "difficulty" => "Ultimate", "section_id" => "Redria", "monster" => "Hildebear", "item" => "Magic Stone 'Iritista'", "rate" => "1/1050", "rate_percent" => 0.095],
    ["episode" => 1, "difficulty" => "Ultimate", "section_id" => "Viridia", "monster" => "Booma", "item" => "Agito (1975)", "rate" => "1/28807", "rate_percent" => 0.003],
    ["episode" => 2, "difficulty" => "Ultimate", "section_id" => "Whitill", "monster" => "Ill Gill", "item" => "Syncesta", "rate" => "1/12604", "rate_percent" => 0.0079],
    ["episode" => 4, "difficulty" => "Ultimate", "section_id" => "Purplenum", "monster" => "Kondrieu", "item" => "Heaven Striker", "rate" => "1/12", "rate_percent" => 8.33]
];

for ($i=0; $i<100; $i++) {
    $eps = [1, 2, 4];
    $diffs = ["Normal", "Hard", "Very Hard", "Ultimate"];
    $sids = ["Viridia", "Greenill", "Skyly", "Bluefull", "Purplenum", "Pinkal", "Redria", "Oran", "Yellowboze", "Whitill"];
    $monsters = ["Booma", "Goboom", "Gigobooma", "Hildebear", "Hildelt", "Rappy", "Al Rappy", "Monest", "Mothmant", "Savage Wolf", "Barbarous Wolf"];
    $items = ["Saber", "Brand", "Buster", "Pallasch", "Gladius", "Agito", "Handgun", "Autogun", "Lockgun", "Railgun"];
    $rate_base = rand(50, 50000);
    $rate = "1/" . $rate_base;
    $rate_pct = round(1 / $rate_base * 100, 4);

    $mock_drops[] = [
        "episode" => $eps[array_rand($eps)],
        "difficulty" => $diffs[array_rand($diffs)],
        "section_id" => $sids[array_rand($sids)],
        "monster" => $monsters[array_rand($monsters)],
        "item" => $items[array_rand($items)],
        "rate" => $rate,
        "rate_percent" => $rate_pct
    ];
}

echo json_encode(["success" => true, "data" => $mock_drops, "mock" => true]);
