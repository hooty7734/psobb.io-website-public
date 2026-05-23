<?php
$from_name = "Hunter's Guild";
$text = "Congrats Gwyn!\nYou unlocked a Lv70 Milestone Reward!";
$accId = 1;

$packet = str_repeat("\x00", 1112);
$packet[0] = chr(0x58); $packet[1] = chr(0x04);
$packet[2] = chr(0x81); $packet[3] = chr(0x00);
$packet[4] = chr(0x00); $packet[5] = chr(0x00); $packet[6] = chr(0x01); $packet[7] = chr(0x00);

$from_utf16 = "\xFF\xFE" . mb_convert_encoding($from_name, 'UTF-16LE', 'UTF-8');
for($i = 0; $i < min(30, strlen($from_utf16)); $i++) {
    $packet[12 + $i] = $from_utf16[$i];
}

$packet[44] = chr($accId & 0xFF);
$packet[45] = chr(($accId >> 8) & 0xFF);
$packet[46] = chr(($accId >> 16) & 0xFF);
$packet[47] = chr(($accId >> 24) & 0xFF);

$date_utf16 = mb_convert_encoding(date('Y-m-d H:i:s'), 'UTF-16LE', 'UTF-8');
for($i = 0; $i < min(38, strlen($date_utf16)); $i++) {
    $packet[48 + $i] = $date_utf16[$i];
}

$text_utf16 = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
for($i = 0; $i < min(1022, strlen($text_utf16)); $i++) {
    $packet[88 + $i] = $text_utf16[$i];
}

echo bin2hex($packet) . "\n";
