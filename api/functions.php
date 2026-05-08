<?php
/**
 * Shared utility functions for the psobb.io platform.
 * Include via require_once from any script that needs these helpers.
 */

/**
 * Send a Simple Mail (command 0x81) to a connected BB client via newserv's shell-exec API.
 *
 * @param int    $client_acc_id  The target player's account ID (decimal).
 * @param string $from_name      The sender name displayed in the mail (max ~15 chars).
 * @param string $text            The mail body text (max ~511 chars).
 */
function send_personal_mail($client_acc_id, $from_name, $text) {
    global $NEWSERV_API_URL;
    $packet = str_repeat("\x00", 1112);
    $packet[0] = chr(0x58); $packet[1] = chr(0x04);
    $packet[2] = chr(0x81); $packet[3] = chr(0x00);
    $packet[4] = chr(0x00); $packet[5] = chr(0x00); $packet[6] = chr(0x01); $packet[7] = chr(0x00);
    
    $date_str = date('Y-m-d H:i:s');
    
    // Fetch target player's language preference from the database
    $marker = "\tE"; // Default to English
    if (function_exists('get_db')) {
        try {
            $db = get_db();
            $stmt = $db->prepare("SELECT language FROM users WHERE account_id = :acc LIMIT 1");
            $stmt->bindValue(':acc', $client_acc_id, SQLITE3_INTEGER);
            $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            if ($row && isset($row['language']) && strtolower(trim($row['language'])) === 'jp') {
                $marker = "\tJ";
            }
        } catch (Exception $e) {
            // Ignore DB errors and fallback to English
        }
    }
    
    // NewServ uses \tE (English) or \tJ (Japanese) as the language marker for the Sender Name
    $from_name = $marker . trim($from_name);
    // Remove the temporary padding hacks now that the structural offsets are correct
    $text = trim($text);

    if (function_exists('mb_convert_encoding')) {
        $from_utf16 = mb_convert_encoding($from_name, 'UTF-16LE', 'UTF-8');
        $date_utf16 = mb_convert_encoding($date_str, 'UTF-16LE', 'UTF-8');
        $text_utf16 = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
    } elseif (function_exists('iconv')) {
        $from_utf16 = iconv('UTF-8', 'UTF-16LE', $from_name);
        $date_utf16 = iconv('UTF-8', 'UTF-16LE', $date_str);
        $text_utf16 = iconv('UTF-8', 'UTF-16LE', $text);
    } else {
        // Fallback for ASCII if both extensions are missing
        $from_utf16 = preg_replace('/(.)/s', "$1\x00", $from_name);
        $date_utf16 = preg_replace('/(.)/s', "$1\x00", $date_str);
        $text_utf16 = preg_replace('/(.)/s', "$1\x00", $text);
    }
    
    // The PSOBB Command Header is 8 bytes long.
    // Total Size = 8 (header) + 1108 (payload) = 1116 bytes (0x45C)
    $packet = str_repeat("\x00", 1116);
    $packet[0] = chr(0x5C); $packet[1] = chr(0x04); // Size = 0x45C
    $packet[2] = chr(0x81); $packet[3] = chr(0x00); // Command = 0x81
    $packet[4] = chr(0x00); $packet[5] = chr(0x00); $packet[6] = chr(0x00); $packet[7] = chr(0x00); // Flag = 0
    
    // Payload starts at offset 8
    // player_tag = 0x00010000
    $packet[8] = chr(0x00); $packet[9] = chr(0x00); $packet[10] = chr(0x01); $packet[11] = chr(0x00);
    
    // from_guild_card_number (offset 12) left as 0
    
    // from_name (offset 16, max 30 bytes)
    for($i = 0; $i < min(30, strlen($from_utf16)); $i++) {
        $packet[16 + $i] = $from_utf16[$i];
    }
    
    // to_guild_card_number (offset 48)
    $packet[48] = chr($client_acc_id & 0xFF);
    $packet[49] = chr(($client_acc_id >> 8) & 0xFF);
    $packet[50] = chr(($client_acc_id >> 16) & 0xFF);
    $packet[51] = chr(($client_acc_id >> 24) & 0xFF);
    
    // received_date (offset 52, max 38 bytes)
    for($i = 0; $i < min(38, strlen($date_utf16)); $i++) {
        $packet[52 + $i] = $date_utf16[$i];
    }
    
    // text (offset 92, max 1022 bytes)
    for($i = 0; $i < min(1022, strlen($text_utf16)); $i++) {
        $packet[92 + $i] = $text_utf16[$i];
    }
    
    $hex = bin2hex($packet);
    $exec_payload = json_encode(["command" => "on " . $client_acc_id . " sc " . $hex]);
    $exec_options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => $exec_payload
        ]
    ];
    @file_get_contents($NEWSERV_API_URL . "/y/shell-exec", false, stream_context_create($exec_options));
}

