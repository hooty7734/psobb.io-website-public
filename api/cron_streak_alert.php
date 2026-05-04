<?php
/**
 * PSOBB Cron: Streak Expiration Alert
 * 
 * Sends a Discord DM to any player whose login streak is about to expire.
 * Run daily at ~11:00 PM server time via crontab.
 * Requires BOT_TOKEN to be set in .env.
 */
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

if (php_sapi_name() !== "cli") exit;

$db = get_db();
$today = date("Y-m-d");
$yesterday = date("Y-m-d", strtotime("-1 day"));

$botToken = $_ENV['BOT_TOKEN'] ?? '';
if (empty($botToken)) {
    echo "[CRON] Error: BOT_TOKEN is not defined in .env. Cannot send streak alerts.\n";
    exit(1);
}

// 1. Find all users who logged in yesterday but NOT today
$query = "SELECT u.discord_id, u.username, u.account_id 
          FROM users u 
          JOIN daily_logins dl ON u.account_id = dl.account_id 
          WHERE dl.login_date = :yesterday 
          AND u.discord_id IS NOT NULL 
          AND u.account_id NOT IN (SELECT account_id FROM daily_logins WHERE login_date = :today)";

$stmt = $db->prepare($query);
$stmt->bindValue(":yesterday", $yesterday, SQLITE3_TEXT);
$stmt->bindValue(":today", $today, SQLITE3_TEXT);
$res = $stmt->execute();

while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $discordId = $row["discord_id"];
    $username = $row["username"];
    
    // Send DM via Discord Bot
    $content = "⚠️ **STREAK ALERT:** Hunter $username, your daily login streak is about to expire in **6 hours**! Log in to the website or game now to keep it alive!";
    
    // We need to create a DM channel first
    $dmCmd = "curl -s -X POST \"https://discord.com/api/v10/users/@me/channels\" " .
             "-H \"Authorization: Bot $botToken\" " .
             "-H \"Content-Type: application/json\" " .
             "-d " . escapeshellarg(json_encode(["recipient_id" => $discordId]));
    
    $dmRes = json_decode(shell_exec($dmCmd), true);
    
    if (isset($dmRes["id"])) {
        $channelId = $dmRes["id"];
        $msgCmd = "curl -s -X POST \"https://discord.com/api/v10/channels/$channelId/messages\" " .
                  "-H \"Authorization: Bot $botToken\" " .
                  "-H \"Content-Type: application/json\" " .
                  "-d " . escapeshellarg(json_encode(["content" => $content]));
        shell_exec($msgCmd);
        echo "Alert sent to $username ($discordId)\n";
    }
}
