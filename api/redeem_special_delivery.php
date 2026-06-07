<?php
/**
 * PSOBB API: Redeem Special Item Delivery
 *
 * GET  — returns count of pending deliveries for the logged-in player.
 * POST — claims a specific delivery (player must be in-game for item to drop).
 */
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';

if (ob_get_length()) ob_clean();
start_secure_session();
header('Content-Type: application/json');

if (empty($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$accountId = (int)$_SESSION['user']['account_id'];
$db = get_db();

// ----------------------------------------------------------------
// GET — return count + list of pending deliveries for this player
// ----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("
        SELECT id, item_name, admin_note, created_at
        FROM special_deliveries
        WHERE recipient_id = :uid AND status = 'pending'
        ORDER BY created_at ASC
    ");
    $stmt->bindValue(':uid', $accountId, SQLITE3_INTEGER);
    $res = $stmt->execute();

    $items = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $items[] = [
            'id'         => (int)$row['id'],
            'item_name'  => $row['item_name'],
            'admin_note' => $row['admin_note'],
            'created_at' => $row['created_at'],
        ];
    }

    echo json_encode(['count' => count($items), 'items' => $items]);
    exit;
}

// ----------------------------------------------------------------
// POST — claim a specific delivery
// ----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($input['id'] ?? 0);

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Delivery ID required']);
        exit;
    }

    // Fetch and lock the delivery row
    $stmt = $db->prepare("
        SELECT id, item_name, item_string
        FROM special_deliveries
        WHERE id = :id AND recipient_id = :uid AND status = 'pending'
        LIMIT 1
    ");
    $stmt->bindValue(':id',  $id,        SQLITE3_INTEGER);
    $stmt->bindValue(':uid', $accountId, SQLITE3_INTEGER);
    $res = $stmt->execute();
    $delivery = $res->fetchArray(SQLITE3_ASSOC);

    if (!$delivery) {
        http_response_code(404);
        echo json_encode(['error' => 'Delivery not found or already claimed']);
        exit;
    }

    // parse_and_drop_items checks function_exists('buildHexPayload') and falls back to
    // a stub that strips all attributes if it's not defined. Define the full version here,
    // the same way redeem_bounty.php does, so attributes and grind are encoded correctly.
    if (!function_exists('buildHexPayload')) {
        function buildHexPayload($itemStr)
        {
            $itemStr = trim($itemStr);
            if (empty($itemStr)) return $itemStr;
            $parts     = explode(' ', $itemStr);
            $firstPart = array_shift($parts);
            if (ctype_xdigit($firstPart) && strlen($firstPart) >= 6) {
                $hex  = str_pad(substr($firstPart, 0, 32), 32, '0');
                $data = hex2bin($hex);
                $is_weapon       = ($data[0] === "\x00");
                $is_armor_shield = ($data[0] === "\x01" && ($data[1] === "\x01" || $data[1] === "\x02"));
                $is_unit         = ($data[0] === "\x01" && $data[1] === "\x03");
                if ($is_weapon) {
                    if (!empty($parts) && strpos($parts[0], '/') !== false) {
                        $stats = explode('/', $parts[0]);
                        $idx   = 6;
                        for ($i = 0; $i < 5; $i++) {
                            if (isset($stats[$i]) && (int)$stats[$i] > 0 && $idx < 12) {
                                $data[$idx]     = chr($i + 1);
                                $data[$idx + 1] = chr((int)$stats[$i]);
                                $idx += 2;
                            }
                        }
                    }
                } elseif ($is_armor_shield) {
                    foreach ($parts as $token) {
                        if (substr($token, 0, 1) === '+') {
                            if (strpos($token, 'def') !== false) {
                                $val = intval(str_replace('def', '', substr($token, 1)));
                                $data[6] = chr($val & 0xFF); $data[7] = chr(($val >> 8) & 0xFF);
                            } elseif (strpos($token, 'evp') !== false) {
                                $val = intval(str_replace('evp', '', substr($token, 1)));
                                $data[8] = chr($val & 0xFF); $data[9] = chr(($val >> 8) & 0xFF);
                            } else {
                                $data[5] = chr(intval(substr($token, 1)) & 0xFF);
                            }
                        }
                    }
                } elseif ($is_unit) {
                    foreach ($parts as $token) {
                        if (preg_match('/^\+(\d+)$/', $token, $m)) {
                            $val = (int)$m[1];
                            $data[6] = chr($val & 0xFF); $data[7] = chr(($val >> 8) & 0xFF);
                        }
                    }
                }
                return strtoupper(bin2hex($data));
            }
            return $itemStr;
        }
    }

    // Attempt to deliver via newserv (player must be in-game)
    $result = parse_and_drop_items($accountId, $delivery['item_string']);

    if (!$result['success']) {
        $errMsg = $result['error'] ?? 'Unknown error';

        // Detect offline / not found specifically so the UI can give a helpful message
        $offline = (stripos($errMsg, 'not found') !== false ||
                    stripos($errMsg, 'offline') !== false ||
                    stripos($errMsg, 'connect') !== false);

        http_response_code($offline ? 503 : 500);
        echo json_encode([
            'error'   => $errMsg,
            'offline' => $offline,
        ]);
        exit;
    }

    // Mark as redeemed
    $upd = $db->prepare("
        UPDATE special_deliveries
        SET status = 'redeemed', redeemed_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    $upd->bindValue(':id', $id, SQLITE3_INTEGER);
    $upd->execute();

    echo json_encode([
        'success'   => true,
        'item_name' => $delivery['item_name'],
        'message'   => "Your item has been delivered! Check your floor.",
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
