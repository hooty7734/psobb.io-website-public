<?php
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';

header('Content-Type: application/json');

require_once 'db.php';

$headers = getallheaders();
$auth = $headers['Authorization'] ?? '';
// Strip "Bearer " prefix if present
$provided = (str_starts_with($auth, 'Bearer ')) ? substr($auth, 7) : $auth;

$authenticated = false;

// Tier 1: legacy env secret (backward-compat)
if (!empty($BOT_API_SECRET) && hash_equals($BOT_API_SECRET, $provided)) {
    $authenticated = true;
}

// Tier 2: DB-managed tokens — bcrypt-verified, expiry and revoke aware
if (!$authenticated && !empty($provided)) {
    $db = get_db();
    $res = $db->query("SELECT id, token_hash FROM bot_tokens WHERE revoked = 0 AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)");
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        if (password_verify($provided, $row['token_hash'])) {
            $authenticated = true;
            // Update last_used_at asynchronously (best-effort, non-blocking)
            $upd = $db->prepare("UPDATE bot_tokens SET last_used_at = CURRENT_TIMESTAMP WHERE id = :id");
            $upd->bindValue(':id', $row['id'], SQLITE3_INTEGER);
            $upd->execute();
            break;
        }
    }
}

if (!$authenticated) {
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
    exit;
}

// ============================================================
// SHARED CHARACTER PARSING HELPERS (ported from character_viewer.php)
// These MUST live at top level (not inside an action branch); a function
// defined inside `if ($action === 'link')` is only declared when that branch
// runs, so get_player would hit "Call to undefined function bot_parse_psochar".
// ============================================================

$CLASS_MAP = [
    0 => 'HUmar',    1 => 'HUnewearl', 2 => 'HUcast',    3 => 'RAmar',
    4 => 'RAcast',   5 => 'RAcaseal',  6 => 'FOmarl',    7 => 'FOnewm',
    8 => 'FOnewearl',9 => 'HUcaseal', 10 => 'FOmar',    11 => 'RAmarl'
];
// Section ID index values verified against newserv StaticGameData.cc section_id_to_name[]
// Note: "Greennill" (double-n) is the canonical spelling used by newserv.
$SECID_MAP = [
    0 => 'Viridia',   1 => 'Greennill', 2 => 'Skyly',    3 => 'Bluefull',
    4 => 'Purplenum', 5 => 'Pinkal',    6 => 'Redria',    7 => 'Oran',
    8 => 'Yellowboze', 9 => 'Whitill'
];

