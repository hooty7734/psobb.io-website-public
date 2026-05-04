<?php
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';

header('Content-Type: application/json');

$headers = getallheaders();
$auth = $headers['Authorization'] ?? '';

// Authenticate via Bearer header only (never accept secrets in query strings)

if ($auth !== "Bearer $BOT_API_SECRET" && $auth !== $BOT_API_SECRET) {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'link') {
    $username = $_POST['username'] ?? '';
    $discord_id = $_POST['discord_id'] ?? '';
    
    if (!$username || !$discord_id) {
        echo json_encode(["error" => "Missing data"]);
        exit;
    }
    
    $db = get_db();
    $stmt = $db->prepare("UPDATE users SET discord_id = :discord_id WHERE username = :username");
    $stmt->bindValue(':discord_id', $discord_id, SQLITE3_TEXT);
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    if ($db->changes() > 0) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["error" => "User not found or already linked"]);
    }
} elseif ($action === 'get_player') {
    $discord_id = $_GET['discord_id'] ?? '';
    
    if (!$discord_id) {
        echo json_encode(["error" => "Missing discord_id"]);
        exit;
    }
    
    $db = get_db();
    $stmt = $db->prepare("SELECT account_id, username, language FROM users WHERE discord_id = :discord_id");
    $stmt->bindValue(':discord_id', $discord_id, SQLITE3_TEXT);
    $res = $stmt->execute();
    $user = $res->fetchArray(SQLITE3_ASSOC);
    
    global $PSO_LANG;
    $PSO_LANG = $user['language'] ?? 'en';
    require_once 'lang.php';
    
    if (!$user) {
        echo json_encode(["error" => "Not linked"]);
        exit;
    }
    
    // Fetch from newserv /y/accounts for basic data
    $url = $NEWSERV_API_URL . "/y/accounts";
    $data = @file_get_contents($url);
    $response_data = ["website_username" => $user['username'], "account_id" => $user['account_id'], "is_online" => false, "Characters" => [], "website_stats" => []];
    
    if ($data) {
        $accounts = json_decode($data, true);
        foreach ($accounts as $acc) {
            if ($acc['AccountID'] == $user['account_id']) {
                unset($acc['BBLicenses']);
                unset($acc['GCLicenses']);
                $response_data = array_merge($response_data, $acc);
                break;
            }
        }
    }
    
    // Fetch live character stats if online
    $clients_url = $NEWSERV_API_URL . "/y/clients";
    $clients_data = @file_get_contents($clients_url);
    if ($clients_data) {
        $clients = json_decode($clients_data, true);
        foreach ($clients as $client) {
            if (isset($client['Account']['AccountID']) && $client['Account']['AccountID'] == $user['account_id']) {
                $response_data['is_online'] = true;
                // Add the active character to the list
                if (isset($client['Name'])) {
                    $response_data['Characters'][] = [
                        "Name" => $client['Name'],
                        "Level" => $client['Level'] ?? 1,
                        "Class" => $client['CharClass'] ?? 'Unknown',
                        "Meseta" => $client['Meseta'] ?? 0,
                        "PlayTimeSeconds" => $client['PlayTimeSeconds'] ?? 0,
                        "EXP" => $client['EXP'] ?? 0,
                        "SectionID" => $client['SectionID'] ?? 'Unknown'
                    ];
                }
            }
        }
    }
    
    // Fetch website DB stats
    $stmt = $db->prepare("SELECT COUNT(*) as login_days FROM daily_logins WHERE account_id = :acc");
    $stmt->bindValue(':acc', $user['account_id'], SQLITE3_INTEGER);
    $login_res = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    $response_data['website_stats']['total_login_days'] = $login_res['login_days'] ?? 0;
    
    // Check missions
    $stmt = $db->prepare("SELECT m.title, m.description, m.goal_type, m.goal_target, pm.status FROM player_missions pm JOIN missions m ON pm.mission_id = m.id WHERE pm.account_id = :acc");
    $stmt->bindValue(':acc', $user['account_id'], SQLITE3_INTEGER);
    $missions_res = $stmt->execute();
    $missions = [];
    while ($m = $missions_res->fetchArray(SQLITE3_ASSOC)) {
        // Hex to string conversion logic for Discord Bot
        $target = $m['goal_target'];
        $type = $m['goal_type'];
        
        $friendly_obj = "";
        switch ($type) {
            case 'MESETA': 
                if ($target === 'ANY') $friendly_obj = __('Collect Meseta (Any source)');
                else $friendly_obj = __('Hold at least %s Meseta in inventory', number_format((int)$target));
                break;
            case 'LEVEL': $friendly_obj = __('Reach Level %s', htmlspecialchars($target)); break;
            case 'ITEM':
                $parts = explode(':', $target, 2);
                $itemName = isset($parts[1]) ? $parts[1] : $target;
                $itemName = explode(' ', $itemName)[0];
                if (ctype_xdigit($itemName) && strlen($itemName) >= 6) {
                    $hex_base = substr($itemName, 0, 6);
                    $map_path = __DIR__ . '/item_map.json';
                    if (file_exists($map_path)) {
                        $map = json_decode(file_get_contents($map_path), true);
                        $reverse_map = array_flip($map);
                        if (isset($reverse_map[$hex_base])) {
                            $itemName = ucwords($reverse_map[$hex_base]);
                        }
                    }
                }
                $friendly_obj = __('Find and hold the item: %s', htmlspecialchars(__($itemName)));
                break;
            case 'EXPLORATION':
                $floors = [1=>'Forest 1',2=>'Forest 2',3=>'Cave 1',4=>'Cave 2',5=>'Cave 3',6=>'Mine 1',7=>'Mine 2',8=>'Ruins 1',9=>'Ruins 2',10=>'Ruins 3'];
                $loc = $floors[$target] ?? "Floor $target";
                $friendly_obj = __('Explore the %s', htmlspecialchars(__($loc)));
                break;
            case 'BOSS_ARENA':
                $bosses = [11=>'Dragon (Forest)', 12=>'De Rol Le (Caves)', 13=>'Vol Opt (Mines)', 14=>'Dark Falz (Ruins)', 17=>'Barba Ray (Temple)', 16=>'Gol Dragon (Spaceship)', 15=>'Gal Gryphon (CCA)', 18=>'Olga Flow (Seabed)'];
                $boss = $bosses[$target] ?? "Boss at Floor $target";
                $friendly_obj = __('Defeat the %s', htmlspecialchars(__($boss)));
                break;
            default:
                $friendly_obj = htmlspecialchars($type) . ": " . htmlspecialchars($target);
        }
        
        $m['friendly_objective'] = $friendly_obj;
        $missions[] = $m;
    }
    $response_data['website_stats']['missions'] = $missions;
    $response_data['language'] = $user['language'] ?? 'en';
    
    echo json_encode($response_data);
} elseif ($action === 'get_events') {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM community_events WHERE status = 'active' ORDER BY created_at DESC");
    $result = $stmt->execute();
    
    $events = [];
    require_once 'lang.php';
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $events[] = [
            "id" => $row['id'],
            "title" => $row['title'],
            "description" => $row['description'],
            "goalType" => $row['goal_type'],
            "goalTarget" => $row['goal_target'],
            "targetAmount" => (int)$row['target_amount'],
            "currentProgress" => (int)$row['current_progress'],
            "rewardItemString" => $row['reward_item_string'],
            "friendly_objective" => getClearObjective($row['goal_type'], $row['goal_target']),
            "friendly_reward" => renderRewardString($row['reward_item_string']),
            "status" => $row['status']
        ];
    }
    
    echo json_encode($events);
}
