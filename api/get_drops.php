<?php
require_once 'config.php';
header('Content-Type: application/json');

$endpoint = '/y/data/rare-table/rare-table-v4';
$url = $NEWSERV_API_URL . $endpoint;

$cache_dir = __DIR__ . '/../scratch';
if (!is_dir($cache_dir)) {
    @mkdir($cache_dir, 0777, true);
}
$cache_file = $cache_dir . '/rare_table_cache_v3.json';
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
            // Flatten Newserv's deeply nested JSON array and map item names
            $flat_drops = [];
            $seen = [];
            
            // Load Mapping dictionaries
            $hex_to_name = [];
            $item_map_file = __DIR__ . '/item_map.json';
            if (file_exists($item_map_file)) {
                $item_map = json_decode(file_get_contents($item_map_file), true);
                if (is_array($item_map)) {
                    foreach ($item_map as $name => $hex) {
                        // Normalize hex by padding to 6 characters
                        $normalized_hex = strtoupper(str_pad($hex, 6, '0', STR_PAD_LEFT));
                        // Title case the name
                        $hex_to_name[$normalized_hex] = ucwords($name);
                    }
                }
            }
            
            $item_subtypes = [];
            $item_subtype_file = __DIR__ . '/item_subtypes.json';
            if (file_exists($item_subtype_file)) {
                $item_subtypes = json_decode(file_get_contents($item_subtype_file), true);
                if (!is_array($item_subtypes)) $item_subtypes = [];
            }

            $item_equips = [];
            $item_equip_file = __DIR__ . '/item_equip_map.json';
            if (file_exists($item_equip_file)) {
                $raw_equips = json_decode(file_get_contents($item_equip_file), true);
                if (is_array($raw_equips)) {
                    foreach ($raw_equips as $name => $classes) {
                        $item_equips[strtolower(trim($name))] = $classes;
                    }
                }
            }

            foreach ($decoded as $mode => $episodes) {
                if ($mode !== 'Normal') continue; // Only care about Normal gameplay drops
                foreach ($episodes as $ep => $diffs) {
                    $episode_num = (int)str_replace('Episode', '', $ep);
                    foreach ($diffs as $diff => $sids) {
                        $difficulty_name = ($diff === 'VeryHard') ? 'Very Hard' : $diff;
                        foreach ($sids as $sid => $monsters) {
                            $section_id = $sid;
                            if ($section_id === 'Greennill') $section_id = 'Greenill'; // Fix Newserv typo
                            
                            foreach ($monsters as $monster => $drops) {
                                foreach ($drops as $drop) {
                                    $rate_str = $drop[0]; // e.g. "7/8192" or large int
                                    $item_hex_raw = $drop[1]; // e.g. integer 43520 or "0x00A600"
                                    $newserv_item_name = $drop[2] ?? null; // Item name from newserv ItemNameIndex
                                    
                                    // Calculate percentage
                                    $rate_pct = 0;
                                    $rate_display = $rate_str;
                                    if (is_string($rate_str) && strpos($rate_str, '/') !== false) {
                                        list($num, $den) = explode('/', $rate_str);
                                        $rate_pct = ((float)$num / (float)$den) * 100;
                                    } elseif (is_numeric($rate_str)) {
                                        $rate_pct = ((float)$rate_str / 4294967296) * 100;
                                        $den = floor(4294967296 / (float)$rate_str);
                                        $rate_display = "1/" . $den;
                                    }
                                    
                                    // Parse Item Hex
                                    if (is_int($item_hex_raw)) {
                                        $clean_hex = strtoupper(dechex($item_hex_raw));
                                    } else {
                                        $clean_hex = str_replace('0x', '', strtoupper((string)$item_hex_raw));
                                    }
                                    $clean_hex = str_pad($clean_hex, 6, '0', STR_PAD_LEFT);
                                    
                                    // Prefer newserv-provided item name, fall back to local hex map
                                    if (!empty($newserv_item_name) && is_string($newserv_item_name)) {
                                        $item_name = ucwords($newserv_item_name);
                                    } else {
                                        $item_name = $hex_to_name[$clean_hex] ?? "Unknown Item ($clean_hex)";
                                    }
                                    
                                    // Determine Item Type
                                    $type_byte = substr($clean_hex, 0, 2);
                                    $item_type = 'Unknown';
                                    if ($type_byte === '00') $item_type = 'Weapon';
                                    elseif ($type_byte === '01') {
                                        $sub_byte = substr($clean_hex, 2, 2);
                                        if ($sub_byte === '01') $item_type = 'Armor';
                                        elseif ($sub_byte === '02') $item_type = 'Shield';
                                        elseif ($sub_byte === '03') $item_type = 'Unit';
                                        else $item_type = 'Armor/Shield/Unit';
                                    }
                                    elseif ($type_byte === '02') $item_type = 'Mag';
                                    elseif ($type_byte === '03') $item_type = 'Tool';
                                    
                                    $item_subtype = $item_subtypes[strtolower($item_name)] ?? 'Other';
                                    $item_equip_classes = $item_equips[strtolower(trim($item_name))] ?? null;
                                    
                                    // Clean up Monster Name (e.g. Box-Cave1 -> Cave 1 Box, HILDEBEAR -> Hildebear)
                                    $monster_clean = str_replace('_', ' ', $monster);
                                    if (strpos($monster_clean, 'Box-') === 0) {
                                        $monster_clean = str_replace('Box-', '', $monster_clean) . ' Box';
                                    } else {
                                        $monster_clean = ucwords(strtolower($monster_clean));
                                    }
                                    
                                    // Deduplicate identical items from the same monster
                                    $uniq = "$episode_num|$difficulty_name|$section_id|$monster_clean|$item_name";
                                    if (isset($seen[$uniq])) continue;
                                    $seen[$uniq] = true;
                                    
                                    $flat_drops[] = [
                                        "episode" => $episode_num,
                                        "difficulty" => $difficulty_name,
                                        "section_id" => $section_id,
                                        "monster" => $monster_clean,
                                        "item" => $item_name,
                                        "type" => $item_type,
                                        "subtype" => $item_subtype,
                                        "equippable_classes" => $item_equip_classes,
                                        "rate" => $rate_display,
                                        "rate_percent" => $rate_pct
                                    ];
                                }
                            }
                        }
                    }
                }
            }

            $data_json = json_encode(["success" => true, "data" => $flat_drops]);
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

http_response_code(500);
echo json_encode(["success" => false, "error" => "Failed to fetch drop telemetry data from game server."]);
exit;
?>