function bot_parse_item_data($bytes) {
    if (strlen($bytes) < 20) return null;
    $data1 = substr($bytes, 0, 12);
    $data2 = substr($bytes, 16, 4);
    $group = ord($data1[0]);
    $type1 = ord($data1[1]);
    $type2 = ord($data1[2]);
    $hex   = strtoupper(bin2hex($bytes));

    $isSRank = ($group === 0x00) && (($type1 > 0x6F && $type1 < 0x89) || ($type1 > 0xA4 && $type1 < 0xAA));

    if ($group === 0x04) {
        $primaryId = 0x04000000;
    } elseif ($group === 0x03 && $type1 === 0x02) {
        $primaryId = 0x03020000 | (ord($data1[4]) << 8) | $type2;
    } elseif ($group === 0x02) {
        $primaryId = 0x02000000 | ($type1 << 16);
    } elseif ($isSRank) {
        $primaryId = ($group << 24) | ($type1 << 16);
    } else {
        $primaryId = ($group << 24) | ($type1 << 16) | ($type2 << 8);
    }
    $lookupKey = strtolower(substr(sprintf('%08X', $primaryId), 0, 6));

    static $codeToName = null;
    if ($codeToName === null) {
        $codeToName = [];
        $mapPath = __DIR__ . '/names-v4.json';
        if (file_exists($mapPath)) {
            $map = json_decode(file_get_contents($mapPath), true);
            if ($map) foreach ($map as $code => $name) $codeToName[strtolower($code)] = $name;
        }
    }

    $item = ['hex' => $hex, 'group' => $group, 'type1' => $type1, 'type2' => $type2,
             'equipped' => false, 'name' => 'Unknown', 'attrs' => []];

    if ($group === 0x00) {
        $grind = ord($data1[3]);
        $isUnid = (ord($data1[4]) & 0x80) !== 0;
        $wName = $codeToName[$lookupKey] ?? $codeToName[strtolower(sprintf('%02X%02X00', $group, $type1))] ?? 'Weapon';
        $item['name'] = ($isUnid ? '???? ' : '') . ucwords($wName) . ($grind > 0 ? " +{$grind}" : '');
        $item['grind'] = $grind;
        if ($isUnid) $item['unidentified'] = true;
        $attrMap = [1 => 'Native', 2 => 'A.Beast', 3 => 'Machine', 4 => 'Dark', 5 => 'Hit'];
        for ($a = 0; $a < 3; $a++) {
            $aType = ord($data1[6 + $a * 2]);
            $aVal  = ord($data1[7 + $a * 2]);
            if ($aType > 0 && isset($attrMap[$aType])) {
                if ($aVal > 127) $aVal -= 256;
                $item['attrs'][] = ['type' => $attrMap[$aType], 'value' => $aVal];
            }
        }
    } elseif ($group === 0x01) {
        $aName = $codeToName[$lookupKey] ?? null;
        $item['name'] = $aName ? ucwords($aName) : match($type1) { 0x01=>'Armor', 0x02=>'Shield', 0x03=>'Unit', default=>'Armor/Shield' };
        if ($type1 === 0x01) {
            $item['slots']     = ord($data1[5]);
            $item['def_bonus'] = unpack('s', substr($data1, 6, 2))[1];
            $item['evp_bonus'] = unpack('s', substr($data1, 8, 2))[1];
        } elseif ($type1 === 0x02) {
            $item['def_bonus'] = unpack('s', substr($data1, 6, 2))[1];
            $item['evp_bonus'] = unpack('s', substr($data1, 8, 2))[1];
        } elseif ($type1 === 0x03) {
            $item['modifier'] = unpack('s', substr($data1, 6, 2))[1];
        }
    } elseif ($group === 0x02) {
        $magName = $codeToName[$lookupKey] ?? 'Mag';
        $item['name'] = ucwords($magName);
        $item['mag_stats'] = [
            'level'   => ord($data1[2]),
            'pb_flags'=> ord($data1[3]),
            'def'     => round(unpack('v', substr($data1, 4, 2))[1] / 100, 2),
            'pow'     => round(unpack('v', substr($data1, 6, 2))[1] / 100, 2),
            'dex'     => round(unpack('v', substr($data1, 8, 2))[1] / 100, 2),
            'mind'    => round(unpack('v', substr($data1, 10, 2))[1] / 100, 2),
            'synchro' => ord($data2[0]),
            'iq'      => ord($data2[1]),
        ];
    } elseif ($group === 0x03) {
        if ($type1 === 0x02) {
            $techs = ['Foie','Gifoie','Rafoie','Barta','Gibarta','Rabarta','Zonde','Gizonde',
                      'Razonde','Grants','Deband','Jellen','Zalure','Shifta','Ryuker','Resta',
                      'Anti','Reverser','Megid'];
            $techNum = ord($data1[4]);
            $techLvl = ord($data1[2]) + 1;
            $item['name'] = 'Disk: ' . ($techs[$techNum] ?? 'Tech') . " Lv.{$techLvl}";
        } else {
            $tName = $codeToName[$lookupKey] ?? 'Tool';
            $item['name'] = ucwords($tName);
            $count = ord($data1[5]);
            $item['count'] = $count > 0 ? $count : 1;
        }
    } elseif ($group === 0x04) {
        $item['name']  = 'Meseta';
        $item['count'] = unpack('V', $data2)[1];
    }
    return $item;
}

