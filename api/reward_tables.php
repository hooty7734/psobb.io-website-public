<?php
// Hex string builders for NewServ $item drops
function build_pso_weapon($base_hex, $special_name = '', $untekked = false, $native = 0, $abeast = 0, $machine = 0, $dark = 0) {
    $special_map = [
        'draw' => 1, 'drain' => 2, 'fill' => 3, 'gush' => 4,
        'heart' => 5, 'mind' => 6, 'soul' => 7, 'geist' => 8,
        'masters' => 9, 'lords' => 10, 'kings' => 11,
        'charge' => 12, 'spirit' => 13, 'berserk' => 14,
        'ice' => 15, 'frost' => 16, 'freeze' => 17, 'blizzard' => 18,
        'bind' => 19, 'hold' => 20, 'seize' => 21, 'arrest' => 22,
        'heat' => 23, 'fire' => 24, 'flame' => 25, 'burning' => 26,
        'shock' => 27, 'thunder' => 28, 'storm' => 29, 'tempest' => 30,
        'dim' => 31, 'shadow' => 32, 'dark' => 33, 'hell' => 34,
        'panic' => 35, 'riot' => 36, 'havoc' => 37, 'chaos' => 38,
        'devil\'s' => 39, 'demon\'s' => 40
    ];
    $hex = str_pad(explode(' ', $base_hex)[0], 32, "0");
    $data = hex2bin($hex);
    $special_val = 0;
    if ($special_name && isset($special_map[strtolower(trim($special_name))])) {
        $special_val = $special_map[strtolower(trim($special_name))];
    }
    if ($untekked) {
        $special_val |= 0x80;
    }
    $data[4] = chr($special_val);
    
    // Add stats (Native=1, ABeast=2, Machine=3, Dark=4)
    $idx = 6;
    if ($native > 0) { $data[$idx] = chr(1); $data[$idx+1] = chr($native); $idx += 2; }
    if ($abeast > 0 && $idx < 12) { $data[$idx] = chr(2); $data[$idx+1] = chr($abeast); $idx += 2; }
    if ($machine > 0 && $idx < 12) { $data[$idx] = chr(3); $data[$idx+1] = chr($machine); $idx += 2; }
    if ($dark > 0 && $idx < 12) { $data[$idx] = chr(4); $data[$idx+1] = chr($dark); $idx += 2; }
    
    return strtoupper(bin2hex($data));
}

function build_pso_mag($magIndex, $def, $pow, $dex, $mind, $sync, $iq, $colorIndex, $flags = 0, $pb_nums = 0) {
    $data = str_repeat("\x00", 16);
    $data[0] = chr(0x02);
    $data[1] = chr($magIndex);
    
    $level = $def + $pow + $dex + $mind;
    $data[2] = chr($level & 0xFF);
    $data[3] = chr($pb_nums & 0xFF);
    
    // Stats (uint16 LE, value * 100)
    $def100 = $def * 100; $data[4] = chr($def100 & 0xFF); $data[5] = chr(($def100 >> 8) & 0xFF);
    $pow100 = $pow * 100; $data[6] = chr($pow100 & 0xFF); $data[7] = chr(($pow100 >> 8) & 0xFF);
    $dex100 = $dex * 100; $data[8] = chr($dex100 & 0xFF); $data[9] = chr(($dex100 >> 8) & 0xFF);
    $mind100 = $mind * 100; $data[10] = chr($mind100 & 0xFF); $data[11] = chr(($mind100 >> 8) & 0xFF);
    
    // Sync and IQ
    $data[12] = chr($sync & 0xFF);
    $data[13] = chr($iq & 0xFF);
    
    // Flags and Color
    $data[14] = chr($flags & 0xFF);
    $data[15] = chr($colorIndex & 0xFF);
    
    return strtoupper(bin2hex($data));
}

function build_pso_armor($base_hex_or_name, $slots = 0, $def = 0, $evp = 0) {
    if (preg_match('/^[0-9A-Fa-f]{6}$/', $base_hex_or_name)) {
        $hex = str_pad($base_hex_or_name, 32, "0");
        $data = hex2bin($hex);
        $data[5] = chr($slots);
        $data[6] = chr($def & 0xFF);
        $data[7] = chr(($def >> 8) & 0xFF);
        $data[8] = chr($evp & 0xFF);
        $data[9] = chr(($evp >> 8) & 0xFF);
        return strtoupper(bin2hex($data));
    }
    // If it's a string literal like "DB'S SHIELD", fall back to dropping the raw string
    return $base_hex_or_name;
}

// reward_tables.php - Defines reward items for each milestone, class, and category

// Weighted random selection algorithm
function get_weighted_random($pool) {
    if (empty($pool)) return '10000 Meseta';
    $total_weight = array_sum($pool);
    $rand = mt_rand(1, $total_weight);
    foreach ($pool as $item => $weight) {
        $rand -= $weight;
        if ($rand <= 0) {
            return $item;
        }
    }
    return array_key_first($pool);
}

function filter_rare_pool_by_level($pool, $level) {
    $filtered = [];
    foreach ($pool as $item => $weight) {
        $adjusted_weight = 0;
        
        if ($weight == 100) {
            if ($level <= 40) $adjusted_weight = 100;
            else if ($level <= 80) $adjusted_weight = 50;
            else if ($level <= 120) $adjusted_weight = 20;
            else $adjusted_weight = 5;
        } else if ($weight == 40) {
            if ($level < 20) $adjusted_weight = 0;
            else if ($level <= 60) $adjusted_weight = 20;
            else if ($level <= 120) $adjusted_weight = 60;
            else $adjusted_weight = 40;
        } else if ($weight == 10) {
            if ($level < 80) $adjusted_weight = 0;
            else if ($level <= 120) $adjusted_weight = 5;
            else if ($level <= 160) $adjusted_weight = 15;
            else $adjusted_weight = 25;
        } else if ($weight == 2 || $weight == 5) {
            if ($level < 130) $adjusted_weight = 0;
            else if ($level <= 170) $adjusted_weight = 2;
            else $adjusted_weight = 5;
        } else {
            $adjusted_weight = $weight;
        }
        
        if ($adjusted_weight > 0) {
            $filtered[$item] = $adjusted_weight;
        }
    }
    
    if (empty($filtered)) return $pool; 
    return $filtered;
}

