<?php
/**
 * PSOBB Community Event Manager & Rotator
 * Run via CLI on your host:
 *   php manage_events.php             (Interactive Menu)
 *   php manage_events.php --list       (List available events)
 *   php manage_events.php --launch=X   (Launch event index X)
 *   php manage_events.php --auto-rotate (Rotate to the next month's event automatically)
 */
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/db.php';

$db = get_db();

// --- 1. Curated 6-Month Highly Scaled Boss Rush Roster ---
require_once __DIR__ . '/api/event_roster.php';

// --- 2. Helper: List all events in the roster ---
function list_events($roster) {
    echo "\n=== Available Community Events ===\n\n";
    foreach ($roster as $index => $event) {
        echo "  [$index] " . $event['title'] . "\n";
        echo "       Goal: " . $event['goal_type'] . " -> " . $event['goal_target'] . "\n";
        echo "       Target: " . number_format($event['target_amount']) . " points\n";
        echo "       Reward: " . $event['reward_item_string'] . "\n";
        echo "       Top 3:  " . ($event['top_3_reward_item_string'] ?? 'None') . "\n\n";
    }
}

// --- 3. CLI Argument Handling ---
$options = getopt("", ["list", "launch:", "auto-rotate"]);

if (isset($options['list'])) {
    list_events($roster);
    exit;
}

if (isset($options['launch'])) {
    $idx = intval($options['launch']);
    launch_event($db, $roster, $idx);
    exit;
}

if (isset($options['auto-rotate'])) {
    // Determine event index based on current month (e.g. Month 1-12 maps to Roster index 1-6 round-robin)
    $month = intval(date('n')); // 1 to 12
    $index = (($month - 1) % count($roster)) + 1; // Maps beautifully to 1-6
    
    echo "[ROTATOR] Auto-rotating. Current month: " . date('F') . " -> Mapping to Event Roster #$index.\n";
    launch_event($db, $roster, $index);
    exit;
}

// --- 4. Interactive Menu (Fallback) ---
list_events($roster);
echo "Enter the index of the event you would like to launch immediately (or Ctrl+C to cancel): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
$selection = intval(trim($line));

if ($selection >= 1 && $selection <= count($roster)) {
    launch_event($db, $roster, $selection);
} else {
    echo "[ABORTED] Invalid selection.\n";
}
?>
