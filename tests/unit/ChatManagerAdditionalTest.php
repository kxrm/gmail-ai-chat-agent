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
 * Additional tests for ChatManager to improve coverage
 * @covers \ChatManager
 */
class ChatManagerAdditionalTest extends TestCase
{
    public function testProcessActionWithToolUse(): void
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

        // Use reflection to test private processAction method
        $reflection = new \ReflectionClass($chatManager);
        $method = $reflection->getMethod('processAction');
        $method->setAccessible(true);

        $response = [
            'action' => 'tool_use',
            'tool_name' => 'search_emails',
            'arguments' => ['query' => 'test']
        ];

        $result = $method->invoke($chatManager, $response);
        
        // With granular approach, processAction now returns tool_call instead of auto-executing
        $this->assertEquals('tool_call', $result['type']);
        $this->assertEquals('search_emails', $result['tool_name']);
        $this->assertEquals(['query' => 'test'], $result['arguments']);
    }

    public function testProcessActionWithMissingToolName(): void
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

        // Use reflection to test private processAction method
        $reflection = new \ReflectionClass($chatManager);
        $method = $reflection->getMethod('processAction');
        $method->setAccessible(true);

        $response = [
            'action' => 'tool_use'
            // Missing tool_name
        ];

        $result = $method->invoke($chatManager, $response);
        
        $this->assertEquals('error', $result['type']);
        $this->assertStringContainsString('no tool name provided', $result['content']);
    }

    public function testProcessActionWithToolExecutionError(): void
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

        // Use reflection to test private processAction method
        $reflection = new \ReflectionClass($chatManager);
        $method = $reflection->getMethod('processAction');
        $method->setAccessible(true);

        $response = [
            'action' => 'tool_use',
            'tool_name' => 'search_emails',
            'arguments' => ['query' => 'test']
        ];

        $result = $method->invoke($chatManager, $response);
        
        // With granular approach, processAction returns tool_call - errors happen in executeTool
        $this->assertEquals('tool_call', $result['type']);
        $this->assertEquals('search_emails', $result['tool_name']);
        $this->assertEquals(['query' => 'test'], $result['arguments']);
    }

    public function testExecuteToolWithError(): void
    {
        $session = new ArraySession();
        
        /** @var OllamaClient&\PHPUnit\Framework\MockObject\MockObject $mockOllamaClient */
        $mockOllamaClient = $this->createMock(OllamaClient::class);
        /** @var ServiceRegistry&\PHPUnit\Framework\MockObject\MockObject $mockServiceRegistry */
        $mockServiceRegistry = $this->createMock(ServiceRegistry::class);
        
        // Mock tool execution throwing an exception
        $mockServiceRegistry->expects($this->once())
            ->method('executeTool')
            ->with('search_emails', ['query' => 'test'])
            ->willThrowException(new \Exception('Tool execution failed'));
        
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

        $result = $chatManager->executeTool('search_emails', ['query' => 'test']);
        
        $this->assertEquals('error', $result['type']);
        $this->assertStringContainsString('Tool execution failed', $result['content']);
    }

    public function testProcessActionWithUnknownAction(): void
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

        // Use reflection to test private processAction method
        $reflection = new \ReflectionClass($chatManager);
        $method = $reflection->getMethod('processAction');
        $method->setAccessible(true);

        $response = [
            'action' => 'unknown_action',
            'some_data' => 'value'
        ];

        $result = $method->invoke($chatManager, $response);
        
        $this->assertEquals('error', $result['type']);
        $this->assertStringContainsString('Unknown response format', $result['content']);
    }

    public function testHandleDirectMarkCommandWithValidPattern(): void
    {
        $session = new ArraySession();
        $session->set('last_summary', 'Email ID abc123: Important Message');
        
        /** @var OllamaClient&\PHPUnit\Framework\MockObject\MockObject $mockOllamaClient */
        $mockOllamaClient = $this->createMock(OllamaClient::class);
        /** @var ServiceRegistry&\PHPUnit\Framework\MockObject\MockObject $mockServiceRegistry */
        $mockServiceRegistry = $this->createMock(ServiceRegistry::class);
        
        // Mock successful mark email execution
        $mockServiceRegistry->expects($this->once())
            ->method('executeTool')
            ->with('mark_email', ['message_id' => 'abc123', 'status' => 'read'])
            ->willReturn(['status' => 'success', 'message' => 'Email marked as read']);
        
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

        // Use reflection to test private handleDirectMarkCommand method
        $reflection = new \ReflectionClass($chatManager);
        $method = $reflection->getMethod('handleDirectMarkCommand');
        $method->setAccessible(true);

        $result = $method->invoke($chatManager, 'mark Important Message as read');
        
        $this->assertNotNull($result);
        $this->assertEquals('response', $result['type']);
        $this->assertStringContainsString('successfully marked as read', $result['content']);
    }

    public function testHandleDirectMarkCommandWithInvalidPattern(): void
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

        // Use reflection to test private handleDirectMarkCommand method
        $reflection = new \ReflectionClass($chatManager);
        $method = $reflection->getMethod('handleDirectMarkCommand');
        $method->setAccessible(true);

        $result = $method->invoke($chatManager, 'this is not a mark command');
        
        $this->assertNull($result);
    }

    public function testProcessMessageWithLongMessage(): void
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

        // Create a message longer than 32KB
        $longMessage = str_repeat('A', 33000);

        $result = $chatManager->processMessage($longMessage);
        
        $this->assertEquals('error', $result['type']);
        $this->assertStringContainsString('too long', $result['content']);
    }
} 