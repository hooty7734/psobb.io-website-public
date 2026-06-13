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
$slot = isset($_GET['slot']) ? clamp((int)$_GET['slot'], 0, 19) : 0;

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
        'type1' => $type1,
        'type2' => $type2,
        'equipped' => false,
        'name' => 'Unknown Item',
        'attrs' => []
    ];
    
    // ---- Compute primary_identifier (same algorithm as newserv ItemData::primary_identifier) ----
    $isSRank = false;
    if ($group === 0x00) {
        $isSRank = ($type1 > 0x6F && $type1 < 0x89) || ($type1 > 0xA4 && $type1 < 0xAA);
    }
    
    if ($group === 0x04) {
        $primaryId = 0x04000000;
    } elseif ($group === 0x03 && $type1 === 0x02) {
        // Tech disk: tech number is in data1[4], level in data1[2]
        $techNum = ord($data1[4]);
        $techLvl = ord($data1[2]);
        $primaryId = 0x03020000 | ($techNum << 8) | $techLvl;
    } elseif ($group === 0x02) {
        // Mag: only uses data1[1]
        $primaryId = 0x02000000 | ($type1 << 16);
    } elseif ($isSRank) {
        // S-rank weapon: no subtype
        $primaryId = ($group << 24) | ($type1 << 16);
    } else {
        // Normal weapon, armor, tool
        $primaryId = ($group << 24) | ($type1 << 16) | ($type2 << 8);
    }
    
    // Convert to 6-char hex lookup key (primary_identifier >> 8, zero-padded)
    $lookupKey = strtolower(substr(sprintf('%08X', $primaryId), 0, 6));
    
    // ---- Load names-v4.json (primary_identifier code -> name, direct from newserv) ----
    static $codeToName = null;
    if ($codeToName === null) {
        $codeToName = [];
        $mapPath = __DIR__ . '/names-v4.json';
        if (file_exists($mapPath)) {
            $map = json_decode(file_get_contents($mapPath), true);
            if ($map) {
                foreach ($map as $code => $name) {
                    $codeToName[strtolower($code)] = $name;
                }
            }
        }
    }
    
    // ---- Parse group-specific data ----
    if ($group === 0x00) {
        // Weapon
        $grind = ord($data1[3]);
        $item['grind'] = $grind;
        
        // Unidentified flag
        $isUnid = (ord($data1[4]) & 0x80) !== 0;
        if ($isUnid) $item['unidentified'] = true;
        
        // Look up name
        $wName = $codeToName[$lookupKey] ?? null;
        if (!$wName) {
            // Fallback: try without subtype for S-rank
            $fallbackKey = strtolower(sprintf('%02X%02X00', $group, $type1));
            $wName = $codeToName[$fallbackKey] ?? 'Weapon';
        }
        $wName = ucwords($wName);
        
        $item['name'] = ($isUnid ? '???? ' : '') . $wName . ($grind > 0 ? " +$grind" : "");
        
        // Attributes (Native, A.Beast, Machine, Dark, Hit)
        $attrMap = [1 => 'Native', 2 => 'A.Beast', 3 => 'Machine', 4 => 'Dark', 5 => 'Hit'];
        for ($a = 0; $a < 3; $a++) {
            $aType = ord($data1[6 + $a * 2]);
            $aVal = ord($data1[7 + $a * 2]);
            if ($aType > 0 && isset($attrMap[$aType])) {
                if ($aVal > 127) $aVal -= 256;
                $item['attrs'][] = ['type' => $attrMap[$aType], 'value' => $aVal];
            }
        }
    } elseif ($group === 0x01) {
        // Armor, Shield, Unit
        $aName = $codeToName[$lookupKey] ?? null;
        if ($aName) {
            $item['name'] = ucwords($aName);
        } elseif ($type1 === 0x01) {
            $item['name'] = 'Armor';
        } elseif ($type1 === 0x02) {
            $item['name'] = 'Shield';
        } elseif ($type1 === 0x03) {
            $item['name'] = 'Unit';
        }
        
        if ($type1 === 0x01) {
            $slots = ord($data1[5]);
            $defBonus = unpack('s', substr($data1, 6, 2))[1];
            $evpBonus = unpack('s', substr($data1, 8, 2))[1];
            $item['slots'] = $slots;
            $item['def_bonus'] = $defBonus;
            $item['evp_bonus'] = $evpBonus;
        } elseif ($type1 === 0x02) {
            $defBonus = unpack('s', substr($data1, 6, 2))[1];
            $evpBonus = unpack('s', substr($data1, 8, 2))[1];
            $item['def_bonus'] = $defBonus;
            $item['evp_bonus'] = $evpBonus;
        } elseif ($type1 === 0x03) {
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
        
        $magName = $codeToName[$lookupKey] ?? 'MAG';
        $item['name'] = ucwords($magName);
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
            $techLvl = ord($data1[2]) + 1;
            $techNum = ord($data1[4]);
            $techs = ['Foie', 'Gifoie', 'Rafoie', 'Barta', 'Gibarta', 'Rabarta', 'Zonde', 'Gizonde', 'Razonde', 'Grants', 'Deband', 'Jellen', 'Zalure', 'Shifta', 'Ryuker', 'Resta', 'Anti', 'Reverser', 'Megid'];
            $techName = $techs[$techNum] ?? 'Technique';
            $item['name'] = "Disk: $techName Lv.$techLvl";
        } else {
            $tName = $codeToName[$lookupKey] ?? 'Consumable';
            $item['name'] = ucwords($tName);
            $count = ord($data1[5]);
            if ($count === 0) $count = 1;
            $item['count'] = $count;
        }
    } elseif ($group === 0x04) {
        $amount = unpack('V', $data2)[1];
        $item['name'] = 'Meseta';
        $item['count'] = $amount;
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
    $charName = normalize_pso_string($nameBytes, true);
    
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
                
                $item = parse_item_data($itemBytes);
                if ($item && $item['group'] <= 0x04) {
                    if ($item['group'] === 0x03 && $item['name'] !== 'Disk') {
                        $item['count'] = $amount;
                    }
                    $bankItems[] = $item;
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
                    
                    $item = parse_item_data($itemBytes);
                    if ($item && $item['group'] <= 0x04) {
                        if ($item['group'] === 0x03 && $item['name'] !== 'Disk') {
                            $item['count'] = $amount;
                        }
                        $sharedBankItems[] = $item;
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
                    // newserv's decode() already strips the \t+language prefix
                    $onlineCharName = $c['Name'] ?? '';
                    
                    if ($onlineCharName === $charName) {
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
                        
                        // Live stats directly from newserv memory
                        if (isset($c['ATP'])) $character['stats']['ATP'] = (int)$c['ATP'];
                        if (isset($c['DFP'])) $character['stats']['DFP'] = (int)$c['DFP'];
                        if (isset($c['MST'])) $character['stats']['MST'] = (int)$c['MST'];
                        if (isset($c['ATA'])) $character['stats']['ATA'] = (int)$c['ATA'];
                        if (isset($c['EVP'])) $character['stats']['EVP'] = (int)$c['EVP'];
                        if (isset($c['LCK'])) $character['stats']['LCK'] = (int)$c['LCK'];
                        if (isset($c['HP'])) $character['stats']['HP'] = (int)$c['HP'];
                        if (isset($c['Meseta'])) $character['stats']['Meseta'] = (int)$c['Meseta'];
                        if (isset($c['Level'])) $character['level'] = (int)$c['Level'];
                        if (isset($c['SectionID'])) $character['section_id'] = $c['SectionID'];
                        if (isset($c['CharClass'])) $character['class'] = $c['CharClass'];
                        
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
} else {
    http_response_code(404);
    echo json_encode(["success" => false, "error" => "Character file for Slot " . ($slot + 1) . " does not exist. Please log in-game and create a character first!"]);
    exit;
}
?>
