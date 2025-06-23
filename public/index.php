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

// --- Application Configuration ---
$config = require $rootDir . '/config/config.php';
$app_debug_mode = $config['app_debug_mode'] ?? false;

// Handle user logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Check if the user is authenticated with Google
$is_connected = isset($_SESSION['google_access_token']) && !empty($_SESSION['google_access_token']);

// Handle the status from the OAuth callback
$status = $_GET['status'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Gmail Assistant Chat</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            AI Gmail Assistant
        </div>
        <div class="chat-messages" id="chat-messages">
            <div class="message ai"><strong>AI:</strong> Hello! How can I help you today?</div>
        </div>
        <div class="gmail-status" id="gmail-status">
            Gmail Status: <span class="<?php echo $is_connected ? 'connected' : 'disconnected'; ?>">
                <?php echo $is_connected ? 'Connected' : 'Not Connected'; ?>
            </span>
        </div>
        <div class="chat-input">
            <input type="text" id="user-input" placeholder="Type your message here..." <?php echo !$is_connected ? 'disabled' : ''; ?>>
            <button id="send-button" <?php echo !$is_connected ? 'disabled' : ''; ?>>Send</button>
        </div>
        <div class="gmail-actions">
            <?php if ($is_connected): ?>
                <a href="?action=logout" class="button">Disconnect Gmail</a>
            <?php else: ?>
                <a href="gmail_oauth.php?action=connect" class="button">Connect Gmail</a>
            <?php endif; ?>
        </div>
        <div class="readme-link">
            <a href="readme.php">View README</a>
        </div>
    </div>

    <?php if ($app_debug_mode): ?>
    <!-- Collapsible Debug Log Panel -->
    <div class="log-panel" id="log-panel">
        <div class="log-header" id="log-header">Debug Log ▲</div>
        <pre class="log-content" id="log-content"></pre>
    </div>
    <?php endif; ?>

    <script src="script.js?v=<?php echo filemtime('script.js'); ?>"></script>
</body>
</html> 