<?php

declare(strict_types=1);

namespace Tests\Unit;

require_once __DIR__ . '/../../core/ServiceRegistry.php';
require_once __DIR__ . '/../../services/Service.php';

use App\Services\ServiceRegistry;
use App\Services\Service;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Additional tests for ServiceRegistry to complete coverage
 * @covers \App\Services\ServiceRegistry
 */
class ServiceRegistryAdditionalTest extends TestCase
{
    public function testConstructorWithValidServiceClass(): void
    {
        // Create a temporary service class for testing
        $serviceClass = $this->createTestServiceClass();
        
        $serviceConfigs = [
            'test_service' => [
                'class' => $serviceClass
            ]
        ];

        $registry = new ServiceRegistry($serviceConfigs, null, new NullLogger());
        
        // Should have one service registered
        $services = $registry->getServices();
        $this->assertCount(1, $services);
        $this->assertArrayHasKey('test_service', $services);
        $this->assertInstanceOf(Service::class, $services['test_service']);
    }

    public function testConstructorWithGoogleAccessToken(): void
    {
        $serviceClass = $this->createTestServiceClass();
        
        $serviceConfigs = [
            'test_service' => [
                'class' => $serviceClass
            ]
        ];

        $googleToken = ['access_token' => 'test_token'];
        $logger = new NullLogger();

        $registry = new ServiceRegistry($serviceConfigs, $googleToken, $logger);
        
        // Should have one service registered
        $services = $registry->getServices();
        $this->assertCount(1, $services);
        $this->assertInstanceOf(Service::class, $services['test_service']);
    }

    private function createTestServiceClass(): string
    {
        // Create an anonymous class that implements Service
        return get_class(new class implements Service {
            public function getName(): string
            {
                return 'test_service';
            }

            public function getAvailableTools(): array
            {
                return ['test_tool'];
            }

            public function executeTool(string $toolName, array $arguments): array
            {
                return ['status' => 'success', 'tool' => $toolName];
            }

            public function getApiClient()
            {
                return null;
            }

            public function getToolDefinitions(): array
            {
                return [['name' => 'test_tool', 'description' => 'Test tool']];
            }
        });
    }
} 