/**
 * Translates underlying backend mission goal types and targets into human-readable descriptions.
 * This is used strictly for rendering objectives gracefully on the frontend UI layout.
 *
 * @param string $type The category of the objective (e.g., 'ITEM', 'BOSS_ARENA').
 * @param mixed $target The specific numerical ID or string required to satisfy the goal.
 * @return string A fully formatted, user-friendly description of what the player must do.
 */
function getClearObjective($type, $target) {
    switch ($type) {
        case 'MESETA':
            if ($target === 'ANY') return __('Collect Meseta (Any source)');
            return __('Hold at least %s Meseta in inventory', number_format((int)$target));
        case 'LEVEL': return __('Reach Level %s', htmlspecialchars($target));
        case 'LEVEL_UP': return __('Earn Level Ups (Any Character)');
        case 'MAT_CONSUME': return __('Consume Any Materials');
        case 'PLAYTIME':
            if ($target === 'ANY') return __('Accumulate Playtime (Server-wide Tracker)');
            return __('Accumulate %s total hours of playtime', (int)($target / 3600));
        case 'CHALLENGE_STAGES':
            if ($target === 'ANY') return __('Clear Any Challenge Mode Stages');
            return __('Complete %s Challenge Mode stages', htmlspecialchars($target));
        case 'ITEM':
            // Legacy items are stored as "ID:Name". Generated base weapons are stored just as "String".
            // If the string is a pure Hex payload, reverse map it back to an English string.
            $parts = explode(':', $target, 2);
            $itemName = isset($parts[1]) ? $parts[1] : $target;

            // Clean up modifier fragments if present
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
            return __('Find and hold the item: %s', htmlspecialchars(__($itemName)));
        case 'TECHNIQUE': return __('Learn the technique: %s', htmlspecialchars($target));
        case 'BATTLE_WINS': return __('Achieve %s 1st place Battle Mode wins', htmlspecialchars($target));
        case 'MAT_HP': return __('Consume a total of %s HP Materials', htmlspecialchars($target));
        case 'MAT_TP': return __('Consume a total of %s TP Materials', htmlspecialchars($target));
        case 'MAT_POWER': return __('Consume a total of %s Power Materials', htmlspecialchars($target));
        case 'MAT_DEF': return __('Consume a total of %s Def Materials', htmlspecialchars($target));
        case 'MAT_MIND': return __('Consume a total of %s Mind Materials', htmlspecialchars($target));
        case 'MAT_EVADE': return __('Consume a total of %s Evade Materials', htmlspecialchars($target));
        case 'MAT_LUCK': return __('Consume a total of %s Luck Materials', htmlspecialchars($target));
        case 'EXPLORATION':
            // Maps integer Floor IDs returned by the game client memory map to friendly Names
            $floors = [1=>'Forest 1',2=>'Forest 2',3=>'Cave 1',4=>'Cave 2',5=>'Cave 3',6=>'Mine 1',7=>'Mine 2',8=>'Ruins 1',9=>'Ruins 2',10=>'Ruins 3'];
            $loc = $floors[$target] ?? "Floor $target";
            return __('[Ep 1] Explore the %s', htmlspecialchars(__($loc)));
        case 'PATROL':
            $floors = [1=>'Forest 1',2=>'Forest 2',3=>'Cave 1',4=>'Cave 2',5=>'Cave 3',6=>'Mine 1',7=>'Mine 2',8=>'Ruins 1',9=>'Ruins 2',10=>'Ruins 3'];
            $loc = $floors[$target] ?? "Floor $target";
            return __('[Ep 1] Survive and patrol the %s for 10 minutes', htmlspecialchars(__($loc)));
        case 'BOSS_ARENA':
            if ($target === 'ANY_DRAGON') return __('Defeat Any Dragon Boss (Forest, Sil, or Gol)');
            // Maps Boss integer Floor IDs to the boss name to ensure users know where to go
            $bosses = [11=>'Dragon (Forest)', 12=>'De Rol Le (Caves)', 13=>'Vol Opt (Mines)', 14=>'Dark Falz (Ruins)', 17=>'Barba Ray (Temple)', 16=>'Gol Dragon (Spaceship)', 15=>'Gal Gryphon (CCA)', 18=>'Olga Flow (Seabed)', 19=>'Saint-Million (Crater)'];
            $ep = ($target >= 15 && $target <= 18) ? '2' : ($target == 19 ? '4' : '1');
            $boss = $bosses[$target] ?? "Boss at Floor $target";
            return __('[Ep %s] Defeat the %s', $ep, htmlspecialchars(__($boss)));
        case 'MENTOR_BOSS':
            if ($target === 'ANY_DRAGON') return __('Mentor a player through Any Dragon Boss (Forest, Sil, or Gol)');
            $bosses = [11=>'Dragon (Forest)', 12=>'De Rol Le (Caves)', 13=>'Vol Opt (Mines)', 14=>'Dark Falz (Ruins)', 17=>'Barba Ray (Temple)', 16=>'Gol Dragon (Spaceship)', 15=>'Gal Gryphon (CCA)', 18=>'Olga Flow (Seabed)', 19=>'Saint-Million (Crater)'];
            $ep = ($target >= 15 && $target <= 18) ? '2' : ($target == 19 ? '4' : '1');
            $boss = $bosses[$target] ?? "Boss at Floor $target";
            return __('[Ep %s] Carry a lower-level player through the %s fight', $ep, htmlspecialchars(__($boss)));
        case 'HARDCORE_MENTOR':
            if ($target === 'ANY_DRAGON') return __('Hardcore Carry 3 lower-level players through Any Dragon Boss');
            $bosses = [11=>'Dragon (Forest)', 12=>'De Rol Le (Caves)', 13=>'Vol Opt (Mines)', 14=>'Dark Falz (Ruins)', 17=>'Barba Ray (Temple)', 16=>'Gol Dragon (Spaceship)', 15=>'Gal Gryphon (CCA)', 18=>'Olga Flow (Seabed)', 19=>'Saint-Million (Crater)'];
            $ep = ($target >= 15 && $target <= 18) ? '2' : ($target == 19 ? '4' : '1');
            $boss = $bosses[$target] ?? "Boss at Floor $target";
            return __('[Ep %s] Hardcore Carry 3 lower-level players through the %s fight', $ep, htmlspecialchars(__($boss)));
        case 'DIVERSE_PARTY_BOSS':
            if ($target === 'ANY_DRAGON') return __('Defeat Any Dragon Boss with a diverse party (HU, RA, FO)');
            $bosses = [11=>'Dragon (Forest)', 12=>'De Rol Le (Caves)', 13=>'Vol Opt (Mines)', 14=>'Dark Falz (Ruins)', 17=>'Barba Ray (Temple)', 16=>'Gol Dragon (Spaceship)', 15=>'Gal Gryphon (CCA)', 18=>'Olga Flow (Seabed)', 19=>'Saint-Million (Crater)'];
            $ep = ($target >= 15 && $target <= 18) ? '2' : ($target == 19 ? '4' : '1');
            $boss = $bosses[$target] ?? "Boss at Floor $target";
            return __('[Ep %s] Defeat the %s with a diverse party (HU, RA, FO)', $ep, htmlspecialchars(__($boss)));
        case 'SPEEDRUN_BOSS':
            list($target_floor, $time_limit) = explode('_', $target);
            $bosses = [11=>'Dragon (Forest)', 12=>'De Rol Le (Caves)', 13=>'Vol Opt (Mines)', 14=>'Dark Falz (Ruins)', 17=>'Barba Ray (Temple)', 16=>'Gol Dragon (Spaceship)', 15=>'Gal Gryphon (CCA)', 18=>'Olga Flow (Seabed)', 19=>'Saint-Million (Crater)'];
            $ep = ($target_floor >= 15 && $target_floor <= 18) ? '2' : ($target_floor == 19 ? '4' : '1');
            $boss = $bosses[$target_floor] ?? "Boss at Floor $target_floor";
            return __('[Ep %s] Defeat the %s in under %s seconds', $ep, htmlspecialchars(__($boss)), htmlspecialchars($time_limit));
        case 'SPEEDRUN_FLOOR':
            list($target_floor, $time_limit) = explode('_', $target);
            $floors = [1=>'Forest 1',2=>'Forest 2',3=>'Cave 1',4=>'Cave 2',5=>'Cave 3',6=>'Mine 1',7=>'Mine 2',8=>'Ruins 1',9=>'Ruins 2',10=>'Ruins 3'];
            $loc = $floors[$target_floor] ?? "Floor $target_floor";
            $mins = floor($time_limit / 60);
            $secs = $time_limit % 60;
            if ($secs == 0) return __('[Ep 1] Clear %s in under %s minutes', htmlspecialchars(__($loc)), $mins);
            return __('[Ep 1] Clear %s in under %s minutes and %s seconds', htmlspecialchars(__($loc)), $mins, $secs);
        default: return htmlspecialchars($type) . ": " . htmlspecialchars($target);
    }
}

