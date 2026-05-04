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

/**
 * Initializes a secure, HTTP-only session with a dedicated save path.
 * 
 * Sets the session duration to 30 days and automatically generates a 
 * 32-byte CSRF token upon session creation to protect against Cross-Site Request Forgery.
 *
 * @return void
 */
function start_secure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        $session_dir = sys_get_temp_dir() . '/psobb_sessions';
        if (!is_dir($session_dir)) {
            @mkdir($session_dir, 0777, true);
        }
        session_save_path($session_dir);
        
        ini_set('session.cookie_httponly', 1);
        ini_set('session.gc_maxlifetime', 86400 * 30);
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
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid or missing CSRF token.']);
        exit;
    }
}

// Load Localization
require_once __DIR__ . '/lang.php';
?>
