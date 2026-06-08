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
function send_personal_mail($client_acc_id, $from_name, $text)
{
    global $NEWSERV_API_URL;

    $date_str = date('Y-m-d H:i:s');

    // Fetch player preferences; default to English and allow sending
    $marker = "\tE";
    try {
        $db = get_db();
        $stmt = $db->prepare("SELECT language, receive_system_mail FROM users WHERE account_id = :acc LIMIT 1");
        $stmt->bindValue(':acc', $client_acc_id, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if ($row && (int) $row['receive_system_mail'] === 0) {
            return; // Player has opted out of system mails.
        }
        if ($row && strtolower(trim($row['language'])) === 'jp') {
            $marker = "\tJ";
        }
    } catch (Exception $e) {
        // Fallback to English on DB error
    }

    // NewServ uses \tE / \tJ as the language marker — required at the start of both fields
    $from_name = $marker . trim($from_name);
    $text = $marker . trim($text);

    // UTF-16LE encoding helper — tries the fastest available method
    $to_utf16 = function (string $s) {
        if (function_exists('mb_convert_encoding'))
            return mb_convert_encoding($s, 'UTF-16LE', 'UTF-8');
        if (function_exists('iconv'))
            return iconv('UTF-8', 'UTF-16LE', $s);
        return preg_replace('/(.)/s', "$1\x00", $s); // ASCII-only fallback
    };

    $from_utf16 = (string) $to_utf16($from_name);
    $date_utf16 = (string) $to_utf16($date_str);
    $text_utf16 = (string) $to_utf16($text);

    // 8-byte header (size=0x0458, cmd=0x81, flag=0x00010000) + 1104 zero bytes = 1112 total
    $packet = pack('vvV', 0x0458, 0x0081, 0x00010000) . str_repeat("\x00", 1104);

    $packet = substr_replace($packet, str_pad(substr($from_utf16, 0, 30), 30, "\x00"), 12, 30); // from_name
    $packet = substr_replace($packet, pack('V', $client_acc_id), 44, 4); // to_guild_card_number
    $packet = substr_replace($packet, str_pad(substr($date_utf16, 0, 38), 38, "\x00"), 48, 38); // received_date
    $packet = substr_replace($packet, str_pad(substr($text_utf16, 0, 1022), 1022, "\x00"), 88, 1022); // text

    $exec_payload = json_encode(['command' => 'on ' . $client_acc_id . ' sc ' . bin2hex($packet)]);
    @file_get_contents(
        $NEWSERV_API_URL . '/y/shell-exec',
        false,
        stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $exec_payload,
            ]
        ])
    );
}

/**
 * Translates underlying backend mission goal types and targets into human-readable descriptions.
 * This is used strictly for rendering objectives gracefully on the frontend UI layout.
 *
 * @param string $type The category of the objective (e.g., 'ITEM', 'BOSS_ARENA').
 * @param mixed $target The specific numerical ID or string required to satisfy the goal.
 * @param string $title The title of the mission for context disambiguation.
 * @param string $desc The description of the mission for context disambiguation.
 * @return string A fully formatted, user-friendly description of what the player must do.
 */