/**
 * Obfuscates Unidentified weapon strings before rendering them to the Bounty Board UI.
 * Prevents players from knowing the exact special ability or grind modifiers prior to taking the weapon to the Tekker.
 *
 * @param string $rewardStr The raw reward string stored in the database (e.g., '? Charge Saber 20/0/45/0' or '000A01008100...')
 * @return string Safely obfuscated string rendering identically to the in-game display ('???? Saber')
 */
function renderRewardString($rewardStr) {
    if (empty($rewardStr)) return "";
    $segments = explode(',', $rewardStr);
    $processed = [];

    foreach ($segments as $segment) {
        $segment = trim($segment);
        if (empty($segment)) continue;
        $parts = explode(' ', $segment);
        $firstPart = $parts[0];

        // If the segment starts with a 32-character Hex payload, parse it
        if (ctype_xdigit($firstPart) && (strlen($firstPart) >= 32 || strlen($firstPart) === 6)) {
            $base = substr($firstPart, 0, 6);
            $untekked_flag = (strlen($firstPart) >= 32) ? hexdec(substr($firstPart, 8, 2)) : 0;

            $weaponName = $base;
            $map_path = __DIR__ . '/item_map.json';
            if (file_exists($map_path)) {
                $map = json_decode(file_get_contents($map_path), true);
                $reverse_map = array_flip($map);
                if (isset($reverse_map[$base])) {
                    $weaponName = ucwords($reverse_map[$base]);
                }
            }

            $prefix = ($untekked_flag & 0x80) ? "???? " : "";

            array_shift($parts);
            $rest = implode(' ', $parts);

            $processed[] = $prefix . $weaponName . ($rest ? " " . $rest : "");
        }
        // Legacy Support: If the string starts with "? ", it indicates an untekked weapon reward
        else if (strpos($segment, '? ') === 0) {
            $legacy_parts = explode(' ', $segment);
            array_shift($legacy_parts);
            array_shift($legacy_parts);
            if (!empty($legacy_parts) && strpos(end($legacy_parts), '/') !== false) {
                array_pop($legacy_parts);
            }
            $weaponName = implode(' ', $legacy_parts);
            $processed[] = "???? " . $weaponName;
        }
        // Non-untekked rewards (Meseta, Materials, Rares)
        else {
            $processed[] = $segment;
        }
    }

    return implode(', ', array_map('htmlspecialchars', $processed));
}

