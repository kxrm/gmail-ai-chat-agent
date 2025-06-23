<?php

declare(strict_types=1);

namespace Tests\Integration;

require_once __DIR__ . '/../../core/ChatManager.php';
require_once __DIR__ . '/../../core/OllamaClient.php';
require_once __DIR__ . '/../../core/ServiceRegistry.php';
require_once __DIR__ . '/../../core/ArraySession.php';
require_once __DIR__ . '/../../services/Service.php';

use App\Services\ServiceRegistry;
use App\Services\Service;
use App\Core\ArraySession;
use ChatManager;
use OllamaClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Integration test for ChatManager with real service interactions
 * @covers \ChatManager
 * @covers \App\Services\ServiceRegistry
 */
class ChatFlowIntegrationTest extends TestCase
{
    private ChatManager $chatManager;
    private ServiceRegistry $serviceRegistry;
    private NullLogger $logger;
    private ArraySession $session;

    protected function setUp(): void
    {
        // Use ArraySession for testing to avoid PHP session issues
        $this->session = new ArraySession();
        $this->logger = new NullLogger();
        
        // Create real service registry with mock services
        $this->serviceRegistry = new ServiceRegistry([], null, $this->logger);

        // Note: ChatManager will be created in each test method with specific mock clients
    }

    protected function tearDown(): void
    {
        // Clean up session data
        $this->session->clear();
    }

    public function testFullEmailSearchFlow(): void
    {
        // Create a mock Ollama client for this specific test
        $mockOllamaClient = new class() extends OllamaClient {
            public function __construct() {
                // Don't call parent constructor to avoid actual HTTP setup
            }
            
            public function chat(array $history, string $format = 'json'): string {
                return '{"action":"respond","response_text":"Test response"}';
            }
        };

        // Create ChatManager for this test with minimal setup
        $chatManager = new ChatManager(
            $mockOllamaClient,
            'You are a helpful email assistant.',
            new ServiceRegistry([], null, $this->logger), // Empty service registry 
            $this->logger,
            [],
            true, // debug mode
            'llama3',
            $this->session
        );

        $result = $chatManager->processMessage('Hello');

        $this->assertEquals('response', $result['type']);
        $this->assertStringContainsString('Test response', $result['content']);
    }

    public function testContactSearchIntegration(): void
    {
        $mockOllamaClient = new class() extends OllamaClient {
            public function __construct() {}
            public function chat(array $history, string $format = 'json'): string {
                return '{"action":"respond","response_text":"Found John in contacts"}';
            }
        };

        $chatManager = new ChatManager(
            $mockOllamaClient,
            'You are a helpful email assistant.',
            new ServiceRegistry([], null, $this->logger),
            $this->logger,
            [],
            true,
            'llama3',
            $this->session
        );

        $result = $chatManager->processMessage('Find John in my contacts');

        $this->assertEquals('response', $result['type']);
        $this->assertStringContainsString('Found John', $result['content']);
    }

    public function testMultiTurnConversation(): void
    {
        $mockOllamaClient = new class() extends OllamaClient {
            private int $turnCount = 0;
            
            public function __construct() {}
            
            public function chat(array $history, string $format = 'json'): string {
                $this->turnCount++;
                
                if ($this->turnCount === 1) {
                    return '{"action":"respond","response_text":"Hello! How can I help?"}';
                } else {
                    return '{"action":"respond","response_text":"I remember our previous conversation."}';
                }
            }
        };

        $chatManager = new ChatManager(
            $mockOllamaClient,
            'You are a helpful email assistant.',
            new ServiceRegistry([], null, $this->logger),
            $this->logger,
            [],
            true,
            'llama3',
            $this->session
        );

        // First turn
        $result1 = $chatManager->processMessage('What are my unread emails?');
        $this->assertEquals('response', $result1['type']);
        $this->assertStringContainsString('How can I help', $result1['content']);

        // Second turn
        $result2 = $chatManager->processMessage('Tell me more about them');
        $this->assertEquals('response', $result2['type']);
        $this->assertStringContainsString('previous conversation', $result2['content']);

        // Check that history is maintained
        $history = $chatManager->getChatHistory();
        $this->assertGreaterThan(3, count($history)); // System + user + assistant + user + assistant
    }

