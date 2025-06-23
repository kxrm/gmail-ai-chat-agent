<?php

// --- Session Configuration ---
$session_save_path = dirname(__DIR__) . '/storage/sessions';
if (!is_dir($session_save_path)) {
    mkdir($session_save_path, 0777, true);
}
session_save_path($session_save_path);
// --- End Session Configuration ---

session_start();
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/config.php';

// Load application config
$appConfig = require dirname(__DIR__) . '/config/config.php';

// The Redirect URI must match exactly what's in gmail_oauth.php and your Google Console.
define('REDIRECT_URI', 'http://localhost:8000/oauth_callback.php');

$client = new Google\Client();
$client->setAuthConfig($appConfig['services']['google']['credentials_path']);
$client->setRedirectUri(REDIRECT_URI);
$client->setAccessType('offline');

// Check if the state value matches to prevent CSRF attacks
if (!isset($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
    unset($_SESSION['oauth2state']);
    exit('Invalid state');
}

if (!isset($_GET['code'])) {
    // If there's no code, something went wrong. Redirect to the start.
    header('Location: index.php?error=oauth_no_code');
    exit();
}

try {
    // Exchange the authorization code for an access token and a refresh token
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    
    // CRITICAL: Check if Google returned an error instead of a token.
    if (isset($token['error'])) {
        $errorMessage = "Google OAuth Error: " . $token['error'] . " - " . ($token['error_description'] ?? 'No description provided.');
        error_log($errorMessage);
        // Redirect with a user-friendly error. The user MUST check their client_secret.json
        header('Location: index.php?status=error&message=' . urlencode('Authentication failed: The OAuth client configuration is invalid. Please check your client_secret.json file.'));
        exit();
    }
    
    // The google-api-php-client library automatically sets the access token
    // on the client object, but we need to store it in the session for
    // subsequent requests.
    $_SESSION['google_access_token'] = $token;

    // A refresh token is only returned on the first authorization from the user.
    // We must save it securely for future use.
    if (isset($token['refresh_token'])) {
        $_SESSION['google_refresh_token'] = $token['refresh_token'];
    }
    
    // Redirect back to the main chat page with a success status
    header('Location: index.php?status=connected');
    exit();

} catch (Exception $e) {
    // If there is an error, log it and redirect with an error status
    error_log('OAuth Callback Error: ' . $e->getMessage());
    header('Location: index.php?status=error&message=' . urlencode($e->getMessage()));
    exit();
} 