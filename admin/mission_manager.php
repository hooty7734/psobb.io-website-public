<?php
require_once __DIR__ . '/../api/config.php';
start_secure_session();
if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    header("Location: ../login.php");
    exit;
}
$page_title = "Manage Missions";
include '../includes/header.php'; 

require_once '../api/db.php';
require_once '../api/functions.php';
$db = get_db();
$message = '';

// Fetch live connected players from newserv for dropdowns
$live_players = [];
$live_response = @file_get_contents($NEWSERV_API_URL . '/y/clients');
if ($live_response) {
    $live_clients = json_decode($live_response, true) ?: [];
    foreach ($live_clients as $lc) {
        if (isset($lc['Account']['AccountID'])) {
            $live_players[] = [
                'account_id' => $lc['Account']['AccountID'],
                'name' => $lc['Name'] ?? 'Unknown',
                'level' => $lc['Level'] ?? '?',
            ];
        }
    }
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_mission') {
        $title = trim($_POST['title']);
        $desc = trim($_POST['description']);
        $type = $_POST['goal_type'];
        $target = trim($_POST['goal_target']);
        $reward = trim($_POST['reward_item_string']);
        
        if ($type === 'SPEEDRUN_BOSS' || $type === 'SPEEDRUN_FLOOR') {
            // Speedrun mission targets require both a floor ID and a time limit in seconds.
            // We concatenate them using an underscore (e.g., '11_180' means Dragon in 180 seconds)
            // so they can be stored in the single 'goal_target' database column.
            $time_limit = (int)($_POST['time_limit'] ?? 0);
            if ($time_limit > 0) {
                $target = $target . '_' . $time_limit;
            }
        }

        if ($title && $desc && $type && $target && $reward) {
            $stmt = $db->prepare("INSERT INTO missions (title, description, goal_type, goal_target, reward_item_string) VALUES (:t, :d, :gt, :gta, :ri)");
            $stmt->bindValue(':t', $title, SQLITE3_TEXT);
            $stmt->bindValue(':d', $desc, SQLITE3_TEXT);
            $stmt->bindValue(':gt', $type, SQLITE3_TEXT);
            $stmt->bindValue(':gta', $target, SQLITE3_TEXT);
            $stmt->bindValue(':ri', $reward, SQLITE3_TEXT);
            if ($stmt->execute()) {
                $message = "<div style='color:#00C851'>Mission added successfully!</div>";
            } else {
                $message = "<div style='color:#ff4444'>Failed to add mission.</div>";
            }
        } else {
            $message = "<div style='color:#ff4444'>All fields are required.</div>";
        }
    } elseif ($action === 'assign_mission') {
        $mission_id = (int)$_POST['mission_id'];
        $account_id = (int)$_POST['account_id'];

        if ($mission_id && $account_id) {
            $stmt = $db->prepare("INSERT OR IGNORE INTO player_missions (account_id, mission_id) VALUES (:acc, :mid)");
            $stmt->bindValue(':acc', $account_id, SQLITE3_INTEGER);
            $stmt->bindValue(':mid', $mission_id, SQLITE3_INTEGER);
            if ($stmt->execute()) {
                if ($db->changes() > 0) {
                    // Send in-game mail notification to the player
                    $m_stmt = $db->prepare("SELECT title FROM missions WHERE id = :mid");
                    $m_stmt->bindValue(':mid', $mission_id, SQLITE3_INTEGER);
                    $m_row = $m_stmt->execute()->fetchArray(SQLITE3_ASSOC);
                    $m_title = $m_row ? $m_row['title'] : 'a new mission';
                    send_personal_mail($account_id, "Hunters Guild", "You've been assigned: " . $m_title . ". Good luck!");
                    $message = "<div style='color:#00C851'>Mission assigned and player notified in-game!</div>";
                } else {
                    $message = "<div style='color:#ff8800'>Player already has this mission.</div>";
                }
            } else {
                $message = "<div style='color:#ff4444'>Failed to assign mission.</div>";
            }
        } else {
            $message = "<div style='color:#ff4444'>Missing information to assign mission.</div>";
        }
    } elseif ($action === 'send_message') {
        $msg_account_id = (int)$_POST['msg_account_id'];
        $msg_from = trim($_POST['msg_from'] ?? 'Admin');
        $msg_text = trim($_POST['msg_text'] ?? '');

        if ($msg_account_id && $msg_text) {
            send_personal_mail($msg_account_id, $msg_from, $msg_text);
            $message = "<div style='color:#00C851'>Message sent to account $msg_account_id!</div>";
        } else {
            $message = "<div style='color:#ff4444'>Account ID and message text are required.</div>";
        }
    } elseif ($action === 'add_community_event') {
        $title = trim($_POST['title']);
        $desc = trim($_POST['description']);
        $type = $_POST['goal_type'];
        $target = trim($_POST['goal_target']);
        $amount = (int)$_POST['target_amount'];
        $reward = trim($_POST['reward_item_string']);
        
        $top3_reward = '';
        if (!empty($_POST['top_3_reward_item_string'])) {
            if (is_array($_POST['top_3_reward_item_string'])) {
                $top3_reward = implode('|', array_map('trim', $_POST['top_3_reward_item_string']));
            } else {
                $top3_reward = trim($_POST['top_3_reward_item_string']);
            }
        }

        if ($title && $desc && $type && $target && $amount > 0 && $reward) {
            $stmt = $db->prepare("INSERT INTO community_events (title, description, goal_type, goal_target, target_amount, reward_item_string, top_3_reward_item_string) VALUES (:t, :d, :gt, :gta, :amt, :ri, :top3)");
            $stmt->bindValue(':t', $title, SQLITE3_TEXT);
            $stmt->bindValue(':d', $desc, SQLITE3_TEXT);
            $stmt->bindValue(':gt', $type, SQLITE3_TEXT);
            $stmt->bindValue(':gta', $target, SQLITE3_TEXT);
            $stmt->bindValue(':amt', $amount, SQLITE3_INTEGER);
            $stmt->bindValue(':ri', $reward, SQLITE3_TEXT);
            $stmt->bindValue(':top3', $top3_reward ?: null, SQLITE3_TEXT);
            if ($stmt->execute()) {
                $message = "<div style='color:#00C851'>Community Event created successfully!</div>";
            } else {
                $message = "<div style='color:#ff4444'>Failed to create community event.</div>";
            }
        } else {
            $message = "<div style='color:#ff4444'>All fields are required. Target Amount must be > 0.</div>";
        }
    }
}

