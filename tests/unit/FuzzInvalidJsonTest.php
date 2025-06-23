<?php

declare(strict_types=1);

namespace Tests\Unit;

require_once __DIR__ . '/../../core/ChatManager.php';
require_once __DIR__ . '/../../core/OllamaClient.php';
require_once __DIR__ . '/../../core/ServiceRegistry.php';
require_once __DIR__ . '/../../services/Service.php';

use ChatManager;
use App\Services\ServiceRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class FuzzInvalidJsonTest extends TestCase
{
    private function createChatManager(string $ollamaResponse): ChatManager
    {
        $logger = new NullLogger();
        /** @var \OllamaClient&\PHPUnit\Framework\MockObject\MockObject $ollama */
        $ollama = $this->getMockBuilder(\OllamaClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['chat'])
            ->getMock();
        $ollama->method('chat')->willReturn($ollamaResponse);
        $registry = new ServiceRegistry([], null, $logger);
        return new ChatManager($ollama, 'prompt', $registry, $logger, []);
    }

    /**
     * Generates a random non-JSON string.
     */
    private function randomGarbage(): string
    {
        $len = random_int(5, 40);
        $bytes = random_bytes($len);
        return rtrim(base64_encode($bytes), '=');
    }

    public function testProcessMessageHandlesMalformedJson(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $garbage = $this->randomGarbage();
            $cm = $this->createChatManager($garbage);
            $out = $cm->processMessage('hello');
            $this->assertSame('error', $out['type'], "Did not return error for input: $garbage");
        }
    }
} 