function get_reward_item($level_milestone, $charClass, $category, $options = []) {
    // Determine tier (1-10)
    $tier = max(1, min(10, floor($level_milestone / 15) + 1));

    $pool = [];
    
    if ($category === 'Weapon') {
            switch ($charClass) {
                case 'HUmar':
                    $pool = [
                        '001006' /* AGITO */ => 40, '002700' /* ANCIENT SABER */ => 40, '000F01' /* ANGRY FIST */ => 40, '000408' /* ASTERON BELT */ => 40, '008902' /* ASUKA */ => 40, '000305' /* BLADE DANCE */ => 100,
                        '000306' /* BLOODY ART */ => 100, '000607' /* BRAVACE */ => 100, '000F00' /* BRAVE KNUCKLE */ => 100, '000405' /* BRIONAC */ => 100, '002100' /* CHAIN SAWD */ => 40, '000307' /* CROSS SCAR */ => 100, '000606' /* CUSTOM RAY ver.OO */ => 100, '009D00' /* DARK FLOW */ => 10, '00C800' /* DAYLIGHT SCAR */ => 2, '009008' /* DBS SABER */ => 100, '009A00' /* DEMOLITION COMET */ => 40, '000507' /* DISKA OF BRAVEMAN */ => 100,
                        '000506' /* DISKA OF LIBERATOR */ => 100, '000E00' /* DOUBLE SABER */ => 100, '000207' /* DRAGON SLAYER */ => 100, '000107' /* DURANDAL */ => 100, '002C00' /* ELYSION */ => 40,
                        '005601' /* FATSIA */ => 40, '000C04' /* FIRE SCEPTER:AGNI */ => 40, '00B900' /* FLAMBERGE */ => 40, '003F00' /* FLIGHT CUTTER */ => 40, '004000' /* FLIGHT FAN */ => 40,
                        '008F08' /* FLOWENS SWORD */ => 100, '000407' /* GAE BOLG */ => 100, '000108' /* GALATINE */ => 2, '000F02' /* GOD HAND */ => 40, '00B600' /* GUREN */ => 40, '000806' /* H&S25 JUSTICE */ => 100, '001300' /* HOLY RAY */ => 40, '000C05' /* ICE STAFF:DAGON */ => 40, '003C00' /* IMPERIAL PICK */ => 40,
                        '001400' /* INFERNO BAZOOKA */ => 40, '000106' /* KALADBOLG */ => 100, '008A02' /* KAMUI */ => 40, '00B400' /* KUSANAGI */ => 40, '000807' /* L&K14 COMBAT */ => 100, '001402' /* L&K38 COMBAT */ => 40,
                        '002001' /* LACONIUM AXE */ => 10, '00AB00' /* LAME DARGENT */ => 40, '000206' /* LAST SURVIVOR */ => 100, '001F00' /* LAVIS CANNON */ => 10, '000805' /* M&A60 VISE */ => 100, '00BE00' /* MAGUWA */ => 40,
                        '002E00' /* METEOR CUDGEL */ => 40, '002F00' /* MONKEY KING BAR */ => 40, '009400' /* MORNING GLORY */ => 40, '008900' /* MUSASHI */ => 100, '009500' /* PARTISAN of LIGHTNING */ => 40, '000D03' /* PHOENIX CLAW */ => 40,
                        '000D00' /* PHOTON CLAW */ => 100, '005600' /* PLANTAIN LEAF */ => 40, '003900' /* RED DAGGER */ => 40, '004400' /* RED HANDGUN */ => 40, '003E00' /* RED PARTISAN */ => 40, '002D00' /* RED SABER */ => 40,
                        '004100' /* RED SLICER */ => 40, '003400' /* RED SWORD */ => 40, '009800' /* RIKAS CLAW */ => 40, '00B500' /* SACRED DUSTER */ => 40,
                        '008A00' /* SANGE */ => 40, '003300' /* SEALED J-SWORD */ => 10, '00C600' /* SHICHISHITO */ => 40, '00B700' /* SHOUREN */ => 40, '000D01' /* SILENCE CLAW */ => 40, '000505' /* SLICER OF ASSASSIN */ => 100,
                        '00AA00' /* SLICER OF FANATIC */ => 40, '00BB00' /* SNAKE SPIRE */ => 40, '001101' /* SOUL BANISH */ => 40, '000E01' /* STAG CUTLERY */ => 40, '002600' /* SUPPRESSED GUN */ => 40, '005E00' /* TWIN BLAZE */ => 40, '000E02' /* TWIN BRAND */ => 40, '003600' /* TWIN CHAKRAM */ => 40, '004900' /* TWIN PSYCHOGUN */ => 40, '000605' /* VARISTA */ => 100, '002000' /* VICTOR AXE */ => 40,
                        '000406' /* VJAYA */ => 100, '008500' /* WINDMILL */ => 40, '008901' /* YAMATO */ => 40, '002900' /* YAMIGARASU */ => 40, '008A01' /* YASHA */ => 10,
                        '00BA00' /* YUNCHANG */ => 40, '009700' /* ZANBA */ => 40, '000308' /* ZERO DIVIDE */ => 40, '00CA00' /* 5TH ANNIV. BLADE */ => 40, '00CC00' /* AKIKOS CLEAVER */ => 40, '00D400' /* BAMBOO SPEAR */ => 40, '00D900' /* BATTLEDORE */ => 40, '00D700' /* BUTTERFLY NET */ => 40, '00B200' /* COMMANDER BLADE */ => 40, '00AE00' /* DAISY CHAIN */ => 40, '00AC00' /* EXCALIBUR */ => 10, '00BD00' /* GETSUGASAN */ => 40, '003001' /* GIRASOLE */ => 40, '00DC00' /* GREAT BOUQUET */ => 40, '000508' /* IZMAELA */ => 40, '00D600' /* JITTE */ => 40, '00B800' /* JIZAI */ => 10, '000A07' /* LOLLIPOP */ => 40, '00C700' /* MURASAME */ => 40, '00CF00' /* NICE SHOT */ => 40, '00CB00' /* PRINCIPAL'S GIFT PARASOL */ => 40, '00DA00' /* RACKET */ => 40, '001007' /* RAIKIRI */ => 40, '00B500' /* SACRED DUSTER */ => 40, '00B700' /* SHOUREN */ => 40, '00BB00' /* SNAKE SPIRE */ => 40, '004100' /* RED SLICER (FALLBACK) */ => 40, '00D300' /* SYNTHESIZER */ => 40, '00CE00' /* TREE CLIPPERS */ => 40, '000309' /* TWO KAMUI */ => 10, '00E200' /* TYPEBL/BLADE */ => 40, '00E500' /* TYPEDS/D.SABER */ => 40, '00E400' /* TYPEHA/HALBERT */ => 40, '00DF02' /* TYPEJS/J-SWORD */ => 40, '00DF00' /* TYPEJS/SABER */ => 40, '00E300' /* TYPEKN/BLADE */ => 40, '00E301' /* TYPEKN/CLAW */ => 40, '00DE02' /* TYPEN-SL/CLAW */ => 40, '00DE01' /* TYPEN-SL/SLICER */ => 40, '00DD00' /* TYPESA/SABER */ => 40, '00E700' /* TYPESS/SWORDS */ => 40, '00E002' /* TYPESW/J-SWORD */ => 40, '00E000' /* TYPESW/SWORD */ => 40,
                    ];
                    break;
                case 'HUnewearl':
                    $pool = [
                        '001006' /* AGITO */ => 40, '002700' /* ANCIENT SABER */ => 40, '000F01' /* ANGRY FIST */ => 40, '008902' /* ASUKA */ => 40, '000305' /* BLADE DANCE */ => 100, '000306' /* BLOODY ART */ => 100,
                        '000607' /* BRAVACE */ => 100, '000F00' /* BRAVE KNUCKLE */ => 100, '000405' /* BRIONAC */ => 100, '002100' /* CHAIN SAWD */ => 40, '00C300' /* CLIO */ => 40, '000307' /* CROSS SCAR */ => 100, '000606' /* CUSTOM RAY ver.OO */ => 100, '009D00' /* DARK FLOW */ => 10, '00C800' /* DAYLIGHT SCAR */ => 2, '009008' /* DBS SABER */ => 100, '009A00' /* DEMOLITION COMET */ => 40, '000507' /* DISKA OF BRAVEMAN */ => 100,
                        '000506' /* DISKA OF LIBERATOR */ => 100, '000E00' /* DOUBLE SABER */ => 100, '000207' /* DRAGON SLAYER */ => 100, '000107' /* DURANDAL */ => 100, '002C00' /* ELYSION */ => 40,
                        '005601' /* FATSIA */ => 40, '000C04' /* FIRE SCEPTER:AGNI */ => 40, '00B900' /* FLAMBERGE */ => 40, '00BC00' /* FLAPJACK FLAPPER */ => 40, '003F00' /* FLIGHT CUTTER */ => 40,
                        '004000' /* FLIGHT FAN */ => 40, '008F08' /* FLOWENS SWORD */ => 100, '000407' /* GAE BOLG */ => 100, '000108' /* GALATINE */ => 2, '000F02' /* GOD HAND */ => 40, '00B600' /* GUREN */ => 40,
                        '000806' /* H&S25 JUSTICE */ => 100, '006900' /* HEART OF POUMN */ => 40, '001300' /* HOLY RAY */ => 40,
                        '000C05' /* ICE STAFF:DAGON */ => 40, '003C00' /* IMPERIAL PICK */ => 40, '001400' /* INFERNO BAZOOKA */ => 40, '000106' /* KALADBOLG */ => 100, '008A02' /* KAMUI */ => 40, '00B400' /* KUSANAGI */ => 40,
                        '000807' /* L&K14 COMBAT */ => 100, '001402' /* L&K38 COMBAT */ => 40, '00AB00' /* LAME DARGENT */ => 40, '000206' /* LAST SURVIVOR */ => 100, '001F00' /* LAVIS CANNON */ => 10, '000805' /* M&A60 VISE */ => 100,
                        '003A00' /* MADAMS PARASOL */ => 10, '003B00' /* MADAMS UMBRELLA */ => 40, '00BE00' /* MAGUWA */ => 40, '002E00' /* METEOR CUDGEL */ => 40, '002F00' /* MONKEY KING BAR */ => 40, '009400' /* MORNING GLORY */ => 40,
                        '008900' /* MUSASHI */ => 100, '009B00' /* NEIS CLAW */ => 10, '009500' /* PARTISAN of LIGHTNING */ => 40, '000D03' /* PHOENIX CLAW */ => 40, '000D00' /* PHOTON CLAW */ => 100, '005600' /* PLANTAIN LEAF */ => 40,
                        '003900' /* RED DAGGER */ => 40, '004400' /* RED HANDGUN */ => 40, '003E00' /* RED PARTISAN */ => 40, '002D00' /* RED SABER */ => 40, '004100' /* RED SLICER */ => 40, '003400' /* RED SWORD */ => 40,
                        '009800' /* RIKAS CLAW */ => 40, '00B500' /* SACRED DUSTER */ => 40, '008A00' /* SANGE */ => 40, '003300' /* SEALED J-SWORD */ => 10,
                        '00C600' /* SHICHISHITO */ => 40, '00B700' /* SHOUREN */ => 40, '000D01' /* SILENCE CLAW */ => 40, '000505' /* SLICER OF ASSASSIN */ => 100, '00AA00' /* SLICER OF FANATIC */ => 40, '00BB00' /* SNAKE SPIRE */ => 40,
                        '001101' /* SOUL BANISH */ => 40, '002600' /* SUPPRESSED GUN */ => 40, '005E00' /* TWIN BLAZE */ => 40, '000E02' /* TWIN BRAND */ => 40, '003600' /* TWIN CHAKRAM */ => 40,
                        '004900' /* TWIN PSYCHOGUN */ => 40, '00CB00' /* TYRELLS PARASOL */ => 100, '000B07' /* VALKYRIE */ => 40, '000605' /* VARISTA */ => 100, '002000' /* VICTOR AXE */ => 40, '00B300' /* VIVIENNE */ => 10,
                        '000406' /* VJAYA */ => 100, '008500' /* WINDMILL */ => 40, '008901' /* YAMATO */ => 40, '002900' /* YAMIGARASU */ => 40, '008A01' /* YASHA */ => 10,
                        '00BA00' /* YUNCHANG */ => 40, '000308' /* ZERO DIVIDE */ => 40, '00CA00' /* 5TH ANNIV. BLADE */ => 40, '00CC00' /* AKIKOS CLEAVER */ => 40, '00D400' /* BAMBOO SPEAR */ => 40, '00D900' /* BATTLEDORE */ => 40, '00D700' /* BUTTERFLY NET */ => 40, '00B200' /* COMMANDER BLADE */ => 40, '00AE00' /* DAISY CHAIN */ => 40, '00AC00' /* EXCALIBUR */ => 10, '00BD00' /* GETSUGASAN */ => 40, '003001' /* GIRASOLE */ => 40, '00DC00' /* GREAT BOUQUET */ => 40, '000508' /* IZMAELA */ => 40, '00D600' /* JITTE */ => 40, '00B800' /* JIZAI */ => 10, '000A07' /* LOLLIPOP */ => 40, '00C700' /* MURASAME */ => 40, '00CF00' /* NICE SHOT */ => 40, '00CB00' /* PRINCIPAL'S GIFT PARASOL */ => 40, '00DA00' /* RACKET */ => 40, '001007' /* RAIKIRI */ => 40, '00B500' /* SACRED DUSTER */ => 40, '00B700' /* SHOUREN */ => 40, '00BB00' /* SNAKE SPIRE */ => 40, '004100' /* RED SLICER (FALLBACK) */ => 40, '00D300' /* SYNTHESIZER */ => 40, '00CE00' /* TREE CLIPPERS */ => 40, '000309' /* TWO KAMUI */ => 10, '00E200' /* TYPEBL/BLADE */ => 40, '00E500' /* TYPEDS/D.SABER */ => 40, '00E400' /* TYPEHA/HALBERT */ => 40, '00DF02' /* TYPEJS/J-SWORD */ => 40, '00DF00' /* TYPEJS/SABER */ => 40, '00E300' /* TYPEKN/BLADE */ => 40, '00E301' /* TYPEKN/CLAW */ => 40, '00DE02' /* TYPEN-SL/CLAW */ => 40, '00DE01' /* TYPEN-SL/SLICER */ => 40, '00DD00' /* TYPESA/SABER */ => 40, '00E700' /* TYPESS/SWORDS */ => 40, '00E002' /* TYPESW/J-SWORD */ => 40, '00E000' /* TYPESW/SWORD */ => 40,
                    ];
                    break;
                case 'HUcast':
                    $pool = [
                        '001006' /* AGITO */ => 40, '002700' /* ANCIENT SABER */ => 40, '000F01' /* ANGRY FIST */ => 40, '000408' /* ASTERON BELT */ => 40, '008902' /* ASUKA */ => 40, '003D00' /* BERDYSH */ => 40,
                        '000305' /* BLADE DANCE */ => 100, '000306' /* BLOODY ART */ => 100, '000607' /* BRAVACE */ => 100, '000F00' /* BRAVE KNUCKLE */ => 100, '000405' /* BRIONAC */ => 100, '002100' /* CHAIN SAWD */ => 40, '000307' /* CROSS SCAR */ => 100, '000606' /* CUSTOM RAY ver.OO */ => 100, '009D00' /* DARK FLOW */ => 10, '00C800' /* DAYLIGHT SCAR */ => 2, '009008' /* DBS SABER */ => 100, '009A00' /* DEMOLITION COMET */ => 40,
                        '000507' /* DISKA OF BRAVEMAN */ => 100, '000506' /* DISKA OF LIBERATOR */ => 100, '000E00' /* DOUBLE SABER */ => 100, '000207' /* DRAGON SLAYER */ => 100, '000107' /* DURANDAL */ => 100, '000C04' /* FIRE SCEPTER:AGNI */ => 40, '00B900' /* FLAMBERGE */ => 40, '003F00' /* FLIGHT CUTTER */ => 40, '004000' /* FLIGHT FAN */ => 40, '008F08' /* FLOWENS SWORD */ => 100,
                        '000407' /* GAE BOLG */ => 100, '000108' /* GALATINE */ => 2, '000F02' /* GOD HAND */ => 40, '00B600' /* GUREN */ => 40, '000806' /* H&S25 JUSTICE */ => 100, '000C05' /* ICE STAFF:DAGON */ => 40, '003C00' /* IMPERIAL PICK */ => 40, '001400' /* INFERNO BAZOOKA */ => 40, '000106' /* KALADBOLG */ => 100,
                        '008A02' /* KAMUI */ => 40, '00B400' /* KUSANAGI */ => 40, '000807' /* L&K14 COMBAT */ => 100, '001402' /* L&K38 COMBAT */ => 40, '002001' /* LACONIUM AXE */ => 10, '00AB00' /* LAME DARGENT */ => 40,
                        '000206' /* LAST SURVIVOR */ => 100, '001F00' /* LAVIS CANNON */ => 10, '000805' /* M&A60 VISE */ => 100, '00BE00' /* MAGUWA */ => 40, '002E00' /* METEOR CUDGEL */ => 40, '002F00' /* MONKEY KING BAR */ => 40,
                        '009400' /* MORNING GLORY */ => 40, '008900' /* MUSASHI */ => 100, '009500' /* PARTISAN of LIGHTNING */ => 40, '000D03' /* PHOENIX CLAW */ => 40, '000D00' /* PHOTON CLAW */ => 100, '003900' /* RED DAGGER */ => 40,
                        '004400' /* RED HANDGUN */ => 40, '003E00' /* RED PARTISAN */ => 40, '002D00' /* RED SABER */ => 40, '004100' /* RED SLICER */ => 40, '003400' /* RED SWORD */ => 40, '009800' /* RIKAS CLAW */ => 40,
                        '00B500' /* SACRED DUSTER */ => 40, '008A00' /* SANGE */ => 40, '003300' /* SEALED J-SWORD */ => 10, '00C600' /* SHICHISHITO */ => 40,
                        '00B700' /* SHOUREN */ => 40, '000D01' /* SILENCE CLAW */ => 40, '000505' /* SLICER OF ASSASSIN */ => 100, '00AA00' /* SLICER OF FANATIC */ => 40, '00BB00' /* SNAKE SPIRE */ => 40, '001101' /* SOUL BANISH */ => 40,
                        '000E01' /* STAG CUTLERY */ => 40, '002600' /* SUPPRESSED GUN */ => 40, '005E00' /* TWIN BLAZE */ => 40, '000E02' /* TWIN BRAND */ => 40, '003600' /* TWIN CHAKRAM */ => 40,
                        '000605' /* VARISTA */ => 100, '002000' /* VICTOR AXE */ => 40, '000406' /* VJAYA */ => 100, '008901' /* YAMATO */ => 40, '002900' /* YAMIGARASU */ => 40,
                        '008A01' /* YASHA */ => 10, '00BA00' /* YUNCHANG */ => 40, '009700' /* ZANBA */ => 40, '000308' /* ZERO DIVIDE */ => 40, '00CA00' /* 5TH ANNIV. BLADE */ => 40, '00CC00' /* AKIKOS CLEAVER */ => 40, '00D400' /* BAMBOO SPEAR */ => 40, '00D900' /* BATTLEDORE */ => 40, '00D700' /* BUTTERFLY NET */ => 40, '00B200' /* COMMANDER BLADE */ => 40, '00AE00' /* DAISY CHAIN */ => 40, '00AC00' /* EXCALIBUR */ => 10, '00BD00' /* GETSUGASAN */ => 40, '003001' /* GIRASOLE */ => 40, '00DC00' /* GREAT BOUQUET */ => 40, '000508' /* IZMAELA */ => 40, '00D600' /* JITTE */ => 40, '00B800' /* JIZAI */ => 10, '000A07' /* LOLLIPOP */ => 40, '00C700' /* MURASAME */ => 40, '00CF00' /* NICE SHOT */ => 40, '00CB00' /* PRINCIPAL'S GIFT PARASOL */ => 40, '00DA00' /* RACKET */ => 40, '001007' /* RAIKIRI */ => 40, '00B500' /* SACRED DUSTER */ => 40, '00B700' /* SHOUREN */ => 40, '00BB00' /* SNAKE SPIRE */ => 40, '004100' /* RED SLICER (FALLBACK) */ => 40, '00D300' /* SYNTHESIZER */ => 40, '00CE00' /* TREE CLIPPERS */ => 40, '000309' /* TWO KAMUI */ => 10, '00E200' /* TYPEBL/BLADE */ => 40, '00E500' /* TYPEDS/D.SABER */ => 40, '00E400' /* TYPEHA/HALBERT */ => 40, '00DF02' /* TYPEJS/J-SWORD */ => 40, '00DF00' /* TYPEJS/SABER */ => 40, '00E300' /* TYPEKN/BLADE */ => 40, '00E301' /* TYPEKN/CLAW */ => 40, '00DE02' /* TYPEN-SL/CLAW */ => 40, '00DE01' /* TYPEN-SL/SLICER */ => 40, '00DD00' /* TYPESA/SABER */ => 40, '00E700' /* TYPESS/SWORDS */ => 40, '00E002' /* TYPESW/J-SWORD */ => 40, '00E000' /* TYPESW/SWORD */ => 40,
                    ];
                    break;
                case 'HUcaseal':
                    $pool = [
                        '001006' /* AGITO */ => 40, '002700' /* ANCIENT SABER */ => 40, '000F01' /* ANGRY FIST */ => 40, '008902' /* ASUKA */ => 40, '003D00' /* BERDYSH */ => 40, '000305' /* BLADE DANCE */ => 100,
                        '000306' /* BLOODY ART */ => 100, '000607' /* BRAVACE */ => 100, '000F00' /* BRAVE KNUCKLE */ => 100, '000405' /* BRIONAC */ => 100, '002100' /* CHAIN SAWD */ => 40, '000307' /* CROSS SCAR */ => 100, '000606' /* CUSTOM RAY ver.OO */ => 100, '009D00' /* DARK FLOW */ => 10, '00C800' /* DAYLIGHT SCAR */ => 2, '009008' /* DBS SABER */ => 100, '009A00' /* DEMOLITION COMET */ => 40, '000507' /* DISKA OF BRAVEMAN */ => 100,
                        '000506' /* DISKA OF LIBERATOR */ => 100, '000E00' /* DOUBLE SABER */ => 100, '000207' /* DRAGON SLAYER */ => 100, '000107' /* DURANDAL */ => 100, '000C04' /* FIRE SCEPTER:AGNI */ => 40, '00B900' /* FLAMBERGE */ => 40, '00BC00' /* FLAPJACK FLAPPER */ => 40, '003F00' /* FLIGHT CUTTER */ => 40, '004000' /* FLIGHT FAN */ => 40, '008F08' /* FLOWENS SWORD */ => 100,
                        '000407' /* GAE BOLG */ => 100, '000108' /* GALATINE */ => 2, '000F02' /* GOD HAND */ => 40, '00B600' /* GUREN */ => 40, '000806' /* H&S25 JUSTICE */ => 100, '000C05' /* ICE STAFF:DAGON */ => 40, '003C00' /* IMPERIAL PICK */ => 40, '001400' /* INFERNO BAZOOKA */ => 40, '000106' /* KALADBOLG */ => 100,
                        '008A02' /* KAMUI */ => 40, '00B400' /* KUSANAGI */ => 40, '000807' /* L&K14 COMBAT */ => 100, '001402' /* L&K38 COMBAT */ => 40, '002001' /* LACONIUM AXE */ => 10, '00AB00' /* LAME DARGENT */ => 40,
                        '000206' /* LAST SURVIVOR */ => 100, '001F00' /* LAVIS CANNON */ => 10, '000805' /* M&A60 VISE */ => 100, '003A00' /* MADAMS PARASOL */ => 10, '003B00' /* MADAMS UMBRELLA */ => 40, '00BE00' /* MAGUWA */ => 40,
                        '002E00' /* METEOR CUDGEL */ => 40, '002F00' /* MONKEY KING BAR */ => 40, '009400' /* MORNING GLORY */ => 40, '008900' /* MUSASHI */ => 100, '009500' /* PARTISAN of LIGHTNING */ => 40, '000D03' /* PHOENIX CLAW */ => 40,
                        '000D00' /* PHOTON CLAW */ => 100, '003900' /* RED DAGGER */ => 40, '004400' /* RED HANDGUN */ => 40, '003E00' /* RED PARTISAN */ => 40, '002D00' /* RED SABER */ => 40, '004100' /* RED SLICER */ => 40,
                        '003400' /* RED SWORD */ => 40, '009800' /* RIKAS CLAW */ => 40, '00B500' /* SACRED DUSTER */ => 40, '008A00' /* SANGE */ => 40,
                        '003300' /* SEALED J-SWORD */ => 10, '00C600' /* SHICHISHITO */ => 40, '00B700' /* SHOUREN */ => 40, '000D01' /* SILENCE CLAW */ => 40, '000505' /* SLICER OF ASSASSIN */ => 100, '00AA00' /* SLICER OF FANATIC */ => 40,
                        '00BB00' /* SNAKE SPIRE */ => 40, '001101' /* SOUL BANISH */ => 40, '002600' /* SUPPRESSED GUN */ => 40, '005E00' /* TWIN BLAZE */ => 40, '000E02' /* TWIN BRAND */ => 40,
                        '003600' /* TWIN CHAKRAM */ => 40, '00CB00' /* TYRELLS PARASOL */ => 100, '000B07' /* VALKYRIE */ => 40, '000605' /* VARISTA */ => 100, '002000' /* VICTOR AXE */ => 40, '00B300' /* VIVIENNE */ => 10,
                        '000406' /* VJAYA */ => 100, '008901' /* YAMATO */ => 40, '002900' /* YAMIGARASU */ => 40, '008A01' /* YASHA */ => 10, '00BA00' /* YUNCHANG */ => 40,
                        '000308' /* ZERO DIVIDE */ => 40, '00CA00' /* 5TH ANNIV. BLADE */ => 40, '00CC00' /* AKIKOS CLEAVER */ => 40, '00D400' /* BAMBOO SPEAR */ => 40, '00D900' /* BATTLEDORE */ => 40, '00D700' /* BUTTERFLY NET */ => 40, '00B200' /* COMMANDER BLADE */ => 40, '00AE00' /* DAISY CHAIN */ => 40, '00AC00' /* EXCALIBUR */ => 10, '00BD00' /* GETSUGASAN */ => 40, '003001' /* GIRASOLE */ => 40, '00DC00' /* GREAT BOUQUET */ => 40, '000508' /* IZMAELA */ => 40, '00D600' /* JITTE */ => 40, '00B800' /* JIZAI */ => 10, '000A07' /* LOLLIPOP */ => 40, '00C700' /* MURASAME */ => 40, '00CF00' /* NICE SHOT */ => 40, '00CB00' /* PRINCIPAL'S GIFT PARASOL */ => 40, '00DA00' /* RACKET */ => 40, '001007' /* RAIKIRI */ => 40, '00B500' /* SACRED DUSTER */ => 40, '00B700' /* SHOUREN */ => 40, '00BB00' /* SNAKE SPIRE */ => 40, '004100' /* RED SLICER (FALLBACK) */ => 40, '00D300' /* SYNTHESIZER */ => 40, '00CE00' /* TREE CLIPPERS */ => 40, '000309' /* TWO KAMUI */ => 10, '00E200' /* TYPEBL/BLADE */ => 40, '00E500' /* TYPEDS/D.SABER */ => 40, '00E400' /* TYPEHA/HALBERT */ => 40, '00DF02' /* TYPEJS/J-SWORD */ => 40, '00DF00' /* TYPEJS/SABER */ => 40, '00E300' /* TYPEKN/BLADE */ => 40, '00E301' /* TYPEKN/CLAW */ => 40, '00DE02' /* TYPEN-SL/CLAW */ => 40, '00DE01' /* TYPEN-SL/SLICER */ => 40, '00DD00' /* TYPESA/SABER */ => 40, '00E700' /* TYPESS/SWORDS */ => 40, '00E002' /* TYPESW/J-SWORD */ => 40, '00E000' /* TYPESW/SWORD */ => 40,
                    ];
                    break;
                case 'RAmar':
                    $pool = [
                        '000F01' /* ANGRY FIST */ => 40, '00D200' /* ANO BAZOOKA */ => 100, '006600' /* ANO RIFLE */ => 40, '004600' /* ANTI ANDROID RIFLE */ => 40, '000408' /* ASTERON BELT */ => 40, '000607' /* BRAVACE */ => 100, '000F00' /* BRAVE KNUCKLE */ => 100, '00C000' /* CANNON ROUGE */ => 40,
                        '000905' /* CRUSH BULLET */ => 100, '000606' /* CUSTOM RAY ver.OO */ => 100, '00C800' /* DAYLIGHT SCAR */ => 2, '009008' /* DBS SABER */ => 100,
                        '000507' /* DISKA OF BRAVEMAN */ => 100, '000506' /* DISKA OF LIBERATOR */ => 100, '000E00' /* DOUBLE SABER */ => 100, '000107' /* DURANDAL */ => 100, '005601' /* FATSIA */ => 40,
                        '000907' /* FINAL IMPACT */ => 100, '00B900' /* FLAMBERGE */ => 40, '001500' /* FLAME VISIT */ => 40, '003F00' /* FLIGHT CUTTER */ => 40, '004000' /* FLIGHT FAN */ => 40, '004500' /* FROZEN SHOOTER */ => 40,
                        '000108' /* GALATINE */ => 2, '000F02' /* GOD HAND */ => 40, '008B01' /* GUILTY LIGHT */ => 40, '000806' /* H&S25 JUSTICE */ => 100, '004200' /* HANDGUN:GULD */ => 40, '004300' /* HANDGUN:MILLA */ => 40, '001E00' /* HEAVEN PUNISHER */ => 2, '00BF00' /* HEAVEN STRIKER */ => 2, '001400' /* INFERNO BAZOOKA */ => 40,
                        '000707' /* JUSTY-23ST */ => 100, '000106' /* KALADBOLG */ => 100, '00B400' /* KUSANAGI */ => 40, '000807' /* L&K14 COMBAT */ => 100, '001402' /* L&K38 COMBAT */ => 40, '00AB00' /* LAME DARGENT */ => 40,
                        '000805' /* M&A60 VISE */ => 100, '006D00' /* MASER BEAM */ => 40, '000906' /* METEOR SMASH */ => 100, '002F00' /* MONKEY KING BAR */ => 40,
                        '009400' /* MORNING GLORY */ => 40, '008D00' /* NUG2000-BAZOOKA */ => 10, '00AF00' /* OPHELIE SEIZE */ => 40, '000D03' /* PHOENIX CLAW */ => 40, '008B03' /* PHONON MASER */ => 40, '000D00' /* PHOTON CLAW */ => 100,
                        '008B00' /* PHOTON LAUNCHER */ => 100, '005600' /* PLANTAIN LEAF */ => 40, '00AD03' /* RAGE DE FEU */ => 40, '004400' /* RED HANDGUN */ => 40, '004C00' /* RED MECHGUN */ => 40, '002D00' /* RED SABER */ => 40,
                        '008B02' /* RED SCORPIO */ => 40, '004100' /* RED SLICER */ => 40, '000708' /* RIANOV 303SNR */ => 100,     '009800' /* RIKAS CLAW */ => 40, '00A300' /* RUBY BULLET */ => 40, '00B500' /* SACRED DUSTER */ => 40,
                        '00C600' /* SHICHISHITO */ => 40, '000505' /* SLICER OF ASSASSIN */ => 100, '00AA00' /* SLICER OF FANATIC */ => 40, '001200' /* SPREAD NEEDLE */ => 40, '000E01' /* STAG CUTLERY */ => 40, '002600' /* SUPPRESSED GUN */ => 40,
                        '00CD00' /* TANEGASHIMA */ => 100, '005E00' /* TWIN BLAZE */ => 40, '000E02' /* TWIN BRAND */ => 40, '003600' /* TWIN CHAKRAM */ => 40, '004900' /* TWIN PSYCHOGUN */ => 40,
                        '000605' /* VARISTA */ => 100, '002000' /* VICTOR AXE */ => 40, '000705' /* VISK-235W */ => 100, '000706' /* WALS-MK2 */ => 100, '002900' /* YAMIGARASU */ => 40,
                        '006A00' /* YASMINKOV 2000H */ => 40, '006500' /* YASMINKOV 3000R */ => 40, '006B00' /* YASMINKOV 7000V */ => 40, '006C00' /* YASMINKOV 9000M */ => 40, '000308' /* ZERO DIVIDE */ => 40, '004B01' /* DUAL BIRD */ => 10, '000508' /* IZMAELA */ => 40, '004301' /* LAST SWAN */ => 40, '00B100' /* LE COGNEUR */ => 40, '004201' /* MASTER RAVEN */ => 40, '00C100' /* METEOR ROUGE */ => 40, '00B000' /* MILLE MARTEAUX */ => 10, '001401' /* RAMBLING MAY */ => 40, '000709' /* RIANOV 303SNR-1 */ => 40, '00070A' /* RIANOV 303SNR-2 */ => 40, '00070B' /* RIANOV 303SNR-3 */ => 40, '00070C' /* RIANOV 303SNR-4 */ => 40, '00070D' /* RIANOV 303SNR-5 */ => 40, '000608' /* TENSION BLASTER */ => 40, '00E800' /* TYPEGU/HANDGUN */ => 40, '00E801' /* TYPEGU/MECHGUN */ => 40, '00EA00' /* TYPEME/MECHGUN */ => 40, '00E900' /* TYPERI/RIFLE */ => 40, '00EB00' /* TYPESH/SHOT */ => 40,
                    ];
                    break;
                case 'RAmarl':
                    $pool = [
                        '009900' /* ANGEL HARP */ => 10, '000F01' /* ANGRY FIST */ => 40, '00D200' /* ANO BAZOOKA */ => 100, '006600' /* ANO RIFLE */ => 40, '004600' /* ANTI ANDROID RIFLE */ => 40, '000607' /* BRAVACE */ => 100, '000F00' /* BRAVE KNUCKLE */ => 100, '00C000' /* CANNON ROUGE */ => 40,
                        '00C300' /* CLIO */ => 40, '000905' /* CRUSH BULLET */ => 100, '000606' /* CUSTOM RAY ver.OO */ => 100, '00C800' /* DAYLIGHT SCAR */ => 2,
                        '009008' /* DBS SABER */ => 100, '000507' /* DISKA OF BRAVEMAN */ => 100, '000506' /* DISKA OF LIBERATOR */ => 100, '000E00' /* DOUBLE SABER */ => 100, '000107' /* DURANDAL */ => 100, '002C00' /* ELYSION */ => 40, '005601' /* FATSIA */ => 40, '000907' /* FINAL IMPACT */ => 100, '00B900' /* FLAMBERGE */ => 40, '001500' /* FLAME VISIT */ => 40, '00BC00' /* FLAPJACK FLAPPER */ => 40,
                        '003F00' /* FLIGHT CUTTER */ => 40, '004000' /* FLIGHT FAN */ => 40, '004500' /* FROZEN SHOOTER */ => 40, '000108' /* GALATINE */ => 2, '000F02' /* GOD HAND */ => 40, '008B01' /* GUILTY LIGHT */ => 40,
                        '000806' /* H&S25 JUSTICE */ => 100, '004200' /* HANDGUN:GULD */ => 40, '004300' /* HANDGUN:MILLA */ => 40,
                        '001E00' /* HEAVEN PUNISHER */ => 2, '00BF00' /* HEAVEN STRIKER */ => 2, '001300' /* HOLY RAY */ => 40, '001400' /* INFERNO BAZOOKA */ => 40, '000707' /* JUSTY-23ST */ => 100, '000106' /* KALADBOLG */ => 100,
                        '00B400' /* KUSANAGI */ => 40, '000807' /* L&K14 COMBAT */ => 100, '001402' /* L&K38 COMBAT */ => 40, '00AB00' /* LAME DARGENT */ => 40, '000805' /* M&A60 VISE */ => 100, '003A00' /* MADAMS PARASOL */ => 10, '003B00' /* MADAMS UMBRELLA */ => 40, '006D00' /* MASER BEAM */ => 40, '000906' /* METEOR SMASH */ => 100, '002F00' /* MONKEY KING BAR */ => 40,
                        '009400' /* MORNING GLORY */ => 40, '008D00' /* NUG2000-BAZOOKA */ => 10, '00AF00' /* OPHELIE SEIZE */ => 40, '000D03' /* PHOENIX CLAW */ => 40, '008B03' /* PHONON MASER */ => 40, '000D00' /* PHOTON CLAW */ => 100,
                        '008B00' /* PHOTON LAUNCHER */ => 100, '005600' /* PLANTAIN LEAF */ => 40, '00AD03' /* RAGE DE FEU */ => 40, '004400' /* RED HANDGUN */ => 40, '004C00' /* RED MECHGUN */ => 40, '002D00' /* RED SABER */ => 40,
                        '008B02' /* RED SCORPIO */ => 40, '004100' /* RED SLICER */ => 40, '000708' /* RIANOV 303SNR */ => 100,     '009800' /* RIKAS CLAW */ => 40, '00A300' /* RUBY BULLET */ => 40, '00B500' /* SACRED DUSTER */ => 40,
                        '00C600' /* SHICHISHITO */ => 40, '000505' /* SLICER OF ASSASSIN */ => 100, '00AA00' /* SLICER OF FANATIC */ => 40, '001200' /* SPREAD NEEDLE */ => 40, '002600' /* SUPPRESSED GUN */ => 40, '00CD00' /* TANEGASHIMA */ => 100, '005E00' /* TWIN BLAZE */ => 40, '000E02' /* TWIN BRAND */ => 40, '003600' /* TWIN CHAKRAM */ => 40, '004900' /* TWIN PSYCHOGUN */ => 40, '00CB00' /* TYRELLS PARASOL */ => 100,
                        '000B07' /* VALKYRIE */ => 40, '000605' /* VARISTA */ => 100, '002000' /* VICTOR AXE */ => 40, '000705' /* VISK-235W */ => 100, '00B300' /* VIVIENNE */ => 10, '000706' /* WALS-MK2 */ => 100, '008500' /* WINDMILL */ => 40, '002900' /* YAMIGARASU */ => 40, '006A00' /* YASMINKOV 2000H */ => 40, '006500' /* YASMINKOV 3000R */ => 40, '006B00' /* YASMINKOV 7000V */ => 40,
                        '006C00' /* YASMINKOV 9000M */ => 40, '000308' /* ZERO DIVIDE */ => 40, '004B01' /* DUAL BIRD */ => 10, '000508' /* IZMAELA */ => 40, '004301' /* LAST SWAN */ => 40, '00B100' /* LE COGNEUR */ => 40, '004201' /* MASTER RAVEN */ => 40, '00C100' /* METEOR ROUGE */ => 40, '00B000' /* MILLE MARTEAUX */ => 10, '001401' /* RAMBLING MAY */ => 40, '000709' /* RIANOV 303SNR-1 */ => 40, '00070A' /* RIANOV 303SNR-2 */ => 40, '00070B' /* RIANOV 303SNR-3 */ => 40, '00070C' /* RIANOV 303SNR-4 */ => 40, '00070D' /* RIANOV 303SNR-5 */ => 40, '000608' /* TENSION BLASTER */ => 40, '00E800' /* TYPEGU/HANDGUN */ => 40, '00E801' /* TYPEGU/MECHGUN */ => 40, '00EA00' /* TYPEME/MECHGUN */ => 40, '00E900' /* TYPERI/RIFLE */ => 40, '00EB00' /* TYPESH/SHOT */ => 40,
                    ];
                    break;
                case 'RAcast':
                    $pool = [
                        '000F01' /* ANGRY FIST */ => 40, '00D200' /* ANO BAZOOKA */ => 100, '006600' /* ANO RIFLE */ => 40, '004600' /* ANTI ANDROID RIFLE */ => 40, '000408' /* ASTERON BELT */ => 40, '000607' /* BRAVACE */ => 100, '000F00' /* BRAVE KNUCKLE */ => 100, '00C000' /* CANNON ROUGE */ => 40,
                        '000905' /* CRUSH BULLET */ => 100, '000606' /* CUSTOM RAY ver.OO */ => 100, '00C800' /* DAYLIGHT SCAR */ => 2, '009008' /* DBS SABER */ => 100,
                        '000507' /* DISKA OF BRAVEMAN */ => 100, '000506' /* DISKA OF LIBERATOR */ => 100, '000E00' /* DOUBLE SABER */ => 100, '000107' /* DURANDAL */ => 100, '000907' /* FINAL IMPACT */ => 100,
                        '00B900' /* FLAMBERGE */ => 40, '001500' /* FLAME VISIT */ => 40, '003F00' /* FLIGHT CUTTER */ => 40, '004000' /* FLIGHT FAN */ => 40, '004500' /* FROZEN SHOOTER */ => 40, '000108' /* GALATINE */ => 2,
                        '000F02' /* GOD HAND */ => 40, '008B01' /* GUILTY LIGHT */ => 40, '000806' /* H&S25 JUSTICE */ => 100,
                        '004200' /* HANDGUN:GULD */ => 40, '004300' /* HANDGUN:MILLA */ => 40, '001E00' /* HEAVEN PUNISHER */ => 2, '00BF00' /* HEAVEN STRIKER */ => 2, '001400' /* INFERNO BAZOOKA */ => 40, '000707' /* JUSTY-23ST */ => 100,
                        '000106' /* KALADBOLG */ => 100, '00B400' /* KUSANAGI */ => 40, '000807' /* L&K14 COMBAT */ => 100, '001402' /* L&K38 COMBAT */ => 40, '00AB00' /* LAME DARGENT */ => 40, '000805' /* M&A60 VISE */ => 100, '006D00' /* MASER BEAM */ => 40, '000906' /* METEOR SMASH */ => 100, '002F00' /* MONKEY KING BAR */ => 40, '009400' /* MORNING GLORY */ => 40,
                        '008D00' /* NUG2000-BAZOOKA */ => 10, '00AF00' /* OPHELIE SEIZE */ => 40, '004E00' /* PANZER FAUST */ => 40, '000D03' /* PHOENIX CLAW */ => 40, '008B03' /* PHONON MASER */ => 40, '000D00' /* PHOTON CLAW */ => 100,
                        '008B00' /* PHOTON LAUNCHER */ => 100, '00AD03' /* RAGE DE FEU */ => 40, '004400' /* RED HANDGUN */ => 40, '004C00' /* RED MECHGUN */ => 40, '002D00' /* RED SABER */ => 40, '008B02' /* RED SCORPIO */ => 40,
                        '004100' /* RED SLICER */ => 40, '000708' /* RIANOV 303SNR */ => 100,     '009800' /* RIKAS CLAW */ => 40, '00A300' /* RUBY BULLET */ => 40, '00B500' /* SACRED DUSTER */ => 40, '00C600' /* SHICHISHITO */ => 40,
                        '000505' /* SLICER OF ASSASSIN */ => 100, '00AA00' /* SLICER OF FANATIC */ => 40, '001200' /* SPREAD NEEDLE */ => 40, '000E01' /* STAG CUTLERY */ => 40, '002600' /* SUPPRESSED GUN */ => 40, '00CD00' /* TANEGASHIMA */ => 100, '005E00' /* TWIN BLAZE */ => 40, '000E02' /* TWIN BRAND */ => 40, '003600' /* TWIN CHAKRAM */ => 40, '000605' /* VARISTA */ => 100, '002000' /* VICTOR AXE */ => 40,
                        '000705' /* VISK-235W */ => 100, '000706' /* WALS-MK2 */ => 100, '002900' /* YAMIGARASU */ => 40, '006A00' /* YASMINKOV 2000H */ => 40, '006500' /* YASMINKOV 3000R */ => 40,
                        '006B00' /* YASMINKOV 7000V */ => 40, '006C00' /* YASMINKOV 9000M */ => 40, '000308' /* ZERO DIVIDE */ => 40, '004B01' /* DUAL BIRD */ => 10, '000508' /* IZMAELA */ => 40, '004301' /* LAST SWAN */ => 40, '00B100' /* LE COGNEUR */ => 40, '004201' /* MASTER RAVEN */ => 40, '00C100' /* METEOR ROUGE */ => 40, '00B000' /* MILLE MARTEAUX */ => 10, '001401' /* RAMBLING MAY */ => 40, '000709' /* RIANOV 303SNR-1 */ => 40, '00070A' /* RIANOV 303SNR-2 */ => 40, '00070B' /* RIANOV 303SNR-3 */ => 40, '00070C' /* RIANOV 303SNR-4 */ => 40, '00070D' /* RIANOV 303SNR-5 */ => 40, '000608' /* TENSION BLASTER */ => 40, '00E800' /* TYPEGU/HANDGUN */ => 40, '00E801' /* TYPEGU/MECHGUN */ => 40, '00EA00' /* TYPEME/MECHGUN */ => 40, '00E900' /* TYPERI/RIFLE */ => 40, '00EB00' /* TYPESH/SHOT */ => 40,
                    ];
                    break;
                case 'RAcaseal':
                    $pool = [
                        '009900' /* ANGEL HARP */ => 10, '000F01' /* ANGRY FIST */ => 40, '00D200' /* ANO BAZOOKA */ => 100, '006600' /* ANO RIFLE */ => 40, '004600' /* ANTI ANDROID RIFLE */ => 40, '000607' /* BRAVACE */ => 100, '000F00' /* BRAVE KNUCKLE */ => 100, '00C000' /* CANNON ROUGE */ => 40,
                        '000905' /* CRUSH BULLET */ => 100, '000606' /* CUSTOM RAY ver.OO */ => 100, '00C800' /* DAYLIGHT SCAR */ => 2, '009008' /* DBS SABER */ => 100,
                        '000507' /* DISKA OF BRAVEMAN */ => 100, '000506' /* DISKA OF LIBERATOR */ => 100, '000E00' /* DOUBLE SABER */ => 100, '000107' /* DURANDAL */ => 100, '000907' /* FINAL IMPACT */ => 100,
                        '00B900' /* FLAMBERGE */ => 40, '001500' /* FLAME VISIT */ => 40, '00BC00' /* FLAPJACK FLAPPER */ => 40, '003F00' /* FLIGHT CUTTER */ => 40, '004000' /* FLIGHT FAN */ => 40, '004500' /* FROZEN SHOOTER */ => 40,
                        '000108' /* GALATINE */ => 2, '000F02' /* GOD HAND */ => 40, '008B01' /* GUILTY LIGHT */ => 40, '000806' /* H&S25 JUSTICE */ => 100, '004200' /* HANDGUN:GULD */ => 40, '004300' /* HANDGUN:MILLA */ => 40, '001E00' /* HEAVEN PUNISHER */ => 2, '00BF00' /* HEAVEN STRIKER */ => 2, '001400' /* INFERNO BAZOOKA */ => 40,
                        '000707' /* JUSTY-23ST */ => 100, '000106' /* KALADBOLG */ => 100, '00B400' /* KUSANAGI */ => 40, '000807' /* L&K14 COMBAT */ => 100, '001402' /* L&K38 COMBAT */ => 40, '00AB00' /* LAME DARGENT */ => 40,
                        '000805' /* M&A60 VISE */ => 100, '003A00' /* MADAMS PARASOL */ => 10, '003B00' /* MADAMS UMBRELLA */ => 40, '006D00' /* MASER BEAM */ => 40,
                        '000906' /* METEOR SMASH */ => 100, '002F00' /* MONKEY KING BAR */ => 40, '009400' /* MORNING GLORY */ => 40, '008D00' /* NUG2000-BAZOOKA */ => 10, '00AF00' /* OPHELIE SEIZE */ => 40, '004E00' /* PANZER FAUST */ => 40,
                        '000D03' /* PHOENIX CLAW */ => 40, '008B03' /* PHONON MASER */ => 40, '000D00' /* PHOTON CLAW */ => 100, '008B00' /* PHOTON LAUNCHER */ => 100, '00AD03' /* RAGE DE FEU */ => 40, '004400' /* RED HANDGUN */ => 40,
                        '004C00' /* RED MECHGUN */ => 40, '002D00' /* RED SABER */ => 40, '008B02' /* RED SCORPIO */ => 40, '004100' /* RED SLICER */ => 40, '000708' /* RIANOV 303SNR */ => 100,     '009800' /* RIKAS CLAW */ => 40, '00A300' /* RUBY BULLET */ => 40,
                        '00B500' /* SACRED DUSTER */ => 40, '00C600' /* SHICHISHITO */ => 40, '000505' /* SLICER OF ASSASSIN */ => 100, '00AA00' /* SLICER OF FANATIC */ => 40, '001200' /* SPREAD NEEDLE */ => 40,
                        '002600' /* SUPPRESSED GUN */ => 40, '00CD00' /* TANEGASHIMA */ => 100, '005E00' /* TWIN BLAZE */ => 40, '000E02' /* TWIN BRAND */ => 40, '003600' /* TWIN CHAKRAM */ => 40,
                        '00CB00' /* TYRELLS PARASOL */ => 100, '000B07' /* VALKYRIE */ => 40, '000605' /* VARISTA */ => 100, '002000' /* VICTOR AXE */ => 40, '000705' /* VISK-235W */ => 100, '00B300' /* VIVIENNE */ => 10,
                        '000706' /* WALS-MK2 */ => 100, '002900' /* YAMIGARASU */ => 40, '006A00' /* YASMINKOV 2000H */ => 40, '006500' /* YASMINKOV 3000R */ => 40, '006B00' /* YASMINKOV 7000V */ => 40,
                        '006C00' /* YASMINKOV 9000M */ => 40, '000308' /* ZERO DIVIDE */ => 40, '004B01' /* DUAL BIRD */ => 10, '000508' /* IZMAELA */ => 40, '004301' /* LAST SWAN */ => 40, '00B100' /* LE COGNEUR */ => 40, '004201' /* MASTER RAVEN */ => 40, '00C100' /* METEOR ROUGE */ => 40, '00B000' /* MILLE MARTEAUX */ => 10, '001401' /* RAMBLING MAY */ => 40, '000709' /* RIANOV 303SNR-1 */ => 40, '00070A' /* RIANOV 303SNR-2 */ => 40, '00070B' /* RIANOV 303SNR-3 */ => 40, '00070C' /* RIANOV 303SNR-4 */ => 40, '00070D' /* RIANOV 303SNR-5 */ => 40, '000608' /* TENSION BLASTER */ => 40, '00E800' /* TYPEGU/HANDGUN */ => 40, '00E801' /* TYPEGU/MECHGUN */ => 40, '00EA00' /* TYPEME/MECHGUN */ => 40, '00E900' /* TYPERI/RIFLE */ => 40, '00EB00' /* TYPESH/SHOT */ => 40,
                    ];
                    break;
                case 'FOmar':
                    $pool = [
                        '000B06' /* ALIVE AQHU */ => 100, '002700' /* ANCIENT SABER */ => 40, '000F01' /* ANGRY FIST */ => 40, '000408' /* ASTERON BELT */ => 40, '000B04' /* BATTLE VERGE */ => 100, '009303' /* BLUEFULL CARD */ => 10,
                        '006800' /* BRANCH OF PAKUPAKU */ => 40, '000607' /* BRAVACE */ => 100, '000B05' /* BRAVE HAMMER */ => 100, '000F00' /* BRAVE KNUCKLE */ => 100, '002200' /* CADUCEUS */ => 40,
                        '00C300' /* CLIO */ => 40, '000A04' /* CLUB OF LACONIUM */ => 100, '000A06' /* CLUB OF ZUMIURAN */ => 100, '000606' /* CUSTOM RAY ver.OO */ => 100, '009008' /* DBS SABER */ => 100,
                        '00C900' /* DECALOG */ => 10, '005700' /* DEMONIC FORK */ => 40, '000507' /* DISKA OF BRAVEMAN */ => 100, '000506' /* DISKA OF LIBERATOR */ => 100, '000E00' /* DOUBLE SABER */ => 100, '000107' /* DURANDAL */ => 100,
                        '000C07' /* EARTH WAND BROWNIE */ => 40, '002C00' /* ELYSION */ => 40, '005100' /* EVIL CURST */ => 10, '005601' /* FATSIA */ => 40, '004000' /* FLIGHT FAN */ => 40,
                        '000108' /* GALATINE */ => 2, '00C500' /* GLIDE DIVINE */ => 40, '009301' /* GREENILL CARD */ => 10, '009200' /* GUARDIANNA */ => 40, '000806' /* H&S25 JUSTICE */ => 100,
                        '008C02' /* HITOGATA */ => 40, '001300' /* HOLY RAY */ => 40, '001400' /* INFERNO BAZOOKA */ => 40, '000106' /* KALADBOLG */ => 100, '000807' /* L&K14 COMBAT */ => 100, '001402' /* L&K38 COMBAT */ => 40,
                        '00AB00' /* LAME DARGENT */ => 40, '000805' /* M&A60 VISE */ => 100, '000A05' /* MACE OF ADAMAN */ => 100, '008C01' /* MAHU */ => 40, '009400' /* MORNING GLORY */ => 40, '009307' /* ORAN CARD */ => 10,
                        '000D03' /* PHOENIX CLAW */ => 40, '000D00' /* PHOTON CLAW */ => 100, '009305' /* PINKAL CARD */ => 10, '005600' /* PLANTAIN LEAF */ => 40, '005A00' /* PROPHETS OF MOTAV */ => 10, '001D00' /* PSYCHO WAND */ => 2,
                        '009304' /* PURPLENUM CARD */ => 10, '004400' /* RED HANDGUN */ => 40, '002D00' /* RED SABER */ => 40, '004100' /* RED SLICER */ => 40,
                        '009306' /* REDRIA CARD */ => 10, '00C600' /* SHICHISHITO */ => 40, '00C400' /* SIREN GLASS HAMMER */ => 40, '009302' /* SKYLY CARD */ => 10, '000505' /* SLICER OF ASSASSIN */ => 100,
                        '00AA00' /* SLICER OF FANATIC */ => 40, '00C200' /* SOLFERINO */ => 40, '001101' /* SOUL BANISH */ => 40, '002300' /* STING TIP */ => 40, '000C06' /* STORM WAND:INDRA */ => 40, '002600' /* SUPPRESSED GUN */ => 40,
                        '008C00' /* TALIS */ => 100, '002500' /* TECHNICAL CROZIER */ => 40,
                        '005B00' /* THE SIGH OF A GOD */ => 40, '003600' /* TWIN CHAKRAM */ => 40, '000605' /* VARISTA */ => 100, '002000' /* VICTOR AXE */ => 40, '009300' /* VIRIDIA CARD */ => 10, '009309' /* WHITILL CARD */ => 10, '008500' /* WINDMILL */ => 40, '009308' /* YELLOWBOZE CARD */ => 10, '000308' /* ZERO DIVIDE */ => 40, '000508' /* IZMAELA */ => 40, '00D500' /* KANEI TSUHO */ => 40, '008C04' /* KUNAI */ => 40, '006E01' /* LOGIN */ => 40, '002201' /* MERCURIUS ROD */ => 10, '00D800' /* SYRINGE */ => 40, '00E501' /* TYPEDS/ROD */ => 40, '00E600' /* TYPEDS/WAND */ => 40, '00E401' /* TYPEHA/ROD */ => 40, '00E101' /* TYPERO/HALBERT */ => 40, '00E102' /* TYPERO/ROD */ => 40, '00E100' /* TYPERO/SWORD */ => 40, '00EC00' /* TYPEWA/WAND */ => 40,
                    ];
                    break;
                case 'FOmarl':
                    $pool = [
                        '000B06' /* ALIVE AQHU */ => 100, '002700' /* ANCIENT SABER */ => 40, '009900' /* ANGEL HARP */ => 10, '000F01' /* ANGRY FIST */ => 40, '000B04' /* BATTLE VERGE */ => 100, '009303' /* BLUEFULL CARD */ => 10,
                        '006800' /* BRANCH OF PAKUPAKU */ => 40, '000607' /* BRAVACE */ => 100, '000B05' /* BRAVE HAMMER */ => 100, '000F00' /* BRAVE KNUCKLE */ => 100, '002200' /* CADUCEUS */ => 40,
                        '00C300' /* CLIO */ => 40, '000A04' /* CLUB OF LACONIUM */ => 100, '000A06' /* CLUB OF ZUMIURAN */ => 100, '000606' /* CUSTOM RAY ver.OO */ => 100, '009008' /* DBS SABER */ => 100,
                        '00C900' /* DECALOG */ => 10, '005700' /* DEMONIC FORK */ => 40, '000507' /* DISKA OF BRAVEMAN */ => 100, '000506' /* DISKA OF LIBERATOR */ => 100, '000E00' /* DOUBLE SABER */ => 100, '000107' /* DURANDAL */ => 100,
                        '000C07' /* EARTH WAND BROWNIE */ => 40, '002C00' /* ELYSION */ => 40, '005100' /* EVIL CURST */ => 10, '005601' /* FATSIA */ => 40, '004000' /* FLIGHT FAN */ => 40,
                        '000108' /* GALATINE */ => 2, '00C500' /* GLIDE DIVINE */ => 40, '009301' /* GREENILL CARD */ => 10, '009200' /* GUARDIANNA */ => 40, '000806' /* H&S25 JUSTICE */ => 100,
                        '008C02' /* HITOGATA */ => 40, '001300' /* HOLY RAY */ => 40, '001400' /* INFERNO BAZOOKA */ => 40, '000106' /* KALADBOLG */ => 100, '000807' /* L&K14 COMBAT */ => 100, '001402' /* L&K38 COMBAT */ => 40,
                        '00AB00' /* LAME DARGENT */ => 40, '000805' /* M&A60 VISE */ => 100, '000A05' /* MACE OF ADAMAN */ => 100, '003A00' /* MADAMS PARASOL */ => 10, '003B00' /* MADAMS UMBRELLA */ => 40, '008C01' /* MAHU */ => 40,
                        '009400' /* MORNING GLORY */ => 40, '009307' /* ORAN CARD */ => 10, '000D03' /* PHOENIX CLAW */ => 40, '000D00' /* PHOTON CLAW */ => 100, '009305' /* PINKAL CARD */ => 10, '005600' /* PLANTAIN LEAF */ => 40,
                        '005A00' /* PROPHETS OF MOTAV */ => 10, '001D00' /* PSYCHO WAND */ => 2, '009304' /* PURPLENUM CARD */ => 10, '005500' /* RABBIT WAND */ => 100,
                        '004400' /* RED HANDGUN */ => 40, '002D00' /* RED SABER */ => 40, '004100' /* RED SLICER */ => 40, '009306' /* REDRIA CARD */ => 10, '00C600' /* SHICHISHITO */ => 40,
                        '00C400' /* SIREN GLASS HAMMER */ => 40, '009302' /* SKYLY CARD */ => 10, '000505' /* SLICER OF ASSASSIN */ => 100, '00AA00' /* SLICER OF FANATIC */ => 40, '00C200' /* SOLFERINO */ => 40, '001101' /* SOUL BANISH */ => 40,
                        '002300' /* STING TIP */ => 40, '000C06' /* STORM WAND:INDRA */ => 40, '002600' /* SUPPRESSED GUN */ => 40, '008C00' /* TALIS */ => 100, '002500' /* TECHNICAL CROZIER */ => 40, '005B00' /* THE SIGH OF A GOD */ => 40, '003600' /* TWIN CHAKRAM */ => 40, '00CB00' /* TYRELLS PARASOL */ => 100,
                        '000B07' /* VALKYRIE */ => 40, '000605' /* VARISTA */ => 100, '002000' /* VICTOR AXE */ => 40, '009300' /* VIRIDIA CARD */ => 10, '00B300' /* VIVIENNE */ => 10, '009309' /* WHITILL CARD */ => 10, '008500' /* WINDMILL */ => 40, '009308' /* YELLOWBOZE CARD */ => 10, '000308' /* ZERO DIVIDE */ => 40, '000508' /* IZMAELA */ => 40, '00D500' /* KANEI TSUHO */ => 40, '008C04' /* KUNAI */ => 40, '006E01' /* LOGIN */ => 40, '002201' /* MERCURIUS ROD */ => 10, '00D800' /* SYRINGE */ => 40, '00E501' /* TYPEDS/ROD */ => 40, '00E600' /* TYPEDS/WAND */ => 40, '00E401' /* TYPEHA/ROD */ => 40, '00E101' /* TYPERO/HALBERT */ => 40, '00E102' /* TYPERO/ROD */ => 40, '00E100' /* TYPERO/SWORD */ => 40, '00EC00' /* TYPEWA/WAND */ => 40,
                    ];
                    break;
                case 'FOnewm':
                    $pool = [
                        '000B06' /* ALIVE AQHU */ => 100, '002700' /* ANCIENT SABER */ => 40, '000F01' /* ANGRY FIST */ => 40, '000B04' /* BATTLE VERGE */ => 100, '009303' /* BLUEFULL CARD */ => 10, '006800' /* BRANCH OF PAKUPAKU */ => 40,
                        '000607' /* BRAVACE */ => 100, '000B05' /* BRAVE HAMMER */ => 100, '000F00' /* BRAVE KNUCKLE */ => 100, '002200' /* CADUCEUS */ => 40, '00C300' /* CLIO */ => 40,
                        '000A04' /* CLUB OF LACONIUM */ => 100, '000A06' /* CLUB OF ZUMIURAN */ => 100, '000606' /* CUSTOM RAY ver.OO */ => 100, '009008' /* DBS SABER */ => 100, '00C900' /* DECALOG */ => 10,
                        '005700' /* DEMONIC FORK */ => 40, '000507' /* DISKA OF BRAVEMAN */ => 100, '000506' /* DISKA OF LIBERATOR */ => 100, '000E00' /* DOUBLE SABER */ => 100, '000107' /* DURANDAL */ => 100, '000C07' /* EARTH WAND BROWNIE */ => 40, '002C00' /* ELYSION */ => 40, '005100' /* EVIL CURST */ => 10, '005601' /* FATSIA */ => 40, '004000' /* FLIGHT FAN */ => 40, '00C500' /* GLIDE DIVINE */ => 40,
                        '009301' /* GREENILL CARD */ => 10, '009200' /* GUARDIANNA */ => 40, '000806' /* H&S25 JUSTICE */ => 100, '008C02' /* HITOGATA */ => 40, '001300' /* HOLY RAY */ => 40,
                        '001400' /* INFERNO BAZOOKA */ => 40, '000106' /* KALADBOLG */ => 100, '000807' /* L&K14 COMBAT */ => 100, '001402' /* L&K38 COMBAT */ => 40, '00AB00' /* LAME DARGENT */ => 40, '000805' /* M&A60 VISE */ => 100,
                        '000A05' /* MACE OF ADAMAN */ => 100, '008C01' /* MAHU */ => 40, '009400' /* MORNING GLORY */ => 40, '009307' /* ORAN CARD */ => 10, '000D03' /* PHOENIX CLAW */ => 40, '000D00' /* PHOTON CLAW */ => 100,
                        '009305' /* PINKAL CARD */ => 10, '005600' /* PLANTAIN LEAF */ => 40, '005A00' /* PROPHETS OF MOTAV */ => 10, '001D00' /* PSYCHO WAND */ => 2, '009304' /* PURPLENUM CARD */ => 10, '004400' /* RED HANDGUN */ => 40, '002D00' /* RED SABER */ => 40, '004100' /* RED SLICER */ => 40, '009306' /* REDRIA CARD */ => 10, '00C600' /* SHICHISHITO */ => 40, '00C400' /* SIREN GLASS HAMMER */ => 40, '009302' /* SKYLY CARD */ => 10, '000505' /* SLICER OF ASSASSIN */ => 100, '00AA00' /* SLICER OF FANATIC */ => 40, '00C200' /* SOLFERINO */ => 40,
                        '002300' /* STING TIP */ => 40, '000C06' /* STORM WAND:INDRA */ => 40, '002600' /* SUPPRESSED GUN */ => 40, '008C00' /* TALIS */ => 100, '002500' /* TECHNICAL CROZIER */ => 40, '005B00' /* THE SIGH OF A GOD */ => 40, '003600' /* TWIN CHAKRAM */ => 40, '000605' /* VARISTA */ => 100,
                        '002000' /* VICTOR AXE */ => 40, '009300' /* VIRIDIA CARD */ => 10, '009309' /* WHITILL CARD */ => 10, '008500' /* WINDMILL */ => 40, '009308' /* YELLOWBOZE CARD */ => 10,
                        '000308' /* ZERO DIVIDE */ => 40, '000508' /* IZMAELA */ => 40, '00D500' /* KANEI TSUHO */ => 40, '008C04' /* KUNAI */ => 40, '006E01' /* LOGIN */ => 40, '002201' /* MERCURIUS ROD */ => 10, '00D800' /* SYRINGE */ => 40, '00E501' /* TYPEDS/ROD */ => 40, '00E600' /* TYPEDS/WAND */ => 40, '00E401' /* TYPEHA/ROD */ => 40, '00E101' /* TYPERO/HALBERT */ => 40, '00E102' /* TYPERO/ROD */ => 40, '00E100' /* TYPERO/SWORD */ => 40, '00EC00' /* TYPEWA/WAND */ => 40,
                    ];
                    break;
                case 'FOnewearl':
                    $pool = [
                        '000B06' /* ALIVE AQHU */ => 100, '009900' /* ANGEL HARP */ => 10, '000B04' /* BATTLE VERGE */ => 100, '009303' /* BLUEFULL CARD */ => 10, '006800' /* BRANCH OF PAKUPAKU */ => 40, '000607' /* BRAVACE */ => 100,
                        '000B05' /* BRAVE HAMMER */ => 100, '000F00' /* BRAVE KNUCKLE */ => 100, '002200' /* CADUCEUS */ => 40, '00C300' /* CLIO */ => 40, '000A04' /* CLUB OF LACONIUM */ => 100,
                        '000A06' /* CLUB OF ZUMIURAN */ => 100, '000606' /* CUSTOM RAY ver.OO */ => 100, '009008' /* DBS SABER */ => 100, '00C900' /* DECALOG */ => 10, '005700' /* DEMONIC FORK */ => 40,
                        '000507' /* DISKA OF BRAVEMAN */ => 100, '000506' /* DISKA OF LIBERATOR */ => 100, '000E00' /* DOUBLE SABER */ => 100, '000107' /* DURANDAL */ => 100, '000C07' /* EARTH WAND BROWNIE */ => 40,
                        '002C00' /* ELYSION */ => 40, '005100' /* EVIL CURST */ => 10, '005601' /* FATSIA */ => 40, '004000' /* FLIGHT FAN */ => 40, '00C500' /* GLIDE DIVINE */ => 40, '009301' /* GREENILL CARD */ => 10,
                        '009200' /* GUARDIANNA */ => 40, '000806' /* H&S25 JUSTICE */ => 100, '008C02' /* HITOGATA */ => 40, '001300' /* HOLY RAY */ => 40, '001400' /* INFERNO BAZOOKA */ => 40,
                        '000106' /* KALADBOLG */ => 100, '000807' /* L&K14 COMBAT */ => 100, '001402' /* L&K38 COMBAT */ => 40, '000805' /* M&A60 VISE */ => 100, '000A05' /* MACE OF ADAMAN */ => 100, '003A00' /* MADAMS PARASOL */ => 10,
                        '003B00' /* MADAMS UMBRELLA */ => 40, '008C01' /* MAHU */ => 40, '009307' /* ORAN CARD */ => 10, '000D03' /* PHOENIX CLAW */ => 40, '000D00' /* PHOTON CLAW */ => 100, '009305' /* PINKAL CARD */ => 10,
                        '005600' /* PLANTAIN LEAF */ => 40, '005A00' /* PROPHETS OF MOTAV */ => 10, '001D00' /* PSYCHO WAND */ => 2, '009304' /* PURPLENUM CARD */ => 10, '005500' /* RABBIT WAND */ => 100, '004400' /* RED HANDGUN */ => 40, '002D00' /* RED SABER */ => 40, '009306' /* REDRIA CARD */ => 10, '00C600' /* SHICHISHITO */ => 40,
                        '00C400' /* SIREN GLASS HAMMER */ => 40, '009302' /* SKYLY CARD */ => 10, '000505' /* SLICER OF ASSASSIN */ => 100, '00AA00' /* SLICER OF FANATIC */ => 40, '00C200' /* SOLFERINO */ => 40, '002300' /* STING TIP */ => 40,
                        '000C06' /* STORM WAND:INDRA */ => 40, '002600' /* SUPPRESSED GUN */ => 40, '008C00' /* TALIS */ => 100, '002500' /* TECHNICAL CROZIER */ => 40, '005B00' /* THE SIGH OF A GOD */ => 40, '003600' /* TWIN CHAKRAM */ => 40, '00CB00' /* TYRELLS PARASOL */ => 100, '000B07' /* VALKYRIE */ => 40,
                        '000605' /* VARISTA */ => 100, '002000' /* VICTOR AXE */ => 40, '009300' /* VIRIDIA CARD */ => 10, '00B300' /* VIVIENNE */ => 10, '009309' /* WHITILL CARD */ => 10,
                        '008500' /* WINDMILL */ => 40, '009308' /* YELLOWBOZE CARD */ => 10, '000308' /* ZERO DIVIDE */ => 40, '000508' /* IZMAELA */ => 40, '00D500' /* KANEI TSUHO */ => 40, '008C04' /* KUNAI */ => 40, '006E01' /* LOGIN */ => 40, '002201' /* MERCURIUS ROD */ => 10, '00D800' /* SYRINGE */ => 40, '00E501' /* TYPEDS/ROD */ => 40, '00E600' /* TYPEDS/WAND */ => 40, '00E401' /* TYPEHA/ROD */ => 40, '00E101' /* TYPERO/HALBERT */ => 40, '00E102' /* TYPERO/ROD */ => 40, '00E100' /* TYPERO/SWORD */ => 40, '00EC00' /* TYPEWA/WAND */ => 40,
                    ];
                    break;
            }

    } else if ($category === 'Armor') {
            switch ($charClass) {
                case 'HUmar':
                    $pool = [
                        '01014D' /* ALLIANCE UNIFORM */ => 40, '010125' /* ATTRIBUTE PLATE */ => 40, '010131' /* AURA FIELD */ => 10, '010138' /* BLACK ODOSHI DOMARU */ => 40, '01013A' /* BLACK ODOSHI RED NIMAIDOU */ => 40, '01013B' /* BLUE ODOSHI VIOLET NIMAIDOU */ => 2,
                        '010130' /* BRIGHTNESS CIRCLE */ => 10, '01014F' /* COMMANDER UNIFORM */ => 40, '010150' /* CRIMSON COAT */ => 10, '010127' /* CUSTOM FRAME ver.OO */ => 40, '010117' /* Celestial Armor */ => 100, '010128' /* DBS ARMOR */ => 40,
                        '01012A' /* DF FIELD */ => 10, '010115' /* Divinity Armor */ => 100, '01012E' /* FLAME GARMENT */ => 10, '010126' /* FLOWENS FRAME */ => 40, '010124' /* GRAVITON PLATE */ => 40, '010129' /* GUARD WAVE */ => 10,
                        '010151' /* INFANTRY GEAR */ => 40, '010153' /* INFANTRY MANTLE */ => 40, '010152' /* LIEUTENANT GEAR */ => 40, '010154' /* LIEUTENANT MANTLE */ => 40, '01012B' /* LUMINOUS FIELD */ => 10, '010137' /* MORNING PRAYER */ => 40,
                        '01014E' /* OFFICER UNIFORM */ => 40, '010140' /* RED COAT */ => 40, '010139' /* RED ODOSHI DOMARU */ => 40, '01014C' /* REVIVAL CUIRASS */ => 40, '01011B' /* REVIVAL GARMENT */ => 40, '010133' /* SACRED CLOTH */ => 10,
                        '010123' /* SENSE PLATE */ => 40, '01014B' /* SPIRIT CUIRASS */ => 40, '01011C' /* SPIRIT GARMENT */ => 40, '01011D' /* STINK FRAME */ => 40, '010141' /* THIRTEEN */ => 40, '010116' /* Ultimate Frame */ => 100,
                        '01013C' /* DIRTY LIFE JACKET */ => 40, '010156' /* SAMURAI ARMOR */ => 40,
                        '01012C' /* CHU CHU FEVER */ => 40, '010144' /* DRESS PLATE */ => 40, '010114' /* GUARDIAN ARMOR */ => 40, '010118' /* HUNTER FIELD */ => 40, '01012F' /* VIRUS ARMOR:LAFUTERIA */ => 40, '010127' /* CUSTOM FRAME VER.OO */ => 40, '01014C' /* REVIVAL CUIRASS */ => 40,
                    ];
                    break;
                case 'HUnewearl':
                    $pool = [
                        '01014D' /* ALLIANCE UNIFORM */ => 40, '010125' /* ATTRIBUTE PLATE */ => 40, '010131' /* AURA FIELD */ => 10, '010138' /* BLACK ODOSHI DOMARU */ => 40, '01013A' /* BLACK ODOSHI RED NIMAIDOU */ => 40, '01013B' /* BLUE ODOSHI VIOLET NIMAIDOU */ => 2,
                        '010130' /* BRIGHTNESS CIRCLE */ => 10, '01014F' /* COMMANDER UNIFORM */ => 40, '010150' /* CRIMSON COAT */ => 10, '010127' /* CUSTOM FRAME ver.OO */ => 40, '010117' /* Celestial Armor */ => 100, '010128' /* DBS ARMOR */ => 40,
                        '01012A' /* DF FIELD */ => 10, '010115' /* Divinity Armor */ => 100, '01012E' /* FLAME GARMENT */ => 10, '010126' /* FLOWENS FRAME */ => 40, '010124' /* GRAVITON PLATE */ => 40, '010129' /* GUARD WAVE */ => 10,
                        '010151' /* INFANTRY GEAR */ => 40, '010153' /* INFANTRY MANTLE */ => 40, '010152' /* LIEUTENANT GEAR */ => 40, '010154' /* LIEUTENANT MANTLE */ => 40, '01012B' /* LUMINOUS FIELD */ => 10, '010137' /* MORNING PRAYER */ => 40,
                        '01014E' /* OFFICER UNIFORM */ => 40, '010140' /* RED COAT */ => 40, '010139' /* RED ODOSHI DOMARU */ => 40, '01014C' /* REVIVAL CUIRASS */ => 40, '01011B' /* REVIVAL GARMENT */ => 40, '010133' /* SACRED CLOTH */ => 10,
                        '010123' /* SENSE PLATE */ => 40, '01014B' /* SPIRIT CUIRASS */ => 40, '01011C' /* SPIRIT GARMENT */ => 40, '01011D' /* STINK FRAME */ => 40, '010141' /* THIRTEEN */ => 40, '010116' /* Ultimate Frame */ => 100,
                        '01013C' /* DIRTY LIFE JACKET */ => 40, '010144' /* DRESS PLATE */ => 40, '01013D' /* KROES SWEATER */ => 40, '010145' /* SWEETHEART */ => 40, '01013E' /* WEDDING DRESS */ => 10,
                        '01012C' /* CHU CHU FEVER */ => 40, '010114' /* GUARDIAN ARMOR */ => 40, '010118' /* HUNTER FIELD */ => 40, '01012D' /* LOVE HEART */ => 40, '01012F' /* VIRUS ARMOR:LAFUTERIA */ => 40, '010127' /* CUSTOM FRAME VER.OO */ => 40, '01014C' /* REVIVAL CUIRASS */ => 40,
                    ];
                    break;
                case 'HUcast':
                    $pool = [
                        '01014D' /* ALLIANCE UNIFORM */ => 40, '010125' /* ATTRIBUTE PLATE */ => 40, '010136' /* BLACK HOUND CUIRASS */ => 40, '010138' /* BLACK ODOSHI DOMARU */ => 40, '01013A' /* BLACK ODOSHI RED NIMAIDOU */ => 40, '01013B' /* BLUE ODOSHI VIOLET NIMAIDOU */ => 2,
                        '010130' /* BRIGHTNESS CIRCLE */ => 10, '010150' /* CRIMSON COAT */ => 10, '010127' /* CUSTOM FRAME ver.OO */ => 40, '010117' /* Celestial Armor */ => 100, '01011E' /* D-PARTS ver1.01 */ => 40, '01011F' /* D-PARTS ver2.10 */ => 40,
                        '010128' /* DBS ARMOR */ => 40, '01012A' /* DF FIELD */ => 10, '010115' /* Divinity Armor */ => 100, '010132' /* ELECTRO FRAME */ => 10, '01012E' /* FLAME GARMENT */ => 10, '010126' /* FLOWENS FRAME */ => 40,
                        '010124' /* GRAVITON PLATE */ => 40, '010129' /* GUARD WAVE */ => 10, '010151' /* INFANTRY GEAR */ => 40, '010153' /* INFANTRY MANTLE */ => 40, '01012B' /* LUMINOUS FIELD */ => 10, '010137' /* MORNING PRAYER */ => 40,
                        '010140' /* RED COAT */ => 40, '010139' /* RED ODOSHI DOMARU */ => 40, '01014C' /* REVIVAL CUIRASS */ => 40, '01011B' /* REVIVAL GARMENT */ => 40, '010133' /* SACRED CLOTH */ => 10, '010123' /* SENSE PLATE */ => 40,
                        '01014B' /* SPIRIT CUIRASS */ => 40, '01011C' /* SPIRIT GARMENT */ => 40, '010135' /* STAR CUIRASS */ => 40, '01011D' /* STINK FRAME */ => 40, '010141' /* THIRTEEN */ => 40, '010116' /* Ultimate Frame */ => 100,
                        '01013C' /* DIRTY LIFE JACKET */ => 40, '010156' /* SAMURAI ARMOR */ => 40,
                        '01012C' /* CHU CHU FEVER */ => 40, '010144' /* DRESS PLATE */ => 40, '010114' /* GUARDIAN ARMOR */ => 40, '010118' /* HUNTER FIELD */ => 40, '010127' /* CUSTOM FRAME VER.OO */ => 40, '01014C' /* REVIVAL CUIRASS */ => 40,
                    ];
                    break;
                case 'HUcaseal':
                    $pool = [
                        '01014D' /* ALLIANCE UNIFORM */ => 40, '010125' /* ATTRIBUTE PLATE */ => 40, '010136' /* BLACK HOUND CUIRASS */ => 40, '010138' /* BLACK ODOSHI DOMARU */ => 40, '01013A' /* BLACK ODOSHI RED NIMAIDOU */ => 40, '01013B' /* BLUE ODOSHI VIOLET NIMAIDOU */ => 2,
                        '010130' /* BRIGHTNESS CIRCLE */ => 10, '010150' /* CRIMSON COAT */ => 10, '010127' /* CUSTOM FRAME ver.OO */ => 40, '010117' /* Celestial Armor */ => 100, '01011E' /* D-PARTS ver1.01 */ => 40, '01011F' /* D-PARTS ver2.10 */ => 40,
                        '010128' /* DBS ARMOR */ => 40, '01012A' /* DF FIELD */ => 10, '010115' /* Divinity Armor */ => 100, '010132' /* ELECTRO FRAME */ => 10, '01012E' /* FLAME GARMENT */ => 10, '010126' /* FLOWENS FRAME */ => 40,
                        '010124' /* GRAVITON PLATE */ => 40, '010129' /* GUARD WAVE */ => 10, '010151' /* INFANTRY GEAR */ => 40, '010153' /* INFANTRY MANTLE */ => 40, '01012B' /* LUMINOUS FIELD */ => 10, '010137' /* MORNING PRAYER */ => 40,
                        '010140' /* RED COAT */ => 40, '010139' /* RED ODOSHI DOMARU */ => 40, '01014C' /* REVIVAL CUIRASS */ => 40, '01011B' /* REVIVAL GARMENT */ => 40, '010133' /* SACRED CLOTH */ => 10, '010123' /* SENSE PLATE */ => 40,
                        '01014B' /* SPIRIT CUIRASS */ => 40, '01011C' /* SPIRIT GARMENT */ => 40, '010135' /* STAR CUIRASS */ => 40, '01011D' /* STINK FRAME */ => 40, '010141' /* THIRTEEN */ => 40, '010116' /* Ultimate Frame */ => 100,
                        '01013C' /* DIRTY LIFE JACKET */ => 40, '010144' /* DRESS PLATE */ => 40, '01013D' /* KROES SWEATER */ => 40, '010145' /* SWEETHEART */ => 40, '01013E' /* WEDDING DRESS */ => 10,
                        '01012C' /* CHU CHU FEVER */ => 40, '010114' /* GUARDIAN ARMOR */ => 40, '010118' /* HUNTER FIELD */ => 40, '01012D' /* LOVE HEART */ => 40, '010127' /* CUSTOM FRAME VER.OO */ => 40, '01014C' /* REVIVAL CUIRASS */ => 40,
                    ];
                    break;
                case 'RAmar':
                    $pool = [
                        '01014D' /* ALLIANCE UNIFORM */ => 40, '010125' /* ATTRIBUTE PLATE */ => 40, '010131' /* AURA FIELD */ => 10, '010130' /* BRIGHTNESS CIRCLE */ => 10, '01014F' /* COMMANDER UNIFORM */ => 40, '010150' /* CRIMSON COAT */ => 10,
                        '010127' /* CUSTOM FRAME ver.OO */ => 40, '010117' /* Celestial Armor */ => 100, '010128' /* DBS ARMOR */ => 40, '01012A' /* DF FIELD */ => 10, '010115' /* Divinity Armor */ => 100, '01012E' /* FLAME GARMENT */ => 10,
                        '010126' /* FLOWENS FRAME */ => 40, '010124' /* GRAVITON PLATE */ => 40, '010129' /* GUARD WAVE */ => 10, '010151' /* INFANTRY GEAR */ => 40, '010153' /* INFANTRY MANTLE */ => 40, '010152' /* LIEUTENANT GEAR */ => 40,
                        '010154' /* LIEUTENANT MANTLE */ => 40, '01012B' /* LUMINOUS FIELD */ => 10, '010137' /* MORNING PRAYER */ => 40, '01014E' /* OFFICER UNIFORM */ => 40, '010140' /* RED COAT */ => 40, '01014C' /* REVIVAL CUIRASS */ => 40,
                        '01011B' /* REVIVAL GARMENT */ => 40, '010133' /* SACRED CLOTH */ => 10, '010123' /* SENSE PLATE */ => 40, '01014B' /* SPIRIT CUIRASS */ => 40, '01011C' /* SPIRIT GARMENT */ => 40, '01011D' /* STINK FRAME */ => 40,
                        '010141' /* THIRTEEN */ => 40, '010116' /* Ultimate Frame */ => 100,
                        '01013C' /* DIRTY LIFE JACKET */ => 40, '010156' /* SAMURAI ARMOR */ => 40,
                        '01012C' /* CHU CHU FEVER */ => 40, '010144' /* DRESS PLATE */ => 40, '010114' /* GUARDIAN ARMOR */ => 40, '010119' /* RANGER FIELD */ => 40, '01012F' /* VIRUS ARMOR:LAFUTERIA */ => 40, '010127' /* CUSTOM FRAME VER.OO */ => 40, '01014C' /* REVIVAL CUIRASS */ => 40,
                    ];
                    break;
                case 'RAmarl':
                    $pool = [
                        '01014D' /* ALLIANCE UNIFORM */ => 40, '010125' /* ATTRIBUTE PLATE */ => 40, '010131' /* AURA FIELD */ => 10, '010130' /* BRIGHTNESS CIRCLE */ => 10, '01014F' /* COMMANDER UNIFORM */ => 40, '010150' /* CRIMSON COAT */ => 10,
                        '010127' /* CUSTOM FRAME ver.OO */ => 40, '010117' /* Celestial Armor */ => 100, '010128' /* DBS ARMOR */ => 40, '01012A' /* DF FIELD */ => 10, '010115' /* Divinity Armor */ => 100, '01012E' /* FLAME GARMENT */ => 10,
                        '010126' /* FLOWENS FRAME */ => 40, '010124' /* GRAVITON PLATE */ => 40, '010129' /* GUARD WAVE */ => 10, '010151' /* INFANTRY GEAR */ => 40, '010153' /* INFANTRY MANTLE */ => 40, '010152' /* LIEUTENANT GEAR */ => 40,
                        '010154' /* LIEUTENANT MANTLE */ => 40, '01012B' /* LUMINOUS FIELD */ => 10, '010137' /* MORNING PRAYER */ => 40, '01014E' /* OFFICER UNIFORM */ => 40, '010140' /* RED COAT */ => 40, '01014C' /* REVIVAL CUIRASS */ => 40,
                        '01011B' /* REVIVAL GARMENT */ => 40, '010133' /* SACRED CLOTH */ => 10, '010123' /* SENSE PLATE */ => 40, '01014B' /* SPIRIT CUIRASS */ => 40, '01011C' /* SPIRIT GARMENT */ => 40, '01011D' /* STINK FRAME */ => 40,
                        '010141' /* THIRTEEN */ => 40, '010116' /* Ultimate Frame */ => 100,
                        '01013C' /* DIRTY LIFE JACKET */ => 40, '010144' /* DRESS PLATE */ => 40, '01013D' /* KROES SWEATER */ => 40, '010145' /* SWEETHEART */ => 40, '01013E' /* WEDDING DRESS */ => 10,
                        '01012C' /* CHU CHU FEVER */ => 40, '010114' /* GUARDIAN ARMOR */ => 40, '01012D' /* LOVE HEART */ => 40, '010119' /* RANGER FIELD */ => 40, '01012F' /* VIRUS ARMOR:LAFUTERIA */ => 40, '010127' /* CUSTOM FRAME VER.OO */ => 40, '01014C' /* REVIVAL CUIRASS */ => 40,
                    ];
                    break;
                case 'RAcast':
                    $pool = [
                        '01014D' /* ALLIANCE UNIFORM */ => 40, '010125' /* ATTRIBUTE PLATE */ => 40, '010136' /* BLACK HOUND CUIRASS */ => 40, '010130' /* BRIGHTNESS CIRCLE */ => 10, '010150' /* CRIMSON COAT */ => 10, '010127' /* CUSTOM FRAME ver.OO */ => 40,
                        '010117' /* Celestial Armor */ => 100, '01011E' /* D-PARTS ver1.01 */ => 40, '01011F' /* D-PARTS ver2.10 */ => 40, '010128' /* DBS ARMOR */ => 40, '01012A' /* DF FIELD */ => 10, '010115' /* Divinity Armor */ => 100,
                        '010132' /* ELECTRO FRAME */ => 10, '01012E' /* FLAME GARMENT */ => 10, '010126' /* FLOWENS FRAME */ => 40, '010124' /* GRAVITON PLATE */ => 40, '010129' /* GUARD WAVE */ => 10, '010151' /* INFANTRY GEAR */ => 40,
                        '010153' /* INFANTRY MANTLE */ => 40, '01012B' /* LUMINOUS FIELD */ => 10, '010137' /* MORNING PRAYER */ => 40, '010140' /* RED COAT */ => 40, '01014C' /* REVIVAL CUIRASS */ => 40, '01011B' /* REVIVAL GARMENT */ => 40,
                        '010133' /* SACRED CLOTH */ => 10, '010123' /* SENSE PLATE */ => 40, '01014B' /* SPIRIT CUIRASS */ => 40, '01011C' /* SPIRIT GARMENT */ => 40, '010135' /* STAR CUIRASS */ => 40, '01011D' /* STINK FRAME */ => 40,
                        '010141' /* THIRTEEN */ => 40, '010116' /* Ultimate Frame */ => 100,
                        '01013C' /* DIRTY LIFE JACKET */ => 40, '010156' /* SAMURAI ARMOR */ => 40,
                        '01012C' /* CHU CHU FEVER */ => 40, '010144' /* DRESS PLATE */ => 40, '010114' /* GUARDIAN ARMOR */ => 40, '010119' /* RANGER FIELD */ => 40, '010127' /* CUSTOM FRAME VER.OO */ => 40, '01014C' /* REVIVAL CUIRASS */ => 40,
                    ];
                    break;
                case 'RAcaseal':
                    $pool = [
                        '01014D' /* ALLIANCE UNIFORM */ => 40, '010125' /* ATTRIBUTE PLATE */ => 40, '010136' /* BLACK HOUND CUIRASS */ => 40, '010130' /* BRIGHTNESS CIRCLE */ => 10, '010150' /* CRIMSON COAT */ => 10, '010127' /* CUSTOM FRAME ver.OO */ => 40,
                        '010117' /* Celestial Armor */ => 100, '01011E' /* D-PARTS ver1.01 */ => 40, '01011F' /* D-PARTS ver2.10 */ => 40, '010128' /* DBS ARMOR */ => 40, '01012A' /* DF FIELD */ => 10, '010115' /* Divinity Armor */ => 100,
                        '010132' /* ELECTRO FRAME */ => 10, '01012E' /* FLAME GARMENT */ => 10, '010126' /* FLOWENS FRAME */ => 40, '010124' /* GRAVITON PLATE */ => 40, '010129' /* GUARD WAVE */ => 10, '010151' /* INFANTRY GEAR */ => 40,
                        '010153' /* INFANTRY MANTLE */ => 40, '01012B' /* LUMINOUS FIELD */ => 10, '010137' /* MORNING PRAYER */ => 40, '010140' /* RED COAT */ => 40, '01014C' /* REVIVAL CUIRASS */ => 40, '01011B' /* REVIVAL GARMENT */ => 40,
                        '010133' /* SACRED CLOTH */ => 10, '010123' /* SENSE PLATE */ => 40, '01014B' /* SPIRIT CUIRASS */ => 40, '01011C' /* SPIRIT GARMENT */ => 40, '010135' /* STAR CUIRASS */ => 40, '01011D' /* STINK FRAME */ => 40,
                        '010141' /* THIRTEEN */ => 40, '010116' /* Ultimate Frame */ => 100,
                        '01013C' /* DIRTY LIFE JACKET */ => 40, '010144' /* DRESS PLATE */ => 40, '01013D' /* KROES SWEATER */ => 40, '010145' /* SWEETHEART */ => 40, '01013E' /* WEDDING DRESS */ => 10,
                        '01012C' /* CHU CHU FEVER */ => 40, '010114' /* GUARDIAN ARMOR */ => 40, '01012D' /* LOVE HEART */ => 40, '010119' /* RANGER FIELD */ => 40, '010127' /* CUSTOM FRAME VER.OO */ => 40, '01014C' /* REVIVAL CUIRASS */ => 40,
                    ];
                    break;
                case 'FOmar':
                    $pool = [
                        '01014D' /* ALLIANCE UNIFORM */ => 40, '010131' /* AURA FIELD */ => 10, '010130' /* BRIGHTNESS CIRCLE */ => 10, '01014F' /* COMMANDER UNIFORM */ => 40, '010147' /* CONGEAL CLOAK */ => 40, '010150' /* CRIMSON COAT */ => 10,
                        '010149' /* CURSED CLOAK */ => 40, '010127' /* CUSTOM FRAME ver.OO */ => 40, '01012A' /* DF FIELD */ => 10, '01012E' /* FLAME GARMENT */ => 10, '010129' /* GUARD WAVE */ => 10, '010146' /* IGNITION CLOAK */ => 40,
                        '010151' /* INFANTRY GEAR */ => 40, '010153' /* INFANTRY MANTLE */ => 40, '010152' /* LIEUTENANT GEAR */ => 40, '010154' /* LIEUTENANT MANTLE */ => 40, '01012B' /* LUMINOUS FIELD */ => 10, '010137' /* MORNING PRAYER */ => 40,
                        '01014E' /* OFFICER UNIFORM */ => 40, '010140' /* RED COAT */ => 40, '01014C' /* REVIVAL CUIRASS */ => 40, '01011B' /* REVIVAL GARMENT */ => 40, '010133' /* SACRED CLOTH */ => 10, '01014A' /* SELECT CLOAK */ => 40,
                        '01014B' /* SPIRIT CUIRASS */ => 40, '01011C' /* SPIRIT GARMENT */ => 40, '01011D' /* STINK FRAME */ => 40, '010148' /* TEMPEST CLOAK */ => 40, '010141' /* THIRTEEN */ => 40, '010116' /* Ultimate Frame */ => 100,
                        '010149' /* CURSED CLOAK */ => 40, '01013C' /* DIRTY LIFE JACKET */ => 40, '010146' /* IGNITION CLOAK */ => 40, '010142' /* MOTHER GARB */ => 10, '010143' /* MOTHER GARB+ */ => 2, '010156' /* SAMURAI ARMOR */ => 40,
                        '01012C' /* CHU CHU FEVER */ => 40, '010144' /* DRESS PLATE */ => 40, '01011A' /* FORCE FIELD */ => 40, '01012F' /* VIRUS ARMOR:LAFUTERIA */ => 40, '010127' /* CUSTOM FRAME VER.OO */ => 40, '01014C' /* REVIVAL CUIRASS */ => 40,
                    ];
                    break;
                case 'FOmarl':
                    $pool = [
                        '01014D' /* ALLIANCE UNIFORM */ => 40, '010131' /* AURA FIELD */ => 10, '010130' /* BRIGHTNESS CIRCLE */ => 10, '01014F' /* COMMANDER UNIFORM */ => 40, '010147' /* CONGEAL CLOAK */ => 40, '010150' /* CRIMSON COAT */ => 10,
                        '010149' /* CURSED CLOAK */ => 40, '010127' /* CUSTOM FRAME ver.OO */ => 40, '01012A' /* DF FIELD */ => 10, '01012E' /* FLAME GARMENT */ => 10, '010129' /* GUARD WAVE */ => 10, '010146' /* IGNITION CLOAK */ => 40,
                        '010151' /* INFANTRY GEAR */ => 40, '010153' /* INFANTRY MANTLE */ => 40, '010152' /* LIEUTENANT GEAR */ => 40, '010154' /* LIEUTENANT MANTLE */ => 40, '01012B' /* LUMINOUS FIELD */ => 10, '010137' /* MORNING PRAYER */ => 40,
                        '01014E' /* OFFICER UNIFORM */ => 40, '010140' /* RED COAT */ => 40, '01014C' /* REVIVAL CUIRASS */ => 40, '01011B' /* REVIVAL GARMENT */ => 40, '010133' /* SACRED CLOTH */ => 10, '01014A' /* SELECT CLOAK */ => 40,
                        '01014B' /* SPIRIT CUIRASS */ => 40, '01011C' /* SPIRIT GARMENT */ => 40, '01011D' /* STINK FRAME */ => 40, '010148' /* TEMPEST CLOAK */ => 40, '010141' /* THIRTEEN */ => 40, '010116' /* Ultimate Frame */ => 100,
                        '010149' /* CURSED CLOAK */ => 40, '01013C' /* DIRTY LIFE JACKET */ => 40, '010144' /* DRESS PLATE */ => 40, '010146' /* IGNITION CLOAK */ => 40, '01013D' /* KROES SWEATER */ => 40, '010142' /* MOTHER GARB */ => 10, '010143' /* MOTHER GARB+ */ => 2, '010145' /* SWEETHEART */ => 40, '01013E' /* WEDDING DRESS */ => 10,
                        '01012C' /* CHU CHU FEVER */ => 40, '01011A' /* FORCE FIELD */ => 40, '01012D' /* LOVE HEART */ => 40, '01012F' /* VIRUS ARMOR:LAFUTERIA */ => 40, '010127' /* CUSTOM FRAME VER.OO */ => 40, '01014C' /* REVIVAL CUIRASS */ => 40,
                    ];
                    break;
                case 'FOnewm':
                    $pool = [
                        '01014D' /* ALLIANCE UNIFORM */ => 40, '010131' /* AURA FIELD */ => 10, '010130' /* BRIGHTNESS CIRCLE */ => 10, '01014F' /* COMMANDER UNIFORM */ => 40, '010147' /* CONGEAL CLOAK */ => 40, '010150' /* CRIMSON COAT */ => 10,
                        '010149' /* CURSED CLOAK */ => 40, '010127' /* CUSTOM FRAME ver.OO */ => 40, '01012A' /* DF FIELD */ => 10, '01012E' /* FLAME GARMENT */ => 10, '010129' /* GUARD WAVE */ => 10, '010146' /* IGNITION CLOAK */ => 40,
                        '010151' /* INFANTRY GEAR */ => 40, '010153' /* INFANTRY MANTLE */ => 40, '010152' /* LIEUTENANT GEAR */ => 40, '010154' /* LIEUTENANT MANTLE */ => 40, '01012B' /* LUMINOUS FIELD */ => 10, '010137' /* MORNING PRAYER */ => 40,
                        '010142' /* MOTHER GARB */ => 10, '010143' /* MOTHER GARB+ */ => 2, '01014E' /* OFFICER UNIFORM */ => 40, '010140' /* RED COAT */ => 40, '01014C' /* REVIVAL CUIRASS */ => 40, '01011B' /* REVIVAL GARMENT */ => 40,
                        '010133' /* SACRED CLOTH */ => 10, '01014A' /* SELECT CLOAK */ => 40, '010134' /* SMOKING PLATE */ => 10, '01014B' /* SPIRIT CUIRASS */ => 40, '01011C' /* SPIRIT GARMENT */ => 40, '01011D' /* STINK FRAME */ => 40,
                        '010148' /* TEMPEST CLOAK */ => 40, '010141' /* THIRTEEN */ => 40, '010116' /* Ultimate Frame */ => 100,
                        '010149' /* CURSED CLOAK */ => 40, '01013C' /* DIRTY LIFE JACKET */ => 40, '010146' /* IGNITION CLOAK */ => 40, '010156' /* SAMURAI ARMOR */ => 40,
                        '01012C' /* CHU CHU FEVER */ => 40, '010144' /* DRESS PLATE */ => 40, '01011A' /* FORCE FIELD */ => 40, '01012F' /* VIRUS ARMOR:LAFUTERIA */ => 40, '010127' /* CUSTOM FRAME VER.OO */ => 40, '01014C' /* REVIVAL CUIRASS */ => 40,
                    ];
                    break;
                case 'FOnewearl':
                    $pool = [
                        '01014D' /* ALLIANCE UNIFORM */ => 40, '010131' /* AURA FIELD */ => 10, '010130' /* BRIGHTNESS CIRCLE */ => 10, '01014F' /* COMMANDER UNIFORM */ => 40, '010147' /* CONGEAL CLOAK */ => 40, '010150' /* CRIMSON COAT */ => 10,
                        '010149' /* CURSED CLOAK */ => 40, '010127' /* CUSTOM FRAME ver.OO */ => 40, '01012A' /* DF FIELD */ => 10, '01012E' /* FLAME GARMENT */ => 10, '010129' /* GUARD WAVE */ => 10, '010146' /* IGNITION CLOAK */ => 40,
                        '010151' /* INFANTRY GEAR */ => 40, '010153' /* INFANTRY MANTLE */ => 40, '010152' /* LIEUTENANT GEAR */ => 40, '010154' /* LIEUTENANT MANTLE */ => 40, '01012B' /* LUMINOUS FIELD */ => 10, '010137' /* MORNING PRAYER */ => 40,
                        '01014E' /* OFFICER UNIFORM */ => 40, '010140' /* RED COAT */ => 40, '01014C' /* REVIVAL CUIRASS */ => 40, '01011B' /* REVIVAL GARMENT */ => 40, '010133' /* SACRED CLOTH */ => 10, '01014A' /* SELECT CLOAK */ => 40,
                        '01014B' /* SPIRIT CUIRASS */ => 40, '01011C' /* SPIRIT GARMENT */ => 40, '01011D' /* STINK FRAME */ => 40, '010148' /* TEMPEST CLOAK */ => 40, '010141' /* THIRTEEN */ => 40, '010116' /* Ultimate Frame */ => 100,
                        '010149' /* CURSED CLOAK */ => 40, '01013C' /* DIRTY LIFE JACKET */ => 40, '010144' /* DRESS PLATE */ => 40, '010146' /* IGNITION CLOAK */ => 40, '01013D' /* KROES SWEATER */ => 40, '010142' /* MOTHER GARB */ => 10, '010143' /* MOTHER GARB+ */ => 2, '010145' /* SWEETHEART */ => 40, '01013E' /* WEDDING DRESS */ => 10,
                        '01012C' /* CHU CHU FEVER */ => 40, '01011A' /* FORCE FIELD */ => 40, '01012D' /* LOVE HEART */ => 40, '01012F' /* VIRUS ARMOR:LAFUTERIA */ => 40, '010127' /* CUSTOM FRAME VER.OO */ => 40, '01014C' /* REVIVAL CUIRASS */ => 40,
                    ];
                    break;
            }

    } else if ($category === 'Shield') {
            switch ($charClass) {
                case 'HUmar':
                    $pool = [
                        '01024B' /* ASSIST BARRIER */ => 40, '01021E' /* ATTRIBUTE WALL */ => 40, '01024D' /* BLUE BARRIER */ => 100, '010220' /* COMBAT GEAR */ => 40, '010225' /* CUSTOM BARRIER ver.OO */ => 40, '010226' /* DBS SHIELD */ => 40,
                        '01028F' /* DF SHIELD */ => 10, '010224' /* FLOWENS SHIELD */ => 40, '010215' /* INVISIBLE GUARD */ => 40, '01022B' /* KASAMI BRACER */ => 40, '010219' /* LIGHT RELIEF */ => 40, '010221' /* PROTO REGENE GEAR */ => 40,
                        '01024A' /* RECOVERY BARRIER */ => 100, '01024C' /* RED BARRIER */ => 100, '010227' /* RED RING */ => 10, '010223' /* REGENE GEAR ADV. */ => 40, '010222' /* REGENERATE GEAR */ => 40, '010288' /* REGENERATE GEAR B.P. */ => 40,
                        '010228' /* TRIPOLIC SHIELD */ => 40, '01028A' /* YATA MIRROR */ => 40, '01024E' /* YELLOW BARRIER */ => 100,
                        '010294' /* ANGEL RING */ => 40, '010291' /* DE ROL LE SHIELD */ => 10, '010293' /* EPSIGUARD */ => 40, '010290' /* FROM THE DEPTHS */ => 40, '0102A4' /* GENPEI */ => 40, '01028E' /* GODS SHIELD KOURYU */ => 10,
                        '01023B' /* ANTI MERGE */ => 40, '01024B' /* ASSIST BARRIER */ => 40, '010242' /* BARTA MERGE */ => 40, '010250' /* BLACK GEAR */ => 40, '010282' /* BLACK RING */ => 40, '010245' /* BLUE MERGE */ => 40, '01025A' /* BLUE RING */ => 40, '01028B' /* BUNNY EARS */ => 40, '01028C' /* CAT EARS */ => 40, '010214' /* CELESTIAL SHIELD */ => 40, '01023D' /* DEBAND MERGE */ => 40, '010211' /* DIVINITY BARRIER */ => 40, '01023E' /* FOIE MERGE */ => 40, '010243' /* GIBARTA MERGE */ => 40, '01023F' /* GIFOIE MERGE */ => 40, '010247' /* GIZONDE MERGE */ => 40, '010262' /* GREEN RING */ => 40, '01021D' /* HUNTER WALL */ => 40, '010230' /* HUNTERS SHELL */ => 40, '010272' /* PURPLE RING */ => 40, '010244' /* RABARTA MERGE */ => 40, '010240' /* RAFOIE MERGE */ => 40, '010252' /* RAGOL RING */ => 40, '010248' /* RAZONDE MERGE */ => 40, '010241' /* RED MERGE */ => 40, '01023A' /* RESTA MERGE */ => 40, '010289' /* RUPIKA */ => 40, '010216' /* SACRED GUARD */ => 40, '01021F' /* SECRET GEAR */ => 40, '01021A' /* SHIELD OF DELSABER */ => 40, '01023C' /* SHIFTA MERGE */ => 40, '010213' /* SPIRITUAL SHIELD */ => 40, '010229' /* STANDSTILL SHIELD */ => 40, '010299' /* STINK SHIELD */ => 40, '010286' /* TRIPOLIC REFLECTOR */ => 40, '010212' /* ULTIMATE SHIELD */ => 40, '01027A' /* WHITE RING */ => 40, '010251' /* WORKS GUARD */ => 40, '010249' /* YELLOW MERGE */ => 40, '01026A' /* YELLOW RING */ => 40, '010246' /* ZONDE MERGE */ => 40, '010225' /* CUSTOM BARRIER VER.OO */ => 40, '01024D' /* RECOVERY BARRIER */ => 40,
                    ];
                    break;
                case 'HUnewearl':
                    $pool = [
                        '01024B' /* ASSIST BARRIER */ => 40, '01021E' /* ATTRIBUTE WALL */ => 40, '01024D' /* BLUE BARRIER */ => 100, '010220' /* COMBAT GEAR */ => 40, '010225' /* CUSTOM BARRIER ver.OO */ => 40, '010226' /* DBS SHIELD */ => 40,
                        '01028F' /* DF SHIELD */ => 10, '010224' /* FLOWENS SHIELD */ => 40, '010215' /* INVISIBLE GUARD */ => 40, '01022B' /* KASAMI BRACER */ => 40, '010219' /* LIGHT RELIEF */ => 40, '010221' /* PROTO REGENE GEAR */ => 40,
                        '01024A' /* RECOVERY BARRIER */ => 100, '01024C' /* RED BARRIER */ => 100, '010227' /* RED RING */ => 10, '010223' /* REGENE GEAR ADV. */ => 40, '010222' /* REGENERATE GEAR */ => 40, '010288' /* REGENERATE GEAR B.P. */ => 40,
                        '010286' /* TRIPOLIC REFLECTOR */ => 40, '010228' /* TRIPOLIC SHIELD */ => 40, '01028A' /* YATA MIRROR */ => 40, '01024E' /* YELLOW BARRIER */ => 100,
                        '010294' /* ANGEL RING */ => 40, '01028B' /* BUNNY EARS */ => 40, '01028C' /* CAT EARS */ => 40, '010291' /* DE ROL LE SHIELD */ => 10, '010293' /* EPSIGUARD */ => 40, '010290' /* FROM THE DEPTHS */ => 40, '0102A4' /* GENPEI */ => 40, '01028E' /* GODS SHIELD KOURYU */ => 10,
                        '01023B' /* ANTI MERGE */ => 40, '01024B' /* ASSIST BARRIER */ => 40, '010242' /* BARTA MERGE */ => 40, '010250' /* BLACK GEAR */ => 40, '010282' /* BLACK RING */ => 40, '010245' /* BLUE MERGE */ => 40, '01025A' /* BLUE RING */ => 40, '010214' /* CELESTIAL SHIELD */ => 40, '01023D' /* DEBAND MERGE */ => 40, '010211' /* DIVINITY BARRIER */ => 40, '01023E' /* FOIE MERGE */ => 40, '010243' /* GIBARTA MERGE */ => 40, '01023F' /* GIFOIE MERGE */ => 40, '010247' /* GIZONDE MERGE */ => 40, '010262' /* GREEN RING */ => 40, '01021D' /* HUNTER WALL */ => 40, '010272' /* PURPLE RING */ => 40, '010244' /* RABARTA MERGE */ => 40, '010240' /* RAFOIE MERGE */ => 40, '010252' /* RAGOL RING */ => 40, '010248' /* RAZONDE MERGE */ => 40, '010241' /* RED MERGE */ => 40, '01023A' /* RESTA MERGE */ => 40, '010232' /* RICOS EARRING */ => 40, '010289' /* RUPIKA */ => 40, '010216' /* SACRED GUARD */ => 40, '01022A' /* SAFETY HEART */ => 40, '01021F' /* SECRET GEAR */ => 40, '01021A' /* SHIELD OF DELSABER */ => 40, '01023C' /* SHIFTA MERGE */ => 40, '010213' /* SPIRITUAL SHIELD */ => 40, '010229' /* STANDSTILL SHIELD */ => 40, '010299' /* STINK SHIELD */ => 40, '010212' /* ULTIMATE SHIELD */ => 40, '01027A' /* WHITE RING */ => 40, '010251' /* WORKS GUARD */ => 40, '010249' /* YELLOW MERGE */ => 40, '01026A' /* YELLOW RING */ => 40, '010246' /* ZONDE MERGE */ => 40, '010225' /* CUSTOM BARRIER VER.OO */ => 40, '01024D' /* RECOVERY BARRIER */ => 40,
                    ];
                    break;
                case 'HUcast':
                    $pool = [
                        '01024B' /* ASSIST BARRIER */ => 40, '01021E' /* ATTRIBUTE WALL */ => 40, '01024D' /* BLUE BARRIER */ => 100, '010220' /* COMBAT GEAR */ => 40, '010225' /* CUSTOM BARRIER ver.OO */ => 40, '010226' /* DBS SHIELD */ => 40,
                        '01028F' /* DF SHIELD */ => 10, '010224' /* FLOWENS SHIELD */ => 40, '010285' /* GRATIA */ => 40, '010215' /* INVISIBLE GUARD */ => 40, '01022B' /* KASAMI BRACER */ => 40, '010219' /* LIGHT RELIEF */ => 40,
                        '010221' /* PROTO REGENE GEAR */ => 40, '01024A' /* RECOVERY BARRIER */ => 100, '01024C' /* RED BARRIER */ => 100, '010227' /* RED RING */ => 10, '010223' /* REGENE GEAR ADV. */ => 40, '010222' /* REGENERATE GEAR */ => 40,
                        '010229' /* STANDSTILL SHIELD */ => 40, '010299' /* STINK SHIELD */ => 100, '010286' /* TRIPOLIC REFLECTOR */ => 40, '010228' /* TRIPOLIC SHIELD */ => 40, '01028A' /* YATA MIRROR */ => 40, '01024E' /* YELLOW BARRIER */ => 100,
                        '010294' /* ANGEL RING */ => 40, '010291' /* DE ROL LE SHIELD */ => 10, '010293' /* EPSIGUARD */ => 40, '010290' /* FROM THE DEPTHS */ => 40, '0102A4' /* GENPEI */ => 40, '01028E' /* GODS SHIELD KOURYU */ => 10,
                        '01023B' /* ANTI MERGE */ => 40, '01024B' /* ASSIST BARRIER */ => 40, '010242' /* BARTA MERGE */ => 40, '010250' /* BLACK GEAR */ => 40, '010282' /* BLACK RING */ => 40, '010245' /* BLUE MERGE */ => 40, '01025A' /* BLUE RING */ => 40, '01028B' /* BUNNY EARS */ => 40, '01028C' /* CAT EARS */ => 40, '010214' /* CELESTIAL SHIELD */ => 40, '01023D' /* DEBAND MERGE */ => 40, '010211' /* DIVINITY BARRIER */ => 40, '01023E' /* FOIE MERGE */ => 40, '010243' /* GIBARTA MERGE */ => 40, '01023F' /* GIFOIE MERGE */ => 40, '010247' /* GIZONDE MERGE */ => 40, '010262' /* GREEN RING */ => 40, '01021D' /* HUNTER WALL */ => 40, '010272' /* PURPLE RING */ => 40, '010244' /* RABARTA MERGE */ => 40, '010240' /* RAFOIE MERGE */ => 40, '010252' /* RAGOL RING */ => 40, '010248' /* RAZONDE MERGE */ => 40, '010241' /* RED MERGE */ => 40, '010288' /* REGENERATE GEAR B.P. */ => 40, '01023A' /* RESTA MERGE */ => 40, '010289' /* RUPIKA */ => 40, '010217' /* S-PARTS VER1.16 */ => 40, '010218' /* S-PARTS VER2.01 */ => 40, '010216' /* SACRED GUARD */ => 40, '01021F' /* SECRET GEAR */ => 40, '01021A' /* SHIELD OF DELSABER */ => 40, '01023C' /* SHIFTA MERGE */ => 40, '010213' /* SPIRITUAL SHIELD */ => 40, '010212' /* ULTIMATE SHIELD */ => 40, '01027A' /* WHITE RING */ => 40, '010251' /* WORKS GUARD */ => 40, '010249' /* YELLOW MERGE */ => 40, '01026A' /* YELLOW RING */ => 40, '010246' /* ZONDE MERGE */ => 40, '010225' /* CUSTOM BARRIER VER.OO */ => 40, '01024D' /* RECOVERY BARRIER */ => 40,
                    ];
                    break;
                case 'HUcaseal':
                    $pool = [
                        '01024B' /* ASSIST BARRIER */ => 40, '01021E' /* ATTRIBUTE WALL */ => 40, '01024D' /* BLUE BARRIER */ => 100, '010220' /* COMBAT GEAR */ => 40, '010225' /* CUSTOM BARRIER ver.OO */ => 40, '010226' /* DBS SHIELD */ => 40,
                        '01028F' /* DF SHIELD */ => 10, '010224' /* FLOWENS SHIELD */ => 40, '010285' /* GRATIA */ => 40, '010215' /* INVISIBLE GUARD */ => 40, '01022B' /* KASAMI BRACER */ => 40, '010219' /* LIGHT RELIEF */ => 40,
                        '010221' /* PROTO REGENE GEAR */ => 40, '01024A' /* RECOVERY BARRIER */ => 100, '01024C' /* RED BARRIER */ => 100, '010227' /* RED RING */ => 10, '010223' /* REGENE GEAR ADV. */ => 40, '010222' /* REGENERATE GEAR */ => 40,
                        '010288' /* REGENERATE GEAR B.P. */ => 40, '010232' /* RICOS EARRING */ => 10, '010289' /* RUPIKA */ => 10, '010217' /* S-PARTS ver1.16 */ => 40, '010218' /* S-PARTS ver2.01 */ => 40, '010216' /* SACRED GUARD */ => 40,
                        '01024E' /* YELLOW BARRIER */ => 100,
                        '010294' /* ANGEL RING */ => 40, '01028B' /* BUNNY EARS */ => 40, '01028C' /* CAT EARS */ => 40, '010291' /* DE ROL LE SHIELD */ => 10, '010293' /* EPSIGUARD */ => 40, '010290' /* FROM THE DEPTHS */ => 40, '0102A4' /* GENPEI */ => 40, '01028E' /* GODS SHIELD KOURYU */ => 10,
                        '01023B' /* ANTI MERGE */ => 40, '01024B' /* ASSIST BARRIER */ => 40, '010242' /* BARTA MERGE */ => 40, '010250' /* BLACK GEAR */ => 40, '010282' /* BLACK RING */ => 40, '010245' /* BLUE MERGE */ => 40, '01025A' /* BLUE RING */ => 40, '010214' /* CELESTIAL SHIELD */ => 40, '01023D' /* DEBAND MERGE */ => 40, '010211' /* DIVINITY BARRIER */ => 40, '01023E' /* FOIE MERGE */ => 40, '010243' /* GIBARTA MERGE */ => 40, '01023F' /* GIFOIE MERGE */ => 40, '010247' /* GIZONDE MERGE */ => 40, '010262' /* GREEN RING */ => 40, '01021D' /* HUNTER WALL */ => 40, '010272' /* PURPLE RING */ => 40, '010244' /* RABARTA MERGE */ => 40, '010240' /* RAFOIE MERGE */ => 40, '010252' /* RAGOL RING */ => 40, '010248' /* RAZONDE MERGE */ => 40, '010241' /* RED MERGE */ => 40, '01023A' /* RESTA MERGE */ => 40, '01022A' /* SAFETY HEART */ => 40, '01021F' /* SECRET GEAR */ => 40, '01021A' /* SHIELD OF DELSABER */ => 40, '01023C' /* SHIFTA MERGE */ => 40, '010213' /* SPIRITUAL SHIELD */ => 40, '010229' /* STANDSTILL SHIELD */ => 40, '010299' /* STINK SHIELD */ => 40, '010286' /* TRIPOLIC REFLECTOR */ => 40, '010228' /* TRIPOLIC SHIELD */ => 40, '010212' /* ULTIMATE SHIELD */ => 40, '01027A' /* WHITE RING */ => 40, '010251' /* WORKS GUARD */ => 40, '01028A' /* YATA MIRROR */ => 40, '010249' /* YELLOW MERGE */ => 40, '01026A' /* YELLOW RING */ => 40, '010246' /* ZONDE MERGE */ => 40, '010225' /* CUSTOM BARRIER VER.OO */ => 40, '01024D' /* RECOVERY BARRIER */ => 40,
                    ];
                    break;
                case 'RAmar':
                    $pool = [
                        '01021E' /* ATTRIBUTE WALL */ => 40, '01024D' /* BLUE BARRIER */ => 100, '010220' /* COMBAT GEAR */ => 40, '010225' /* CUSTOM BARRIER ver.OO */ => 40, '010226' /* DBS SHIELD */ => 40, '01028F' /* DF SHIELD */ => 10,
                        '010224' /* FLOWENS SHIELD */ => 40, '010215' /* INVISIBLE GUARD */ => 40, '010219' /* LIGHT RELIEF */ => 40, '010221' /* PROTO REGENE GEAR */ => 40, '01024A' /* RECOVERY BARRIER */ => 100, '01024C' /* RED BARRIER */ => 100,
                        '010227' /* RED RING */ => 10, '010223' /* REGENE GEAR ADV. */ => 40, '010222' /* REGENERATE GEAR */ => 40, '010288' /* REGENERATE GEAR B.P. */ => 40, '010289' /* RUPIKA */ => 10, '010216' /* SACRED GUARD */ => 40,
                        '010228' /* TRIPOLIC SHIELD */ => 40, '01028A' /* YATA MIRROR */ => 40, '01024E' /* YELLOW BARRIER */ => 100,
                        '010294' /* ANGEL RING */ => 40, '010291' /* DE ROL LE SHIELD */ => 10, '010293' /* EPSIGUARD */ => 40, '010290' /* FROM THE DEPTHS */ => 40, '0102A4' /* GENPEI */ => 40, '01028E' /* GODS SHIELD KOURYU */ => 10,
                        '01023B' /* ANTI MERGE */ => 40, '01024B' /* ASSIST BARRIER */ => 40, '010242' /* BARTA MERGE */ => 40, '010250' /* BLACK GEAR */ => 40, '010282' /* BLACK RING */ => 40, '010245' /* BLUE MERGE */ => 40, '01025A' /* BLUE RING */ => 40, '01028B' /* BUNNY EARS */ => 40, '01028C' /* CAT EARS */ => 40, '010214' /* CELESTIAL SHIELD */ => 40, '01023D' /* DEBAND MERGE */ => 40, '010211' /* DIVINITY BARRIER */ => 40, '01023E' /* FOIE MERGE */ => 40, '010243' /* GIBARTA MERGE */ => 40, '01023F' /* GIFOIE MERGE */ => 40, '010247' /* GIZONDE MERGE */ => 40, '010262' /* GREEN RING */ => 40, '010272' /* PURPLE RING */ => 40, '010244' /* RABARTA MERGE */ => 40, '010240' /* RAFOIE MERGE */ => 40, '010252' /* RAGOL RING */ => 40, '01021C' /* RANGER WALL */ => 40, '010248' /* RAZONDE MERGE */ => 40, '010241' /* RED MERGE */ => 40, '01023A' /* RESTA MERGE */ => 40, '01021F' /* SECRET GEAR */ => 40, '010235' /* SECURE FEET */ => 40, '01021A' /* SHIELD OF DELSABER */ => 40, '01023C' /* SHIFTA MERGE */ => 40, '010213' /* SPIRITUAL SHIELD */ => 40, '010229' /* STANDSTILL SHIELD */ => 40, '010299' /* STINK SHIELD */ => 40, '010287' /* STRIKER PLUS */ => 40, '010286' /* TRIPOLIC REFLECTOR */ => 40, '010212' /* ULTIMATE SHIELD */ => 40, '01027A' /* WHITE RING */ => 40, '010251' /* WORKS GUARD */ => 40, '010249' /* YELLOW MERGE */ => 40, '01026A' /* YELLOW RING */ => 40, '010246' /* ZONDE MERGE */ => 40, '010225' /* CUSTOM BARRIER VER.OO */ => 40, '01024D' /* RECOVERY BARRIER */ => 40,
                    ];
                    break;
                case 'RAmarl':
                    $pool = [
                        '01021E' /* ATTRIBUTE WALL */ => 40, '01024D' /* BLUE BARRIER */ => 100, '010220' /* COMBAT GEAR */ => 40, '010225' /* CUSTOM BARRIER ver.OO */ => 40, '010226' /* DBS SHIELD */ => 40, '01028F' /* DF SHIELD */ => 10,
                        '010224' /* FLOWENS SHIELD */ => 40, '010215' /* INVISIBLE GUARD */ => 40, '010219' /* LIGHT RELIEF */ => 40, '010221' /* PROTO REGENE GEAR */ => 40, '01024A' /* RECOVERY BARRIER */ => 100, '01024C' /* RED BARRIER */ => 100,
                        '010227' /* RED RING */ => 10, '010223' /* REGENE GEAR ADV. */ => 40, '010222' /* REGENERATE GEAR */ => 40, '010288' /* REGENERATE GEAR B.P. */ => 40, '010232' /* RICOS EARRING */ => 10, '010289' /* RUPIKA */ => 10,
                        '010286' /* TRIPOLIC REFLECTOR */ => 40, '010228' /* TRIPOLIC SHIELD */ => 40, '01028A' /* YATA MIRROR */ => 40, '01024E' /* YELLOW BARRIER */ => 100,
                        '010294' /* ANGEL RING */ => 40, '01028B' /* BUNNY EARS */ => 40, '01028C' /* CAT EARS */ => 40, '010291' /* DE ROL LE SHIELD */ => 10, '010293' /* EPSIGUARD */ => 40, '010290' /* FROM THE DEPTHS */ => 40, '0102A4' /* GENPEI */ => 40, '01028E' /* GODS SHIELD KOURYU */ => 10,
                        '01023B' /* ANTI MERGE */ => 40, '01024B' /* ASSIST BARRIER */ => 40, '010242' /* BARTA MERGE */ => 40, '010250' /* BLACK GEAR */ => 40, '010282' /* BLACK RING */ => 40, '010245' /* BLUE MERGE */ => 40, '01025A' /* BLUE RING */ => 40, '010214' /* CELESTIAL SHIELD */ => 40, '01023D' /* DEBAND MERGE */ => 40, '010211' /* DIVINITY BARRIER */ => 40, '01023E' /* FOIE MERGE */ => 40, '010243' /* GIBARTA MERGE */ => 40, '01023F' /* GIFOIE MERGE */ => 40, '010247' /* GIZONDE MERGE */ => 40, '010262' /* GREEN RING */ => 40, '010272' /* PURPLE RING */ => 40, '010244' /* RABARTA MERGE */ => 40, '010240' /* RAFOIE MERGE */ => 40, '010252' /* RAGOL RING */ => 40, '01021C' /* RANGER WALL */ => 40, '010248' /* RAZONDE MERGE */ => 40, '010241' /* RED MERGE */ => 40, '01023A' /* RESTA MERGE */ => 40, '010216' /* SACRED GUARD */ => 40, '01022A' /* SAFETY HEART */ => 40, '01021F' /* SECRET GEAR */ => 40, '010235' /* SECURE FEET */ => 40, '01021A' /* SHIELD OF DELSABER */ => 40, '01023C' /* SHIFTA MERGE */ => 40, '010213' /* SPIRITUAL SHIELD */ => 40, '010229' /* STANDSTILL SHIELD */ => 40, '010299' /* STINK SHIELD */ => 40, '010287' /* STRIKER PLUS */ => 40, '010212' /* ULTIMATE SHIELD */ => 40, '01027A' /* WHITE RING */ => 40, '010251' /* WORKS GUARD */ => 40, '010249' /* YELLOW MERGE */ => 40, '01026A' /* YELLOW RING */ => 40, '010246' /* ZONDE MERGE */ => 40, '010225' /* CUSTOM BARRIER VER.OO */ => 40, '01024D' /* RECOVERY BARRIER */ => 40,
                    ];
                    break;
                case 'RAcast':
                    $pool = [
                        '01021E' /* ATTRIBUTE WALL */ => 40, '01024D' /* BLUE BARRIER */ => 100, '010220' /* COMBAT GEAR */ => 40, '010225' /* CUSTOM BARRIER ver.OO */ => 40, '010226' /* DBS SHIELD */ => 40, '01028F' /* DF SHIELD */ => 10,
                        '010224' /* FLOWENS SHIELD */ => 40, '010285' /* GRATIA */ => 40, '010215' /* INVISIBLE GUARD */ => 40, '010219' /* LIGHT RELIEF */ => 40, '010221' /* PROTO REGENE GEAR */ => 40, '01024A' /* RECOVERY BARRIER */ => 100,
                        '01024C' /* RED BARRIER */ => 100, '010227' /* RED RING */ => 10, '010223' /* REGENE GEAR ADV. */ => 40, '010222' /* REGENERATE GEAR */ => 40, '010288' /* REGENERATE GEAR B.P. */ => 40, '010289' /* RUPIKA */ => 10,
                        '010287' /* STRIKER PLUS */ => 10, '010286' /* TRIPOLIC REFLECTOR */ => 40, '010228' /* TRIPOLIC SHIELD */ => 40, '01028A' /* YATA MIRROR */ => 40, '01024E' /* YELLOW BARRIER */ => 100,
                        '010294' /* ANGEL RING */ => 40, '010291' /* DE ROL LE SHIELD */ => 10, '010293' /* EPSIGUARD */ => 40, '010290' /* FROM THE DEPTHS */ => 40, '0102A4' /* GENPEI */ => 40, '01028E' /* GODS SHIELD KOURYU */ => 10,
                        '01023B' /* ANTI MERGE */ => 40, '01024B' /* ASSIST BARRIER */ => 40, '010242' /* BARTA MERGE */ => 40, '010250' /* BLACK GEAR */ => 40, '010282' /* BLACK RING */ => 40, '010245' /* BLUE MERGE */ => 40, '01025A' /* BLUE RING */ => 40, '01028B' /* BUNNY EARS */ => 40, '01028C' /* CAT EARS */ => 40, '010214' /* CELESTIAL SHIELD */ => 40, '01023D' /* DEBAND MERGE */ => 40, '010211' /* DIVINITY BARRIER */ => 40, '01023E' /* FOIE MERGE */ => 40, '010243' /* GIBARTA MERGE */ => 40, '01023F' /* GIFOIE MERGE */ => 40, '010247' /* GIZONDE MERGE */ => 40, '010262' /* GREEN RING */ => 40, '010272' /* PURPLE RING */ => 40, '010244' /* RABARTA MERGE */ => 40, '010240' /* RAFOIE MERGE */ => 40, '010252' /* RAGOL RING */ => 40, '01021C' /* RANGER WALL */ => 40, '010248' /* RAZONDE MERGE */ => 40, '010241' /* RED MERGE */ => 40, '01023A' /* RESTA MERGE */ => 40, '010217' /* S-PARTS VER1.16 */ => 40, '010218' /* S-PARTS VER2.01 */ => 40, '010216' /* SACRED GUARD */ => 40, '01021F' /* SECRET GEAR */ => 40, '01021A' /* SHIELD OF DELSABER */ => 40, '01023C' /* SHIFTA MERGE */ => 40, '010213' /* SPIRITUAL SHIELD */ => 40, '010229' /* STANDSTILL SHIELD */ => 40, '010299' /* STINK SHIELD */ => 40, '010212' /* ULTIMATE SHIELD */ => 40, '01027A' /* WHITE RING */ => 40, '010251' /* WORKS GUARD */ => 40, '010249' /* YELLOW MERGE */ => 40, '01026A' /* YELLOW RING */ => 40, '010246' /* ZONDE MERGE */ => 40, '010225' /* CUSTOM BARRIER VER.OO */ => 40, '01024D' /* RECOVERY BARRIER */ => 40,
                    ];
                    break;
                case 'RAcaseal':
                    $pool = [
                        '01021E' /* ATTRIBUTE WALL */ => 40, '01024D' /* BLUE BARRIER */ => 100, '010220' /* COMBAT GEAR */ => 40, '010225' /* CUSTOM BARRIER ver.OO */ => 40, '010226' /* DBS SHIELD */ => 40, '01028F' /* DF SHIELD */ => 10,
                        '010224' /* FLOWENS SHIELD */ => 40, '010285' /* GRATIA */ => 40, '010215' /* INVISIBLE GUARD */ => 40, '010219' /* LIGHT RELIEF */ => 40, '010221' /* PROTO REGENE GEAR */ => 40, '01024A' /* RECOVERY BARRIER */ => 100,
                        '01024C' /* RED BARRIER */ => 100, '010227' /* RED RING */ => 10, '010223' /* REGENE GEAR ADV. */ => 40, '010222' /* REGENERATE GEAR */ => 40, '010288' /* REGENERATE GEAR B.P. */ => 40, '010232' /* RICOS EARRING */ => 10,
                        '010299' /* STINK SHIELD */ => 100, '010287' /* STRIKER PLUS */ => 10, '010286' /* TRIPOLIC REFLECTOR */ => 40, '010228' /* TRIPOLIC SHIELD */ => 40, '01028A' /* YATA MIRROR */ => 40, '01024E' /* YELLOW BARRIER */ => 100,
                        '010294' /* ANGEL RING */ => 40, '01028B' /* BUNNY EARS */ => 40, '01028C' /* CAT EARS */ => 40, '010291' /* DE ROL LE SHIELD */ => 10, '010293' /* EPSIGUARD */ => 40, '010290' /* FROM THE DEPTHS */ => 40, '0102A4' /* GENPEI */ => 40, '01028E' /* GODS SHIELD KOURYU */ => 10,
                        '01023B' /* ANTI MERGE */ => 40, '01024B' /* ASSIST BARRIER */ => 40, '010242' /* BARTA MERGE */ => 40, '010250' /* BLACK GEAR */ => 40, '010282' /* BLACK RING */ => 40, '010245' /* BLUE MERGE */ => 40, '01025A' /* BLUE RING */ => 40, '010214' /* CELESTIAL SHIELD */ => 40, '01023D' /* DEBAND MERGE */ => 40, '010211' /* DIVINITY BARRIER */ => 40, '01023E' /* FOIE MERGE */ => 40, '010243' /* GIBARTA MERGE */ => 40, '01023F' /* GIFOIE MERGE */ => 40, '010247' /* GIZONDE MERGE */ => 40, '010262' /* GREEN RING */ => 40, '010272' /* PURPLE RING */ => 40, '010244' /* RABARTA MERGE */ => 40, '010240' /* RAFOIE MERGE */ => 40, '010252' /* RAGOL RING */ => 40, '01021C' /* RANGER WALL */ => 40, '010248' /* RAZONDE MERGE */ => 40, '010241' /* RED MERGE */ => 40, '01023A' /* RESTA MERGE */ => 40, '010289' /* RUPIKA */ => 40, '010217' /* S-PARTS VER1.16 */ => 40, '010218' /* S-PARTS VER2.01 */ => 40, '010216' /* SACRED GUARD */ => 40, '01022A' /* SAFETY HEART */ => 40, '01021F' /* SECRET GEAR */ => 40, '01021A' /* SHIELD OF DELSABER */ => 40, '01023C' /* SHIFTA MERGE */ => 40, '010213' /* SPIRITUAL SHIELD */ => 40, '010229' /* STANDSTILL SHIELD */ => 40, '010212' /* ULTIMATE SHIELD */ => 40, '01027A' /* WHITE RING */ => 40, '010251' /* WORKS GUARD */ => 40, '010249' /* YELLOW MERGE */ => 40, '01026A' /* YELLOW RING */ => 40, '010246' /* ZONDE MERGE */ => 40, '010225' /* CUSTOM BARRIER VER.OO */ => 40, '01024D' /* RECOVERY BARRIER */ => 40,
                    ];
                    break;
                case 'FOmar':
                    $pool = [
                        '01021E' /* ATTRIBUTE WALL */ => 40, '01024D' /* BLUE BARRIER */ => 100, '010220' /* COMBAT GEAR */ => 40, '010225' /* CUSTOM BARRIER ver.OO */ => 40, '01028F' /* DF SHIELD */ => 10, '010215' /* INVISIBLE GUARD */ => 40,
                        '010219' /* LIGHT RELIEF */ => 40, '010221' /* PROTO REGENE GEAR */ => 40, '01024A' /* RECOVERY BARRIER */ => 100, '01024C' /* RED BARRIER */ => 100, '010227' /* RED RING */ => 10, '010223' /* REGENE GEAR ADV. */ => 40,
                        '010286' /* TRIPOLIC REFLECTOR */ => 40, '010228' /* TRIPOLIC SHIELD */ => 40, '01024E' /* YELLOW BARRIER */ => 100,
                        '010294' /* ANGEL RING */ => 40, '010291' /* DE ROL LE SHIELD */ => 10, '010293' /* EPSIGUARD */ => 40, '010290' /* FROM THE DEPTHS */ => 40, '0102A4' /* GENPEI */ => 40, '01028E' /* GODS SHIELD KOURYU */ => 10, '01028D' /* THREE SEALS */ => 10,
                        '01023B' /* ANTI MERGE */ => 40, '01024B' /* ASSIST BARRIER */ => 40, '010242' /* BARTA MERGE */ => 40, '010250' /* BLACK GEAR */ => 40, '010282' /* BLACK RING */ => 40, '010245' /* BLUE MERGE */ => 40, '01025A' /* BLUE RING */ => 40, '01028B' /* BUNNY EARS */ => 40, '01028C' /* CAT EARS */ => 40, '01023D' /* DEBAND MERGE */ => 40, '010211' /* DIVINITY BARRIER */ => 40, '01023E' /* FOIE MERGE */ => 40, '01021B' /* FORCE WALL */ => 40, '010243' /* GIBARTA MERGE */ => 40, '01023F' /* GIFOIE MERGE */ => 40, '010247' /* GIZONDE MERGE */ => 40, '010262' /* GREEN RING */ => 40, '010272' /* PURPLE RING */ => 40, '010244' /* RABARTA MERGE */ => 40, '010240' /* RAFOIE MERGE */ => 40, '010252' /* RAGOL RING */ => 40, '010248' /* RAZONDE MERGE */ => 40, '010241' /* RED MERGE */ => 40, '010222' /* REGENERATE GEAR */ => 40, '010288' /* REGENERATE GEAR B.P. */ => 40, '01023A' /* RESTA MERGE */ => 40, '010289' /* RUPIKA */ => 40, '010216' /* SACRED GUARD */ => 40, '01021F' /* SECRET GEAR */ => 40, '01023C' /* SHIFTA MERGE */ => 40, '010299' /* STINK SHIELD */ => 40, '01027A' /* WHITE RING */ => 40, '010251' /* WORKS GUARD */ => 40, '010249' /* YELLOW MERGE */ => 40, '01026A' /* YELLOW RING */ => 40, '010246' /* ZONDE MERGE */ => 40, '010225' /* CUSTOM BARRIER VER.OO */ => 40, '01024D' /* RECOVERY BARRIER */ => 40,
                    ];
                    break;
                case 'FOmarl':
                    $pool = [
                        '01021E' /* ATTRIBUTE WALL */ => 40, '01024D' /* BLUE BARRIER */ => 100, '010220' /* COMBAT GEAR */ => 40, '010225' /* CUSTOM BARRIER ver.OO */ => 40, '01028F' /* DF SHIELD */ => 10, '010215' /* INVISIBLE GUARD */ => 40,
                        '010219' /* LIGHT RELIEF */ => 40, '010221' /* PROTO REGENE GEAR */ => 40, '01024A' /* RECOVERY BARRIER */ => 100, '01024C' /* RED BARRIER */ => 100, '010227' /* RED RING */ => 10, '010223' /* REGENE GEAR ADV. */ => 40,
                        '01021F' /* SECRET GEAR */ => 40, '010299' /* STINK SHIELD */ => 100, '010286' /* TRIPOLIC REFLECTOR */ => 40, '010228' /* TRIPOLIC SHIELD */ => 40, '01024E' /* YELLOW BARRIER */ => 100,
                        '010294' /* ANGEL RING */ => 40, '01028B' /* BUNNY EARS */ => 40, '01028C' /* CAT EARS */ => 40, '010291' /* DE ROL LE SHIELD */ => 10, '010293' /* EPSIGUARD */ => 40, '010290' /* FROM THE DEPTHS */ => 40, '0102A4' /* GENPEI */ => 40, '01028E' /* GODS SHIELD KOURYU */ => 10, '01028D' /* THREE SEALS */ => 10,
                        '01023B' /* ANTI MERGE */ => 40, '01024B' /* ASSIST BARRIER */ => 40, '010242' /* BARTA MERGE */ => 40, '010250' /* BLACK GEAR */ => 40, '010282' /* BLACK RING */ => 40, '010245' /* BLUE MERGE */ => 40, '01025A' /* BLUE RING */ => 40, '01023D' /* DEBAND MERGE */ => 40, '010211' /* DIVINITY BARRIER */ => 40, '01023E' /* FOIE MERGE */ => 40, '01021B' /* FORCE WALL */ => 40, '010243' /* GIBARTA MERGE */ => 40, '01023F' /* GIFOIE MERGE */ => 40, '010247' /* GIZONDE MERGE */ => 40, '010262' /* GREEN RING */ => 40, '010272' /* PURPLE RING */ => 40, '010244' /* RABARTA MERGE */ => 40, '010240' /* RAFOIE MERGE */ => 40, '010252' /* RAGOL RING */ => 40, '010248' /* RAZONDE MERGE */ => 40, '010241' /* RED MERGE */ => 40, '010222' /* REGENERATE GEAR */ => 40, '010288' /* REGENERATE GEAR B.P. */ => 40, '01023A' /* RESTA MERGE */ => 40, '010232' /* RICOS EARRING */ => 40, '010231' /* RICOS GLASSES */ => 40, '010289' /* RUPIKA */ => 40, '010216' /* SACRED GUARD */ => 40, '01022A' /* SAFETY HEART */ => 40, '01023C' /* SHIFTA MERGE */ => 40, '01027A' /* WHITE RING */ => 40, '010251' /* WORKS GUARD */ => 40, '010249' /* YELLOW MERGE */ => 40, '01026A' /* YELLOW RING */ => 40, '010246' /* ZONDE MERGE */ => 40, '010225' /* CUSTOM BARRIER VER.OO */ => 40, '01024D' /* RECOVERY BARRIER */ => 40,
                    ];
                    break;
                case 'FOnewm':
                    $pool = [
                        '01021E' /* ATTRIBUTE WALL */ => 40, '01024D' /* BLUE BARRIER */ => 100, '010220' /* COMBAT GEAR */ => 40, '010225' /* CUSTOM BARRIER ver.OO */ => 40, '01028F' /* DF SHIELD */ => 10, '010215' /* INVISIBLE GUARD */ => 40,
                        '010219' /* LIGHT RELIEF */ => 40, '010221' /* PROTO REGENE GEAR */ => 40, '01024A' /* RECOVERY BARRIER */ => 100, '01024C' /* RED BARRIER */ => 100, '010227' /* RED RING */ => 10, '010223' /* REGENE GEAR ADV. */ => 40,
                        '010286' /* TRIPOLIC REFLECTOR */ => 40, '010228' /* TRIPOLIC SHIELD */ => 40, '01024E' /* YELLOW BARRIER */ => 100,
                        '010294' /* ANGEL RING */ => 40, '010291' /* DE ROL LE SHIELD */ => 10, '010293' /* EPSIGUARD */ => 40, '010290' /* FROM THE DEPTHS */ => 40, '0102A4' /* GENPEI */ => 40, '01028E' /* GODS SHIELD KOURYU */ => 10, '01028D' /* THREE SEALS */ => 10,
                        '01023B' /* ANTI MERGE */ => 40, '01024B' /* ASSIST BARRIER */ => 40, '010242' /* BARTA MERGE */ => 40, '010250' /* BLACK GEAR */ => 40, '010282' /* BLACK RING */ => 40, '010245' /* BLUE MERGE */ => 40, '01025A' /* BLUE RING */ => 40, '01028B' /* BUNNY EARS */ => 40, '01028C' /* CAT EARS */ => 40, '01023D' /* DEBAND MERGE */ => 40, '010211' /* DIVINITY BARRIER */ => 40, '01023E' /* FOIE MERGE */ => 40, '01021B' /* FORCE WALL */ => 40, '010243' /* GIBARTA MERGE */ => 40, '01023F' /* GIFOIE MERGE */ => 40, '010247' /* GIZONDE MERGE */ => 40, '010262' /* GREEN RING */ => 40, '010272' /* PURPLE RING */ => 40, '010244' /* RABARTA MERGE */ => 40, '010240' /* RAFOIE MERGE */ => 40, '010252' /* RAGOL RING */ => 40, '010248' /* RAZONDE MERGE */ => 40, '010241' /* RED MERGE */ => 40, '010222' /* REGENERATE GEAR */ => 40, '010288' /* REGENERATE GEAR B.P. */ => 40, '01023A' /* RESTA MERGE */ => 40, '010289' /* RUPIKA */ => 40, '010216' /* SACRED GUARD */ => 40, '01021F' /* SECRET GEAR */ => 40, '01023C' /* SHIFTA MERGE */ => 40, '010299' /* STINK SHIELD */ => 40, '01027A' /* WHITE RING */ => 40, '010251' /* WORKS GUARD */ => 40, '010249' /* YELLOW MERGE */ => 40, '01026A' /* YELLOW RING */ => 40, '010246' /* ZONDE MERGE */ => 40, '010225' /* CUSTOM BARRIER VER.OO */ => 40, '01024D' /* RECOVERY BARRIER */ => 40,
                    ];
                    break;
                case 'FOnewearl':
                    $pool = [
                        '01021E' /* ATTRIBUTE WALL */ => 40, '01024D' /* BLUE BARRIER */ => 100, '010220' /* COMBAT GEAR */ => 40, '010225' /* CUSTOM BARRIER ver.OO */ => 40, '01028F' /* DF SHIELD */ => 10, '010215' /* INVISIBLE GUARD */ => 40,
                        '010219' /* LIGHT RELIEF */ => 40, '010221' /* PROTO REGENE GEAR */ => 40, '01024A' /* RECOVERY BARRIER */ => 100, '01024C' /* RED BARRIER */ => 100, '010227' /* RED RING */ => 10, '010223' /* REGENE GEAR ADV. */ => 40,
                        '010299' /* STINK SHIELD */ => 100, '010286' /* TRIPOLIC REFLECTOR */ => 40, '010228' /* TRIPOLIC SHIELD */ => 40, '01024E' /* YELLOW BARRIER */ => 100,
                        '010294' /* ANGEL RING */ => 40, '01028B' /* BUNNY EARS */ => 40, '01028C' /* CAT EARS */ => 40, '010291' /* DE ROL LE SHIELD */ => 10, '010293' /* EPSIGUARD */ => 40, '010290' /* FROM THE DEPTHS */ => 40, '0102A4' /* GENPEI */ => 40, '01028E' /* GODS SHIELD KOURYU */ => 10, '01028D' /* THREE SEALS */ => 10,
                        '01023B' /* ANTI MERGE */ => 40, '01024B' /* ASSIST BARRIER */ => 40, '010242' /* BARTA MERGE */ => 40, '010250' /* BLACK GEAR */ => 40, '010282' /* BLACK RING */ => 40, '010245' /* BLUE MERGE */ => 40, '01025A' /* BLUE RING */ => 40, '01023D' /* DEBAND MERGE */ => 40, '010211' /* DIVINITY BARRIER */ => 40, '01023E' /* FOIE MERGE */ => 40, '01021B' /* FORCE WALL */ => 40, '010243' /* GIBARTA MERGE */ => 40, '01023F' /* GIFOIE MERGE */ => 40, '010247' /* GIZONDE MERGE */ => 40, '010262' /* GREEN RING */ => 40, '010272' /* PURPLE RING */ => 40, '010244' /* RABARTA MERGE */ => 40, '010240' /* RAFOIE MERGE */ => 40, '010252' /* RAGOL RING */ => 40, '010248' /* RAZONDE MERGE */ => 40, '010241' /* RED MERGE */ => 40, '010222' /* REGENERATE GEAR */ => 40, '010288' /* REGENERATE GEAR B.P. */ => 40, '01023A' /* RESTA MERGE */ => 40, '010232' /* RICOS EARRING */ => 40, '010289' /* RUPIKA */ => 40, '010216' /* SACRED GUARD */ => 40, '01022A' /* SAFETY HEART */ => 40, '01021F' /* SECRET GEAR */ => 40, '01023C' /* SHIFTA MERGE */ => 40, '01027A' /* WHITE RING */ => 40, '010251' /* WORKS GUARD */ => 40, '010249' /* YELLOW MERGE */ => 40, '01026A' /* YELLOW RING */ => 40, '010246' /* ZONDE MERGE */ => 40, '010225' /* CUSTOM BARRIER VER.OO */ => 40, '01024D' /* RECOVERY BARRIER */ => 40,
                    ];
                    break;
            }

    } else if ($category === 'Unit') {
        if ($tier <= 2) {
            $pool = [
                '010330' /* All/Resist */ => 40, '010310' /* Digger/HP */ => 40, '01030A' /* Elf/Arm */ => 40, '01030E' /* Elf/Legs */ => 40, 
                '010309' /* General/Arm */ => 40, '010319' /* General/Body */ => 40, '010311' /* General/HP */ => 40, '01030D' /* General/Legs */ => 40, 
                '010305' /* General/Mind */ => 40, '010301' /* General/Power */ => 40, '010300' /* Knight/Power */ => 40, '010308' /* Marksman/Arm */ => 40, 
                '01031A' /* Metal/Body */ => 40, '010302' /* Ogre/Power */ => 40, '010304' /* Priest/Mind */ => 40, '01030C' /* Thief/Legs */ => 40, 
                '010318' /* Warrior/Body */ => 40, '01033C' /* Wizard/Technique */ => 40, '01033F' /* General/Battle */ => 10
            ];
        } else if ($tier <= 5) {
            $pool = [
                '01031C' /* Angel/Luck */ => 40, '010306' /* Angel/Mind */ => 40, '010312' /* Dragon/HP */ => 40, '010334' /* HP/Generate */ => 40, 
                '010333' /* HP/Restorate */ => 40, '010339' /* PB/Amplifier */ => 40, '01033A' /* PB/Generate */ => 40, '010337' /* TP/Generate */ => 40, 
                '010336' /* TP/Restorate */ => 40, '010324' /* Resist/Cold */ => 40, '01032D' /* Resist/Dark */ => 40, '01032E' /* Resist/Evil */ => 40, 
                '010321' /* Resist/Fire */ => 40, '010322' /* Resist/Flame */ => 40, '010325' /* Resist/Freeze */ => 40, '01032A' /* Resist/Light */ => 40, 
                '01032B' /* Resist/Saint */ => 40, '010327' /* Resist/Shock */ => 40, '010328' /* Resist/Thunder */ => 40
            ];
        } else if ($tier <= 8) {
            $pool = [
                '01031E' /* Master/Ability */ => 40, '010331' /* Super/Resist */ => 40, '010343' /* Trap/Search */ => 40, 
                '010347' /* Cure/Shock */ => 20, '010346' /* Cure/Freeze */ => 20, '010343' /* Cure/Paralysis */ => 20, '010345' /* Cure/Confuse */ => 20,
                '010341' /* God/Battle */ => 10, '010320' /* God/Ability */ => 10, '010303' /* God/Power */ => 20, '010307' /* God/Mind */ => 20
            ];
        } else {
            $pool = [
                '010353' /* Heavenly/Battle */ => 40, '01035A' /* Heavenly/Ability */ => 40,
                '010354' /* Heavenly/Power */ => 60, '010355' /* Heavenly/Mind */ => 60, '010356' /* Heavenly/Arms */ => 60,
                '010349' /* V101 */ => 15, '01034B' /* V502 */ => 15, '01034C' /* V801 */ => 15, '01034D' /* LIMITER */ => 5, '01034E' /* ADEPT */ => 5,
                '01034F' /* SWORDSMAN LORE */ => 10, '010350' /* PROOF OF SWORD-SAINT */ => 10, '010351' /* SMARTLINK */ => 20,
                '010352' /* DIVINE PROTECTION */ => 10, '01035B' /* Centurion/Ability */ => 10
            ];
        }

    } else if ($category === 'Random') {
        if ($tier <= 2) {
            $pool = [
                '030A00' /* Monogrinder */ => 100, '030A01' /* Digrinder */ => 50, '030B00' /* Power Material */ => 50, '030B01' /* Mind Material */ => 50, '030B02' /* Evade Material */ => 50, '030B04' /* Def Material */ => 50, '010300' /* Knight/Power */ => 40, '010304' /* Priest/Mind */ => 40, '010309' /* General/Arm */ => 40, '010330' /* All/Resist */ => 20
            ];
        } else if ($tier <= 4) {
            $pool = [
                '030A01' /* Digrinder */ => 100, '030A02' /* Trigrinder */ => 50, '030B00' /* Power Material */ => 100, '030B01' /* Mind Material */ => 100, 
                '030B03' /* HP Material */ => 80, '030F00' /* AddSlot */ => 20, 
                '031000' /* Photon Drop */ => 100, '031002' /* Photon Crystal */ => 10, '010301' /* General/Power */ => 50, '010305' /* General/Mind */ => 50,
            ];
        } else if ($tier <= 6) {
            $pool = [
                '030A02' /* Trigrinder */ => 100, '030B00' /* Power Material */ => 100, '030B01' /* Mind Material */ => 100, 
                '030B03' /* HP Material */ => 80, '030B06' /* Luck Material */ => 60, '030F00' /* AddSlot */ => 80, 
                '031000, 031000, 031000' /* Photon Drop x3 */ => 100, '031001' /* Photon Sphere */ => 10, '010347' /* Cure/Shock */ => 40, 
                '010346' /* Cure/Freeze */ => 40, '010343' /* Cure/Paralysis */ => 40, '010345' /* Cure/Confuse */ => 40, 
                '010302' /* Ogre/Power */ => 50, '010310' /* Digger/HP */ => 50,
                '031002' /* Photon Crystal */ => 50, '031805' /* Amities Memo */ => 10, '031806' /* Heart of Morolian */ => 10
            ];
        } else if ($tier <= 8) {
            $pool = [
                '030A02' /* Trigrinder */ => 100, '030B00' /* Power Material */ => 100, '030B01' /* Mind Material */ => 100, 
                '030B03' /* HP Material */ => 80, '030B06' /* Luck Material */ => 60, '030F00' /* AddSlot */ => 80,
                '031001' /* Photon Sphere */ => 50, '010341' /* God/Battle */ => 30, '010320' /* God/Ability */ => 30, '010303' /* God/Power */ => 50, '010307' /* God/Mind */ => 50,
                '01034A' /* V501 */ => 20, '030E03' /* Blue-Black Stone */ => 30, 
                '030E09' /* Star Amplifier */ => 40, '030E04' /* Syncesta */ => 20, '031002' /* Photon Crystal */ => 50, '031809' /* D-Photon Core */ => 10,
                '031804' /* Pioneer Parts */ => 10, '031808' /* Yahoo!s engine */ => 10
            ];
        } else {
            $pool = [
                '030A02' /* Trigrinder */ => 100, '030B06' /* Luck Material */ => 60, '030F00' /* AddSlot */ => 80,
                '031001' /* Photon Sphere */ => 80, '010227' /* Red Ring */ => 2, '030E01' /* Parasitic Gene "Flow" */ => 5, 
                '010353' /* Heavenly/Battle */ => 40, '01035A' /* Heavenly/Ability */ => 40,
                '010354' /* Heavenly/Power */ => 60, '010355' /* Heavenly/Mind */ => 60, '010356' /* Heavenly/Arms */ => 60,
                '010349' /* V101 */ => 15, '01034B' /* V502 */ => 15, '01034C' /* V801 */ => 15, '01034D' /* LIMITER */ => 5, '01034E' /* ADEPT */ => 5,
                '01034F' /* SWORDSMAN LORE */ => 10, '010350' /* PROOF OF SWORD-SAINT */ => 10, '010351' /* SMARTLINK */ => 20,
                '010352' /* DIVINE PROTECTION */ => 10, '01035B' /* Centurion/Ability */ => 10, '030209001D0000000000000000000000' /* Disk:Grants Lv.30 */ => 40, 
                '030212001D0000000000000000000000' /* Disk:Megid Lv.30 */ => 40, '031002' /* Photon Crystal */ => 50, 
                '031803' /* Heaven Striker Coat */ => 10, '03180A' /* Liberta Kit */ => 10, '031800' /* Tablet */ => 10
            ];
        }
    }
    
    if ($category === 'Weapon' || $category === 'Armor' || $category === 'Shield') {
        $pool = filter_rare_pool_by_level($pool, $level_milestone);
    }
    
    $chosen = get_weighted_random($pool);
    
    // If it is an Armor or Frame (Hex starts with 0101 and length is 6)
    if (strpos($chosen, '0101') === 0 && strlen($chosen) === 6) {
        $slots = $options['slots'] ?? mt_rand(3, 4);
        return build_pso_armor($chosen, $slots);
    }
    
    // If it is a Unit (Hex starts with 0103 and length is 6)
    if (strpos($chosen, '0103') === 0 && strlen($chosen) === 6) {
        // Only basic stat units get modifiers. God/Heavenly units shouldn't, but 1-2 doesn't hurt.
        // Grant all units the ++ modifier (2) to ensure maximum stat boosts.
        $modifier = 2;
        return build_pso_armor($chosen, 0, $modifier, 0);
    }
    
    return $chosen;
}

