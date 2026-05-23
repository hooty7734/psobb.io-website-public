<?php
function send_personal_mail($client_acc_id, $from_name, $text) {
    global $NEWSERV_API_URL;
    $packet = str_repeat("\x00", 1112);
    $packet[0] = chr(0x58); $packet[1] = chr(0x04);
    $packet[2] = chr(0x81); $packet[3] = chr(0x00);
    $packet[4] = chr(0x00); $packet[5] = chr(0x00); $packet[6] = chr(0x01); $packet[7] = chr(0x00);
    
    $from_utf16 = "\xFF\xFE" . mb_convert_encoding($from_name, 'UTF-16LE', 'UTF-8');
    for($i = 0; $i < min(30, strlen($from_utf16)); $i++) {
        $packet[12 + $i] = $from_utf16[$i];
    }
    
    $packet[44] = chr($client_acc_id & 0xFF);
    $packet[45] = chr(($client_acc_id >> 8) & 0xFF);
    $packet[46] = chr(($client_acc_id >> 16) & 0xFF);
    $packet[47] = chr(($client_acc_id >> 24) & 0xFF);
    
    // Fixed time for test reproduciability
    $date_utf16 = mb_convert_encoding('2026-04-20 15:27:00', 'UTF-16LE', 'UTF-8');
    for($i = 0; $i < min(38, strlen($date_utf16)); $i++) {
        $packet[48 + $i] = $date_utf16[$i];
    }
    
    $text_utf16 = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
    for($i = 0; $i < min(1022, strlen($text_utf16)); $i++) {
        $packet[88 + $i] = $text_utf16[$i];
    }
    
    $hex = bin2hex($packet);
    $exec_payload = json_encode(["command" => "on " . $client_acc_id . " sc " . $hex]);
    echo "Payload Length: " . strlen($exec_payload) . "\n";
    echo substr($exec_payload, 0, 100) . " ... " . substr($exec_payload, -50) . "\n";
}

send_personal_mail(10, "Hunter's Guild", "Gwyn,\nYour new bounty is here!");
