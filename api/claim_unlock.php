<?php
require_once 'config.php';
require_once 'db.php';
require_once 'reward_tables.php';

if (ob_get_length()) ob_clean();
start_secure_session();
header('Content-Type: application/json');

if (empty($_SESSION['user']) || empty($_SESSION['user']['account_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}
$accountId = $_SESSION['user']['account_id'];

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$milestone = intval($input['level'] ?? 0);
$category = $input['category'] ?? '';

$validCategories = ['Weapon', 'Armor', 'Shield', 'Mag', 'Random'];

if ($milestone < 5 || $milestone % 5 !== 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid milestone level."]);
    exit;
}
if (!in_array($category, $validCategories)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid reward category."]);
    exit;
}

// 1. Fetch online clients from newserv to verify level
$url = $NEWSERV_API_URL . "/y/clients";
$data = @file_get_contents($url);

if ($data === FALSE) {
    http_response_code(500);
    echo json_encode(["error" => "Game server is offline, cannot verify character."]);
    exit;
}

$clients = json_decode($data, true);
$onlineCharacter = null;

if (is_array($clients)) {
    foreach ($clients as $c) {
        if (isset($c['Account']) && $c['Account']['AccountID'] == $accountId) {
            $onlineCharacter = $c;
            break;
        }
    }
}

if (!$onlineCharacter) {
    http_response_code(400);
    echo json_encode(["error" => "Character must be online to claim rewards."]);
    exit;
}

$lobbyId = $onlineCharacter['LobbyID'] ?? null;
if ($lobbyId === null) {
    http_response_code(400);
    echo json_encode(["error" => "Character must be actively logged into a server lobby or game."]);
    exit;
}

// 1.5 Fetch lobbies to ensure the player is in an actual game, not just the lobby
$lobbiesData = @file_get_contents($NEWSERV_API_URL . "/y/lobbies");
if ($lobbiesData === FALSE) {
    http_response_code(500);
    echo json_encode(["error" => "Game server lobbies offline, cannot verify game status."]);
    exit;
}

$lobbies = json_decode($lobbiesData, true);
$inGame = false;

if (is_array($lobbies)) {
    foreach ($lobbies as $l) {
        if (isset($l['ID']) && $l['ID'] === $lobbyId) {
            if (!empty($l['IsGame'])) {
                $inGame = true;
            }
            break;
        }
    }
}

if (!$inGame) {
    http_response_code(400);
    echo json_encode(["error" => "You must be actively inside a game (not in a lobby) to claim a reward."]);
    exit;
}

$level = $onlineCharacter['Level'] ?? 1;
$name = $onlineCharacter['Name'] ?? 'Unknown';
$charClass = $onlineCharacter['CharClass'] ?? 'HUmar';

if ($level < $milestone) {
    http_response_code(400);
    echo json_encode(["error" => "Your character's level ($level) is too low for this milestone ($milestone)."]);
    exit;
}

try {
    $db = get_db();
    
    // 2. Check if already claimed
    $stmt = $db->prepare("SELECT id FROM rewards_claimed WHERE account_id = :aid AND character_name = :cname AND level_milestone = :lvl");
    $stmt->bindValue(':aid', $accountId, SQLITE3_INTEGER);
    $stmt->bindValue(':cname', $name, SQLITE3_TEXT);
    $stmt->bindValue(':lvl', $milestone, SQLITE3_INTEGER);
    $res = $stmt->execute();
    if ($res->fetchArray()) {
        http_response_code(400);
        echo json_encode(["error" => "Reward previously claimed for this level milestone on this character."]);
        exit;
    }

    $iterations = ($category === 'Random') ? 3 : 1;
    $droppedItems = [];

    for ($iter = 0; $iter < $iterations; $iter++) {
        // 3.5. Weapon/Armor Random Attributes
        $options = [];
        if ($category === 'Weapon') {
            $numStatsToAssign = rand(1, 3);
            $availableIndices = ['native', 'abeast', 'machine', 'dark'];
            shuffle($availableIndices);
            
            for ($i = 0; $i < $numStatsToAssign; $i++) {
                $stat = $availableIndices[$i];
                $amount = rand(1, 10) * 5; // e.g. 1 -> 5%, 10 -> 50%
                $options[$stat] = $amount;
            }
        } else if ($category === 'Armor' || $category === 'Shield') {
            // Roll random bonuses for DEF and EVP
            $defBonus = rand(0, 5) * 5;
            $evpBonus = rand(0, 5) * 5;
            
            if ($defBonus > 0) $options['def'] = $defBonus;
            if ($evpBonus > 0) $options['evp'] = $evpBonus;
            if ($category === 'Armor') $options['slots'] = 4;
        }

        // 3. Generate Item String
        if ($category === 'Mag') {
            $itemString = 'Random Mag';
        } else {
            // Rares only drop at 25, 50, 75, 100, etc.
            if ($milestone % 25 === 0 || $category === 'Random') {
                $itemString = get_reward_item($milestone, $charClass, $category, $options);
            } else {
                // Above level 100, there is a 20% chance to roll from the rare pool
                if ($milestone > 100 && rand(1, 100) <= 20) {
                    $itemString = get_reward_item($milestone, $charClass, $category, $options);
                } else {
                    $itemString = get_common_reward_item($milestone, $charClass, $category, $options);
                }
            }
        }
        
        // 3.7. Random Mag Attributes
        if ($itemString === 'Random Mag') {
            // Native hex indices for all allowed rare mags, bypassing string lookups
            $rareMagIndices = [
                0x3F, /* Sato */      0x41, /* Nidra */     0x3A, /* Rati */      0x40, /* Bhima */
                0x3B, /* Savitri */   0x39, /* Deva */      0x3D, /* Pushan */    0x3E, /* Diwari */
                0x27, /* Churel */    0x26, /* Preta */     0x21, /* Pitri */     0x25, /* Soniti */
                0x0D, /* Kumara */    0x1B, /* Ila */       0x27, /* Andhaka */   0x24, /* Bana */
                0x20, /* Naga */      0x22, /* Kabanda */   0x23, /* Ravana */    0x0F, /* Marutah */
                0x1A, /* Soma */      0x1C, /* Durga */     0x11, /* Kalki */     0x1D, /* Vritra */
                0x09, /* Varaha */    0x0A, /* Kama */      0x0B, /* Ushasu */    0x14, /* Ashvinau */
                0x24, /* Marica */    0x26, /* Madhu */     0x0F, /* Tapas */     0x15, /* Ribha */
                0x15, /* Sita */      0x14, /* Yaksa */     0x16, /* Garuda */    0x12, /* Rudra */
                0x07, /* Surya */     0x0C, /* Apsaras */   0x10, /* Bhirava */
            ];
            
            $magIndex = $rareMagIndices[array_rand($rareMagIndices)];
            $chosenColorIndex = rand(0, 17);
            
            // Stats
            $def = rand(5, 50);
            $pow = rand(0, 100);
            $dex = rand(0, 100);
            $mind = rand(0, 100);
            
            // Scale Mag level to the player's actual milestone
            $totalStats = $def + $pow + $dex + $mind;
            $targetLevel = min(200, max(50, $milestone));
            
            $scale = $targetLevel / $totalStats;
            $def = floor($def * $scale);
            $pow = floor($pow * $scale);
            $dex = floor($dex * $scale);
            $mind = floor($mind * $scale);
            
            $sync = rand(0, 120);
            $iq = rand(0, 200);
            
            // Generate legal PB flags (1-3 unique photon blasts)
            // PB bits: 0=Farlla, 1=Estlla, 2=Golla, 3=Pilla, 4=Leilla, 5=Mylla&Youlla
            $pbBits = [0, 1, 2, 3, 4, 5];
            shuffle($pbBits);
            $numPbs = rand(1, 3);
            $pbFlags = 0;
            for ($i = 0; $i < $numPbs; $i++) {
                $pbFlags |= (1 << $pbBits[$i]);
            }
            
            // Build the hex natively, completely bypassing string parsing vulnerability
            $itemString = build_pso_mag($magIndex, $def, $pow, $dex, $mind, $sync, $iq, $chosenColorIndex, $pbFlags);
        }

        // 4. Send shell-exec to newserv
        // We use the robust parser to support multiple items (e.g. 'Photon Drop x2'), disks, and strings.
        if (!function_exists('parse_and_drop_items')) {
            require_once 'functions.php';
        }
        
        $dropResult = parse_and_drop_items($accountId, $itemString);
        
        if (!$dropResult['success']) {
            http_response_code(400);
            echo json_encode(["error" => $dropResult['error']]);
            exit;
        }

        // 5. Verify character loading state to prevent drop loss
        error_log("[Bounty Claim] Success - Item delivered via shell-exec.");
        
        $droppedItems[] = $itemString;
    }

    $finalItemString = implode(", ", $droppedItems);

    // 5. Store claim record in website DB
    $stmt = $db->prepare("INSERT INTO rewards_claimed (account_id, character_name, level_milestone, category, item_string) VALUES (:aid, :cname, :lvl, :cat, :item)");
    $stmt->bindValue(':aid', $accountId, SQLITE3_INTEGER);
    $stmt->bindValue(':cname', $name, SQLITE3_TEXT);
    $stmt->bindValue(':lvl', $milestone, SQLITE3_INTEGER);
    $stmt->bindValue(':cat', $category, SQLITE3_TEXT);
    $stmt->bindValue(':item', $finalItemString, SQLITE3_TEXT);
    $stmt->execute();

    echo json_encode([
        "success" => true,
        "message" => "Item dropped! Return to game to pick it up!",
        "item" => $finalItemString
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>
