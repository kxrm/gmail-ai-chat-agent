<?php

require_once __DIR__ . '/OllamaClient.php';
require_once __DIR__ . '/ServiceRegistry.php';
require_once __DIR__ . '/GmailHelpers.php';
require_once __DIR__ . '/SessionInterface.php';
require_once __DIR__ . '/PhpSession.php';
require_once __DIR__ . '/ResponseBuilder.php';
require_once __DIR__ . '/RetryService.php';
require_once __DIR__ . '/EmailSummaryService.php';

use Psr\Log\LoggerInterface as Logger;
use League\CommonMark\CommonMarkConverter;
use App\Services\ServiceRegistry;
use App\Core\SessionInterface;
use App\Core\PhpSession;
use App\Core\ResponseBuilder;
use App\Core\RetryService;
use App\Core\EmailSummaryService;
use League\CommonMark\Exception\Exception;

class ChatManager
{
    private string $systemPrompt;
    private ServiceRegistry $serviceRegistry;
    private Logger $logger;
    private OllamaClient $ollamaClient;
    private CommonMarkConverter $markdownConverter;
    private array $modelCapabilities;
    private bool $useToolRole = false;
    private array $debugInfo = [];
    private bool $debugModeEnabled = false;
    private string $modelFamily = 'llama'; // or 'gorilla'
    private SessionInterface $session;
    private RetryService $retryService;
    private EmailSummaryService $emailSummaryService;

