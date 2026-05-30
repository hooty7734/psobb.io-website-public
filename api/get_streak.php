<?php
/**
 * --------------------------------------------------------------------------
 * PSOBB Streak & Rewards Engine
 * --------------------------------------------------------------------------
 * This endpoint is polled by the active player dashboard to render the Hunter
 * Rewards module. It computes contiguous login days backwards from the present
 * and determines which Reward Cycle (1-365 days) the player is currently on.
 */
require_once 'config.php';
require_once 'db.php';

if (ob_get_length()) ob_clean();
start_secure_session();
header('Content-Type: application/json');

// 1. Session Enforcement
if (empty($_SESSION['user']) || empty($_SESSION['user']['account_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}
$accountId = $_SESSION['user']['account_id'];

// 2. Fetch Live Game Server Status
// We query the NewServ binary directly. If the player is currently online in-game,
// the dashboard will render "Online: Yes".
$url = $NEWSERV_API_URL . "/y/clients";
$data = @file_get_contents($url);
$isOnline = false;

if ($data !== FALSE) {
    $clients = json_decode($data, true);
    if (is_array($clients)) {
        foreach ($clients as $c) {
            if (isset($c['Account']) && $c['Account']['AccountID'] == $accountId) {
                $isOnline = true;
                break;
            }
        }
    }
}

$db = get_db();
$today = date('Y-m-d');

// 3. Website Check-In Logging
// By virtue of reaching this endpoint securely with an active session, the player
// is viewing their web dashboard. We securely record today as a successful login hit.
$stmt = $db->prepare("INSERT OR IGNORE INTO daily_logins (account_id, login_date) VALUES (:aid, :date)");
$stmt->bindValue(':aid', $accountId, SQLITE3_INTEGER);
$stmt->bindValue(':date', $today, SQLITE3_TEXT);
$stmt->execute();

// 4. Streak Calculation Engine
// Instead of storing a mutable "streak" integer which can desync, we dynamically 
// compute the streak by walking backwards one day at a time from today.
// The loop breaks the moment a contiguous day is missed in the database.
$streak = 0;
$checkDate = new DateTime($today);

while (true) {
    $dateStr = $checkDate->format('Y-m-d');
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM daily_logins WHERE account_id = :aid AND login_date = :date");
    $stmt->bindValue(':aid', $accountId, SQLITE3_INTEGER);
    $stmt->bindValue(':date', $dateStr, SQLITE3_TEXT);
    $result = $stmt->execute()->fetchArray();
    
    // If they logged in on $checkDate, increment streak and step backwards 1 day
    if ($result['cnt'] > 0) {
        $streak++;
        $checkDate->modify('-1 day');
    } else {
        // As soon as we find a day without a login, the contiguous streak is broken
        break;
    }
}

// 5. Reward Cycle Management
// Rewards run in 365-day "Cycles". Once a player claims Milestone 365 of Cycle N,
// they are automatically bumped into Cycle N+1.
$stmt = $db->prepare("SELECT COALESCE(MAX(streak_cycle), 1) as cycle FROM streak_claims WHERE account_id = :aid");
$stmt->bindValue(':aid', $accountId, SQLITE3_INTEGER);
$result = $stmt->execute()->fetchArray();
$currentCycle = $result['cycle'];

// Verify if the terminal milestone (365) of the current cycle has been claimed,
// or if the player's streak has broken since their last claim.
$stmt = $db->prepare("SELECT COALESCE(MAX(milestone), 0) as max_ms FROM streak_claims WHERE account_id = :aid AND streak_cycle = :cycle");
$stmt->bindValue(':aid', $accountId, SQLITE3_INTEGER);
$stmt->bindValue(':cycle', $currentCycle, SQLITE3_INTEGER);
$result = $stmt->execute()->fetchArray();
$maxClaimedMilestone = $result['max_ms'];

if ($maxClaimedMilestone == 365) {
    $currentCycle++;
} else if ($maxClaimedMilestone > 0 && $streak < $maxClaimedMilestone) {
    $currentCycle++;
}

// 6. Claim State Mapping
// Map out exactly which of the 365 milestones the player has ALREADY claimed, 
// and which ones they CAN claim today based on their computed $streak score.
$milestones = range(1, 365);
$claimed = [];
$claimable = [];

foreach ($milestones as $m) {
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM streak_claims WHERE account_id = :aid AND streak_cycle = :cycle AND milestone = :ms");
    $stmt->bindValue(':aid', $accountId, SQLITE3_INTEGER);
    $stmt->bindValue(':cycle', $currentCycle, SQLITE3_INTEGER);
    $stmt->bindValue(':ms', $m, SQLITE3_INTEGER);
    $result = $stmt->execute()->fetchArray();
    
    if ($result['cnt'] > 0) {
        $claimed[] = $m;
    } else if ($streak >= $m) {
        $claimable[] = $m;
    }
}

// 7. Daily Flat Reward Processing
// In addition to milestone streaks, we offer a flat daily check-in reward.
// (e.g. 1 Photon Drop per day)
$db->exec("CREATE TABLE IF NOT EXISTS daily_rewards (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    account_id INTEGER NOT NULL,
    claim_date TEXT NOT NULL,
    item_string TEXT NOT NULL,
    claimed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(account_id, claim_date)
)");

// Check if they already pressed the "Claim Daily" button today
$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM daily_rewards WHERE account_id = :aid AND claim_date = :date");
$stmt->bindValue(':aid', $accountId, SQLITE3_INTEGER);
$stmt->bindValue(':date', $today, SQLITE3_TEXT);
$result = $stmt->execute()->fetchArray();
$dailyClaimed = $result['cnt'] > 0;

// Compute UI countdown timers (Unix Timestamp of precisely 12:00 AM Midnight next day)
$tomorrow = new DateTime('tomorrow');
$nextReset = $tomorrow->getTimestamp();

// Check if the user has ever claimed the Day 365 milestone in any cycle
$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM streak_claims WHERE account_id = :aid AND milestone = 365");
$stmt->bindValue(':aid', $accountId, SQLITE3_INTEGER);
$result = $stmt->execute()->fetchArray();
$hasClaimedYahoo = $result['cnt'] > 0;

// 8. Output Pipeline
echo json_encode([
    "is_online" => $isOnline,
    "streak" => $streak,
    "cycle" => $currentCycle,
    "claimed" => $claimed,
    "claimable" => $claimable,
    "today_recorded" => $isOnline,     // Deprecated legacy output
    "daily_claimed" => $dailyClaimed,
    "next_daily_reset" => $nextReset,
    "server_time" => time(),
    "has_claimed_yahoo" => $hasClaimedYahoo
]);
?>
