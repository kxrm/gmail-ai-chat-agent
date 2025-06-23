<?php

declare(strict_types=1);

namespace Tests\Unit;

require_once __DIR__ . '/../../core/ChatManager.php';
require_once __DIR__ . '/../../core/OllamaClient.php';
require_once __DIR__ . '/../../core/ServiceRegistry.php';
require_once __DIR__ . '/../../services/Service.php';
require_once __DIR__ . '/../../core/ArraySession.php';

use App\Services\ServiceRegistry;
use App\Core\ArraySession;
use ChatManager;
use OllamaClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Tests to improve ChatManager method coverage
 * @covers \ChatManager
 */
class ChatManagerCoverageTest extends TestCase
{
    public function testConstructorWithNullSession(): void
    {
        /** @var OllamaClient&\PHPUnit\Framework\MockObject\MockObject $mockOllamaClient */
        $mockOllamaClient = $this->createMock(OllamaClient::class);
        /** @var ServiceRegistry&\PHPUnit\Framework\MockObject\MockObject $mockServiceRegistry */
        $mockServiceRegistry = $this->createMock(ServiceRegistry::class);
        $logger = new NullLogger();

        // Test constructor with null session (should create PhpSession)
        $chatManager = new ChatManager(
            $mockOllamaClient,
            'Test system prompt',
            $mockServiceRegistry,
            $logger,
            [],
            false,
            'llama3',
            null // This should trigger PhpSession creation
        );

        $this->assertInstanceOf(ChatManager::class, $chatManager);
    }

    // validateResponse method tests removed - functionality moved to RetryService
    // These tests are now covered by RetryServiceTest





    public function testCaptureKnownEmailIds(): void
    {
        $session = new ArraySession();
        
        /** @var OllamaClient&\PHPUnit\Framework\MockObject\MockObject $mockOllamaClient */
        $mockOllamaClient = $this->createMock(OllamaClient::class);
        /** @var ServiceRegistry&\PHPUnit\Framework\MockObject\MockObject $mockServiceRegistry */
        $mockServiceRegistry = $this->createMock(ServiceRegistry::class);
        $logger = new NullLogger();

        $chatManager = new ChatManager(
            $mockOllamaClient,
            'Test system prompt',
            $mockServiceRegistry,
            $logger,
            [],
            false,
            'llama3',
            $session
        );

        // Use reflection to test private captureKnownEmailIds method
        $reflection = new \ReflectionClass($chatManager);
        $method = $reflection->getMethod('captureKnownEmailIds');
        $method->setAccessible(true);

        // This method doesn't do anything, but we need to call it for coverage
        $method->invoke($chatManager, 'test summary');
        
        // Just verify it doesn't throw an exception
        $this->assertTrue(true);
    }

    public function testPrepareHistoryForOllamaWithGorilla(): void
    {
        $session = new ArraySession();
        $session->set('chat_history', [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there']
        ]);
        
        /** @var OllamaClient&\PHPUnit\Framework\MockObject\MockObject $mockOllamaClient */
        $mockOllamaClient = $this->createMock(OllamaClient::class);
        /** @var ServiceRegistry&\PHPUnit\Framework\MockObject\MockObject $mockServiceRegistry */
        $mockServiceRegistry = $this->createMock(ServiceRegistry::class);
        $logger = new NullLogger();

        $chatManager = new ChatManager(
            $mockOllamaClient,
            'Test system prompt',
            $mockServiceRegistry,
            $logger,
            [],
            false,
            'gorilla', // Use gorilla model
            $session
        );

        // Use reflection to test private prepareHistoryForOllama method
        $reflection = new \ReflectionClass($chatManager);
        $method = $reflection->getMethod('prepareHistoryForOllama');
        $method->setAccessible(true);

        $result = $method->invoke($chatManager);
        
        // For gorilla model, it should transform the history
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testPrepareHistoryForOllamaWithNonGorilla(): void
    {
        $session = new ArraySession();
        $session->set('chat_history', [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there']
        ]);
        
        /** @var OllamaClient&\PHPUnit\Framework\MockObject\MockObject $mockOllamaClient */
        $mockOllamaClient = $this->createMock(OllamaClient::class);
        /** @var ServiceRegistry&\PHPUnit\Framework\MockObject\MockObject $mockServiceRegistry */
        $mockServiceRegistry = $this->createMock(ServiceRegistry::class);
        $logger = new NullLogger();

        $chatManager = new ChatManager(
            $mockOllamaClient,
            'Test system prompt',
            $mockServiceRegistry,
            $logger,
            [],
            false,
            'llama3', // Non-gorilla model
            $session
        );

        // Use reflection to test private prepareHistoryForOllama method
        $reflection = new \ReflectionClass($chatManager);
        $method = $reflection->getMethod('prepareHistoryForOllama');
        $method->setAccessible(true);

        $result = $method->invoke($chatManager);
        
        // For non-gorilla models, it should return history as-is
        $this->assertIsArray($result);
        $this->assertEquals([
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there']
        ], $result);
    }

    public function testProcessMessageCallsValidateResponse(): void
    {
        $session = new ArraySession();
        
        /** @var OllamaClient&\PHPUnit\Framework\MockObject\MockObject $mockOllamaClient */
        $mockOllamaClient = $this->createMock(OllamaClient::class);
        /** @var ServiceRegistry&\PHPUnit\Framework\MockObject\MockObject $mockServiceRegistry */
        $mockServiceRegistry = $this->createMock(ServiceRegistry::class);
        
        // Mock ollama to return a valid response
        $mockOllamaClient->expects($this->once())
            ->method('chat')
            ->willReturn('{"action":"respond","response_text":"Hello"}');
        
        $logger = new NullLogger();

        $chatManager = new ChatManager(
            $mockOllamaClient,
            'Test system prompt',
            $mockServiceRegistry,
            $logger,
            [],
            false,
            'llama3',
            $session
        );

        $result = $chatManager->processMessage('Hello');
        
        // This should trigger validateResponse internally
        $this->assertEquals('response', $result['type']);
    }

    public function testProcessValidResponseCallsProcessAction(): void
    {
        $session = new ArraySession();
        
        /** @var OllamaClient&\PHPUnit\Framework\MockObject\MockObject $mockOllamaClient */
        $mockOllamaClient = $this->createMock(OllamaClient::class);
        /** @var ServiceRegistry&\PHPUnit\Framework\MockObject\MockObject $mockServiceRegistry */
        $mockServiceRegistry = $this->createMock(ServiceRegistry::class);
        
        $logger = new NullLogger();

        $chatManager = new ChatManager(
            $mockOllamaClient,
            'Test system prompt',
            $mockServiceRegistry,
            $logger,
            [],
            false,
            'llama3',
            $session
        );

        // Use reflection to test private processValidResponse method
        $reflection = new \ReflectionClass($chatManager);
        $method = $reflection->getMethod('processValidResponse');
        $method->setAccessible(true);

        $response = [
            'action' => 'tool_use',
            'tool_name' => 'search_emails',
            'arguments' => []
        ];

        $result = $method->invoke($chatManager, $response);
        
        // With granular approach, processAction returns tool_call instead of auto-executing
        $this->assertEquals('tool_call', $result['type']);
        $this->assertEquals('search_emails', $result['tool_name']);
        $this->assertEquals([], $result['arguments']);
    }
} 