/**
 * Robustly parses a reward string (like "Photon Drop x2", "Disk:Shifta Lv.15", or "001006 0/30/0/20")
 * and sends the exact shell-exec payload(s) to NewServ.
 * 
 * @return array Assc. array with "success" (bool), "dropped" (array of payloads), or "error" (string).
 */
function parse_and_drop_items($accountId, $itemString) {
    global $NEWSERV_API_URL, $NEWSERV_COMMAND_PREFIX;
    
    // Ensure buildHexPayload is available (it's defined in redeem_bounty.php, but we might not have it loaded)
    if (!function_exists('buildHexPayload') && !function_exists('simpleBuildHexPayload')) {
        // Fallback simple payload builder if the full one isn't loaded
        function simpleBuildHexPayload($itemStr) {
            $parts = explode(' ', trim($itemStr));
            $firstPart = $parts[0];
            if (ctype_xdigit($firstPart) && strlen($firstPart) >= 6) {
                return strtoupper(str_pad(substr($firstPart, 0, 32), 32, "0"));
            }
            return $itemStr; // Can't parse
        }
    }
    
    $items = explode(',', $itemString);
    $droppedPayloads = [];

    foreach ($items as $rawItem) {
        $rawItem = trim($rawItem);
        if (empty($rawItem)) continue;

        $multiplier = 1;
        $baseItemName = $rawItem;
        
        // Parse multipliers (e.g. "Photon Drop x2" -> 2x "Photon Drop", or "2x Photon Drop")
        if (preg_match('/^(.*)\s+x(\d+)$/i', $rawItem, $matches)) {
            $baseItemName = trim($matches[1]);
            $multiplier = intval($matches[2]);
        } else if (preg_match('/^(\d+)x\s+(.*)$/i', $rawItem, $matches)) {
            $multiplier = intval($matches[1]);
            $baseItemName = trim($matches[2]);
        }
        
        if ($multiplier > 50) $multiplier = 50; // Cap to prevent server spam
        
        // Parse Meseta natively into pure hex payload (e.g. "1000 Meseta")
        if (preg_match('/^(\d+)\s*meseta$/i', $baseItemName, $mmatch)) {
            $amount = intval($mmatch[1]);
            $data = str_repeat("\x00", 16);
            $data[0] = chr(0x04);
            $data[12] = chr($amount & 0xFF);
            $data[13] = chr(($amount >> 8) & 0xFF);
            $data[14] = chr(($amount >> 16) & 0xFF);
            $data[15] = chr(($amount >> 24) & 0xFF);
            $baseItemName = strtoupper(bin2hex($data));
        }
        
        // Parse Disks specifically because of their level mechanic
        if (stripos($baseItemName, 'disk:') === 0) {
            if (preg_match('/Disk:([A-Za-z]+)\s+Lv\.(\d+)/i', $baseItemName, $dmatch)) {
                $techName = strtolower($dmatch[1]);
                $techLevel = intval($dmatch[2]) - 1; // 0-indexed in memory
                
                $techMap = [
                    'foie' => 0x00, 'gifoie' => 0x01, 'rafoie' => 0x02,
                    'barta' => 0x03, 'gibarta' => 0x04, 'rabarta' => 0x05,
                    'zonde' => 0x06, 'gizonde' => 0x07, 'razonde' => 0x08,
                    'grants' => 0x09, 'deband' => 0x0A, 'jellen' => 0x0B,
                    'zalure' => 0x0C, 'shifta' => 0x0D, 'ryuker' => 0x0E,
                    'resta' => 0x0F, 'anti' => 0x10, 'reverser' => 0x11,
                    'megid' => 0x12
                ];
                
                if (isset($techMap[$techName])) {
                    $data = str_repeat("\x00", 16);
                    $data[0] = chr(0x03);
                    $data[1] = chr(0x02);
                    $data[2] = chr($techMap[$techName]);
                    $data[4] = chr($techLevel);
                    $baseItemName = strtoupper(bin2hex($data));
                }
            }
        }
        
        // Literal string lookup using the master item_hex.txt
        $firstWord = explode(' ', $baseItemName)[0];
        if (!ctype_xdigit($firstWord) || strlen($firstWord) < 6) {
            $path = __DIR__ . '/../item_hex.txt';
            if (file_exists($path)) {
                $searchName = strtolower(trim($baseItemName));
                $lines = file($path);
                foreach ($lines as $line) {
                    $parts = preg_split('/\s{2,}/', trim($line));
                    $itemName = strtolower(trim(end($parts)));
                    // Remove trailing x1 if it exists in item_hex.txt
                    $itemName = preg_replace('/\s+x1$/', '', $itemName);
                    
                    if ($itemName === $searchName || strpos($itemName, $searchName) === 0) {
                        if (preg_match('/^\s*([0-9A-Fa-f]{6})\s*=>/', $line, $matches)) {
                            $baseItemName = $matches[1];
                            break;
                        }
                    }
                }
            }
        }
        
        // Execute the drop multiple times if necessary
        for ($i = 0; $i < $multiplier; $i++) {
            $finalPayload = function_exists('buildHexPayload') ? buildHexPayload($baseItemName) : simpleBuildHexPayload($baseItemName);
            $cmd = "on " . $accountId . " cc {$NEWSERV_COMMAND_PREFIX}item " . $finalPayload;
            
            $url = $NEWSERV_API_URL . "/y/shell-exec";
            $opts = [
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => json_encode(['command' => $cmd]),
                    'ignore_errors' => true
                ],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
            ];
            
            $execRes = @file_get_contents($url, false, stream_context_create($opts));
            if ($execRes === false) {
                return ["success" => false, "error" => "Failed to connect to game server API."];
            }
            
            $execData = json_decode($execRes, true);
            if (isset($execData['Error']) && $execData['Error']) {
                $serverMsg = $execData['Message'] ?? "Unknown server error.";
                return ["success" => false, "error" => "Game server rejected the drop: " . $serverMsg];
            }
            if (isset($execData['result'])) {
                $resStr = strtolower($execData['result']);
                if (strpos($resStr, 'error') !== false || strpos($resStr, 'not found') !== false || strpos($resStr, 'failed') !== false) {
                    return ["success" => false, "error" => "Could not drop item: " . $execData['result']];
                }
            }
            
            $droppedPayloads[] = $finalPayload;
            
            // 100ms stutter-step to ensure NewServ correctly renders each physical box 
            // on the client floor instead of dropping packets on multi-item rewards
            usleep(100000);
        }
    }
    
    return ["success" => true, "dropped" => $droppedPayloads];
}