// Fetch existing missions
$missions = [];
$res = $db->query("SELECT * FROM missions ORDER BY id DESC");
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $missions[] = $row;
}

// Fetch all player missions for display
$player_missions = [];
$res = $db->query("SELECT pm.id, pm.status, pm.completed_at, u.username, u.account_id, m.title 
                   FROM player_missions pm
                   JOIN users u ON pm.account_id = u.account_id
                   JOIN missions m ON pm.mission_id = m.id
                   ORDER BY pm.id DESC LIMIT 50");
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $player_missions[] = $row;
}

// Fetch active Community Events telemetry
$active_telemetry = [];
$tele_res = $db->query("SELECT id, title, target_amount, current_progress FROM community_events WHERE status = 'active' ORDER BY id DESC");
while ($ce = $tele_res->fetchArray(SQLITE3_ASSOC)) {
    $ce['participants'] = [];
    $p_stmt = $db->prepare("SELECT u.username, u.account_id, cep.contribution_count 
                            FROM community_event_participants cep 
                            JOIN users u ON cep.account_id = u.account_id 
                            WHERE cep.event_id = :eid 
                            ORDER BY cep.contribution_count DESC");
    $p_stmt->bindValue(':eid', $ce['id'], SQLITE3_INTEGER);
    $p_exec = $p_stmt->execute();
    while ($p = $p_exec->fetchArray(SQLITE3_ASSOC)) {
        $ce['participants'][] = $p;
    }
    $active_telemetry[] = $ce;
}
?>

<style>
    .admin-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 1.5rem;
        margin-top: 2rem;
    }
    .admin-card {
        background: rgba(0, 0, 0, 0.6);
        border: 1px solid var(--primary-color);
        padding: 1.5rem;
        border-radius: 8px;
    }
    .admin-card h3 {
        margin-top: 0;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        padding-bottom: 0.5rem;
        margin-bottom: 1rem;
        color: var(--secondary-color);
    }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; opacity: 0.8;}
    input[type="text"], input[type="number"], select {
        width: 100%; padding: 8px; background: rgba(0,0,0,0.4); border: 1px solid #444; color: #fff;
    }