    public function __construct(
        OllamaClient $ollamaClient,
        string $systemPrompt,
        ServiceRegistry $serviceRegistry,
        Logger $logger,
        array $modelCapabilities = [],
        bool $debugModeEnabled = false,
        string $modelName = '',
        ?SessionInterface $session = null,
        ?RetryService $retryService = null,
        ?EmailSummaryService $emailSummaryService = null
    ) {
        $this->ollamaClient = $ollamaClient;
        $this->systemPrompt = $systemPrompt;
        $this->serviceRegistry = $serviceRegistry;
        $this->logger = $logger;
        $this->modelCapabilities = $modelCapabilities;
        $this->useToolRole = in_array('tool-role', $this->modelCapabilities, true);
        $this->markdownConverter = new CommonMarkConverter(['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $this->debugModeEnabled = $debugModeEnabled;
        $this->session = $session ?? new PhpSession();
        $this->retryService = $retryService ?? new RetryService($logger);
        $this->emailSummaryService = $emailSummaryService ?? new EmailSummaryService($this->session, $logger, $this->useToolRole);
        if (strpos($modelName, 'gorilla') !== false) {
            $this->modelFamily = 'gorilla';
        }

        if (empty($this->session->get('chat_history', []))) {
            $this->resetChat();
        }
    }

    private function packageResponse(array $response): array
    {
        $debug = ($this->debugModeEnabled && !empty($this->debugInfo)) ? $this->debugInfo : [];
        
        // If response already has debug info, merge it
        if (isset($response['_debug'])) {
            $debug = array_merge($debug, $response['_debug']);
            unset($response['_debug']);
        }
        
        // Use ResponseBuilder to ensure consistent packaging
        $type = $response['type'] ?? 'response';
        unset($response['type']);
        
        return ResponseBuilder::custom($type, $response, $debug);
    }

    public function processMessage(?string $message): array
    {
        $this->debugInfo = []; // Reset debug info for each call

        // Basic guard-rail: reject unreasonably large user inputs that would blow out context window.
        if ($message !== null && strlen($message) > 32000) {
            $this->logger->warning('User message exceeded max length; refusing.');
            return $this->packageResponse(ResponseBuilder::error('Your message is too long. Please shorten it and try again.'));
        }

        $userName = $this->session->get('user_display_name', 'there');

        if ($message) {
            $this->addToHistory('user', $message);

            // Quick command resolution: detect "mark ... as read/unread" and run tool directly
            $maybeHandled = $this->handleDirectMarkCommand($message);
            if ($maybeHandled !== null) {
                return $maybeHandled; // already executed & responded
            }
        }

        $this->logger->debug("Processing a turn with the AI.");
        $history = $this->prepareHistoryForOllama();
        
        // Use RetryService for AI response with validation
        $validator = RetryService::createAiResponseValidator();
        $operation = function () use ($history) {
            return $this->ollamaClient->chat($history, 'json');
        };
        
        $result = $this->retryService->executeWithRetry(
            $operation,
            $validator,
            3, // max retries
            "AI chat request"
        );
        
        if (!$result->isSuccessful()) {
            // All retries failed
            $this->debugInfo['retry_attempts'] = $result->getAttempts();
            
            return $this->packageResponse(ResponseBuilder::error(
                "I'm sorry, I had trouble understanding the response from the AI after {$result->getAttemptCount()} attempts. Please try again."
            ));
        }
        
        // Parse the successful response
        $jsonResponse = $result->getData();
        $response = json_decode(trim($jsonResponse), true);
        
        // Store retry information in debug info
        $this->debugInfo['retry_attempts'] = $result->getAttempts();
        $this->debugInfo['ollama_raw_response'] = $jsonResponse;
        $this->debugInfo['ollama_parsed_response'] = $response;
        
        return $this->processValidResponse($response);
    }
    

    
    private function processValidResponse(array $response): array
    {
        // Accommodate models that forget to wrap the tool call in a 'tool_call' object.
        if (isset($response['action']) && $response['action'] === 'tool_use' && !isset($response['tool_call']) && isset($response['tool_name'])) {
            $this->logger->info("Model returned a flat tool_use structure. Wrapping it for compatibility.");
            $response['tool_call'] = [
                'tool_name' => $response['tool_name'],
                'arguments' => $response['arguments'] ?? []
            ];
        }

        if (!isset($response['action']) || !in_array($response['action'], ['respond', 'tool_use'])) {
            // If the model responded directly with a tool-style payload (e.g. {"status":"success",...})
            if (isset($response['status'])) {
                $this->logger->info("Model returned a direct tool_use structure. Wrapping it for compatibility.");
                $response['tool_call'] = [
                    'tool_name' => $response['tool_name'],
                    'arguments' => $response['arguments'] ?? []
                ];
            }
        }

        return $this->processAction($response);
    }

    private function addToHistory(string $role, string $content): void
    {
        $this->logger->info("Adding to history: Role='$role'");
        
        $message = ['role' => $role, 'content' => $content];

        if ($role === 'tool') {
             // Find the last assistant message that requested a tool call
             $lastAssistantMsgIndex = -1;
             $chatHistory = $this->session->get('chat_history', []);
             for ($i = count($chatHistory) - 1; $i >= 0; $i--) {
                 if ($chatHistory[$i]['role'] === 'assistant') {
                     $lastAssistantMsgIndex = $i;
                     break;
                 }
             }
 
             if ($lastAssistantMsgIndex !== -1) {
                 $lastAssistantMsg = json_decode($chatHistory[$lastAssistantMsgIndex]['content'], true);
                 if (isset($lastAssistantMsg['action']) && $lastAssistantMsg['action'] === 'tool_use') {
                     $message['tool_call_id'] = $lastAssistantMsg['tool_name'] . '_' . $lastAssistantMsgIndex;
                 }
             }
        }
        
        $chatHistory = $this->session->get('chat_history', []);
        $chatHistory[] = $message;
        $this->session->set('chat_history', $chatHistory);
    }

    private function prepareHistoryForOllama(): array
    {
        $history = $this->session->get('chat_history', []);

        if ($this->modelFamily === 'gorilla') {
            $this->logger->debug("Formatting history for Gorilla model.");
            $gorillaHistory = [];
            // The very first message is the system prompt, which doesn't get wrapped.
            if (!empty($history) && $history[0]['role'] === 'system') {
                $gorillaHistory[] = array_shift($history);
            }
        
            foreach ($history as $message) {
                if ($message['role'] === 'user') {
                    $gorillaHistory[] = ['role' => 'user', 'content' => '[user] ' . $message['content']];
                } elseif ($message['role'] === 'assistant') {
                    $gorillaHistory[] = ['role' => 'assistant', 'content' => '[assistant] ' . $message['content']];
                } else {
                    $gorillaHistory[] = $message; // Keep tool role as-is
                }
            }
            return $gorillaHistory;
        }

        return $history;
    }

    public function resetChat(): void
    {
        $toolResultRole = $this->useToolRole ? 'tool' : 'user';

        // Use dynamic values so the model can't simply parrot the example back to the user.
        $sampleSender  = ['Alice', 'Bob', 'Carol', 'Dave'][array_rand(['Alice', 'Bob', 'Carol', 'Dave'])];
        $sampleSubject = ['Project Update', 'Lunch', 'Invoice', 'Event Invite'][array_rand(['Project Update', 'Lunch', 'Invoice', 'Event Invite'])];
        $sampleId      = substr(md5(uniqid('', true)), 0, 8);

        $exampleToolUse = [
            ['role' => 'user', 'content' => 'what are my unread emails?'],
            // 1. Assistant requests tool use
            ['role' => 'assistant', 'content' => json_encode([
                'action' => 'tool_use',
                'tool_name' => 'search_emails',
                'arguments' => ['query' => 'is:unread']
            ])],
            // 2. The system provides the tool's result
            ['role' => $toolResultRole, 'content' => json_encode([
                'status' => 'found_emails',
                'emails' => [
                    ['from' => "$sampleSender <sender@example.com>", 'subject' => $sampleSubject, 'message_id' => $sampleId]
                ]
            ])],
            // 3. The assistant summarizes the result
            ['role' => 'assistant', 'content' => json_encode([
                'action' => 'respond',
                'response_text' => "You have one unread email from $sampleSender about '$sampleSubject'."
            ])]
        ];

        $chatHistory = [
            ['role' => 'system', 'content' => $this->systemPrompt],
            // Few-shot example 1: Simple greeting
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => json_encode(['action' => 'respond', 'response_text' => 'Hello! How can I help you today?'])],
        ];

        // Add the tool use example to the history
        $chatHistory = array_merge($chatHistory, $exampleToolUse);
        $this->session->set('chat_history', $chatHistory);
        
        $this->logger->info("Chat history has been reset with few-shot examples.");
    }

    public function getChatHistory(): array {
        return $this->session->get('chat_history', []);
    }





    /** Store mapping of message_id => [sender, subject] for later validation */
    private function captureKnownEmailIds(string $summary): void
    {
        // ... (this function is not used and can be ignored)
    }

    /**
     * Detect a natural-language command like "Mark the email with subject \"Foo\" as read"
     * and execute mark_email directly. Returns response array or null.
     */
    private function handleDirectMarkCommand(string $msg): ?array
    {
        // Pattern to match: "mark ... as read/unread"
        if (preg_match('/mark\s+(.+?)\s+as\s+(read|unread)/i', $msg, $matches)) {
            $identifier = trim($matches[1]);
            $status = strtolower($matches[2]);

            // Try to find the ID in the last summary
            $id = $this->emailSummaryService->searchIdInLastSummary($identifier);
            if ($id) {
                return $this->executeMarkEmail($id, $status);
            }
        }
        return null;
    }

    private function executeMarkEmail(string $id, string $status): array
    {
        try {
            $result = $this->serviceRegistry->executeTool('mark_email', [
                'message_id' => $id,
                'status' => $status
            ]);

            if (isset($result['status']) && $result['status'] === 'success') {
                return $this->packageResponse(ResponseBuilder::success("Email successfully marked as $status."));
            } else {
                return $this->packageResponse(ResponseBuilder::error(
                    "Failed to mark email as $status: " . ($result['message'] ?? 'Unknown error')
                ));
            }
        } catch (\Exception $e) {
            $this->logger->error("Error executing mark_email: " . $e->getMessage());
            return $this->packageResponse(ResponseBuilder::error("An error occurred while marking the email."));
        }
    }







    private function processAction(array $response): array
    {
        $this->debugInfo['ollama_parsed_response'] = $response;

        if (isset($response['action']) && $response['action'] === 'tool_use') {
            // Extract tool call information
            $toolCall = $response['tool_call'] ?? $response; // Fallback for flat structure
            $toolName = $toolCall['tool_name'] ?? null;
            $arguments = $toolCall['arguments'] ?? [];

            if (!$toolName) {
                return $this->packageResponse(ResponseBuilder::error('Tool use requested but no tool name provided.'));
            }

            $this->logger->info("AI requested tool: $toolName", ['arguments' => $arguments]);
            $this->addToHistory('assistant', json_encode($response));

            // Special handling for unread_emails to avoid duplicates
            if ($toolName === 'unread_emails' && $this->emailSummaryService->isDuplicateUnreadCall()) {
                $this->logger->info("Duplicate unread_emails call detected. Using cached result.");

                // Ensure summaries after an unread_emails call include every email subject.
                $recentEmails = $this->emailSummaryService->getLastUnreadEmails();
                if (!empty($recentEmails) && $this->emailSummaryService->hasRecentUnreadResult()) {
                    $userName = $this->session->get('user_display_name', 'there');
                    $summary = $this->emailSummaryService->buildEmailSummary($recentEmails);
                    return $this->packageResponse(ResponseBuilder::success("$userName, $summary"));
                }
            }

            // Return tool call for execution by caller - don't auto-execute
            return $this->packageResponse(ResponseBuilder::toolCall($toolName, $arguments));

        } elseif (isset($response['action']) && $response['action'] === 'respond') {
            $responseText = $response['response_text'] ?? 'No response text provided.';
            $this->addToHistory('assistant', json_encode($response));
            return $this->packageResponse(ResponseBuilder::success($responseText));
        } else {
            $this->logger->warning("Unknown action or response format", ['response' => $response]);
            return $this->packageResponse(ResponseBuilder::error('Unknown response format from AI.'));
        }
    }

    /**
     * Execute a tool and get the AI's response to the result
     */
    public function executeTool(string $toolName, array $arguments): array
    {
        try {
            $this->logger->info("Executing tool: $toolName", ['arguments' => $arguments]);
            $toolResult = $this->serviceRegistry->executeTool($toolName, $arguments);
            
            // Store tool execution info for E2E test compatibility
            $toolExecutionInfo = [
                'tool_name' => $toolName,
                'arguments' => $arguments,
                'result' => $toolResult
            ];

            // Store the tool result in history
            $toolResultRole = $this->useToolRole ? 'tool' : 'user';
            $this->addToHistory($toolResultRole, json_encode($toolResult));

            // Now get the AI's response to the tool result
            $response = $this->processMessage(null);
            
            // Merge tool execution info with existing debug info
            if (isset($response['_debug'])) {
                $response['_debug']['tool_execution'] = $toolExecutionInfo;
            } else {
                $response['_debug'] = ['tool_execution' => $toolExecutionInfo];
            }
            
            return $response;

        } catch (\Exception $e) {
            $errorMsg = "Error executing tool '$toolName': " . $e->getMessage();
            $this->logger->error($errorMsg);
            return $this->packageResponse(ResponseBuilder::error($errorMsg));
        }
    }
}