function get_boss_episode_by_context($title, $desc, $default_floor)
{
    $text = strtolower($title . ' ' . $desc);
    $default_floor = (int) $default_floor;

    // Olga Flow (Ep2) vs Vol Opt (Ep1)
    if ($default_floor === 13) {
        if (strpos($text, 'olga') !== false || strpos($text, 'flow') !== false || strpos($text, 'seabed') !== false || strpos($text, 'フロウ') !== false || strpos($text, '海底') !== false) {
            return 2;
        }
        return 1;
    }

    // Gal Gryphon (Ep2) vs De Rol Le (Ep1)
    if ($default_floor === 12) {
        if (strpos($text, 'gryphon') !== false || strpos($text, 'gal') !== false || strpos($text, 'cca') !== false || strpos($text, 'jungle') !== false || strpos($text, 'mountain') !== false || strpos($text, 'seaside') !== false || strpos($text, 'グリフォン') !== false || strpos($text, '中央管理区') !== false || strpos($text, '高山') !== false || strpos($text, '海岸') !== false || strpos($text, 'ジャングル') !== false) {
            return 2;
        }
        return 1;
    }

    // Barba Ray (Ep2) vs Dark Falz (Ep1)
    if ($default_floor === 14) {
        if (strpos($text, 'barba') !== false || strpos($text, 'ray') !== false || strpos($text, 'temple') !== false || strpos($text, 'バルバレイ') !== false || strpos($text, '神殿') !== false) {
            return 2;
        }
        return 1;
    }

    // Gol Dragon (Ep2)
    if ($default_floor === 15) {
        return 2;
    }

    // Saint-Milion (Ep4)
    if ($default_floor === 9) {
        return 4;
    }

    // Forest Dragon & Sil Dragon are both Episode 1 Forest
    if ($default_floor === 11) {
        return 1;
    }

    return 1;
}

