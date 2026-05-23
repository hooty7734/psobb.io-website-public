<?php
/**
 * PSOBB.io - Agent Telemetry Receiver (telemetry_ingest.php)
 * 
 * Secure wrapper to accept the Python agent's JSON POST requests 
 * and stash them in a flat file for the public dashboard to consume.
 */

// 1. Define your secure webhook token here (Must match 'CHANGE_ME' in decrypter.py)
define('AGENT_SECRET', 'CHANGE_ME');

// 2. Setup Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// 3. Authenticate the Python script's POST request
$headers = getallheaders();
$auth = isset($headers['Authorization']) ? $headers['Authorization'] : '';
if ($auth !== 'Bearer ' . AGENT_SECRET) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

// 4. Capture and validate the JSON payload
$json_data = file_get_contents("php://input");
$decoded = json_decode($json_data, true);

if (!$decoded) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid JSON payload"]);
    exit;
}

// 5. Write state atomically so visitors don't hit an empty file mid-write
$target_file = __DIR__ . '/agent_state.json';
$temp_file = $target_file . '.tmp';

$all_states = [];
if (file_exists($target_file)) {
    $existing = json_decode(file_get_contents($target_file), true);
    if ($existing && isset($existing['agents'])) {
        $all_states = $existing['agents'];
    } elseif ($existing) {
        $all_states['1'] = $existing; // Legacy migration
    }
}

$agent_id = isset($decoded['agent_id']) ? $decoded['agent_id'] : '1';
$all_states[$agent_id] = $decoded;

// Remove any agent data older than 2 minutes (they probably finished/died)
foreach ($all_states as $id => $state) {
    if (isset($state['_last_update']) && (time() - $state['_last_update'] > 120)) {
        unset($all_states[$id]);
    }
}
$all_states[$agent_id]['_last_update'] = time();

// Merge logic to create a unified state for the frontend
$merged = [
    'agents' => $all_states,
    'batch_num' => 0,
    'status' => 'Swarm Active (' . count($all_states) . ' agents)',
    'model' => isset($decoded['model']) ? $decoded['model'] : '',
    'active_tools' => [],
    'terminal_feed' => [],
    'modifications' => [],
    'unknown_fns' => isset($decoded['unknown_fns']) ? $decoded['unknown_fns'] : '0',
    'unknown_thunks' => isset($decoded['unknown_thunks']) ? $decoded['unknown_thunks'] : '0',
    'unknown_dat' => isset($decoded['unknown_dat']) ? $decoded['unknown_dat'] : '0',
    'unknown_ptr' => isset($decoded['unknown_ptr']) ? $decoded['unknown_ptr'] : '0',
    'unknown_floats' => isset($decoded['unknown_floats']) ? $decoded['unknown_floats'] : '0',
    'unknown_strings' => isset($decoded['unknown_strings']) ? $decoded['unknown_strings'] : '0',
    'unknown_vtables' => isset($decoded['unknown_vtables']) ? $decoded['unknown_vtables'] : '0',
    'unknown_vars' => isset($decoded['unknown_vars']) ? $decoded['unknown_vars'] : '0',
    'total_fns' => isset($decoded['total_fns']) ? $decoded['total_fns'] : '0',
    'total_vars' => isset($decoded['total_vars']) ? $decoded['total_vars'] : '0',
    'total_mods_all_time' => 0,
    'total_tokens' => 0,
    'tps' => 0,
    'eta' => isset($decoded['eta']) ? $decoded['eta'] : 'Calculating...',
    'pipeline_phase' => isset($decoded['pipeline_phase']) ? $decoded['pipeline_phase'] : 1,
    'recompiler_status' => isset($decoded['recompiler_status']) ? $decoded['recompiler_status'] : 'Standby',
    'recompiler_attempts' => isset($decoded['recompiler_attempts']) ? $decoded['recompiler_attempts'] : 0,
    'compile_errors' => isset($decoded['compile_errors']) ? $decoded['compile_errors'] : 0,
    'last_build_output' => isset($decoded['last_build_output']) ? $decoded['last_build_output'] : '',
    'extracted_files' => isset($decoded['extracted_files']) ? $decoded['extracted_files'] : 0
];

foreach ($all_states as $id => $state) {
    $merged['total_mods_all_time'] += isset($state['total_mods_all_time']) ? (int)$state['total_mods_all_time'] : 0;
    $merged['total_tokens'] += isset($state['total_tokens']) ? (int)$state['total_tokens'] : 0;
    $merged['tps'] += isset($state['tps']) ? (float)$state['tps'] : 0;
    $merged['batch_num'] = max($merged['batch_num'], isset($state['batch_num']) ? (int)$state['batch_num'] : 0);
    
    $merged['pipeline_phase'] = max($merged['pipeline_phase'], isset($state['pipeline_phase']) ? (int)$state['pipeline_phase'] : 1);
    
    if (isset($state['recompiler_status']) && $state['recompiler_status'] !== 'Standby') {
        $merged['recompiler_status'] = $state['recompiler_status'];
    }
    $merged['recompiler_attempts'] = max($merged['recompiler_attempts'], isset($state['recompiler_attempts']) ? (int)$state['recompiler_attempts'] : 0);
    $merged['compile_errors'] = max($merged['compile_errors'], isset($state['compile_errors']) ? (int)$state['compile_errors'] : 0);
    $merged['extracted_files'] = max($merged['extracted_files'], isset($state['extracted_files']) ? (int)$state['extracted_files'] : 0);
    
    if (!empty($state['last_build_output'])) {
        $merged['last_build_output'] = $state['last_build_output'];
    }
    
    if (isset($state['active_tools']) && is_array($state['active_tools'])) {
        $merged['active_tools'] = array_unique(array_merge($merged['active_tools'], $state['active_tools']));
    }
    
    if (isset($state['terminal_feed']) && is_array($state['terminal_feed'])) {
        foreach ($state['terminal_feed'] as $feed) {
            $feed['content'] = "[Agent $id] " . $feed['content'];
            $merged['terminal_feed'][] = $feed;
        }
    }
    
    if (isset($state['modifications']) && is_array($state['modifications'])) {
        foreach ($state['modifications'] as $mod) {
            $mod['action'] = "[Agent $id] " . $mod['action'];
            $merged['modifications'][] = $mod;
        }
    }
}

usort($merged['terminal_feed'], function($a, $b) {
    return strtotime($a['timestamp']) - strtotime($b['timestamp']);
});
$merged['terminal_feed'] = array_slice($merged['terminal_feed'], -50);

usort($merged['modifications'], function($a, $b) {
    return strtotime($b['timestamp']) - strtotime($a['timestamp']);
});
$merged['modifications'] = array_slice($merged['modifications'], 0, 20);

$merged['tps'] = sprintf("%.1f", $merged['tps']);

file_put_contents($temp_file, json_encode($merged));
rename($temp_file, $target_file);

// 6. Return success
echo json_encode(["status" => "success", "message" => "Telemetry synchronized and aggregated."]);
?>