function bot_parse_psochar($charData, $slot, $CLASS_MAP, $SECID_MAP) {
    if (!$charData || strlen($charData) < 0x399C) return null;

    // --- Inventory (offset 8, size 844) ---
    $invBlock  = substr($charData, 8, 844);
    $numItems  = ord($invBlock[0]);
    $hpMats    = ord($invBlock[1]) >> 1;
    $tpMats    = ord($invBlock[2]) >> 1;
    $inventory = [];
    for ($i = 0; $i < 30; $i++) {
        $off     = 4 + $i * 28;
        $present = ord($invBlock[$off]);
        if ($present) {
            $flags = unpack('V', substr($invBlock, $off + 4, 4))[1];
            $item  = bot_parse_item_data(substr($invBlock, $off + 8, 20));
            if ($item) {
                $item['equipped'] = ($flags & 8) !== 0;
                $inventory[] = $item;
            }
        }
    }

    // --- Display/Stats block (offset 852, size 400) ---
    $dispBlock = substr($charData, 852, 400);
    $atp    = unpack('v', substr($dispBlock,  0, 2))[1];
    $mst    = unpack('v', substr($dispBlock,  2, 2))[1];
    $evp    = unpack('v', substr($dispBlock,  4, 2))[1];
    $hp     = unpack('v', substr($dispBlock,  6, 2))[1];
    $dfp    = unpack('v', substr($dispBlock,  8, 2))[1];
    $ata    = unpack('v', substr($dispBlock, 10, 2))[1];
    $lck    = unpack('v', substr($dispBlock, 12, 2))[1];
    $lvl    = unpack('V', substr($dispBlock, 24, 4))[1] + 1;
    $exp    = unpack('V', substr($dispBlock, 28, 4))[1];
    $meseta = unpack('V', substr($dispBlock, 32, 4))[1];

    $sectionIdVal = ord($dispBlock[36 + 0x30]);
    $charClassVal = ord($dispBlock[36 + 0x31]);
    $charClass  = $CLASS_MAP[$charClassVal]  ?? 'Unknown';
    $sectionId  = $SECID_MAP[$sectionIdVal] ?? 'Unknown';

    // Name: UTF-16LE, skip 4-byte language prefix (\t + marker)
    $nameBytes = substr($dispBlock, 116 + 4, 28);
    $charName  = trim(str_replace("\x00", '', mb_convert_encoding($nameBytes, 'UTF-8', 'UTF-16LE')));

    // --- Material counts from inventory extension bytes ---
    $powerMats = ord($invBlock[4 + 8  * 28 + 3]);
    $mindMats  = ord($invBlock[4 + 9  * 28 + 3]);
    $evadeMats = ord($invBlock[4 + 10 * 28 + 3]);
    $defMats   = ord($invBlock[4 + 11 * 28 + 3]);
    $luckMats  = ord($invBlock[4 + 12 * 28 + 3]);

    // --- Play time ---
    $playTimeSecs = unpack('V', substr($charData, 8 + 0x04E8, 4))[1];

    // --- Quest flags (offset 1276, size 512) ---
    $questFlagsBlock = substr($charData, 1276, 512);
    $get_bit = function($diff, $flag_index) use ($questFlagsBlock) {
        $byteIndex  = $flag_index >> 3;
        $bitIndex   = $flag_index & 7;
        $byte       = $questFlagsBlock[$diff * 128 + $byteIndex] ?? "\x00";
        return !!(ord($byte) & (0x80 >> $bitIndex));
    };
    $diffs = ['Normal' => 0, 'Hard' => 1, 'VeryHard' => 2, 'Ultimate' => 3];
    $questProgress = [];
    foreach ($diffs as $diffName => $diffIdx) {
        $questProgress[$diffName] = [
            'Forest'    => $get_bit($diffIdx, 0x01F1),
            'Caves'     => $get_bit($diffIdx, 0x01F9),
            'Mines'     => $get_bit($diffIdx, 0x0201),
            'Ruins'     => $get_bit($diffIdx, 0x0207),
            'Temple'    => $get_bit($diffIdx, 0x0213),
            'Spaceship' => $get_bit($diffIdx, 0x021B),
            'CCA'       => $get_bit($diffIdx, 0x0225),
            'Seabed'    => $get_bit($diffIdx, 0x022F),
            'Desert'    => $get_bit($diffIdx, 0x02C1),
        ];
    }

    // --- Bank meseta (embedded bank at offset 1792) ---
    $bankMeseta = 0;
    $bankBlock = substr($charData, 1792, 8);
    if (strlen($bankBlock) >= 8) {
        $bankMeseta = unpack('V', substr($bankBlock, 4, 4))[1];
    }

    return [
        'slot'              => $slot,
        'exists'            => true,
        'name'              => $charName,
        'class'             => $charClass,
        'level'             => $lvl,
        'section_id'        => $sectionId,
        'experience'        => $exp,
        'play_time_hours'   => round($playTimeSecs / 3600, 1),
        'play_time_seconds' => $playTimeSecs,
        'is_online'         => false,
        'stats' => [
            'ATP' => $atp, 'MST' => $mst, 'EVP' => $evp,
            'HP'  => $hp,  'DFP' => $dfp, 'ATA' => $ata,
            'LCK' => $lck, 'Meseta' => $meseta,
        ],
        'mats' => [
            'HP'    => $hpMats,    'TP'    => $tpMats,
            'Power' => $powerMats, 'Mind'  => $mindMats,
            'Evade' => $evadeMats, 'Def'   => $defMats,
            'Luck'  => $luckMats,
        ],
        'inventory'      => $inventory,
        'bank_meseta'    => $bankMeseta,
        'quest_progress' => $questProgress,
    ];
}