function get_common_reward_item($level_milestone, $charClass, $category, $options = []) {
    // Determine the tier based on milestone (10 tiers up to max level)
    $tier = max(1, min(10, floor($level_milestone / 15) + 1));
    
    // For PSO items, common item types max out at tier 6.
    $item_tier = min(6, $tier);

    $specials = ['Draw', 'Drain', 'Fill', 'Gush', 'Heart', 'Mind', 'Soul', 'Geist', 'Masters', 'Lords', 'Kings', 'Charge', 'Spirit', 'Berserk', 'Ice', 'Frost', 'Freeze', 'Blizzard', 'Bind', 'Hold', 'Seize', 'Arrest', 'Heat', 'Fire', 'Flame', 'Burning', 'Shock', 'Thunder', 'Storm', 'Tempest', 'Dim', 'Shadow', 'Dark', 'Hell', 'Panic', 'Riot', 'Havoc', 'Chaos', 'Devil\'s', 'Demon\'s'];
    
    // Group classes
    $isHunter = in_array($charClass, ['HUmar', 'HUnewearl', 'HUcast', 'HUcaseal']);
    $isRanger = in_array($charClass, ['RAmar', 'RAmarl', 'RAcast', 'RAcaseal']);
    $isForce = !$isHunter && !$isRanger;
    
    if ($category === 'Weapon') {
        $weapons = [];
        if ($isHunter) {
            switch ($item_tier) {
                case 1: $weapons = ['000100' /* Saber */, '000200' /* Sword */, '000300' /* Dagger */, '000400' /* Partisan */, '000500' /* Slicer */, '000600' /* Handgun */]; break;
                case 2: $weapons = ['000101' /* Brand */, '000201' /* Gigush */, '000301' /* Knife */, '000401' /* Halbert */, '000501' /* Spinner */, '000601' /* Autogun */]; break;
                case 3: $weapons = ['000102' /* Buster */, '000202' /* Breaker */, '000302' /* Blade */, '000402' /* Glaive */, '000502' /* Cutter */, '000602' /* Lockgun */]; break;
                case 4: $weapons = ['000103' /* Pallasch */, '000203' /* Claymore */, '000303' /* Edge */, '000403' /* Berdys */, '000503' /* Sawcer */, '000603' /* Railgun */]; break;
                case 5: $weapons = ['000104' /* Gladius */, '000204' /* Calibur */, '000304' /* Ripper */, '000404' /* Gungnir */, '000504' /* Diska */, '000604' /* Raygun */]; break;
                case 6: $weapons = ['000104' /* Gladius */, '000204' /* Calibur */, '000304' /* Ripper */, '000404' /* Gungnir */, '000504' /* Diska */, '000604' /* Raygun */]; break;
            }
        } else if ($isRanger) {
            switch ($item_tier) {
                case 1: $weapons = ['000700' /* Rifle */, '000900' /* Shot */, '000800' /* Mechgun */, '000600' /* Handgun */, '000500' /* Slicer */, '000100' /* Saber */]; break;
                case 2: $weapons = ['000701' /* Sniper */, '000901' /* Spread */, '000801' /* Assault */, '000601' /* Autogun */, '000501' /* Spinner */, '000101' /* Brand */]; break;
                case 3: $weapons = ['000702' /* Blaster */, '000902' /* Cannon */, '000802' /* Repeater */, '000602' /* Lockgun */, '000502' /* Cutter */, '000102' /* Buster */]; break;
                case 4: $weapons = ['000703' /* Beam */, '000903' /* Launcher */, '000803' /* Gatling */, '000603' /* Railgun */, '000503' /* Sawcer */, '000103' /* Pallasch */]; break;
                case 5: $weapons = ['000704' /* Laser */, '000904' /* Arms */, '000804' /* Vulcan */, '000604' /* Raygun */, '000504' /* Diska */, '000104' /* Gladius */]; break;
                case 6: $weapons = ['000704' /* Laser */, '000904' /* Arms */, '000804' /* Vulcan */, '000604' /* Raygun */, '000504' /* Diska */, '000104' /* Gladius */]; break;
            }
        } else { // Forces
            switch ($item_tier) {
                case 1: $weapons = ['000A00' /* Cane */, '000B00' /* Rod */, '000C00' /* Wand */, '000600' /* Handgun */, '000500' /* Slicer */]; break;
                case 2: $weapons = ['000A01' /* Stick */, '000B01' /* Pole */, '000C01' /* Staff */, '000601' /* Autogun */, '000501' /* Spinner */]; break;
                case 3: $weapons = ['000A02' /* Mace */, '000B02' /* Pillar */, '000C02' /* Baton */, '000602' /* Lockgun */, '000502' /* Cutter */]; break;
                case 4: $weapons = ['000A03' /* Club */, '000B03' /* Striker */, '000C03' /* Scepter */, '000603' /* Railgun */, '000503' /* Sawcer */]; break;
                case 5: $weapons = ['000A03' /* Club */, '000B03' /* Striker */, '000C03' /* Scepter */, '000604' /* Raygun */, '000504' /* Diska */]; break;
                case 6: $weapons = ['000A03' /* Club */, '000B03' /* Striker */, '000C03' /* Scepter */, '000604' /* Raygun */, '000504' /* Diska */]; break;
            }
        }
        
                $chosenWeapon = $weapons[array_rand($weapons)];
        
        // At higher levels, better specials are more likely
        if ($tier > 7) {
            $high_specials = ['Charge', 'Berserk', 'Spirit', 'Arrest', 'Blizzard', 'Hell', 'Demon\'s', 'Geist', 'Gush'];
            $chosenSpecial = $high_specials[array_rand($high_specials)];
        } else {
            $chosenSpecial = $specials[array_rand($specials)];
        }
        
        // Scale attributes dynamically with level
        $base_attr = mt_rand(0, $tier * 10);
        $attr_types = ['native', 'abeast', 'machine', 'dark'];
        shuffle($attr_types);
        $attr1 = $attr_types[0];
        $attr2 = $attr_types[1];
        
        $options[$attr1] = $options[$attr1] ?? min(100, $base_attr);
        if ($tier > 4 && mt_rand(0, 100) > 50) {
            $options[$attr2] = $options[$attr2] ?? min(100, $base_attr);
        }

        $native = $options['native'] ?? 0;
        $abeast = $options['abeast'] ?? 0;
        $machine = $options['machine'] ?? 0;
        $dark = $options['dark'] ?? 0;
        
        return build_pso_weapon($chosenWeapon, $chosenSpecial, true, $native, $abeast, $machine, $dark);
        
    } else if ($category === 'Armor') {
        $frames = [];
        $armors = [];
        switch ($item_tier) {
            case 1: $frames = ['010100' /* Frame */, '010103' /* Giga Frame */]; $armors = ['010101' /* Armor */, '010102' /* Psy Armor */]; break;
            case 2: $frames = ['010104' /* Soul Frame */, '010106' /* Solid Frame */]; $armors = ['010105' /* Cross Armor */, '010107' /* Brave Armor */]; break;
            case 3: $frames = ['010108' /* Hyper Frame */, '01010A' /* Shock Frame */]; $armors = ['010109' /* Grand Armor */, '01010D' /* Absorb Armor */]; break;
            case 4: $frames = ['01010B' /* King's Frame */, '01010C' /* Dragon Frame */]; $armors = ['01010F' /* General Armor */, '010113' /* Holiness Armor */]; break;
            case 5: $frames = ['01010E' /* Protect Frame */, '010110' /* Perfect Frame */]; $armors = ['010112' /* Imperial Armor */, '010114' /* Guardian Armor */]; break;
            case 6: $frames = ['010111' /* Valiant Frame */, '010116' /* Ultimate Frame */]; $armors = ['010115' /* Divinity Armor */, '010117' /* Celestial Armor */]; break;
        }
        
        // Forces can only equip frames in PSOBB. Hunters and Rangers can equip both.
        $validArmors = $isForce ? $frames : array_merge($frames, $armors);
        
        $chosenArmor = $validArmors[array_rand($validArmors)];
        
        // Scale DEF and EVP based on tier
        $def = $options['def'] ?? mt_rand(0, $tier * 5);
        $evp = $options['evp'] ?? mt_rand(0, $tier * 5);
        $slots = $options['slots'] ?? mt_rand(max(0, $tier - 6), 4);
        
        return build_pso_armor($chosenArmor, $slots, $def, $evp);
        
    } else if ($category === 'Shield') {
        $barriers = [];
        $shields = []; // Shields are normally unequippable by Forces
        switch ($item_tier) {
            case 1: $barriers = ['010200' /* Barrier */, '010204' /* Soul Barrier */]; $shields = ['010201' /* Shield */, '010202' /* Core Shield */]; break;
            case 2: $barriers = ['010206' /* Brave Barrier */, '010208' /* Flame Barrier */]; $shields = ['010203' /* Giga Shield */, '010205' /* Hard Shield */]; break;
            case 3: $barriers = ['010209' /* Plasma Barrier */, '01020A' /* Freeze Barrier */]; $shields = ['010207' /* Solid Shield */, '01020C' /* General Shield */]; break;
            case 4: $barriers = ['01020B' /* Psychic Barrier */, '01020D' /* Protect Barrier */]; $shields = ['01020E' /* Glorious Shield */, '010210' /* Guardian Shield */]; break;
            case 5: $barriers = ['01020F' /* Imperial Barrier */, '010211' /* Divinity Barrier */]; $shields = ['010213' /* Spiritual Shield */, '010214' /* Celestial Shield */]; break;
            case 6: $barriers = ['01020F' /* Imperial Barrier */, '010211' /* Divinity Barrier */]; $shields = ['010212' /* Ultimate Shield */, '010214' /* Celestial Shield */]; break;
        }
        
        // Forces typically can only equip barriers, with few exceptions. Play it safe by only rewarding barriers to FO.
        $validShields = $isForce ? $barriers : array_merge($barriers, $shields);

        $chosenShield = $validShields[array_rand($validShields)];
        $def = $options['def'] ?? mt_rand(0, $tier * 5);
        $evp = $options['evp'] ?? mt_rand(0, $tier * 5);
        return build_pso_armor($chosenShield, 0, $def, $evp);
    } else if ($category === 'Unit') {
        $units = [];
        switch ($item_tier) {
            case 1: $units = ['010300' /* Knight/Power */, '010304' /* Priest/Mind */, '01030C' /* Thief/Legs */, '010318' /* Warrior/Body */]; break;
            case 2: $units = ['010301' /* General/Power */, '010305' /* General/Mind */, '01030D' /* General/Legs */, '010319' /* General/Body */, '010309' /* General/Arm */, '010311' /* General/HP */]; break;
            case 3: $units = ['010302' /* Ogre/Power */, '010310' /* Digger/HP */, '01030E' /* Elf/Legs */, '01030A' /* Elf/Arm */, '010308' /* Marksman/Arm */, '01033C' /* Wizard/Technique */]; break;
            case 4: $units = ['010312' /* Dragon/HP */, '010306' /* Angel/Mind */, '01031C' /* Angel/Luck */, '01030E' /* Elf/Legs */]; break;
            case 5: $units = ['01031E' /* Master/Ability */, '010334' /* HP/Generate */, '010337' /* TP/Generate */, '01033A' /* PB/Generate */]; break;
            case 6: $units = ['010333' /* HP/Restorate */, '010336' /* TP/Restorate */, '010339' /* PB/Amplifier */]; break;
        }
                $chosenUnit = $units[array_rand($units)];
        $modifier = 2; // Always grant ++ modifier for max stats
        return build_pso_armor($chosenUnit, 0, $modifier, 0); // Units use same hex base logic
    }
    
    // Fallback if bad category
    return '10000 Meseta';
}
?>