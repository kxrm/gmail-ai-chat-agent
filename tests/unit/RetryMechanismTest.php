<?php

declare(strict_types=1);

namespace Tests\Unit;

require_once __DIR__ . '/../../core/ChatManager.php';
require_once __DIR__ . '/../../core/OllamaClient.php';
require_once __DIR__ . '/../../core/ServiceRegistry.php';
require_once __DIR__ . '/../../services/Service.php';
require_once __DIR__ . '/../../core/ArraySession.php';

use App\Services\ServiceRegistry;
use App\Services\Service;
use App\Core\ArraySession;
use ChatManager;
use OllamaClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Tests for retry mechanism without session dependencies
 * @covers \ChatManager
 */
class RetryMechanismTest extends TestCase
{
    // validateResponse method test removed - functionality moved to RetryService
    // This functionality is now covered by RetryServiceTest

    public function testProcessValidResponseMethod(): void
    {
        $session = new ArraySession();
        
        /** @var OllamaClient&\PHPUnit\Framework\MockObject\MockObject $mockOllamaClient */
        $mockOllamaClient = $this->createMock(OllamaClient::class);
        /** @var ServiceRegistry&\PHPUnit\Framework\MockObject\MockObject $mockServiceRegistry */
        $mockServiceRegistry = $this->createMock(ServiceRegistry::class);
        
        // No mock expectations needed for respond action
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

        // Test with respond action
        $response = [
            'action' => 'respond',
            'response_text' => 'Test response'
        ];

        $result = $method->invoke($chatManager, $response);
        
        $this->assertEquals('response', $result['type']);
        $this->assertEquals('Test response', $result['content']);
    }

    public function testPackageResponseMethod(): void
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
            true, // debug mode enabled
            'llama3',
            $session
        );

        // Use reflection to test private packageResponse method
        $reflection = new \ReflectionClass($chatManager);
        
        // Set some debug info
        $debugProperty = $reflection->getProperty('debugInfo');
        $debugProperty->setAccessible(true);
        $debugProperty->setValue($chatManager, ['test_key' => 'test_value']);
        
        $method = $reflection->getMethod('packageResponse');
        $method->setAccessible(true);

        $response = ['type' => 'response', 'content' => 'Test response'];
        $result = $method->invoke($chatManager, $response);

        $this->assertEquals('response', $result['type']);
        $this->assertEquals('Test response', $result['content']);
        $this->assertArrayHasKey('_debug', $result);
        $this->assertEquals('test_value', $result['_debug']['test_key']);
    }



    public function testCaptureKnownEmailIdsMethod(): void
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

        // The method expects a string summary, not an array
        $summary = "Email ID abc123: Test Subject 1\nEmail ID def456: Test Subject 2";

        $method->invoke($chatManager, $summary);

        // This method doesn't actually store anything in the current implementation
        // It's a placeholder method, so we just test that it doesn't throw an error
        $this->assertTrue(true);
    }






} 