if ($action === 'get_player') {
    $discord_id = $_GET['discord_id'] ?? '';

    if (!$discord_id) {
        echo json_encode(["error" => "Missing discord_id"]);
        exit;
    }

    $db = get_db();
    $stmt = $db->prepare("SELECT account_id, username, language FROM users WHERE discord_id = :discord_id");
    $stmt->bindValue(':discord_id', $discord_id, SQLITE3_TEXT);
    $res  = $stmt->execute();
    $user = $res->fetchArray(SQLITE3_ASSOC);

    global $PSO_LANG;
    $PSO_LANG = $user['language'] ?? 'en';
    require_once 'lang.php';

    if (!$user) {
        echo json_encode(["error" => "Not linked"]);
        exit;
    }

    // --- Resolve BB username from /y/accounts ---
    $bb_username = strtolower(trim($user['username']));
    $account_info = [];
    $url = $NEWSERV_API_URL . "/y/accounts";
    $data = @file_get_contents($url);
    if ($data) {
        $accounts = json_decode($data, true);
        if (is_array($accounts)) {
            foreach ($accounts as $acc) {
                if ($acc['AccountID'] == $user['account_id']) {
                    if (isset($acc['BBLicenses'][0]['UserName'])) {
                        $bb_username = strtolower(trim($acc['BBLicenses'][0]['UserName']));
                    }
                    $account_info = [
                        'guild_card' => $acc['AccountID'] ?? null,
                        'is_shared_bank_enabled' => $acc['UseSharedBank'] ?? false,
                    ];
                    break;
                }
            }
        }
    }

    // --- Fetch live client list once (used for online overlay) ---
    $live_clients = [];
    $clients_raw = @file_get_contents($NEWSERV_API_URL . "/y/clients");
    if ($clients_raw) {
        $all = json_decode(iconv('UTF-8', 'UTF-8//IGNORE', $clients_raw), true);
        if (is_array($all)) {
            foreach ($all as $c) {
                if (isset($c['Account']['AccountID']) && $c['Account']['AccountID'] == $user['account_id']) {
                    $live_clients[] = $c;
                }
            }
        }
    }
    $is_online = count($live_clients) > 0;

    // --- Parse all 20 character slots ---
    $playersDir = '/opt/newserv/system/players/';
    if (!is_dir($playersDir)) $playersDir = __DIR__ . '/../../newserv/system/players/';

    $resolve_file = function($dir, $filename) {
        $full = $dir . $filename;
        if (file_exists($full)) return $full;
        if (is_dir($dir)) foreach (scandir($dir) as $f) if (strcasecmp($f, $filename) === 0) return $dir . $f;
        return $full;
    };

    // Shared bank (one per account)
    $shared_bank = ['meseta' => 0, 'item_count' => 0];
    $sharedPath = $resolve_file($playersDir, "shared_bank_{$bb_username}.psobank");
    if (file_exists($sharedPath)) {
        $shData = @file_get_contents($sharedPath);
        if ($shData && strlen($shData) >= 8) {
            $shared_bank['item_count'] = unpack('V', substr($shData, 0, 4))[1];
            $shared_bank['meseta']     = unpack('V', substr($shData, 4, 4))[1];
        }
    }

    $characters = [];
    for ($slot = 0; $slot < 20; $slot++) {
        $path = $resolve_file($playersDir, "player_{$bb_username}_{$slot}.psochar");
        if (!file_exists($path)) {
            $characters[] = ['slot' => $slot, 'exists' => false];
            continue;
        }

        $charData = @file_get_contents($path);
        $parsed   = bot_parse_psochar($charData, $slot, $CLASS_MAP, $SECID_MAP);
        if (!$parsed) {
            $characters[] = ['slot' => $slot, 'exists' => false, 'error' => 'parse_failed'];
            continue;
        }

        // --- Overlay live data if this slot is the active character ---
        foreach ($live_clients as $c) {
            $liveSlot = $c['BBCharacterIndex'] ?? -1;
            $liveName = $c['Name'] ?? '';
            // Match by slot index OR by name if slot isn't set
            if ($liveSlot === $slot || ($liveSlot < 0 && $liveName === $parsed['name'])) {
                $parsed['is_online']    = true;
                $parsed['lobby_id']     = $c['LobbyID']      ?? null;
                $parsed['floor']        = $c['LocationFloor'] ?? null;
                $parsed['location_x']   = $c['LocationX']    ?? null;
                $parsed['location_z']   = $c['LocationZ']    ?? null;

                // Live stat overrides
                foreach (['ATP','DFP','MST','ATA','EVP','LCK','HP'] as $s) {
                    if (isset($c[$s])) $parsed['stats'][$s] = (int)$c[$s];
                }
                if (isset($c['Meseta'])) $parsed['stats']['Meseta'] = (int)$c['Meseta'];
                if (isset($c['Level']))  $parsed['level']            = (int)$c['Level'];
                if (isset($c['EXP']))    $parsed['experience']       = (int)$c['EXP'];
                if (isset($c['SectionID'])) $parsed['section_id']    = $c['SectionID'];
                if (isset($c['CharClass']))  $parsed['class']         = $c['CharClass'];

                // Live material overrides
                $matMap = [
                    'NumHPMaterialsUsed'    => 'HP',
                    'NumTPMaterialsUsed'    => 'TP',
                    'NumPowerMaterialsUsed' => 'Power',
                    'NumDefMaterialsUsed'   => 'Def',
                    'NumMindMaterialsUsed'  => 'Mind',
                    'NumEvadeMaterialsUsed' => 'Evade',
                    'NumLuckMaterialsUsed'  => 'Luck',
                ];
                foreach ($matMap as $key => $mat) {
                    if (isset($c[$key])) $parsed['mats'][$mat] = (int)$c[$key];
                }

                // Live inventory from newserv memory
                if (isset($c['InventoryItems']) && is_array($c['InventoryItems'])) {
                    $liveInv = [];
                    foreach ($c['InventoryItems'] as $inv) {
                        $bin    = @hex2bin(preg_replace('/[^a-fA-F0-9]/', '', $inv['Data'] ?? ''));
                        $parsed_inv = bot_parse_item_data($bin ?: '');
                        if ($parsed_inv) {
                            $parsed_inv['equipped'] = (($inv['Flags'] ?? 0) & 8) !== 0;
                            if (!empty($inv['Description'])) $parsed_inv['name'] = $inv['Description'];
                            $parsed_inv['item_id'] = $inv['ItemID'] ?? null;
                            $liveInv[] = $parsed_inv;
                        }
                    }
                    $parsed['inventory'] = $liveInv;
                }
                break;
            }
        }

        $characters[] = $parsed;
    }

    // --- Website DB stats ---
    $stmt = $db->prepare("SELECT COUNT(*) as login_days FROM daily_logins WHERE account_id = :acc");
    $stmt->bindValue(':acc', $user['account_id'], SQLITE3_INTEGER);
    $login_days = $stmt->execute()->fetchArray(SQLITE3_ASSOC)['login_days'] ?? 0;

    $stmt = $db->prepare("SELECT m.title, m.description, m.goal_type, m.goal_target, pm.status
                          FROM player_missions pm
                          JOIN missions m ON pm.mission_id = m.id
                          WHERE pm.account_id = :acc");
    $stmt->bindValue(':acc', $user['account_id'], SQLITE3_INTEGER);
    $mRes = $stmt->execute();
    $missions = [];
    while ($m = $mRes->fetchArray(SQLITE3_ASSOC)) {
        $m['friendly_objective'] = getClearObjective($m['goal_type'], $m['goal_target'], $m['title'], $m['description']);
        $missions[] = $m;
    }

    echo json_encode([
        'website_username' => $user['username'],
        'account_id'       => $user['account_id'],
        'language'         => $user['language'] ?? 'en',
        'is_online'        => $is_online,
        'account'          => $account_info,
        'shared_bank'      => $shared_bank,
        'characters'       => $characters,
        'website_stats'    => [
            'total_login_days' => (int)$login_days,
            'missions'         => $missions,
        ],
    ]);
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
            "friendly_objective" => getClearObjective($row['goal_type'], $row['goal_target'], $row['title'], $row['description']),
            "friendly_reward" => renderRewardString($row['reward_item_string']),
            "status" => $row['status']
        ];
    }
    
    echo json_encode($events);
}
