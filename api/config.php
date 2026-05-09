<?php
/**
 * PSOBB Website Core Configuration
 * 
 * This file handles environment variable parsing, global configuration constants,
 * and secure session management. It must be included at the top of all API endpoints
 * and frontend pages.
 */

/**
 * Parses a .env file and loads its contents into $_ENV and $_SERVER.
 *
 * @param string $path The absolute path to the .env file.
 * @return void
 */
function loadEnv($path) {
    if(!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach($lines as $line) {
        $trimmed = trim($line);
        if(strpos($trimmed, '#') === 0) continue;
        if(strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if(!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// Load from project root
$envPath = __DIR__ . '/../.env';
loadEnv($envPath);

// Core Configuration
$NEWSERV_API_URL = $_ENV['NEWSERV_API_URL'] ?? 'http://127.0.0.1:8443';
$NEWSERV_COMMAND_PREFIX = $_ENV['NEWSERV_COMMAND_PREFIX'] ?? '$';

// Email Configuration
$SMTP_ENABLED = false; 
$SMTP_HOST = 'smtp.gmail.com';
$BREVO_API_KEY = $_ENV['BREVO_API_KEY'] ?? '';
$SMTP_FROM = $_ENV['SMTP_FROM'] ?? 'noreply@psobb.io';

// Integrations
$GEMINI_API_KEY = $_ENV['GEMINI_API_KEY'] ?? '';

// Discord OAuth2 Configuration
$DISCORD_CLIENT_ID = $_ENV['DISCORD_CLIENT_ID'] ?? '';
$DISCORD_CLIENT_SECRET = $_ENV['DISCORD_CLIENT_SECRET'] ?? '';
$DISCORD_REDIRECT_URI = $_ENV['DISCORD_REDIRECT_URI'] ?? '';

// Discord Bot API
$BOT_API_SECRET = $_ENV['BOT_API_SECRET'] ?? '';

// Discord Bot Token (used by cron_streak_alert.php for DM alerts)
$BOT_TOKEN = $_ENV['BOT_TOKEN'] ?? '';

class SQLiteSessionHandler implements SessionHandlerInterface {
    private $db;

    public function __construct() {
        require_once __DIR__ . '/db.php';
        $this->db = get_db();
    }

    #[\ReturnTypeWillChange]
    public function open($path, $name) {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function close() {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function read($id) {
        $stmt = $this->db->prepare('SELECT data FROM sessions WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            return $row['data'];
        }
        return '';
    }

    #[\ReturnTypeWillChange]
    public function write($id, $data) {
        $stmt = $this->db->prepare('REPLACE INTO sessions (id, data, last_accessed) VALUES (:id, :data, :time)');
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $stmt->bindValue(':data', $data, SQLITE3_TEXT);
        $stmt->bindValue(':time', time(), SQLITE3_INTEGER);
        return (bool)$stmt->execute();
    }

    #[\ReturnTypeWillChange]
    public function destroy($id) {
        $stmt = $this->db->prepare('DELETE FROM sessions WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        return (bool)$stmt->execute();
    }

    #[\ReturnTypeWillChange]
    public function gc($max_lifetime) {
        $stmt = $this->db->prepare('DELETE FROM sessions WHERE last_accessed < :old');
        $stmt->bindValue(':old', time() - $max_lifetime, SQLITE3_INTEGER);
        $stmt->execute();
        return $this->db->changes();
    }
}

/**
 * Initializes a secure, HTTP-only session using the SQLite backend.
 * 
 * Sets the session duration to 30 days and automatically generates a 
 * 32-byte CSRF token upon session creation to protect against Cross-Site Request Forgery.
 *
 * @return void
 */
function start_secure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        // Register the custom SQLite session handler
        $handler = new SQLiteSessionHandler();
        session_set_save_handler($handler, true);
        
        // Ensure sessions last 30 days
        ini_set('session.cookie_httponly', 1);
        ini_set('session.gc_maxlifetime', 86400 * 30);
        
        // Enable PHP's internal GC probability so it cleans old DB rows
        ini_set('session.gc_probability', 1);
        ini_set('session.gc_divisor', 100);

        session_set_cookie_params(86400 * 30, '/');
        session_start();
        
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
}

/**
 * Validates an incoming CSRF token against the active session.
 * 
 * If the token is missing or invalid, execution is halted immediately and a 
 * 403 Forbidden HTTP status is returned.
 *
 * @param string $token The CSRF token extracted from the request headers or body.
 * @return void
 */
function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], trim($token))) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid or missing CSRF token.']);
        exit;
    }
}

// Load Localization
require_once __DIR__ . '/lang.php';
?>