</style>

<main class="container">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h1>Manage Missions</h1>
            <div style="font-size:0.9rem; opacity:0.7;">Create and assign dynamic server-events tracking quests!</div>
        </div>
        <div>
            <a href="dashboard.php" class="dl-btn">Back to Dashboard</a>
        </div>
    </div>

    <?php if ($message) echo "<div style='margin-top:10px; padding:10px; border:1px solid rgba(255,255,255,0.2); background:rgba(0,0,0,0.5);'>$message</div>"; ?>

    <div class="admin-grid">
        <!-- Add Mission Form -->
        <div class="admin-card">
            <h3 style="color: #00bfff;">Create Daily Bounty Template</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_mission">
                
                <div class="form-group">
                    <label>Mission Title</label>
                    <input type="text" name="title" required placeholder="e.g. Become a Millionaire">
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" required placeholder="User friendly text of what to do">
                </div>

                <div style="display:flex; gap:10px;">
                    <div class="form-group" style="flex:1;">
                        <label>Goal Type</label>
                        <select name="goal_type" id="goal_type_select" onchange="updateGoalTargetUI()">
                            <option value="MESETA">Meseta Check</option>
                            <option value="LEVEL">Reach Level</option>
                            <option value="PLAYTIME">Play Time (Seconds)</option>
                            <option value="ITEM">Obtain Item</option>
                            <option value="BOSS_ARENA">Defeat Boss (Floor ID)</option>
                            <option value="SPEEDRUN_BOSS">Speedrun Boss (Time Limit)</option>
                            <option value="EXPLORATION">Explore Area (Floor ID)</option>
                            <option value="PATROL">Patrol Area 10m (Floor ID)</option>
                            <option value="SPEEDRUN_FLOOR">Speedrun Area (Time Limit)</option>
                            <option value="BATTLE_WINS">Battle Mode Wins</option>
                            <option value="CHALLENGE_STAGES">Challenge Stages Cleared</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label id="goal_target_label">Goal Target</label>
                        <div id="goal_target_container">
                            <input type="text" name="goal_target" id="goal_target_input" required placeholder="e.g. 100000 or Saber">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Reward Item / Hex Code</label>
                    <input type="text" name="reward_item_string" required placeholder="e.g. 1000 Meseta">
                </div>

                <button type="submit" class="dl-btn success-btn" style="width:100%">Create Mission</button>
            </form>
        </div>

        <script>
        const bossOptions = [
            {val: 'ANY_DRAGON', text: 'Any Dragon Boss (Forest, Sil, Gol)'},
            {val: '11', text: 'Dragon (Forest)'},
            {val: '12', text: 'De Rol Le (Caves)'},
            {val: '13', text: 'Vol Opt (Mines)'},
            {val: '14', text: 'Dark Falz (Ruins)'},
            {val: '17', text: 'Barba Ray (Temple)'},
            {val: '16', text: 'Gol Dragon (Spaceship)'},
            {val: '15', text: 'Gal Gryphon (CCA)'},
            {val: '18', text: 'Olga Flow (Seabed)'}
        ];
        
        const floorOptions = [
            {val: '1', text: 'Forest 1'}, {val: '2', text: 'Forest 2'},
            {val: '3', text: 'Cave 1'}, {val: '4', text: 'Cave 2'}, {val: '5', text: 'Cave 3'},
            {val: '6', text: 'Mine 1'}, {val: '7', text: 'Mine 2'},
            {val: '8', text: 'Ruins 1'}, {val: '9', text: 'Ruins 2'}, {val: '10', text: 'Ruins 3'}
        ];

        function updateGoalTargetUI() {
            const type = document.getElementById('goal_type_select').value;
            const container = document.getElementById('goal_target_container');
            const label = document.getElementById('goal_target_label');

            if (type === 'BOSS_ARENA' || type === 'SPEEDRUN_BOSS') {
                label.textContent = "Select Target Boss";
                let html = '<div style="display:flex; gap:10px;"><select name="goal_target" required style="flex:1; padding:8px; background:rgba(0,0,0,0.4); border:1px solid #444; color:#fff;">';
                bossOptions.forEach(b => html += `<option value="${b.val}">${b.text}</option>`);
                html += '</select>';
                if (type === 'SPEEDRUN_BOSS') {
                    html += '<input type="number" name="time_limit" required placeholder="Seconds (e.g. 90)" style="flex:1; padding:8px; background:rgba(0,0,0,0.4); border:1px solid #444; color:#fff;">';
                }
                html += '</div>';
                container.innerHTML = html;
            } else if (type === 'EXPLORATION' || type === 'PATROL' || type === 'SPEEDRUN_FLOOR') {
                label.textContent = "Select Target Area";
                let html = '<div style="display:flex; gap:10px;"><select name="goal_target" required style="flex:1; padding:8px; background:rgba(0,0,0,0.4); border:1px solid #444; color:#fff;">';
                floorOptions.forEach(f => html += `<option value="${f.val}">${f.text}</option>`);
                html += '</select>';
                if (type === 'SPEEDRUN_FLOOR') {
                    html += '<input type="number" name="time_limit" required placeholder="Seconds (e.g. 300)" style="flex:1; padding:8px; background:rgba(0,0,0,0.4); border:1px solid #444; color:#fff;">';
                }
                html += '</div>';
                container.innerHTML = html;
            } else {
                label.textContent = "Goal Target (Number or Item Name)";
                let ph = "e.g. 100000 or Saber";
                if(type === 'BATTLE_WINS' || type === 'CHALLENGE_STAGES' || type === 'LEVEL') ph = "e.g. 10";
                if(type === 'PLAYTIME') ph = "Play time in seconds (e.g. 3600)";
                
                container.innerHTML = `<input type="text" name="goal_target" required placeholder="${ph}" style="width:100%; padding:8px; background:rgba(0,0,0,0.4); border:1px solid #444; color:#fff;">`;
            }
        }
        
        // Initialize UI on load just in case
        document.addEventListener('DOMContentLoaded', () => { updateGoalTargetUI(); updateCEGoalTargetUI(); });
        
        function updateCEGoalTargetUI() {
            const type = document.getElementById('ce_goal_type_select').value;
            const container = document.getElementById('ce_goal_target_container');
            const label = document.getElementById('ce_goal_target_label');

            if (type === 'BOSS_ARENA') {
                label.textContent = "Select Target Boss";
                let html = '<select name="goal_target" required style="width:100%; padding:8px; background:rgba(0,0,0,0.4); border:1px solid #444; color:#fff;">';
                bossOptions.forEach(b => html += `<option value="${b.val}">${b.text}</option>`);
                html += '</select>';
                container.innerHTML = html;
            } else if (type === 'EXPLORATION' || type === 'PATROL') {
                label.textContent = "Select Target Area";
                let html = '<select name="goal_target" required style="width:100%; padding:8px; background:rgba(0,0,0,0.4); border:1px solid #444; color:#fff;">';
                floorOptions.forEach(f => html += `<option value="${f.val}">${f.text}</option>`);
                html += '</select>';
                container.innerHTML = html;
            } else if (['MESETA', 'LEVEL_UP', 'MAT_CONSUME', 'PLAYTIME', 'CHALLENGE_STAGES'].includes(type)) {
                label.textContent = "Target Specification";
                container.innerHTML = `<input type="text" name="goal_target" value="ANY" readonly style="width:100%; padding:8px; background:rgba(0,0,0,0.2); border:1px solid #222; color:#888; cursor:not-allowed;">`;
            } else {
                label.textContent = "Goal Target (Number or Item Name)";
                let ph = "e.g. 100000 or Saber";
                if(type === 'BATTLE_WINS' || type === 'CHALLENGE_STAGES' || type === 'LEVEL') ph = "e.g. 10";
                if(type === 'PLAYTIME') ph = "Play time in seconds (e.g. 3600)";
                container.innerHTML = `<input type="text" name="goal_target" required placeholder="${ph}" style="width:100%; padding:8px; background:rgba(0,0,0,0.4); border:1px solid #444; color:#fff;">`;
            }
        }
        </script>

        <!-- Assign Mission Form -->
        <div class="admin-card">
            <h3>Assign Mission Privately</h3>
            <p>Give an existing user a mission manually (Later, users can accept missions from a 'Bounty Board').</p>
            <form method="POST">
                <input type="hidden" name="action" value="assign_mission">
                
                <div class="form-group">
                    <label>Select Mission</label>
                    <select name="mission_id" required>
                        <?php foreach($missions as $m): ?>
                            <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['title'] . " (Goal: " . $m['goal_target'] . " " . $m['goal_type'] . ")"); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Select Player</label>
                    <?php if (!empty($live_players)): ?>
                        <select name="account_id" required>
                            <option value="">-- Select a player --</option>
                            <?php foreach ($live_players as $lp): ?>
                                <option value="<?php echo $lp['account_id']; ?>"><?php echo htmlspecialchars($lp['name']); ?> (Lv<?php echo $lp['level']; ?>, ID: <?php echo $lp['account_id']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="number" name="account_id" required placeholder="No players online — enter Account ID manually">
                    <?php endif; ?>
                </div>

                <button type="submit" class="dl-btn" style="width:100%">Assign to Player</button>
            </form>
        </div>

        <!-- Send Direct Message -->
        <div class="admin-card">
            <h3 style="color: #00bfff;">Send In-Game Mail</h3>
            <p style="opacity:0.7; font-size:0.85rem;">Send a Simple Mail directly to a connected player's mailbox.</p>
            <form method="POST">
                <input type="hidden" name="action" value="send_message">
                
                <div class="form-group">
                    <label>Select Player</label>
                    <?php if (!empty($live_players)): ?>
                        <select name="msg_account_id" required>
                            <option value="">-- Select a player --</option>
                            <?php foreach ($live_players as $lp): ?>
                                <option value="<?php echo $lp['account_id']; ?>"><?php echo htmlspecialchars($lp['name']); ?> (Lv<?php echo $lp['level']; ?>, ID: <?php echo $lp['account_id']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="number" name="msg_account_id" required placeholder="No players online — enter Account ID manually">
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label>From Name</label>
                    <input type="text" name="msg_from" value="Hunters Guild" placeholder="Sender display name">
                </div>
                
                <div class="form-group">
                    <label>Message</label>
                    <input type="text" name="msg_text" required placeholder="Your message here..." maxlength="500">
                </div>

                <button type="submit" class="dl-btn" style="width:100%; border-color:#00bfff; color:#00bfff; background:rgba(0,191,255,0.15);">Send Mail</button>
            </form>
        </div>

        <!-- Create Community Event Form -->
        <div class="admin-card">
            <h3 style="color: #ffaa00;">Create Community Event</h3>
            <p>Generates a server-wide shared goal. Anyone who contributes will get the reward when the total runs out!</p>
            <form method="POST">
                <input type="hidden" name="action" value="add_community_event">
                
                <div class="form-group">
                    <label>Event Title</label>
                    <input type="text" name="title" required placeholder="e.g. The Great Dragon Purge">
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" required placeholder="Slay 50 Dragons together to unlock a reward!">
                </div>

                <div style="display:flex; gap:10px;">
                    <div class="form-group" style="flex:1;">
                        <label>Participant Goal</label>
                        <select name="goal_type" id="ce_goal_type_select" onchange="updateCEGoalTargetUI()">
                            <option value="BOSS_ARENA" selected>Defeat Boss (Floor ID)</option>
                            <option value="MESETA">Meseta Extracted (Total)</option>
                            <option value="ITEM">Obtain Item</option>
                            <option value="PATROL">Patrol Area 10m (Floor ID)</option>
                            <option value="LEVEL_UP">Server Level Ups (Total)</option>
                            <option value="MAT_CONSUME">Materials Consumed (Total)</option>
                            <option value="PLAYTIME">Playtime Logged (Hours)</option>
                            <option value="CHALLENGE_STAGES">Challenge Stages Cleared (Total)</option>
                        </select>
                    </div>
                </div>
                
                <div style="display:flex; gap:10px;">
                    <div class="form-group" style="flex:1;">
                        <label id="ce_goal_target_label">Goal Target</label>
                        <div id="ce_goal_target_container">
                            <input type="text" name="goal_target" id="ce_goal_target_input" required placeholder="e.g. 11">
                        </div>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label style="color:#ffaa00; font-weight:bold;">Total Amount Required</label>
                        <input type="number" name="target_amount" required min="1" placeholder="e.g. 50">
                    </div>
                </div>

                <div class="form-group">
                    <label>Community Reward Item</label>
                    <input type="text" name="reward_item_string" required list="rare_rewards_list" placeholder="e.g. 10 Photon Drop">
                    <datalist id="rare_rewards_list">
                        <!-- Standard Currencies -->
                        <option value="10 Photon Drop">
                        <option value="1 Photon Sphere">
                        <option value="999999 Meseta">
                        <!-- Mag Cells & Evolutions -->
                        <option value="Cell of Mag 502">
                        <option value="Cell of Mag 213">
                        <option value="Parts of RoboChao">
                        <option value="Heart of Opa Opa">
                        <option value="Heart of Chao">
                        <option value="Heart of Pian">
                        <option value="Heart of Chu Chu">
                        <option value="Heart of Angel">
                        <option value="Heart of Devil">
                        <option value="Kit of hamburger">
                        <option value="Kit of Dreamcast">
                        <option value="Kit of Sega Saturn">
                        <option value="Kit of Genesis">
                        <option value="Kit of Master System">
                        <option value="Kit of Mark3">
                        <option value="Panther's Spirit">
                        <option value="Halo Soul">
                        <!-- Super Rare Cosmetics/Weapons -->
                        <option value="Dragon's Scale">
                        <option value="Heaven Striker Coat">
                        <option value="Pioneer Parts">
                        <option value="Amitie's Memo">
                        <option value="Heart of Morolian">
                        <option value="Rappy's Beak">
                        <option value="Red Ring">
                        <option value="Parasitic Gene Flow">
                    </datalist>
                </div>

                <div class="form-group">
                    <label>Top 3 Contributors Bonus Reward Choices (Hold CTRL to select multiple)</label>
                    <select name="top_3_reward_item_string[]" multiple style="height: 180px; width: 100%; padding: 8px; background: rgba(0,0,0,0.4); border: 1px solid #444; color: #fff;">
                        <option value="">-- No Bonus Reward --</option>
                        <optgroup label="Sega Console Kits">
                            <option value="Kit of Dreamcast">Kit of Dreamcast</option>
                            <option value="Kit of Sega Saturn">Kit of Sega Saturn</option>
                            <option value="Kit of Genesis">Kit of Genesis</option>
                            <option value="Kit of Master System">Kit of Master System</option>
                            <option value="Kit of Mark3">Kit of Mark3</option>
                        </optgroup>
                        <optgroup label="Creature Hearts">
                            <option value="Heart of Chao">Heart of Chao</option>
                            <option value="Parts of RoboChao">Parts of RoboChao</option>
                            <option value="Heart of Opa Opa">Heart of Opa Opa</option>
                            <option value="Heart of Pian">Heart of Pian</option>
                            <option value="Heart of Chu Chu">Heart of Chu Chu</option>
                            <option value="Heart of Morolian">Heart of Morolian</option>
                        </optgroup>
                        <optgroup label="Angel / Demon Mags">
                            <option value="Heart of Angel">Heart of Angel</option>
                            <option value="Heart of Devil">Heart of Devil</option>
                        </optgroup>
                        <optgroup label="Special & Joke Mags">
                            <option value="Kit of hamburger">Kit of hamburger</option>
                            <option value="Panther's Spirit">Panther's Spirit</option>
                            <option value="Halo Soul">Halo Soul</option>
                            <option value="Cell of Mag 502">Cell of Mag 502 (Soniti)</option>
                            <option value="Cell of Mag 213">Cell of Mag 213 (Pitri)</option>
                            <option value="Amitie's Memo">Amitie's Memo (Kapu Kapu)</option>
                            <option value="Pioneer Parts">Pioneer Parts (Pioneer 2)</option>
                            <option value="Dragon's Scale">Dragon's Scale (Tellusis)</option>
                            <option value="Heaven Striker Coat">Heaven Striker Coat (Striker Unit)</option>
                        </optgroup>
                    </select>
                </div>

                <button type="submit" class="dl-btn" style="width:100%; border-color:#ffaa00; color:#ffaa00; background:rgba(255,170,0,0.15);">Start Global Event!</button>
            </form>
        </div>

        <div class="admin-card" style="grid-column: span 3;">
            <h3>Currently Tracked Player Missions (Last 50)</h3>
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table>
                    <thead style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th>ID</th>
                            <th>Username (Acc ID)</th>
                            <th>Mission</th>
                            <th>Status</th>
                            <th>Completed At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($player_missions)): ?>
                            <tr><td colspan="5">No missions tracked currently.</td></tr>
                        <?php else: ?>
                            <?php foreach ($player_missions as $pm): ?>
                                <tr>
                                    <td style="font-family:monospace;"><?php echo $pm['id']; ?></td>
                                    <td><?php echo htmlspecialchars($pm['username']) . ' (' . $pm['account_id'] . ')'; ?></td>
                                    <td><?php echo htmlspecialchars($pm['title']); ?></td>
                                    <td>
                                        <?php if ($pm['status'] === 'completed') echo "<span style='color:#00C851'>Completed!</span>"; else echo "<span style='color:#ff8800'>In Progress</span>"; ?>
                                    </td>
                                    <td><?php echo $pm['completed_at'] ? date('M j, g:i A', strtotime($pm['completed_at'])) : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="admin-card" style="grid-column: span 3;">
            <h3>Live Community Event Telemetry</h3>
            <p style="opacity: 0.8; font-size: 0.9rem;">Monitor real-time progress for active global events. Watch as players contribute to the goal!</p>
            
            <?php if (empty($active_telemetry)): ?>
                <div style="padding: 20px; text-align: center; background: rgba(255,255,255,0.05); border-radius: 5px;">
                    No active community events are running right now.
                </div>
            <?php else: ?>
                <div class="admin-grid" style="margin-top: 10px;">
                    <?php foreach ($active_telemetry as $tele): ?>
                        <div style="background: rgba(0,0,0,0.4); border: 1px solid #ffaa00; padding: 15px; border-radius: 5px;">
                            <h4 style="color: #ffaa00; margin-top: 0; margin-bottom: 5px;"><?php echo htmlspecialchars($tele['title']); ?></h4>
                            <div style="font-size: 0.85rem; color: #aaa; margin-bottom: 15px;">
                                Total Progress: <strong style="color:#fff;"><?php echo number_format($tele['current_progress']); ?> / <?php echo number_format($tele['target_amount']); ?></strong>
                            </div>
                            
                            <h5 style="margin: 0 0 10px 0; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 5px;">Participant Contributions</h5>
                            <div style="max-height: 250px; overflow-y: auto;">
                                <?php if (empty($tele['participants'])): ?>
                                    <div style="font-size: 0.85rem; color: #888;">No contributions recorded yet.</div>
                                <?php else: ?>
                                    <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.9rem;">
                                        <?php foreach ($tele['participants'] as $idx => $p): ?>
                                            <li style="display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                                <span><span style="color:#888; font-size:0.8em;"><?php echo ($idx+1); ?>.</span> <?php echo htmlspecialchars($p['username']); ?> <span style="opacity:0.5; font-size:0.8em;">(ID: <?php echo $p['account_id']; ?>)</span></span>
                                                <strong style="color: #00ffcc;"><?php echo number_format($p['contribution_count']); ?></strong>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php include '../includes/footer.php'; ?>
