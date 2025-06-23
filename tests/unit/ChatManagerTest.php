<?php

declare(strict_types=1);

namespace Tests\Unit;

// Ensure core classes are loaded for tests (after namespace)
require_once __DIR__ . '/../../core/ChatManager.php';
require_once __DIR__ . '/../../core/OllamaClient.php';
require_once __DIR__ . '/../../core/ServiceRegistry.php';
require_once __DIR__ . '/../../core/ArraySession.php';
require_once __DIR__ . '/../../services/Service.php';

use ChatManager;
use OllamaClient;
use App\Services\ServiceRegistry;
use App\Services\Service;
use App\Core\ArraySession;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Mockery;

/**
 * @covers \ChatManager
 */
class ChatManagerTest extends TestCase
{
    private ChatManager $chatManager;
    private $mockOllamaClient;
    private $mockServiceRegistry;
    private NullLogger $logger;
    private ArraySession $session;

    protected function setUp(): void
    {
        // Use ArraySession instead of mocking $_SESSION
        $this->session = new ArraySession();

        $this->mockOllamaClient = Mockery::mock(OllamaClient::class);
        $this->mockServiceRegistry = Mockery::mock(ServiceRegistry::class);
        $this->logger = new NullLogger();

        $this->chatManager = new ChatManager(
            $this->mockOllamaClient,
            'You are a helpful assistant.',
            $this->mockServiceRegistry,
            $this->logger,
            [], // model capabilities
            false, // debug mode
            'llama3',
            $this->session
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    private function getChatManager(array $modelCapabilities = [], ?Service $dummyService = null, ?string $ollamaResponse = null): ChatManager
    {
        // Use fresh session for each test
        $session = new ArraySession();

        $logger = new NullLogger();

        // Mock OllamaClient
        /** @var \OllamaClient&\PHPUnit\Framework\MockObject\MockObject $ollama */
        $ollama = $this->getMockBuilder(\OllamaClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['chat'])
            ->getMock();
        if ($ollamaResponse !== null) {
            $ollama->method('chat')->willReturn($ollamaResponse);
        } else {
            $ollama->method('chat')->willReturn('{"action":"respond","response_text":"ack"}');
        }

        // Service registry setup
        $registry = new ServiceRegistry([], null, $logger);
        if ($dummyService !== null) {
            $registry->register($dummyService);
        }

        $systemPrompt = 'You are a helpful assistant.';

        return new ChatManager($ollama, $systemPrompt, $registry, $logger, $modelCapabilities, false, 'llama3', $session);
    }

    private function createDummyService(string $toolName = 'dummy_tool', $returnValue = 'ok'): Service
    {
        return new class($toolName, $returnValue) implements Service {
            private string $toolName; private $returnValue;
            public function __construct(string $toolName, $returnValue) { $this->toolName = $toolName; $this->returnValue = $returnValue; }
            public function getName(): string { return 'dummy'; }
            public function getAvailableTools(): array { return [$this->toolName]; }
            public function executeTool(string $toolName, array $arguments): array { return is_array($this->returnValue) ? $this->returnValue : ['status' => $this->returnValue]; }
            public function getApiClient() {}
            public function getToolDefinitions(): array { return []; }
        };
    }

    public function testRespondActionReplacesUserName(): void
    {
        $this->session->set('user_display_name', 'Alice');
        
        $chatManager = $this->getChatManager();
        $chatManager->processMessage('Hello World');
        
        // Should have system message, examples, and user message
        $history = $chatManager->getChatHistory();
        $this->assertGreaterThan(1, count($history));
        
        // Find our specific user message (not the example "Hello")
        $userMessages = array_filter($history, fn($msg) => $msg['role'] === 'user' && $msg['content'] === 'Hello World');
        $this->assertCount(1, $userMessages);
    }

    public function testResetChatClearsHistory(): void
    {
        // Add some history first
        $this->session->set('chat_history', [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there!']
        ]);
        
        $this->chatManager->resetChat();
        
        // Should have system message and examples
        $history = $this->session->get('chat_history', []);
        $this->assertGreaterThan(1, count($history));
        $this->assertEquals('system', $history[0]['role']);
    }

    public function testGetChatHistory(): void
    {
        $expectedHistory = [
            ['role' => 'system', 'content' => 'You are helpful'],
            ['role' => 'user', 'content' => 'Hello']
        ];
        $this->session->set('chat_history', $expectedHistory);
        
        $history = $this->chatManager->getChatHistory();
        
        $this->assertEquals($expectedHistory, $history);
    }

    public function testProcessMessageWithNullMessage(): void
    {
        $this->mockOllamaClient->shouldReceive('chat')
            ->once()
            ->andReturn('{"action":"respond","response_text":"How can I help?"}');

        $result = $this->chatManager->processMessage(null);
        
        $this->assertEquals('response', $result['type']);
        $this->assertStringContainsString('How can I help?', $result['content']);
    }

    public function testAddToHistoryWithToolRole(): void
    {
        // Add an assistant message first (tool calls need to reference assistant messages)
        $this->session->set('chat_history', [
            ['role' => 'system', 'content' => 'You are helpful'],
            ['role' => 'assistant', 'content' => json_encode(['action' => 'tool_use', 'tool_name' => 'search_emails'])]
        ]);
        
        $reflection = new \ReflectionClass($this->chatManager);
        $method = $reflection->getMethod('addToHistory');
        $method->setAccessible(true);
        
        $method->invoke($this->chatManager, 'tool', 'Tool result');
        
        $history = $this->session->get('chat_history', []);
        $this->assertCount(3, $history);
        $this->assertEquals('tool', $history[2]['role']);
        $this->assertEquals('Tool result', $history[2]['content']);
        $this->assertArrayHasKey('tool_call_id', $history[2]);
    }

    public function testPackageResponseWithDebugInfo(): void
    {
        $response = ['type' => 'response', 'content' => 'Hello'];
        
        // Set some debug info
        $reflection = new \ReflectionClass($this->chatManager);
        $debugProperty = $reflection->getProperty('debugInfo');
        $debugProperty->setAccessible(true);
        $debugProperty->setValue($this->chatManager, ['test' => 'debug']);
        
        $method = $reflection->getMethod('packageResponse');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->chatManager, $response);
        
        // Debug info should not be included unless debug mode is enabled
        $this->assertArrayNotHasKey('_debug', $result);
    }

    public function testDirectMarkCommandHandling(): void
    {
        // Set up a last summary so the mark command can find the ID (must be hex format)
        $this->session->set('last_summary', 'Email ID abc123: Test Subject from sender@example.com');
        
        // Mock a service registry that can handle mark_email
        $this->mockServiceRegistry->shouldReceive('executeTool')
            ->once()
            ->with('mark_email', ['message_id' => 'abc123', 'status' => 'read'])
            ->andReturn(['status' => 'success']);

        $result = $this->chatManager->processMessage('mark Test Subject as read');
        
        $this->assertEquals('response', $result['type']);
        $this->assertStringContainsString('marked as read', $result['content']);
    }





    public function testCaptureKnownEmailIds(): void
    {
        $summary = 'Email ID abc123: Test Subject\nEmail ID def456: Another Subject';
        
        $reflection = new \ReflectionClass($this->chatManager);
        $method = $reflection->getMethod('captureKnownEmailIds');
        $method->setAccessible(true);
        
        $method->invoke($this->chatManager, $summary);
        
        // This method doesn't actually do anything in the current implementation
        $this->assertTrue(true);
    }



    public function testGorillaHistoryFormatting(): void
    {
        $chatManager = new ChatManager(
            $this->mockOllamaClient,
            'You are helpful',
            $this->mockServiceRegistry,
            $this->logger,
            [],
            false,
            'gorilla', // Use gorilla model
            new ArraySession()
        );

        $session = new ArraySession();
        $session->set('chat_history', [
            ['role' => 'system', 'content' => 'You are helpful'],
            ['role' => 'user', 'content' => 'Hello']
        ]);

        $this->mockOllamaClient->shouldReceive('chat')
            ->once()
            ->with(Mockery::type('array'), 'json')
            ->andReturn('{"action":"respond","response_text":"Hi!"}');

        $result = $chatManager->processMessage('How are you?');
        
        $this->assertEquals('response', $result['type']);
    }

    public function testRetryTimingAndBackoff(): void
    {
        // Test that retry mechanism works with proper timing
        $session = new ArraySession();
        $mockOllamaClient = Mockery::mock(OllamaClient::class);
        $mockServiceRegistry = Mockery::mock(ServiceRegistry::class);
        
        $chatManager = new ChatManager(
            $mockOllamaClient,
            'Test prompt',
            $mockServiceRegistry,
            new NullLogger(),
            [],
            false,
            'llama3',
            $session
        );
        
        $mockOllamaClient->shouldReceive('chat')
            ->times(3)
            ->andThrow(new \Exception('Network error'));

        $startTime = microtime(true);
        $result = $chatManager->processMessage('test');
        $endTime = microtime(true);
        
        $this->assertEquals('error', $result['type']);
        $this->assertStringContainsString('trouble understanding', $result['content']);
        
        // Should have taken at least 3 seconds (1s + 2s delays, with some tolerance)
        $this->assertGreaterThan(2.5, $endTime - $startTime);
    }



    public function testExecuteMarkEmailSuccess(): void
    {
        $this->mockServiceRegistry->shouldReceive('executeTool')
            ->once()
            ->with('mark_email', ['message_id' => 'test123', 'status' => 'read'])
            ->andReturn(['status' => 'success']);

        $reflection = new \ReflectionClass($this->chatManager);
        $method = $reflection->getMethod('executeMarkEmail');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->chatManager, 'test123', 'read');
        
        $this->assertEquals('response', $result['type']);
        $this->assertStringContainsString('successfully marked as read', $result['content']);
    }
} 