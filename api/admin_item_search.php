<?php
/**
 * Admin: Item Search
 * Searches item_hex.txt by name and returns matching items with their hex codes.
 * Used by the special deliveries admin page to look up item IDs.
 */
require_once 'config.php';
start_secure_session();
header('Content-Type: application/json');

if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$q = strtolower(trim($_GET['q'] ?? ''));
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$path = __DIR__ . '/../item_hex.txt';
if (!file_exists($path)) {
    http_response_code(500);
    echo json_encode(['error' => 'item_hex.txt not found']);
    exit;
}

$results = [];
$limit   = 20;

$lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    // Format: "  XXXXXX => [data...]   NAME"
    if (!preg_match('/^\s*([0-9A-Fa-f]{6})\s*=>.*\s{2,}(\S.*?)$/', $line, $m)) continue;

    $code = strtoupper($m[1]);
    $name = trim($m[2]);

    // Skip internal/unnamed entries
    if (str_starts_with($name, '!ID:') || $name === '') continue;

    if (str_contains(strtolower($name), $q)) {
        // Determine item category from first byte of code
        $byte0 = hexdec(substr($code, 0, 2));
        $byte1 = hexdec(substr($code, 2, 2));

        if ($byte0 === 0x00) {
            $cat = 'Weapon';
        } elseif ($byte0 === 0x01 && $byte1 === 0x01) {
            $cat = 'Armor';
        } elseif ($byte0 === 0x01 && $byte1 === 0x02) {
            $cat = 'Shield';
        } elseif ($byte0 === 0x01 && $byte1 === 0x03) {
            $cat = 'Unit';
        } elseif ($byte0 === 0x02) {
            $cat = 'Mag';
        } elseif ($byte0 === 0x03) {
            $cat = 'Disk';
        } elseif ($byte0 === 0x04) {
            $cat = 'Meseta';
        } else {
            $cat = 'Tool';
        }

        $results[] = ['code' => $code, 'name' => $name, 'cat' => $cat];

        if (count($results) >= $limit) break;
    }
}

// Exact matches first
usort($results, function ($a, $b) use ($q) {
    $aExact = strtolower($a['name']) === $q ? 0 : 1;
    $bExact = strtolower($b['name']) === $q ? 0 : 1;
    if ($aExact !== $bExact) return $aExact - $bExact;
    // Then starts-with
    $aStart = str_starts_with(strtolower($a['name']), $q) ? 0 : 1;
    $bStart = str_starts_with(strtolower($b['name']), $q) ? 0 : 1;
    return $aStart - $bStart;
});

echo json_encode(array_values($results));
