<?php
/**
 * PSOBB API: Character & Bank Viewer
 * 
 * Fetches and parses a player's binary character (.psochar) and bank (.psobank) files
 * directly from the game server's player database folder.
 * Gracefully overlays live online stats if the character is active in-game.
 * Falls back to high-fidelity, lore-friendly Mock data if no files exist locally.
 */
require_once __DIR__ . '/config.php';

if (ob_get_length()) ob_clean();
start_secure_session();
header('Content-Type: application/json');

// 1. Verify User Login
if (empty($_SESSION['user']) || empty($_SESSION['user']['account_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$accountId = (int)$_SESSION['user']['account_id'];
$username = '';

// Retrieve BB username from Session user data
if (isset($_SESSION['user']['BBLicenses']) && is_array($_SESSION['user']['BBLicenses']) && count($_SESSION['user']['BBLicenses']) > 0) {
    $username = $_SESSION['user']['BBLicenses'][0]['UserName'] ?? '';
}

// Fallback search in session variables if username is not directly set
if (empty($username)) {
    $username = $_SESSION['user']['LastPlayerName'] ?? $_SESSION['user']['username'] ?? '';
}

$username = strtolower(trim($username));
$slot = isset($_GET['slot']) ? clamp((int)$_GET['slot'], 0, 3) : 0;

// Path definition to players folder (checks production /opt first, falls back to local dev)
$playersDir = '/opt/newserv/system/players/';
if (!is_dir($playersDir)) {
    $playersDir = __DIR__ . '/../../newserv/system/players/';
}

// Helper to clamp values
function clamp($val, $min, $max) {
    return max($min, min($max, $val));
}

// Helper to resolve player files case-insensitively
function resolve_player_file($dir, $filename) {
    $fullPath = $dir . $filename;
    if (file_exists($fullPath)) {
        return $fullPath;
    }
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $f) {
            if (strcasecmp($f, $filename) === 0) {
                return $dir . $f;
            }
        }
    }
    return $fullPath;
}

$psocharPath = resolve_player_file($playersDir, "player_{$username}_{$slot}.psochar");

// Master Class Names Map
$CLASS_MAP = [
    0 => 'HUmar', 1 => 'HUnewearl', 2 => 'HUcast', 3 => 'RAmar', 
    4 => 'RAcast', 5 => 'RAcaseal', 6 => 'FOmarl', 7 => 'FOnewm', 
    8 => 'FOnewearl', 9 => 'HUcaseal', 10 => 'FOmar', 11 => 'RAmarl'
];

// Master Section ID Map
$SECID_MAP = [
    0 => 'Viridia', 1 => 'Greenill', 2 => 'Skyly', 3 => 'Bluefull', 
    4 => 'Purplenum', 5 => 'Pinkal', 6 => 'Redria', 7 => 'Oran', 
    8 => 'Yellowboze', 9 => 'Whitill'
];

// Helper to parse binary ItemData (20 bytes)
function parse_item_data($bytes) {
    if (strlen($bytes) < 20) return null;
    
    $data1 = substr($bytes, 0, 12);
    $id = unpack('V', substr($bytes, 12, 4))[1];
    $data2 = substr($bytes, 16, 4);
    
    $group = ord($data1[0]);
    $type1 = ord($data1[1]);
    $type2 = ord($data1[2]);
    
    $hex = bin2hex($bytes);
    
    $item = [
        'hex' => strtoupper($hex),
        'item_id' => $id,
        'group' => $group,
        'equipped' => false,
        'name' => 'Unknown Item',
        'attrs' => []
    ];
    
    // Resolve basic names using generic map (fallback helper)
    $weaponNames = [
        0 => 'Saber', 1 => 'Brand', 2 => 'Buster', 3 => 'Pallasch', 4 => 'Gladius',
        5 => 'Sword', 6 => 'Gigush', 7 => 'Breaker', 8 => 'Claymore', 9 => 'Calibur',
        10 => 'Dagger', 11 => 'Knife', 12 => 'Blade', 13 => 'Edge', 14 => 'Ripper',
        15 => 'Partisan', 16 => 'Halbert', 17 => 'Glaive', 18 => 'Berdys', 19 => 'Gungnir',
        20 => 'Slicer', 21 => 'Spinner', 22 => 'Cutter', 23 => 'Sawcer', 24 => 'Diska',
        25 => 'Handgun', 26 => 'Autogun', 27 => 'Lockgun', 28 => 'Railgun', 29 => 'Raygun',
        30 => 'Rifle', 31 => 'Sniper', 32 => 'Blaster', 33 => 'Beam', 34 => 'Laser',
        35 => 'Mechgun', 36 => 'Assault', 37 => 'Repeater', 38 => 'Gatling', 39 => 'Vulcan',
        40 => 'Shot', 41 => 'Spread', 42 => 'Cannon', 43 => 'Launcher', 44 => 'Arms',
        45 => 'Cane', 46 => 'Stick', 47 => 'Mace', 48 => 'Club',
        49 => 'Rod', 50 => 'Pole', 51 => 'Pillar', 52 => 'Striker',
        53 => 'Wand', 54 => 'Staff', 55 => 'Baton', 56 => 'Scepter',
        70 => 'Talis', 71 => 'Mahu', 72 => 'Hitogata'
    ];
    
    if ($group === 0x00) {
        // Weapon
        $wId = $type1;
        $wName = $weaponNames[$wId] ?? 'Weapon';
        $grind = ord($data1[3]);
        $specialId = ord($data1[4]) & 0x3F;
        
        $item['name'] = $wName . ($grind > 0 ? " +$grind" : "");
        $item['grind'] = $grind;
        
        // Unidentified flag
        if (ord($data1[4]) & 0x80) {
            $item['name'] = "???? " . $wName;
            $item['unidentified'] = true;
        }
        
        // Attributes (Up to 3 attributes: native, beast, machine, dark, hit)
        $attrMap = [1 => 'Native', 2 => 'A.Beast', 3 => 'Machine', 4 => 'Dark', 5 => 'Hit'];
        for ($a = 0; $a < 3; $a++) {
            $aType = ord($data1[6 + $a * 2]);
            $aVal = ord($data1[7 + $a * 2]);
            if ($aType > 0 && isset($attrMap[$aType])) {
                // Convert to signed 8-bit byte
                if ($aVal > 127) $aVal -= 256;
                $item['attrs'][] = ['type' => $attrMap[$aType], 'value' => $aVal];
            }
        }
    } elseif ($group === 0x01) {
        // Armor, Shield or Unit
        if ($type1 === 0x01) {
            $item['name'] = 'Armor';
            $slots = ord($data1[5]);
            $defBonus = unpack('s', substr($data1, 6, 2))[1];
            $evpBonus = unpack('s', substr($data1, 8, 2))[1];
            $item['slots'] = $slots;
            $item['def_bonus'] = $defBonus;
            $item['evp_bonus'] = $evpBonus;
        } elseif ($type1 === 0x02) {
            $item['name'] = 'Shield';
            $defBonus = unpack('s', substr($data1, 6, 2))[1];
            $evpBonus = unpack('s', substr($data1, 8, 2))[1];
            $item['def_bonus'] = $defBonus;
            $item['evp_bonus'] = $evpBonus;
        } elseif ($type1 === 0x03) {
            $item['name'] = 'Unit';
            $modifier = unpack('s', substr($data1, 6, 2))[1];
            $item['modifier'] = $modifier;
        }
    } elseif ($group === 0x02) {
        // MAG
        $level = ord($data1[2]);
        $pbFlags = ord($data1[3]);
        $def = unpack('v', substr($data1, 4, 2))[1] / 100;
        $pow = unpack('v', substr($data1, 6, 2))[1] / 100;
        $dex = unpack('v', substr($data1, 8, 2))[1] / 100;
        $mind = unpack('v', substr($data1, 10, 2))[1] / 100;
        $synchro = ord($data2[0]);
        $iq = ord($data2[1]);
        
        $item['name'] = 'MAG';
        $item['mag_stats'] = [
            'level' => $level,
            'def' => $def,
            'pow' => $pow,
            'dex' => $dex,
            'mind' => $mind,
            'synchro' => $synchro,
            'iq' => $iq,
            'pb_flags' => $pbFlags
        ];
    } elseif ($group === 0x03) {
        // Tool / Tech Disk
        if ($type1 === 0x02) {
            // Disk
            $techLvl = ord($data1[2]) + 1;
            $techNum = ord($data1[4]);
            $techs = ['Foie', 'Gifoie', 'Rafoie', 'Barta', 'Gibarta', 'Rabarta', 'Zonde', 'Gizonde', 'Razonde', 'Grants', 'Deband', 'Jellen', 'Zalure', 'Shifta', 'Ryuker', 'Resta', 'Anti', 'Reverser', 'Megid'];
            $techName = $techs[$techNum] ?? 'Technique';
            $item['name'] = "Disk: $techName Lv.$techLvl";
        } else {
            $item['name'] = 'Consumable';
            $count = ord($data1[5]);
            if ($count === 0) $count = 1;
            $item['count'] = $count;
        }
    } elseif ($group === 0x04) {
        // Meseta
        $amount = unpack('V', $data2)[1];
        $item['name'] = 'Meseta';
        $item['count'] = $amount;
    }
    
    // Reverse Map from local item_hex.txt if available to make it super accurate!
    $itemHexPath = __DIR__ . '/../item_hex.txt';
    if (file_exists($itemHexPath)) {
        $searchPrefix = substr($item['hex'], 0, 6);
        $lines = file($itemHexPath);
        foreach ($lines as $line) {
            if (strpos(trim($line), $searchPrefix) === 0) {
                $parts = preg_split('/\s{2,}/', trim($line));
                $fullName = end($parts);
                $fullName = preg_replace('/\s+x1$/', '', $fullName);
                
                // Add details
                if ($group === 0x00) {
                    $item['name'] = $fullName . ($grind > 0 ? " +$grind" : "");
                    if (isset($item['unidentified'])) $item['name'] = "???? " . $fullName;
                } else if ($group === 0x01) {
                    $item['name'] = $fullName;
                } else if ($group === 0x02) {
                    $item['name'] = $fullName;
                } else if ($group === 0x03 && $type1 !== 0x02) {
                    $item['name'] = $fullName;
                }
                break;
            }
        }
    }
    
    return $item;
}

// 2. Fetch File Contents OR fallback to Mock
if (file_exists($psocharPath)) {
    // Parsing real binary player profile
    $charData = @file_get_contents($psocharPath);
    if ($charData === false || strlen($charData) < 0x399C) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to read binary character file."]);
        exit;
    }
    
    // Inventory starts at offset 8 (size 844)
    $invBlock = substr($charData, 8, 844);
    $numItems = ord($invBlock[0]);
    $hpMats = ord($invBlock[1]) >> 1;
    $tpMats = ord($invBlock[2]) >> 1;
    
    $inventory = [];
    for ($i = 0; $i < 30; $i++) {
        $offset = 4 + $i * 28;
        $present = ord($invBlock[$offset]);
        if ($present) {
            $flags = unpack('V', substr($invBlock, $offset + 4, 4))[1];
            $itemBytes = substr($invBlock, $offset + 8, 20);
            
            $item = parse_item_data($itemBytes);
            if ($item) {
                $item['equipped'] = ($flags & 8) !== 0;
                $inventory[] = $item;
            }
        }
    }
    
    // Display data (stats + visual + name) starts at offset 852 (size 400)
    $dispBlock = substr($charData, 852, 400);
    
    // Stats Block
    $atp = unpack('v', substr($dispBlock, 0, 2))[1];
    $mst = unpack('v', substr($dispBlock, 2, 2))[1];
    $evp = unpack('v', substr($dispBlock, 4, 2))[1];
    $hp = unpack('v', substr($dispBlock, 6, 2))[1];
    $dfp = unpack('v', substr($dispBlock, 8, 2))[1];
    $ata = unpack('v', substr($dispBlock, 10, 2))[1];
    $lck = unpack('v', substr($dispBlock, 12, 2))[1];
    
    $lvl = unpack('V', substr($dispBlock, 24, 4))[1];
    $exp = unpack('V', substr($dispBlock, 28, 4))[1];
    $meseta = unpack('V', substr($dispBlock, 32, 4))[1];
    
    // Visual Block
    $sectionIdVal = ord($dispBlock[36 + 0x30]);
    $charClassVal = ord($dispBlock[36 + 0x31]);
    
    $charClass = $CLASS_MAP[$charClassVal] ?? 'HUmar';
    $sectionId = $SECID_MAP[$sectionIdVal] ?? 'Viridia';
    
    // UTF-16LE name starts at offset 116 (size 32)
    $nameBytes = substr($dispBlock, 116, 32);
    $charName = mb_convert_encoding($nameBytes, 'UTF-8', 'UTF-16LE');
    $charName = trim(str_replace("\x00", "", $charName));
    
    // Material stats from extensions striped across items
    $powerMats = ord($invBlock[4 + 8 * 28 + 3]);
    $mindMats = ord($invBlock[4 + 9 * 28 + 3]);
    $evadeMats = ord($invBlock[4 + 10 * 28 + 3]);
    $defMats = ord($invBlock[4 + 11 * 28 + 3]);
    $luckMats = ord($invBlock[4 + 12 * 28 + 3]);
    
    // Play time
    $playTime = unpack('V', substr($charData, 8 + 0x04E8, 4))[1];
    
    // Parse Bank
    $bankItems = [];
    $bankMeseta = 0;
    
    // Try to load dedicated .psobank file first, then fall back to embedded bank
    $psobankPath = resolve_player_file($playersDir, "player_{$username}_{$slot}.psobank");
    $bankData = false;
    if (file_exists($psobankPath)) {
        $bankData = @file_get_contents($psobankPath);
    }
    
    if ($bankData === false) {
        // Embedded bank inside .psochar starts at offset 8 + 1784 = 1792 (size 4808)
        $bankBlock = substr($charData, 1792, 4808);
    } else {
        // Raw .psobank file starting at offset 0 (same structural format)
        $bankBlock = $bankData;
    }
    
    if (strlen($bankBlock) >= 8) {
        $bankNumItems = unpack('V', substr($bankBlock, 0, 4))[1];
        $bankMeseta = unpack('V', substr($bankBlock, 4, 4))[1];
        
        for ($i = 0; $i < min(200, $bankNumItems); $i++) {
            $offset = 8 + $i * 24;
            if ($offset + 24 <= strlen($bankBlock)) {
                $itemBytes = substr($bankBlock, $offset, 20);
                $amount = unpack('v', substr($bankBlock, $offset + 20, 2))[1];
                $present = unpack('v', substr($bankBlock, $offset + 22, 2))[1];
                
                if ($present) {
                    $item = parse_item_data($itemBytes);
                    if ($item) {
                        if ($item['group'] === 0x03 && $item['name'] !== 'Disk') {
                            $item['count'] = $amount;
                        }
                        $bankItems[] = $item;
                    }
                }
            }
        }
    }
    
    // Shared Bank parsing
    $sharedBankItems = [];
    $sharedBankMeseta = 0;
    $sharedBankPath = resolve_player_file($playersDir, "shared_bank_{$username}.psobank");
    if (file_exists($sharedBankPath)) {
        $sharedData = @file_get_contents($sharedBankPath);
        if ($sharedData && strlen($sharedData) >= 8) {
            $shNumItems = unpack('V', substr($sharedData, 0, 4))[1];
            $sharedBankMeseta = unpack('V', substr($sharedData, 4, 4))[1];
            for ($i = 0; $i < min(200, $shNumItems); $i++) {
                $offset = 8 + $i * 24;
                if ($offset + 24 <= strlen($sharedData)) {
                    $itemBytes = substr($sharedData, $offset, 20);
                    $amount = unpack('v', substr($sharedData, $offset + 20, 2))[1];
                    $present = unpack('v', substr($sharedData, $offset + 22, 2))[1];
                    if ($present) {
                        $item = parse_item_data($itemBytes);
                        if ($item) {
                            if ($item['group'] === 0x03 && $item['name'] !== 'Disk') {
                                $item['count'] = $amount;
                            }
                            $sharedBankItems[] = $item;
                        }
                    }
                }
            }
        }
    }
    
    $character = [
        'name' => $charName,
        'class' => $charClass,
        'level' => $lvl + 1,
        'section_id' => $sectionId,
        'experience' => $exp,
        'play_time_hours' => round($playTime / 3600, 1),
        'stats' => [
            'ATP' => $atp, 'MST' => $mst, 'EVP' => $evp, 'HP' => $hp, 
            'DFP' => $dfp, 'ATA' => $ata, 'LCK' => $lck, 'Meseta' => $meseta
        ],
        'mats' => [
            'HP' => $hpMats, 'TP' => $tpMats, 'Power' => $powerMats, 
            'Mind' => $mindMats, 'Evade' => $evadeMats, 'Def' => $defMats, 'Luck' => $luckMats
        ],
        'inventory' => $inventory,
        'bank' => [
            'items' => $bankItems,
            'meseta' => $bankMeseta
        ],
        'shared_bank' => [
            'items' => $sharedBankItems,
            'meseta' => $sharedBankMeseta
        ]
    ];
    
    // Check if character is online to dynamically merge stats from live server memory
    $clientsResponse = @file_get_contents($NEWSERV_API_URL . '/y/clients');
    $accountOnline = false;
    $onlineCharName = '';
    if ($clientsResponse !== false) {
        $clients = json_decode($clientsResponse, true);
        if (is_array($clients)) {
            foreach ($clients as $c) {
                if (isset($c['Account']['AccountID']) && (int)$c['Account']['AccountID'] === $accountId) {
                    $accountOnline = true;
                    $onlineCharName = $c['Name'] ?? '';
                    
                    if (isset($c['Name']) && $c['Name'] === $charName) {
                        $character['online'] = true;
                        $character['lobby_id'] = $c['LobbyID'] ?? null;
                        
                        // Pull material counts from the internal server API
                        $character['mats']['HP'] = (int)($c['NumHPMaterialsUsed'] ?? $character['mats']['HP']);
                        $character['mats']['TP'] = (int)($c['NumTPMaterialsUsed'] ?? $character['mats']['TP']);
                        $character['mats']['Power'] = (int)($c['NumPowerMaterialsUsed'] ?? $character['mats']['Power']);
                        $character['mats']['Def'] = (int)($c['NumDefMaterialsUsed'] ?? $character['mats']['Def']);
                        $character['mats']['Mind'] = (int)($c['NumMindMaterialsUsed'] ?? $character['mats']['Mind']);
                        $character['mats']['Evade'] = (int)($c['NumEvadeMaterialsUsed'] ?? $character['mats']['Evade']);
                        $character['mats']['Luck'] = (int)($c['NumLuckMaterialsUsed'] ?? $character['mats']['Luck']);
                        
                        if (isset($c['Level'])) {
                            $character['level'] = (int)$c['Level'];
                        }
                        
                        // Direct override of inventory items with memory allocation mapping
                        if (isset($c['InventoryItems']) && is_array($c['InventoryItems'])) {
                            $liveInv = [];
                            foreach ($c['InventoryItems'] as $item) {
                                $desc = $item['Description'] ?? '';
                                $data = $item['Data'] ?? '';
                                $itemId = $item['ItemID'] ?? 0;
                                $flags = $item['Flags'] ?? 0;
                                
                                $bin = hex2bin(preg_replace('/[^a-fA-F0-9]/', '', $data));
                                $parsed = parse_item_data($bin);
                                if ($parsed) {
                                    $parsed['item_id'] = $itemId;
                                    $parsed['equipped'] = ($flags & 8) !== 0;
                                    if (!empty($desc)) $parsed['name'] = $desc;
                                    $liveInv[] = $parsed;
                                }
                            }
                            $character['inventory'] = $liveInv;
                        }
                    }
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true, 
        'character' => $character, 
        'mock' => false,
        'account_online' => $accountOnline,
        'online_char_name' => $onlineCharName
    ]);
    exit;
}

// ============================================================================
// 3. Mock Graceful Fallback (Developer Local PC environment)
// ============================================================================

// High Quality Sato MAG Mock
$satoMag = [
    'hex' => '0236C803E8032E3A000000000000000028644A0E',
    'item_id' => 999901,
    'group' => 2,
    'equipped' => true,
    'name' => 'Sato',
    'mag_stats' => [
        'level' => 200, 'def' => 50.0, 'pow' => 0.0, 'dex' => 0.0, 
        'mind' => 150.0, 'synchro' => 120, 'iq' => 200, 'pb_flags' => 42
    ]
];

$kamaMag = [
    'hex' => '0204C803E8030500A50532000000000028644A05',
    'item_id' => 999902,
    'group' => 2,
    'equipped' => true,
    'name' => 'Kama',
    'mag_stats' => [
        'level' => 200, 'def' => 5.0, 'pow' => 145.0, 'dex' => 50.0, 
        'mind' => 0.0, 'synchro' => 120, 'iq' => 200, 'pb_flags' => 5
    ]
];

$diwariMag = [
    'hex' => '0245C803E803050096052D000000000028644A0E',
    'item_id' => 999903,
    'group' => 2,
    'equipped' => true,
    'name' => 'Diwari',
    'mag_stats' => [
        'level' => 200, 'def' => 5.0, 'pow' => 150.0, 'dex' => 45.0, 
        'mind' => 0.0, 'synchro' => 120, 'iq' => 200, 'pb_flags' => 40
    ]
];

// Helper mock items
$psychoWand = [
    'hex' => '005C000000000000000000000000000000000000',
    'item_id' => 880001, 'group' => 0, 'equipped' => true, 'name' => 'Psycho Wand',
    'grind' => 0, 'attrs' => [['type' => 'Hit', 'value' => 20], ['type' => 'Dark', 'value' => 15]]
];

$spreadNeedle = [
    'hex' => '0029002800000000000000000000000000000000',
    'item_id' => 880002, 'group' => 0, 'equipped' => true, 'name' => 'Spread Needle +40',
    'grind' => 40, 'attrs' => [['type' => 'Hit', 'value' => 30], ['type' => 'Native', 'value' => 25]]
];

$sealedJSword = [
    'hex' => '0054000000000000000000000000000000000000',
    'item_id' => 880003, 'group' => 0, 'equipped' => true, 'name' => 'Sealed J-Sword',
    'grind' => 0, 'attrs' => [['type' => 'Hit', 'value' => 15], ['type' => 'Native', 'value' => 25]]
];

$guldMilla = [
    'hex' => '007D000000000000000000000000000000000000',
    'item_id' => 880004, 'group' => 0, 'equipped' => false, 'name' => 'Guld Milla',
    'grind' => 9, 'attrs' => [['type' => 'A.Beast', 'value' => 30]]
];

$heavenPunisher = [
    'hex' => '002C000000000000000000000000000000000000',
    'item_id' => 880005, 'group' => 0, 'equipped' => false, 'name' => 'Heaven Punisher',
    'grind' => 0, 'attrs' => [['type' => 'Machine', 'value' => 25], ['type' => 'Dark', 'value' => 20]]
];

$yamato = [
    'hex' => '007C000A00000000000000000000000000000000',
    'item_id' => 880006, 'group' => 0, 'equipped' => true, 'name' => 'Yamato +10',
    'grind' => 10, 'attrs' => [['type' => 'Hit', 'value' => 10]]
];

$auraField = [
    'hex' => '0101410040040000000000000000000000000000',
    'item_id' => 770001, 'group' => 1, 'equipped' => true, 'name' => 'Aura Field',
    'slots' => 4, 'def_bonus' => 22, 'evp_bonus' => 15
];

$luminousField = [
    'hex' => '01013D0040040000000000000000000000000000',
    'item_id' => 770002, 'group' => 1, 'equipped' => true, 'name' => 'Luminous Field',
    'slots' => 4, 'def_bonus' => 15, 'evp_bonus' => 10
];

$crimsonCoat = [
    'hex' => '01015B0040040000000000000000000000000000',
    'item_id' => 770003, 'group' => 1, 'equipped' => true, 'name' => 'Crimson Coat',
    'slots' => 4, 'def_bonus' => 10, 'evp_bonus' => 8
];

$kasamiBracer = [
    'hex' => '01026E0040000000000000000000000000000000',
    'item_id' => 770004, 'group' => 1, 'equipped' => true, 'name' => 'Kasami Bracer',
    'def_bonus' => 12, 'evp_bonus' => 8
];

$standStill = [
    'hex' => '0102710040000000000000000000000000000000',
    'item_id' => 770005, 'group' => 1, 'equipped' => true, 'name' => 'Stand Still Shield',
    'def_bonus' => 18, 'evp_bonus' => 12
];

$delsaberShield = [
    'hex' => '0102430040000000000000000000000000000000',
    'item_id' => 770006, 'group' => 1, 'equipped' => true, 'name' => 'Shield of Delsaber',
    'def_bonus' => 5, 'evp_bonus' => 5
];

$v502 = [
    'hex' => '01036B0000000000000000000000000000000000',
    'item_id' => 770007, 'group' => 1, 'equipped' => true, 'name' => 'V502'
];

$v501 = [
    'hex' => '01036A0000000000000000000000000000000000',
    'item_id' => 770008, 'group' => 1, 'equipped' => true, 'name' => 'V501'
];

$godBattle = [
    'hex' => '01033D0000000000000000000000000000000000',
    'item_id' => 770009, 'group' => 1, 'equipped' => true, 'name' => 'God/Battle'
];

$godMind = [
    'hex' => '01032C0000000000000000000000000000000000',
    'item_id' => 770010, 'group' => 1, 'equipped' => true, 'name' => 'God/Mind'
];

$godPower = [
    'hex' => '01032B0000000000000000000000000000000000',
    'item_id' => 770011, 'group' => 1, 'equipped' => true, 'name' => 'God/Power'
];

$photonDrop = ['hex' => '0310000000010000000000000000000000000000', 'item_id' => 660001, 'group' => 3, 'name' => 'Photon Drop', 'count' => 1];
$addSlot = ['hex' => '0314000000010000000000000000000000000000', 'item_id' => 660002, 'group' => 3, 'name' => 'AddSlot', 'count' => 1];
$scapedoll = ['hex' => '0309000000010000000000000000000000000000', 'item_id' => 660003, 'group' => 3, 'name' => 'Scape Doll', 'count' => 1];

// Generate Mock Characters Sheet
$mockCharacters = [
    0 => [
        'name' => 'Aria',
        'class' => 'FOnewearl',
        'level' => 135,
        'section_id' => 'Pinkal',
        'experience' => 18450122,
        'play_time_hours' => 342.5,
        'stats' => [
            'ATP' => 450, 'MST' => 1350, 'EVP' => 620, 'HP' => 840, 
            'DFP' => 390, 'ATA' => 125, 'LCK' => 80, 'Meseta' => 250000
        ],
        'mats' => [
            'HP' => 50, 'TP' => 75, 'Power' => 0, 'Mind' => 120, 
            'Evade' => 10, 'Def' => 5, 'Luck' => 15
        ],
        'inventory' => [
            $psychoWand,
            $auraField,
            $kasamiBracer,
            $v502,
            $godMind,
            $godMind,
            $godMind,
            $satoMag,
            ['hex' => '03020A0004010000000000000000000000000000', 'item_id' => 550001, 'group' => 3, 'name' => 'Disk: Resta Lv.11'],
            ['hex' => '03021E0000010000000000000000000000000000', 'item_id' => 550002, 'group' => 3, 'name' => 'Disk: Deband Lv.15'],
            ['hex' => '03021F0000010000000000000000000000000000', 'item_id' => 550003, 'group' => 3, 'name' => 'Disk: Shifta Lv.15'],
            $scapedoll, $scapedoll,
            ['hex' => '03000000000A0000000000000000000000000000', 'item_id' => 550004, 'group' => 3, 'name' => 'Trimate', 'count' => 10],
            ['hex' => '03010000000A0000000000000000000000000000', 'item_id' => 550005, 'group' => 3, 'name' => 'Trifluid', 'count' => 10],
            ['hex' => '0303000000050000000000000000000000000000', 'item_id' => 550006, 'group' => 3, 'name' => 'Sol Atomizer', 'count' => 5],
        ],
        'bank' => [
            'items' => [
                ['hex' => '0068000000000000000000000000000000000000', 'item_id' => 440001, 'group' => 0, 'name' => 'Caduceus', 'attrs' => [['type' => 'Hit', 'value' => 15]]],
                ['hex' => '0069000000000000000000000000000000000000', 'item_id' => 440002, 'group' => 0, 'name' => 'Elysion', 'attrs' => []],
                ['hex' => '006F000000000000000000000000000000000000', 'item_id' => 440003, 'group' => 0, 'name' => 'Summit Moon', 'attrs' => []],
                $addSlot, $addSlot,
                $photonDrop, $photonDrop, $photonDrop,
            ],
            'meseta' => 500000
        ]
    ],
    1 => [
        'name' => 'Kaelen',
        'class' => 'RAcast',
        'level' => 180,
        'section_id' => 'Purplenum',
        'experience' => 48210344,
        'play_time_hours' => 610.2,
        'stats' => [
            'ATP' => 1180, 'MST' => 0, 'EVP' => 740, 'HP' => 1650, 
            'DFP' => 580, 'ATA' => 218, 'LCK' => 100, 'Meseta' => 999999
        ],
        'mats' => [
            'HP' => 80, 'TP' => 0, 'Power' => 110, 'Mind' => 0, 
            'Evade' => 15, 'Def' => 20, 'Luck' => 40
        ],
        'inventory' => [
            $spreadNeedle,
            $luminousField,
            $standStill,
            $v501,
            $godBattle,
            $godPower,
            $kamaMag,
            ['hex' => '0032000000000000000000000000000000000000', 'item_id' => 550101, 'group' => 0, 'name' => 'Frozen Shooter +9', 'grind' => 9, 'attrs' => [['type' => 'Hit', 'value' => 15]]],
            $scapedoll, $scapedoll,
            ['hex' => '03000000000A0000000000000000000000000000', 'item_id' => 550102, 'group' => 3, 'name' => 'Trimate', 'count' => 10],
            ['hex' => '0303000000050000000000000000000000000000', 'item_id' => 550103, 'group' => 3, 'name' => 'Sol Atomizer', 'count' => 5],
        ],
        'bank' => [
            'items' => [
                $guldMilla,
                $heavenPunisher,
                ['hex' => '0033000000000000000000000000000000000000', 'item_id' => 440101, 'group' => 0, 'name' => 'Snow Queen', 'attrs' => [['type' => 'Hit', 'value' => 25]]],
                ['hex' => '001A000000000000000000000000000000000000', 'item_id' => 440102, 'group' => 0, 'name' => 'Red Handgun +5', 'grind' => 5, 'attrs' => []],
                $photonDrop, $photonDrop,
            ],
            'meseta' => 250000
        ]
    ],
    2 => [
        'name' => 'Saber_X',
        'class' => 'HUmar',
        'level' => 200,
        'section_id' => 'Skyly',
        'experience' => 80000000,
        'play_time_hours' => 1250.4,
        'stats' => [
            'ATP' => 1399, 'MST' => 640, 'EVP' => 810, 'HP' => 1820, 
            'DFP' => 620, 'ATA' => 170, 'LCK' => 100, 'Meseta' => 999999
        ],
        'mats' => [
            'HP' => 150, 'TP' => 150, 'Power' => 150, 'Mind' => 10, 
            'Evade' => 20, 'Def' => 25, 'Luck' => 45
        ],
        'inventory' => [
            $sealedJSword,
            $crimsonCoat,
            $delsaberShield,
            $godBattle,
            $godPower,
            $godPower,
            $godPower,
            $diwariMag,
            $scapedoll,
            ['hex' => '03000000000A0000000000000000000000000000', 'item_id' => 550201, 'group' => 3, 'name' => 'Trimate', 'count' => 10],
        ],
        'bank' => [
            'items' => [
                ['hex' => '002D000000000000000000000000000000000000', 'item_id' => 440201, 'group' => 0, 'name' => 'Tsumikiri J-Sword', 'attrs' => [['type' => 'Hit', 'value' => 30]]],
                ['hex' => '0023000000000000000000000000000000000000', 'item_id' => 440202, 'group' => 0, 'name' => 'Lavateinn', 'attrs' => []],
                ['hex' => '0019000000000000000000000000000000000000', 'item_id' => 440203, 'group' => 0, 'name' => 'Red Sword +52', 'grind' => 52, 'attrs' => []],
                $photonDrop, $photonDrop,
            ],
            'meseta' => 999999
        ]
    ],
    3 => [
        'name' => 'Miku',
        'class' => 'HUnewearl',
        'level' => 45,
        'section_id' => 'Viridia',
        'experience' => 1250220,
        'play_time_hours' => 32.1,
        'stats' => [
            'ATP' => 580, 'MST' => 310, 'EVP' => 390, 'HP' => 510, 
            'DFP' => 280, 'ATA' => 92, 'LCK' => 45, 'Meseta' => 45000
        ],
        'mats' => [
            'HP' => 10, 'TP' => 5, 'Power' => 12, 'Mind' => 4, 
            'Evade' => 0, 'Def' => 2, 'Luck' => 0
        ],
        'inventory' => [
            $yamato,
            ['hex' => '0101030040020000000000000000000000000000', 'item_id' => 550301, 'group' => 1, 'equipped' => true, 'name' => 'Custom Frame', 'slots' => 2, 'def_bonus' => 2, 'evp_bonus' => 1],
            ['hex' => '0102030040000000000000000000000000000000', 'item_id' => 550302, 'group' => 1, 'equipped' => true, 'name' => 'Custom Barrier', 'def_bonus' => 1, 'evp_bonus' => 1],
            ['hex' => '020032000000280000002800000028000000280E', 'item_id' => 550303, 'group' => 2, 'equipped' => true, 'name' => 'Baby MAG', 'mag_stats' => ['level' => 50, 'def' => 10.0, 'pow' => 20.0, 'dex' => 10.0, 'mind' => 10.0, 'synchro' => 40, 'iq' => 40, 'pb_flags' => 1]],
            ['hex' => '0300000000050000000000000000000000000000', 'item_id' => 550304, 'group' => 3, 'name' => 'Monomate', 'count' => 5],
            ['hex' => '0301000000050000000000000000000000000000', 'item_id' => 550305, 'group' => 3, 'name' => 'Monofluid', 'count' => 5],
        ],
        'bank' => [
            'items' => [
                ['hex' => '0000000000000000000000000000000000000000', 'item_id' => 440301, 'group' => 0, 'name' => 'Saber +5', 'grind' => 5, 'attrs' => []],
                ['hex' => '03000000000A0000000000000000000000000000', 'item_id' => 440302, 'group' => 3, 'name' => 'Monomate', 'count' => 10],
            ],
            'meseta' => 10000
        ]
    ]
];

$mockSharedBank = [
    'items' => [
        ['hex' => '0010000000000000000000000000000000000000', 'item_id' => 330001, 'group' => 0, 'name' => 'Spread Needle +40', 'grind' => 40, 'attrs' => [['type' => 'Hit', 'value' => 30], ['type' => 'Native', 'value' => 15]]],
        ['hex' => '002D000000000000000000000000000000000000', 'item_id' => 330002, 'group' => 0, 'name' => 'Sealed J-Sword', 'grind' => 0, 'attrs' => [['type' => 'A.Beast', 'value' => 25]]],
        ['hex' => '002C000000000000000000000000000000000000', 'item_id' => 330003, 'group' => 0, 'name' => 'Heaven Punisher', 'grind' => 0, 'attrs' => [['type' => 'Dark', 'value' => 20]]],
        ['hex' => '0019000000000000000000000000000000000000', 'item_id' => 330004, 'group' => 0, 'name' => 'Frozen Shooter +9', 'grind' => 9, 'attrs' => [['type' => 'Hit', 'value' => 15]]],
        ['hex' => '001A000000000000000000000000000000000000', 'item_id' => 330005, 'group' => 0, 'name' => 'Red Handgun +50', 'grind' => 50, 'attrs' => [['type' => 'Machine', 'value' => 35]]],
        ['hex' => '0036000000000000000000000000000000000000', 'item_id' => 330006, 'group' => 0, 'name' => 'Demolition Comet', 'grind' => 0, 'attrs' => []],
        ['hex' => '0101340040040000000000000000000000000000', 'item_id' => 330007, 'group' => 1, 'name' => 'Luminous Field', 'slots' => 4, 'def_bonus' => 12, 'evp_bonus' => 8],
        ['hex' => '0102600040000000000000000000000000000000', 'item_id' => 330008, 'group' => 1, 'name' => 'Kasami Bracer', 'def_bonus' => 8, 'evp_bonus' => 5],
        ['hex' => '01036B0000000000000000000000000000000000', 'item_id' => 330009, 'group' => 1, 'name' => 'V502'],
        ['hex' => '01033D0000000000000000000000000000000000', 'item_id' => 330010, 'group' => 1, 'name' => 'God/Battle'],
        ['hex' => '0310000000320000000000000000000000000000', 'item_id' => 330011, 'group' => 3, 'name' => 'Photon Drop', 'count' => 50],
        ['hex' => '03110000000F0000000000000000000000000000', 'item_id' => 330012, 'group' => 3, 'name' => 'Photon Crystal', 'count' => 15],
        $addSlot, $addSlot, $addSlot,
        ['hex' => '03021E0000010000000000000000000000000000', 'item_id' => 330013, 'group' => 3, 'name' => 'Disk: Resta Lv.15'],
        ['hex' => '03021F0000010000000000000000000000000000', 'item_id' => 330014, 'group' => 3, 'name' => 'Disk: Shifta Lv.15'],
        ['hex' => '0302200000010000000000000000000000000000', 'item_id' => 330015, 'group' => 3, 'name' => 'Disk: Deband Lv.15'],
        ['hex' => '03050000000A0000000000000000000000000000', 'item_id' => 330016, 'group' => 3, 'name' => 'Power Material', 'count' => 10],
        ['hex' => '03050100000A0000000000000000000000000000', 'item_id' => 330017, 'group' => 3, 'name' => 'Mind Material', 'count' => 10],
        ['hex' => '0305050000050000000000000000000000000000', 'item_id' => 330018, 'group' => 3, 'name' => 'Luck Material', 'count' => 5],
    ],
    'meseta' => 999999
];

$character = $mockCharacters[$slot];
$character['shared_bank'] = $mockSharedBank;

// Mock online state for demonstration visual wow
$character['online'] = ($slot === 0);
if ($character['online']) {
    $character['lobby_id'] = 1;
}

echo json_encode([
    'success' => true, 
    'character' => $character, 
    'mock' => true, 
    'account_online' => true, 
    'online_char_name' => 'Aria'
]);
exit;
?>
