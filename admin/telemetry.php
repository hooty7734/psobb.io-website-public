<?php
require_once __DIR__ . '/../api/config.php';
start_secure_session();
if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    header("Location: ../login.php");
    exit;
}
$page_title = "Live Telemetry Debug";
include '../includes/header.php'; 

// Fetch Live Game State
$options = [
    'http' => [
        'header' => "Content-type: application/json\r\n",
        'method' => 'GET'
    ]
];
$context = stream_context_create($options);
$response = @file_get_contents($NEWSERV_API_URL . '/y/clients', false, $context);
$clients = $response ? json_decode($response, true) : [];

// Load Cron State Cache
$state_cache_file = __DIR__ . '/../db/.cron_player_state.json';
$player_states = file_exists($state_cache_file) ? json_decode(file_get_contents($state_cache_file), true) : [];
if (!is_array($player_states)) $player_states = [];

// Compact Floor Map for UI
$floor_names = [
    0 => 'Pioneer 2', 1 => 'Forest 1', 2 => 'Forest 2', 11 => 'Dragon', 
    3 => 'Cave 1', 4 => 'Cave 2', 5 => 'Cave 3', 12 => 'De Rol Le', 
    6 => 'Mine 1', 7 => 'Mine 2', 13 => 'Vol Opt', 
    8 => 'Ruins 1', 9 => 'Ruins 2', 10 => 'Ruins 3', 14 => 'Dark Falz',
    15 => 'Spaceship', 16 => 'Temple', 17 => 'Barba Ray',
    18 => 'Olga Flow', 19 => 'Crater'
];

// Fetch Telemetry Log
$debug_log_file = __DIR__ . '/../api/.debug_telemetry.json';
$telemetry_logs = file_exists($debug_log_file) ? json_decode(file_get_contents($debug_log_file), true) : [];
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
    
    /* Compact Table Styling for iframe */
    .telemetry-table th, .telemetry-table td {
        font-size: 0.85rem;
        padding: 0.4rem;
        white-space: nowrap;
    }
    
    /* Custom styling removed per user request to eliminate inner scrollbars */
</style>

<main class="container">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h1>Live Telemetry Debug</h1>
            <div style="font-size:0.9rem; opacity:0.7;">Monitor real-time player locations and server hook detections.</div>
        </div>
        <div>
            <a href="dashboard.php" class="dl-btn">Back to Dashboard</a>
            <button onclick="window.location.reload();" class="dl-btn" style="border-color:#00C851; color:#00C851;">Refresh Data</button>
        </div>
    </div>

    <div class="admin-grid">
        <!-- Live Players Map -->
        <div class="admin-card" style="grid-column: span 2;">
            <h3>Live Connected Players</h3>
            <div class="table-responsive">
                <table class="telemetry-table">
                    <thead style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th>Char</th>
                            <th>Acc ID</th>
                            <th>Lv</th>
                            <th>Prev</th>
                            <th>Curr</th>
                            <th>Old EXP</th>
                            <th>New EXP</th>
                            <th>Delta</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clients)): ?>
                            <tr><td colspan="6">No players currently online.</td></tr>
                        <?php else: ?>
                            <?php foreach ($clients as $client): ?>
                                <?php 
                                    $charName = $client['Name'] ?? 'Unknown';
                                    $accId = (string)($client['Account']['AccountID'] ?? 'Unknown');
                                    $level = $client['Level'] ?? '?';
                                    $currFloorId = $client['LocationFloor'] ?? 0;
                                    $currFloorName = $floor_names[$currFloorId] ?? "Floor $currFloorId";
                                    
                                    $state = $player_states[$accId] ?? [];
                                    $prevFloorId = $state['floor'] ?? $currFloorId;
                                    $prevFloorName = $floor_names[$prevFloorId] ?? "Floor $prevFloorId";
                                    
                                    $currExp = $client['EXP'] ?? 0;
                                    $prevExp = $state['exp'] ?? $currExp;
                                    $expDelta = $currExp - $prevExp;
                                    
                                    // Note: floor 15 (0x0F) is the lobby, NOT Gol Dragon
                                    $inBossRoom = in_array($currFloorId, [11, 12, 13, 14, 9, 16, 17, 18]);
                                    
                                    // Visual indicator for significant EXP spike (e.g., boss kill)
                                    $spikeStyle = ($expDelta >= 10) ? 'color:#00C851; font-weight:bold;' : '';
                                ?>
                                <tr>
                                    <td style="font-weight:bold; color:var(--pso-blue);"><?php echo htmlspecialchars($charName); ?></td>
                                    <td style="font-family:monospace;"><?php echo htmlspecialchars($accId); ?></td>
                                    <td><?php echo htmlspecialchars($level); ?></td>
                                    <td style="opacity:0.7;"><?php echo htmlspecialchars($prevFloorName); ?></td>
                                    <td style="<?php echo $inBossRoom ? 'color:#ffaa00; font-weight:bold;' : ''; ?>">
                                        <?php echo htmlspecialchars($currFloorName); ?>
                                    </td>
                                    <td style="opacity:0.7;"><?php echo number_format($prevExp); ?></td>
                                    <td><?php echo number_format($currExp); ?></td>
                                    <td style="<?php echo $spikeStyle; ?>">+<?php echo number_format($expDelta); ?></td>
                                    <td>
                                        <?php if ($inBossRoom): ?>
                                            <span style="color:#ffaa00; font-size:0.7rem; border:1px solid #ffaa00; padding:2px 4px; border-radius:3px;">BOSS ARENA</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Telemetry Log -->
        <div class="admin-card" style="grid-column: span 1;">
            <h3 style="color: #00ffcc;">Recent Hook Detections</h3>
            <div style="background: rgba(0,0,0,0.8); border: 1px solid #333; padding: 10px; border-radius: 5px; font-family: monospace; font-size: 0.85rem;">
                <?php if (empty($telemetry_logs)): ?>
                    <div style="color: #666; text-align: center; margin-top: 50px;">No telemetry events recorded yet.</div>
                <?php else: ?>
                    <?php foreach ($telemetry_logs as $log): ?>
                        <div style="margin-bottom: 10px; border-bottom: 1px solid #222; padding-bottom: 5px;">
                            <div style="color: #888; font-size: 0.75rem;"><?php echo date('Y-m-d H:i:s', $log['time'] ?? 0); ?> | <?php echo htmlspecialchars($log['char'] ?? 'Unknown'); ?></div>
                            <div style="color: #00ffcc; margin-top: 3px;">
                                &gt; Killed <strong><?php echo htmlspecialchars($log['boss'] ?? '?'); ?></strong> (+<?php echo number_format($log['exp_delta'] ?? 0); ?> EXP)
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
<?php include '../includes/footer.php'; ?>