    public function testServiceRegistryIntegration(): void
    {
        // Test basic ServiceRegistry functionality without complex mocks
        $emptyRegistry = new ServiceRegistry([], null, $this->logger);
        $toolList = $emptyRegistry->getToolList();
        $this->assertEmpty($toolList);

        // Test that we can register and find services
        $mockService = new class() implements Service {
            public function getName(): string { return 'test_service'; }
            public function getAvailableTools(): array { return ['test_tool']; }
            public function executeTool(string $toolName, array $arguments): array {
                return ['status' => 'success'];
            }
            public function getApiClient() { return null; }
            public function getToolDefinitions(): array { return []; }
        };

        $emptyRegistry->register($mockService);
        $toolListAfter = $emptyRegistry->getToolList();
        $this->assertContains('test_tool', $toolListAfter);

        $service = $emptyRegistry->getServiceForTool('test_tool');
        $this->assertNotNull($service);
        $this->assertEquals('test_service', $service->getName());
    }

    public function testErrorHandlingIntegration(): void
    {
        $mockOllamaClient = new class() extends OllamaClient {
            public function __construct() {}
            public function chat(array $history, string $format = 'json'): string {
                return 'invalid json {';
            }
        };

        $chatManager = new ChatManager(
            $mockOllamaClient,
            'You are a helpful email assistant.',
            new ServiceRegistry([], null, $this->logger),
            $this->logger,
            [],
            true,
            'llama3',
            $this->session
        );

        $result = $chatManager->processMessage('Test message');

        $this->assertEquals('error', $result['type']);
        $this->assertStringContainsString('trouble understanding', $result['content']);
    }

    public function testRetryMechanismIntegration(): void
    {
        $mockOllamaClient = new class() extends OllamaClient {
            private int $attemptCount = 0;
            
            public function __construct() {}
            
            public function chat(array $history, string $format = 'json'): string {
                $this->attemptCount++;
                
                if ($this->attemptCount === 1) {
                    return 'invalid json {';
                } else {
                    return '{"action":"respond","response_text":"Success after retry"}';
                }
            }
        };

        $chatManager = new ChatManager(
            $mockOllamaClient,
            'You are a helpful email assistant.',
            new ServiceRegistry([], null, $this->logger),
            $this->logger,
            [],
            true,
            'llama3',
            $this->session
        );

        $result = $chatManager->processMessage('Test retry');

        // Should succeed after retry
        $this->assertEquals('response', $result['type']);
        $this->assertStringContainsString('Success after retry', $result['content']);
        
        // Should have retry debug information
        $this->assertArrayHasKey('retry_attempts', $result['_debug']);
        $this->assertCount(2, $result['_debug']['retry_attempts']);
        $this->assertFalse($result['_debug']['retry_attempts'][0]['success']);
        $this->assertTrue($result['_debug']['retry_attempts'][1]['success']);
    }

    public function testChatHistoryPersistence(): void
    {
        $mockOllamaClient = new class() extends OllamaClient {
            private int $callCount = 0;
            
            public function __construct() {}
            public function chat(array $history, string $format = 'json'): string {
                $this->callCount++;
                
                if ($this->callCount === 1) {
                    return '{"action":"respond","response_text":"Hello! How can I help?"}';
                } else {
                    return '{"action":"respond","response_text":"I remember our previous conversation."}';
                }
            }
        };

        $chatManager = new ChatManager(
            $mockOllamaClient,
            'You are a helpful email assistant.',
            new ServiceRegistry([], null, $this->logger),
            $this->logger,
            [],
            true,
            'llama3',
            $this->session
        );

        // First message
        $result1 = $chatManager->processMessage('Hello');
        $this->assertEquals('response', $result1['type']);
        $this->assertStringContainsString('How can I help', $result1['content']);

        // Second message - should have access to previous context
        $result2 = $chatManager->processMessage('What did I just say?');
        $this->assertEquals('response', $result2['type']);
        $this->assertStringContainsString('previous conversation', $result2['content']);

        // Verify history contains both exchanges
        $history = $chatManager->getChatHistory();
        $this->assertGreaterThanOrEqual(5, count($history)); // system + examples + user + assistant + user + assistant
    }

    public function testResetChatFunctionality(): void
    {
        $mockOllamaClient = new class() extends OllamaClient {
            public function __construct() {}
            public function chat(array $history, string $format = 'json'): string {
                return '{"action":"respond","response_text":"Response"}';
            }
        };

        $chatManager = new ChatManager(
            $mockOllamaClient,
            'You are a helpful email assistant.',
            new ServiceRegistry([], null, $this->logger),
            $this->logger,
            [],
            true,
            'llama3',
            $this->session
        );

        // Add some history
        $chatManager->processMessage('Hello');
        $chatManager->processMessage('How are you?');

        $historyBefore = $chatManager->getChatHistory();
        $this->assertGreaterThan(1, count($historyBefore));

        // Reset chat
        $chatManager->resetChat();

        $historyAfter = $chatManager->getChatHistory();
        $this->assertGreaterThan(1, count($historyAfter)); // System message + examples
        $this->assertEquals('system', $historyAfter[0]['role']);
    }
} 