<?php
$msg = '$C6[Hunter\'s Guild] Congrats Alex!' . "\n" . 'You unlocked a Lv10 Milestone Reward!';
$text_utf16 = mb_convert_encoding($msg, "UTF-16LE", "UTF-8");
$text_utf16 .= "\x00\x00"; // null terminator

// pad to 4 bytes
while (strlen($text_utf16) % 4 !== 0) {
    $text_utf16 .= "\x00";
}

$payload_size = 8 + strlen($text_utf16);
$total_size = 4 + $payload_size;

// args: size(16), command(8), flag(8), player_tag(32), guild_card(32)
$header = pack("vCCVV", $total_size, 0xB0, 0x00, 0, 0);

$packet = $header . $text_utf16;
echo bin2hex($packet) . "\n";
