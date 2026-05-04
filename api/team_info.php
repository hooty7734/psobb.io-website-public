<?php
require_once 'config.php';

if (ob_get_length())
    ob_clean();

start_secure_session();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['account_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in', 'success' => false]);
    exit;
}

$accountId = (int) $_SESSION['user']['account_id'];

// 1. Fetch user account to get BBTeamID
$accountUrl = $NEWSERV_API_URL . '/y/account/' . $accountId;
$accountResponse = @file_get_contents($accountUrl);

if ($accountResponse === FALSE) {
    http_response_code(502);
    echo json_encode(['error' => 'Failed to connect to the game server.', 'success' => false]);
    exit;
}

$accountData = json_decode($accountResponse, true);
if (!isset($accountData['BBTeamID']) || $accountData['BBTeamID'] == 0) {
    // User is not in a team
    echo json_encode(['success' => true, 'hasTeam' => false]);
    exit;
}

$teamId = (int) $accountData['BBTeamID'];

// 2. Fetch team data
$teamUrl = $NEWSERV_API_URL . '/y/team/' . $teamId;
$teamResponse = @file_get_contents($teamUrl);

if ($teamResponse === FALSE) {
    http_response_code(404);
    echo json_encode(['error' => 'Team not found.', 'success' => false]);
    exit;
}

$teamData = json_decode($teamResponse, true);
if (!$teamData) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid team data from server.', 'success' => false]);
    exit;
}

// 3. Determine user role and format response
$myMemberData = null;
foreach ($teamData['Members'] as $member) {
    if ((int) $member['AccountID'] === $accountId) {
        $myMemberData = $member;
        break;
    }
}

if (!$myMemberData) {
    // Fallback: the account says it belongs to a team but the team doesn't list them
    echo json_encode(['success' => true, 'hasTeam' => false, 'error' => 'Not found in team roster']);
    exit;
}

// Flags: IS_MASTER = 0x01, IS_LEADER = 0x02
$flags = (int) $myMemberData['Flags'];
$isMaster = ($flags & 0x01) !== 0;
$isLeader = ($flags & 0x02) !== 0;
$role = 'Member';
if ($isMaster) {
    $role = 'Master';
} else if ($isLeader) {
    $role = 'Leader';
}

// Calculate team size limits based on RewardFlags
// MEMBERS_20_LEADERS_3 = 0x04
// MEMBERS_40_LEADERS_5 = 0x08
// MEMBERS_70_LEADERS_8 = 0x10
// MEMBERS_100_LEADERS_10 = 0x20
$rf = (int) $teamData['RewardFlags'];
$maxMembers = 10;
if ($rf & 0x20)
    $maxMembers = 100;
else if ($rf & 0x10)
    $maxMembers = 70;
else if ($rf & 0x08)
    $maxMembers = 40;
else if ($rf & 0x04)
    $maxMembers = 20;

$teamViewData = [
    'success' => true,
    'hasTeam' => true,
    'TeamName' => $teamData['Name'],
    'TeamID' => $teamId,
    'TotalPoints' => $teamData['SpentPoints'] + array_sum(array_column($teamData['Members'], 'Points')),
    'UnspentPoints' => array_sum(array_column($teamData['Members'], 'Points')),
    'MemberCount' => count($teamData['Members']),
    'MaxMembers' => $maxMembers,
    'RewardKeys' => $teamData['RewardKeys'] ?? [],
    'MyRole' => $role,
    'MyPoints' => $myMemberData['Points']
];

// If Master or Leader, could expose full roster, but user wants Master to have aspects, Members only to see what's unlocking next.
// We'll expose full roster to Master.
if ($isMaster || $isLeader) {
    $teamViewData['Roster'] = $teamData['Members'];
}

echo json_encode($teamViewData);
?>