<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Session Configuration ---
// Set a dedicated, writable directory for session files.
$rootDir = dirname(dirname(__DIR__));
$session_save_path = $rootDir . '/storage/sessions';
if (!is_dir($session_save_path)) {
    mkdir($session_save_path, 0777, true);
}
session_save_path($session_save_path);
// --- End Session Configuration ---

session_start();

header('Content-Type: application/json');

// Autoload vendor libraries
require_once $rootDir . '/vendor/autoload.php';

// --- Application Configuration ---
$config = require $rootDir . '/config/config.php';

// Include necessary application files
require_once $rootDir . '/services/Service.php';
require_once $rootDir . '/services/GoogleService.php';
require_once $rootDir . '/core/ServiceRegistry.php';
require_once $rootDir . '/core/OllamaClient.php';
require_once $rootDir . '/core/ChatManager.php';


use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use App\Services\ServiceRegistry;


// --- Main Application Logic ---

$response = [];

try {
    // --- Dependency Injection Setup ---
    $logger = new Logger('chat_app');
    $logger->pushHandler(new StreamHandler($rootDir . '/storage/logs/chat_app.log', Logger::DEBUG));

    $ollamaClient = new OllamaClient($config['ollama_host'], $config['ollama_model'], $logger);
    
    $google_access_token = $_SESSION['google_access_token'] ?? null;
    $serviceRegistry = new ServiceRegistry($config['services'], $google_access_token, $logger);

    // --- System Prompt Loading ---
    $modelName = $config['ollama_model'];
    // Sanitize model name to create a valid filename, e.g., 'llama3:8b' -> 'llama3-8b'
    $sanitizedModelName = str_replace([':', '/'], '-', $modelName);
    $promptFile = $rootDir . '/prompts/' . $sanitizedModelName . '.txt';

    // NEW FALLBACK: If not found, try stripping version tag like "-latest" or colon variant
    if (!file_exists($promptFile)) {
        // Try again with only the part before the first colon (e.g. "gorilla-q4-custom" from "gorilla-q4-custom:latest")
        $baseModelName = explode(':', $modelName, 2)[0];
        $alternativeSanitized = str_replace([':', '/'], '-', $baseModelName);
        $altPrompt = $rootDir . '/prompts/' . $alternativeSanitized . '.txt';
        if (file_exists($altPrompt)) {
            $promptFile = $altPrompt;
            $logger->warning("Model-specific prompt for '{$modelName}' not found. Falling back to '{$alternativeSanitized}.txt'.");
        }
    }

    if (!file_exists($promptFile)) {
        // Fallback to the generic prompt if a model-specific one isn't found
        $promptFile = $rootDir . '/prompts/default.txt';
        $logger->warning("Model-specific prompt for '{$modelName}' not found. Falling back to generic prompt.txt.");
    }

    if (!file_exists($promptFile)) {
        throw new Exception("Could not find a system prompt file.");
    }

    $systemPrompt = file_get_contents($promptFile);
    // Replace placeholder with the actual user's name if available
    $userName = $_SESSION['user_display_name'] ?? 'there';
    $systemPrompt = str_replace('{{USER_DISPLAY_NAME}}', $userName, $systemPrompt);


    // Use PhpSession for production
    require_once $rootDir . '/core/PhpSession.php';
    $session = new App\Core\PhpSession();

    $chatManager = new ChatManager(
        $ollamaClient,
        $systemPrompt,
        $serviceRegistry,
        $logger,
        $config['ollama_model_capabilities'] ?? [],
        $config['app_debug_mode'] ?? false,
        $config['ollama_model'],
        $session
    );

    // --- Request Handling ---
    $rawInput = file_get_contents('php://input');
    $postData = json_decode($rawInput, true);
    if ($postData === null && json_last_error() !== JSON_ERROR_NONE) {
        // Lenient fallback: attempt to extract action/message with regex even if JSON is malformed (e.g. unescaped quotes)
        $postData = [];
        if (preg_match('/"action"\s*:\s*"([^"]+)"/i', $rawInput, $m)) {
            $postData['action'] = $m[1];
        }
        if (preg_match('/"message"\s*:\s*"(.+)"/is', $rawInput, $m)) {
            // Remove trailing quote if present
            $msg = rtrim($m[1], '"');
            $postData['message'] = stripcslashes($msg);
        }
        if (empty($postData)) {
            throw new Exception('Malformed JSON payload.');
        }
    }

    $action = $postData['action'] ?? 'send_message'; // Default to sending a message

    switch ($action) {
        case 'send_message':
            $userMessage = $postData['message'] ?? '';
            if (trim($userMessage) === '') {
                throw new Exception("Message cannot be empty.");
            }
            $response = $chatManager->processMessage($userMessage);
            break;
        
        case 'continue_processing':
            $response = $chatManager->processMessage(null); // Continue without new user input
            break;

        case 'execute_tool':
            $toolName = $postData['tool_name'] ?? '';
            $arguments = $postData['arguments'] ?? [];
            if (empty($toolName)) {
                throw new Exception("Tool name is required for execute_tool action.");
            }
            $response = $chatManager->executeTool($toolName, $arguments);
            break;

        case 'reset_chat':
            $chatManager->resetChat();
            $response = ['type' => 'success', 'content' => 'Chat history reset.'];
            break;
            
        default:
            throw new Exception("Unknown action.");
    }

} catch (Exception $e) {
    // If an exception is thrown that indicates an auth failure, log the user out.
    if (str_contains($e->getMessage(), 'Google has expired')) {
        session_destroy();
    }
    $logger->error("An error occurred in ajax_handler: " . $e->getMessage());
    http_response_code(500);
    $response = ['type' => 'error', 'content' => $e->getMessage()];
}

echo json_encode($response);
exit;

// Should not happen, but as a fallback.
header('Content-Type: application/json');
echo json_encode(['type' => 'error', 'content' => 'Invalid action.']);

?>
