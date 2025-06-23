<?php

// --- Session Configuration ---
$rootDir = dirname(__DIR__);
$session_save_path = $rootDir . '/storage/sessions';
if (!is_dir($session_save_path)) {
    mkdir($session_save_path, 0777, true);
}
session_save_path($session_save_path);
// --- End Session Configuration ---

session_start();

require_once $rootDir . '/vendor/autoload.php';
require_once $rootDir . '/config/config.php';
$appConfig = require $rootDir . '/config/config.php';

// The URI Google will redirect to after successful authorization.
// This must match one of the Authorized redirect URIs configured in your Google Cloud Project.
define('REDIRECT_URI', 'http://localhost:8000/oauth_callback.php');

$client = new Google\Client();
$client->setAuthConfig($appConfig['services']['google']['credentials_path']);
$client->setRedirectUri(REDIRECT_URI);

// Define the scopes needed for Gmail access.
// https://developers.google.com/gmail/api/auth/scopes
$client->addScope('https://www.googleapis.com/auth/gmail.readonly');
$client->addScope('https://www.googleapis.com/auth/gmail.send');
$client->addScope('https://www.googleapis.com/auth/gmail.modify');
$client->addScope('https://www.googleapis.com/auth/contacts.readonly');
$client->addScope('https://www.googleapis.com/auth/contacts.other.readonly');
$client->setAccessType('offline'); // Crucial: Request a refresh token
$client->setPrompt('consent');   // Force a new refresh token to be issued.

// Create a state token to prevent request forgery and store it in the session.
$state = bin2hex(random_bytes(16));
$_SESSION['oauth2state'] = $state;
$client->setState($state);

error_log("gmail_oauth.php: Current GET parameters: " . print_r($_GET, true));
$action = $_GET['action'] ?? '';

if ($action === 'connect') {
    error_log("gmail_oauth.php: 'connect' action detected. Initiating OAuth flow.");
    
    // Redirect to Google's OAuth 2.0 server
    $authUrl = $client->createAuthUrl();
    error_log("gmail_oauth.php: Generated Auth URL: " . $authUrl);
    header('Location: ' . $authUrl);
    error_log("gmail_oauth.php: Redirect header sent. Exiting.");
    exit();
} else {
    // Default action or error
    error_log("gmail_oauth.php: No 'connect' action. Redirecting to index.");
    header('Location: index.php');
    exit();
}

?> 