function getClearObjective($type, $target, $title = '', $desc = '')
{
    switch ($type) {
        case 'MESETA':
            if ($target === 'ANY')
                return __('Collect Meseta (Any source)');
            return __('Hold at least %s Meseta in inventory', number_format((int) $target));
        case 'LEVEL':
            return __('Reach Level %s', htmlspecialchars($target));
        case 'LEVEL_UP':
            return __('Earn Level Ups (Any Character)');
        case 'MAT_CONSUME':
            return __('Consume Any Materials');
        case 'PLAYTIME':
            if ($target === 'ANY')
                return __('Accumulate Playtime (Server-wide Tracker)');
            return __('Accumulate %s total hours of playtime', (int) ($target / 3600));
        case 'CHALLENGE_STAGES':
            if ($target === 'ANY')
                return __('Clear Any Challenge Mode Stages');
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

                $generic_weapon_hex_map = [
                    'Saber' => '000100',
                    'Brand' => '000101',
                    'Buster' => '000102',
                    'Pallasch' => '000103',
                    'Gladius' => '000104',
                    'Sword' => '000200',
                    'Gigush' => '000201',
                    'Breaker' => '000202',
                    'Claymore' => '000203',
                    'Calibur' => '000204',
                    'Dagger' => '000300',
                    'Knife' => '000301',
                    'Blade' => '000302',
                    'Edge' => '000303',
                    'Ripper' => '000304',
                    'Partisan' => '000400',
                    'Halbert' => '000401',
                    'Glaive' => '000402',
                    'Berdys' => '000403',
                    'Gungnir' => '000404',
                    'Slicer' => '000500',
                    'Spinner' => '000501',
                    'Cutter' => '000502',
                    'Sawcer' => '000503',
                    'Diska' => '000504',
                    'Handgun' => '000600',
                    'Autogun' => '000601',
                    'Lockgun' => '000602',
                    'Railgun' => '000603',
                    'Raygun' => '000604',
                    'Rifle' => '000700',
                    'Sniper' => '000701',
                    'Blaster' => '000702',
                    'Beam' => '000703',
                    'Laser' => '000704',
                    'Mechgun' => '000800',
                    'Assault' => '000801',
                    'Repeater' => '000802',
                    'Gatling' => '000803',
                    'Vulcan' => '000804',
                    'Shot' => '000900',
                    'Spread' => '000901',
                    'Cannon' => '000902',
                    'Launcher' => '000903',
                    'Arms' => '000904',
                    'Cane' => '000A00',
                    'Stick' => '000A01',
                    'Mace' => '000A02',
                    'Club' => '000A03',
                    'Rod' => '000B00',
                    'Pole' => '000B01',
                    'Pillar' => '000B02',
                    'Striker' => '000B03',
                    'Wand' => '000C00',
                    'Staff' => '000C01',
                    'Baton' => '000C02',
                    'Scepter' => '000C03',
                    'Talis' => '008C00',
                    'Mahu' => '008C01',
                    'Hitogata' => '008C02'
                ];
                $reverse_generic_map = array_flip($generic_weapon_hex_map);

                if (isset($reverse_generic_map[$hex_base])) {
                    $itemName = $reverse_generic_map[$hex_base];
                } else {
                    $map_path = __DIR__ . '/item_map.json';
                    if (file_exists($map_path)) {
                        $map = json_decode(file_get_contents($map_path), true);
                        $reverse_map = array_flip($map);
                        if (isset($reverse_map[$hex_base])) {
                            $itemName = ucwords($reverse_map[$hex_base]);
                        }
                    }
                }
            }
            return __('Find and hold the item: %s', htmlspecialchars(__($itemName)));
        case 'TECHNIQUE':
            return __('Learn the technique: %s', htmlspecialchars($target));
        case 'BATTLE_WINS':
            return __('Achieve %s 1st place Battle Mode wins', htmlspecialchars($target));
        case 'MAT_HP':
            return __('Consume a total of %s HP Materials', htmlspecialchars($target));
        case 'MAT_TP':
            return __('Consume a total of %s TP Materials', htmlspecialchars($target));
        case 'MAT_POWER':
            return __('Consume a total of %s Power Materials', htmlspecialchars($target));
        case 'MAT_DEF':
            return __('Consume a total of %s Def Materials', htmlspecialchars($target));
        case 'MAT_MIND':
            return __('Consume a total of %s Mind Materials', htmlspecialchars($target));
        case 'MAT_EVADE':
            return __('Consume a total of %s Evade Materials', htmlspecialchars($target));
        case 'MAT_LUCK':
            return __('Consume a total of %s Luck Materials', htmlspecialchars($target));
        case 'EXPLORATION':
            // Maps integer Floor IDs returned by the game client memory map to friendly Names
            $floors = [1 => 'Forest 1', 2 => 'Forest 2', 3 => 'Cave 1', 4 => 'Cave 2', 5 => 'Cave 3', 6 => 'Mine 1', 7 => 'Mine 2', 8 => 'Ruins 1', 9 => 'Ruins 2', 10 => 'Ruins 3'];
            $loc = $floors[$target] ?? "Floor $target";
            return __('[Ep 1] Explore the %s', htmlspecialchars(__($loc)));
        case 'PATROL':
            $floors = [1 => 'Forest 1', 2 => 'Forest 2', 3 => 'Cave 1', 4 => 'Cave 2', 5 => 'Cave 3', 6 => 'Mine 1', 7 => 'Mine 2', 8 => 'Ruins 1', 9 => 'Ruins 2', 10 => 'Ruins 3'];
            $loc = $floors[$target] ?? "Floor $target";
            return __('[Ep 1] Survive and patrol the %s for 10 minutes', htmlspecialchars(__($loc)));
        case 'BOSS_ARENA':
            if ($target === 'ANY_DRAGON')
                return __('Defeat Any Dragon Boss (Forest, Sil, or Gol)');

            // Custom multi-boss community event targets (tracked dynamically in cron_community.php)
            $custom_boss_events = [
                'DIGITAL_BLASPHEMY' => __('Defeat Vol Opt (+1 pt), Gol Dragon (+2 pts), or Shambertin (+3 pts). Difficulty bonus: +1 Hard, +2 VHard, +3 Ult'),
                'EP1_BOSS_RUSH' => __('Defeat Any Episode 1 Boss'),
                'EP2_BOSS_RUSH' => __('Defeat Any Episode 2 Boss'),
                'ALL_BOSSES' => __('Defeat Any Boss (All Episodes)'),
                'DRACONIC_DOMINION' => __('Defeat Dragon (Ep1), Gol Dragon (Ep2), or Shambertin (Ep4)'),
                'CATACLYSMIC_CORE' => __('Defeat Dark Falz (Ep1), Olga Flow (Ep2), or Shambertin (Ep4)'),
            ];
            if (isset($custom_boss_events[$target]))
                return $custom_boss_events[$target];

            $target_floor = (int) $target;
            if ($target_floor === 0)
                return __('Boss Bounty Completed!');
            $ep = (string) get_boss_episode_by_context($title, $desc, $target_floor);

            $boss = "Boss at Floor $target_floor";
            if ($target_floor === 11) {
                $boss = (strpos($text, 'sil') !== false || strpos($text, 'シル') !== false) ? 'Sil Dragon (Forest)' : 'Dragon (Forest)';
            } elseif ($target_floor === 12) {
                $boss = ($ep === '2') ? 'Gal Gryphon (CCA)' : 'De Rol Le (Caves)';
            } elseif ($target_floor === 13) {
                $boss = ($ep === '2') ? 'Olga Flow (Seabed)' : 'Vol Opt (Mines)';
            } elseif ($target_floor === 14) {
                $boss = ($ep === '2') ? 'Barba Ray (Temple)' : 'Dark Falz (Ruins)';
            } elseif ($target_floor === 15) {
                $boss = 'Gol Dragon (Spaceship)';
            } elseif ($target_floor === 9) {
                $boss = 'Saint-Milion (Crater)';
            }

            $has_the = true;
            foreach (['Vol Opt', 'Dark Falz', 'Olga Flow', 'Gal Gryphon', 'Saint-Milion', 'Gol Dragon'] as $ntb) {
                if (stripos($boss, $ntb) !== false) {
                    $has_the = false;
                    break;
                }
            }
            if ($has_the) {
                return __('[Ep %s] Defeat the %s', $ep, htmlspecialchars(__($boss)));
            } else {
                return __('[Ep %s] Defeat %s', $ep, htmlspecialchars(__($boss)));
            }
        case 'MENTOR_BOSS':
            if ($target === 'ANY_DRAGON')
                return __('Mentor a player (5+ levels lower) through Any Dragon Boss (Forest, Sil, or Gol)');

            $target_floor = (int) $target;
            if ($target_floor === 0)
                return __('Mentor Bounty Completed!');
            $ep = (string) get_boss_episode_by_context($title, $desc, $target_floor);

            $boss = "Boss at Floor $target_floor";
            if ($target_floor === 11) {
                $boss = (strpos($text, 'sil') !== false || strpos($text, 'シル') !== false) ? 'Sil Dragon (Forest)' : 'Dragon (Forest)';
            } elseif ($target_floor === 12) {
                $boss = ($ep === '2') ? 'Gal Gryphon (CCA)' : 'De Rol Le (Caves)';
            } elseif ($target_floor === 13) {
                $boss = ($ep === '2') ? 'Olga Flow (Seabed)' : 'Vol Opt (Mines)';
            } elseif ($target_floor === 14) {
                $boss = ($ep === '2') ? 'Barba Ray (Temple)' : 'Dark Falz (Ruins)';
            } elseif ($target_floor === 15) {
                $boss = 'Gol Dragon (Spaceship)';
            } elseif ($target_floor === 9) {
                $boss = 'Saint-Milion (Crater)';
            }
            return __('[Ep %s] Carry a lower-level player (5+ levels lower) through the %s fight', $ep, htmlspecialchars(__($boss)));
        case 'HARDCORE_MENTOR':
            if ($target === 'ANY_DRAGON')
                return __('Hardcore Carry 3 lower-level players (10+ levels lower) through Any Dragon Boss');

            $target_floor = (int) $target;
            if ($target_floor === 0)
                return __('Hardcore Carry Completed!');
            $ep = (string) get_boss_episode_by_context($title, $desc, $target_floor);

            $boss = "Boss at Floor $target_floor";
            if ($target_floor === 11) {
                $boss = (strpos($text, 'sil') !== false || strpos($text, 'シル') !== false) ? 'Sil Dragon (Forest)' : 'Dragon (Forest)';
            } elseif ($target_floor === 12) {
                $boss = ($ep === '2') ? 'Gal Gryphon (CCA)' : 'De Rol Le (Caves)';
            } elseif ($target_floor === 13) {
                $boss = ($ep === '2') ? 'Olga Flow (Seabed)' : 'Vol Opt (Mines)';
            } elseif ($target_floor === 14) {
                $boss = ($ep === '2') ? 'Barba Ray (Temple)' : 'Dark Falz (Ruins)';
            } elseif ($target_floor === 15) {
                $boss = 'Gol Dragon (Spaceship)';
            } elseif ($target_floor === 9) {
                $boss = 'Saint-Milion (Crater)';
            }
            return __('[Ep %s] Hardcore Carry 3 lower-level players (10+ levels lower) through the %s fight', $ep, htmlspecialchars(__($boss)));
        case 'DIVERSE_PARTY_BOSS':
            if ($target === 'ANY_DRAGON')
                return __('Defeat Any Dragon Boss with a diverse party (HU, RA, FO)');

            $target_floor = (int) $target;
            if ($target_floor === 0)
                return __('Diverse Party Bounty Completed!');
            $ep = (string) get_boss_episode_by_context($title, $desc, $target_floor);

            $boss = "Boss at Floor $target_floor";
            if ($target_floor === 11) {
                $boss = (strpos($text, 'sil') !== false || strpos($text, 'シル') !== false) ? 'Sil Dragon (Forest)' : 'Dragon (Forest)';
            } elseif ($target_floor === 12) {
                $boss = ($ep === '2') ? 'Gal Gryphon (CCA)' : 'De Rol Le (Caves)';
            } elseif ($target_floor === 13) {
                $boss = ($ep === '2') ? 'Olga Flow (Seabed)' : 'Vol Opt (Mines)';
            } elseif ($target_floor === 14) {
                $boss = ($ep === '2') ? 'Barba Ray (Temple)' : 'Dark Falz (Ruins)';
            } elseif ($target_floor === 15) {
                $boss = 'Gol Dragon (Spaceship)';
            } elseif ($target_floor === 9) {
                $boss = 'Saint-Milion (Crater)';
            }

            $has_the = true;
            foreach (['Vol Opt', 'Dark Falz', 'Olga Flow', 'Gal Gryphon', 'Saint-Milion', 'Gol Dragon'] as $ntb) {
                if (stripos($boss, $ntb) !== false) {
                    $has_the = false;
                    break;
                }
            }
            if ($has_the) {
                return __('[Ep %s] Defeat the %s with a diverse party (HU, RA, FO)', $ep, htmlspecialchars(__($boss)));
            } else {
                return __('[Ep %s] Defeat %s with a diverse party (HU, RA, FO)', $ep, htmlspecialchars(__($boss)));
            }
        case 'SPEEDRUN_BOSS':
            list($target_floor, $time_limit) = explode('_', $target);
            $target_floor = (int) $target_floor;
            $ep = (string) get_boss_episode_by_context($title, $desc, $target_floor);

            $boss = "Boss at Floor $target_floor";
            if ($target_floor === 11) {
                $boss = (strpos($text, 'sil') !== false || strpos($text, 'シル') !== false) ? 'Sil Dragon (Forest)' : 'Dragon (Forest)';
            } elseif ($target_floor === 12) {
                $boss = ($ep === '2') ? 'Gal Gryphon (CCA)' : 'De Rol Le (Caves)';
            } elseif ($target_floor === 13) {
                $boss = ($ep === '2') ? 'Olga Flow (Seabed)' : 'Vol Opt (Mines)';
            } elseif ($target_floor === 14) {
                $boss = ($ep === '2') ? 'Barba Ray (Temple)' : 'Dark Falz (Ruins)';
            } elseif ($target_floor === 15) {
                $boss = 'Gol Dragon (Spaceship)';
            } elseif ($target_floor === 9) {
                $boss = 'Saint-Milion (Crater)';
            }

            $has_the = true;
            foreach (['Vol Opt', 'Dark Falz', 'Olga Flow', 'Gal Gryphon', 'Saint-Milion', 'Gol Dragon'] as $ntb) {
                if (stripos($boss, $ntb) !== false) {
                    $has_the = false;
                    break;
                }
            }

            $mins = floor($time_limit / 60);
            $secs = $time_limit % 60;
            if ($mins > 0) {
                if ($secs == 0) {
                    return $has_the
                        ? __('[Ep %s] Defeat the %s in under %s minutes', $ep, htmlspecialchars(__($boss)), $mins)
                        : __('[Ep %s] Defeat %s in under %s minutes', $ep, htmlspecialchars(__($boss)), $mins);
                }
                return $has_the
                    ? __('[Ep %s] Defeat the %s in under %s minutes and %s seconds', $ep, htmlspecialchars(__($boss)), $mins, $secs)
                    : __('[Ep %s] Defeat %s in under %s minutes and %s seconds', $ep, htmlspecialchars(__($boss)), $mins, $secs);
            }
            return $has_the
                ? __('[Ep %s] Defeat the %s in under %s seconds', $ep, htmlspecialchars(__($boss)), htmlspecialchars($time_limit))
                : __('[Ep %s] Defeat %s in under %s seconds', $ep, htmlspecialchars(__($boss)), htmlspecialchars($time_limit));
        case 'SPEEDRUN_FLOOR':
            list($target_floor, $time_limit) = explode('_', $target);
            $floors = [1 => 'Forest 1', 2 => 'Forest 2', 3 => 'Cave 1', 4 => 'Cave 2', 5 => 'Cave 3', 6 => 'Mine 1', 7 => 'Mine 2', 8 => 'Ruins 1', 9 => 'Ruins 2', 10 => 'Ruins 3'];
            $loc = $floors[$target_floor] ?? "Floor $target_floor";
            $mins = floor($time_limit / 60);
            $secs = $time_limit % 60;
            if ($secs == 0)
                return __('[Ep 1] Clear %s in under %s minutes', htmlspecialchars(__($loc)), $mins);
            return __('[Ep 1] Clear %s in under %s minutes and %s seconds', htmlspecialchars(__($loc)), $mins, $secs);
        default:
            return htmlspecialchars($type) . ": " . htmlspecialchars($target);
    }
}

