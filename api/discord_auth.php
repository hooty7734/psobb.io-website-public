<?php
/**
 * --------------------------------------------------------------------------
 * Discord OAuth2 - Authorization Initiator
 * --------------------------------------------------------------------------
 * This endpoint kicks off the Discord OAuth2 linking process. When a user clicks
 * "Link Discord" from their dashboard, they are sent here.
 *
 * Primary Duties:
 * 1. Ensure the user is currently authenticated natively with their PSOBB account.
 * 2. Generate a cryptographically secure CSRF 'state' token and bind it to their session.
 * 3. Construct the official Discord Authorization URL and perform a 302 Redirect.
 */
require_once 'config.php';
start_secure_session();

// 1. Session Enforcement
// Only allow logged in users to initiate a link limit exposure and orphaned links.
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php?error=session_expired");
    exit;
}

// 2. Integration Verification
// Both values MUST be set inside `api/config.php` (derived from .env).
if (empty($DISCORD_CLIENT_ID) || empty($DISCORD_REDIRECT_URI)) {
    die("Discord OAuth is not fully configured in config.php. Please add your DISCORD_CLIENT_ID and DISCORD_REDIRECT_URI.");
}

// 3. CSRF Protection (State Generation)
// We generate a random hex string and store it in their secure PHP session.
// When Discord redirects back to our callback, we MUST verify this state matches
// exactly. This prevents attackers from forging auth responses and linking the wrong discord account.
$state = bin2hex(random_bytes(16));
$_SESSION['discord_state'] = $state;

// 4. Construct OAuth Payload
$params = [
    'client_id' => $DISCORD_CLIENT_ID,
    'redirect_uri' => $DISCORD_REDIRECT_URI,
    'response_type' => 'code', // Standard Authorization Code Grant Flow
    'scope' => 'identify',     // We only request their basic Identity (Username/ID), NOT their servers/email
    'state' => $state
];

// 5. Fire Redirect
// Redirect the player's browser directly to Discord to ask for their consent.
$discord_url = 'https://discord.com/api/oauth2/authorize?' . http_build_query($params);
header("Location: $discord_url");
exit;
?>