/**
 * Obfuscates Unidentified weapon strings before rendering them to the Bounty Board UI.
 * Prevents players from knowing the exact special ability or grind modifiers prior to taking the weapon to the Tekker.
 *
 * @param string $rewardStr The raw reward string stored in the database (e.g., '? Charge Saber 20/0/45/0' or '000A01008100...')
 * @return string Safely obfuscated string rendering identically to the in-game display ('???? Saber')
 */
function renderRewardString($rewardStr)
{
    if (empty($rewardStr))
        return "";
    $segments = explode(',', $rewardStr);
    $processed = [];

    foreach ($segments as $segment) {
        $segment = trim($segment);
        if (empty($segment))
            continue;
        $parts = explode(' ', $segment);
        $firstPart = $parts[0];

        // If the segment starts with a 32-character Hex payload, parse it
        if (ctype_xdigit($firstPart) && (strlen($firstPart) >= 32 || strlen($firstPart) === 6)) {
            $base = substr($firstPart, 0, 6);
            $untekked_flag = (strlen($firstPart) >= 32) ? hexdec(substr($firstPart, 8, 2)) : 0;

            $weaponName = $base;

            $generic_item_hex_map = [
                'Saber' => '000100',
                'Brand' => '000101',
                'Buster' => '000102',
                'Pallasch' => '000103',
                'Gladius' => '000104',
                'Sword' => '000200',
                'Gigush' => '000201',
                'Breaker' => '000202',
                'Claymore' => '000203',
                'Calibur' => '000204',
                'Dagger' => '000300',
                'Knife' => '000301',
                'Blade' => '000302',
                'Edge' => '000303',
                'Ripper' => '000304',
                'Partisan' => '000400',
                'Halbert' => '000401',
                'Glaive' => '000402',
                'Berdys' => '000403',
                'Gungnir' => '000404',
                'Slicer' => '000500',
                'Spinner' => '000501',
                'Cutter' => '000502',
                'Sawcer' => '000503',
                'Diska' => '000504',
                'Handgun' => '000600',
                'Autogun' => '000601',
                'Lockgun' => '000602',
                'Railgun' => '000603',
                'Raygun' => '000604',
                'Rifle' => '000700',
                'Sniper' => '000701',
                'Blaster' => '000702',
                'Beam' => '000703',
                'Laser' => '000704',
                'Mechgun' => '000800',
                'Assault' => '000801',
                'Repeater' => '000802',
                'Gatling' => '000803',
                'Vulcan' => '000804',
                'Shot' => '000900',
                'Spread' => '000901',
                'Cannon' => '000902',
                'Launcher' => '000903',
                'Arms' => '000904',
                'Cane' => '000A00',
                'Stick' => '000A01',
                'Mace' => '000A02',
                'Club' => '000A03',
                'Rod' => '000B00',
                'Pole' => '000B01',
                'Pillar' => '000B02',
                'Striker' => '000B03',
                'Wand' => '000C00',
                'Staff' => '000C01',
                'Baton' => '000C02',
                'Scepter' => '000C03',
                'Talis' => '008C00',
                'Mahu' => '008C01',
                'Hitogata' => '008C02',
                'Frame' => '010100',
                'Armor' => '010101',
                'Psy Armor' => '010102',
                'Giga Frame' => '010103',
                'Soul Frame' => '010104',
                'Cross Armor' => '010105',
                'Solid Frame' => '010106',
                'Brave Armor' => '010107',
                'Hyper Frame' => '010108',
                'Grand Armor' => '010109',
                'Shock Frame' => '01010A',
                'King\'s Frame' => '01010B',
                'Dragon Frame' => '01010C',
                'Absorb Armor' => '01010D',
                'Protect Frame' => '01010E',
                'General Armor' => '01010F',
                'Perfect Frame' => '010110',
                'Valiant Frame' => '010111',
                'Imperial Armor' => '010112',
                'Holiness Armor' => '010113',
                'Guardian Armor' => '010114',
                'Divinity Armor' => '010115',
                'Ultimate Frame' => '010116',
                'Celestial Armor' => '010117',
                'Barrier' => '010200',
                'Shield' => '010201',
                'Core Shield' => '010202',
                'Giga Shield' => '010203',
                'Soul Barrier' => '010204',
                'Hard Shield' => '010205',
                'Brave Barrier' => '010206',
                'Solid Shield' => '010207',
                'Flame Barrier' => '010208',
                'Plasma Barrier' => '010209',
                'Freeze Barrier' => '01020A',
                'Psychic Barrier' => '01020B',
                'General Shield' => '01020C',
                'Protect Barrier' => '01020D',
                'Glorious Shield' => '01020E',
                'Imperial Barrier' => '01020F',
                'Guardian Shield' => '010210',
                'Divinity Barrier' => '010211',
                'Ultimate Shield' => '010212',
                'Spiritual Shield' => '010213',
                'Celestial Shield' => '010214'
            ];
            $reverse_generic_map = array_flip($generic_item_hex_map);

            if (isset($reverse_generic_map[$base])) {
                $weaponName = $reverse_generic_map[$base];
            } else {
                $map_path = __DIR__ . '/item_map.json';
                if (file_exists($map_path)) {
                    $map = json_decode(file_get_contents($map_path), true);
                    $reverse_map = array_flip($map);
                    if (isset($reverse_map[$base])) {
                        $weaponName = ucwords($reverse_map[$base]);
                    }
                }
            }

            $prefix = ($untekked_flag & 0x80) ? "???? " : "";

            $suffix = "";
            if (strpos($base, '0103') === 0 && strlen($firstPart) >= 14) {
                $modHex = substr($firstPart, 12, 2);
                $modVal = hexdec($modHex);
                if ($modVal === 1) {
                    $suffix = "+";
                } elseif ($modVal === 2) {
                    $suffix = "++";
                } elseif ($modVal === 255) {
                    $suffix = "-";
                }
            }

            array_shift($parts);
            $rest = implode(' ', $parts);

            $processed[] = $prefix . $weaponName . $suffix . ($rest ? " " . $rest : "");
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
        // Non-untekked rewards (Meseta, Materials, Rares, Disks)
        else {
            // Normalize Disk: technique names → "Disk:Megid Lv.1" regardless of stored case
            if (preg_match('/^Disk:([A-Za-z]+)\s+Lv\.(\d+)$/i', $segment, $dm)) {
                $segment = 'Disk:' . ucfirst(strtolower($dm[1])) . ' Lv.' . (int)$dm[2];
            }
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
function parse_and_drop_items($accountId, $itemString, $characterName = null)
{
    global $NEWSERV_API_URL, $NEWSERV_COMMAND_PREFIX;

    // Disambiguate the target identity to bypass NewServ's "multiple clients found" bug.
    // NewServ parses numeric identifiers as both Hex and Decimal simultaneously, causing collisions.
    // BB Usernames are alphanumeric, bypassing this flaw.
    $targetIdent = $accountId;
    $clients_raw = @file_get_contents($NEWSERV_API_URL . "/y/clients");
    if ($clients_raw !== FALSE) {
        $clients = json_decode($clients_raw, true);
        if (is_array($clients)) {
            foreach ($clients as $c) {
                if (isset($c['Account']) && $c['Account']['AccountID'] == $accountId) {
                    // Enforce strict character matching if a character name was provided
                    if ($characterName !== null && isset($c['Name']) && $c['Name'] !== $characterName) {
                        continue;
                    }
                    if (!empty($c['Account']['BBLicenses']) && is_array($c['Account']['BBLicenses'])) {
                        $targetIdent = $c['Account']['BBLicenses'][0]['UserName'];
                    }
                    break;
                }
            }
        }
    }

    // Ensure buildHexPayload is available (it's defined in redeem_bounty.php, but we might not have it loaded)
    if (!function_exists('buildHexPayload') && !function_exists('simpleBuildHexPayload')) {
        // Fallback simple payload builder if the full one isn't loaded
        function simpleBuildHexPayload($itemStr)
        {
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
        if (empty($rawItem))
            continue;

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

        if ($multiplier > 50)
            $multiplier = 50; // Cap to prevent server spam

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
                    'foie' => 0x00,
                    'gifoie' => 0x01,
                    'rafoie' => 0x02,
                    'barta' => 0x03,
                    'gibarta' => 0x04,
                    'rabarta' => 0x05,
                    'zonde' => 0x06,
                    'gizonde' => 0x07,
                    'razonde' => 0x08,
                    'grants' => 0x09,
                    'deband' => 0x0A,
                    'jellen' => 0x0B,
                    'zalure' => 0x0C,
                    'shifta' => 0x0D,
                    'ryuker' => 0x0E,
                    'resta' => 0x0F,
                    'anti' => 0x10,
                    'reverser' => 0x11,
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
            $cmd = "on " . $targetIdent . " cc {$NEWSERV_COMMAND_PREFIX}item " . $finalPayload;

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

