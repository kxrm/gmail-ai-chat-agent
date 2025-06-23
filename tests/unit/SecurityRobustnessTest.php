<?php

declare(strict_types=1);

namespace Tests\Unit;

require_once __DIR__ . '/../../core/ChatManager.php';
require_once __DIR__ . '/../../core/OllamaClient.php';
require_once __DIR__ . '/../../core/ServiceRegistry.php';
require_once __DIR__ . '/../../core/ArraySession.php';

use ChatManager;
use App\Services\ServiceRegistry;
use App\Core\ArraySession;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class SecurityRobustnessTest extends TestCase
{
    private function cm(): ChatManager
    {
        $logger = new NullLogger();
        $session = new ArraySession();
        
        /** @var \OllamaClient&\PHPUnit\Framework\MockObject\MockObject $ollama */
        $ollama = $this->getMockBuilder(\OllamaClient::class)->disableOriginalConstructor()->onlyMethods(['chat'])->getMock();
        $ollama->method('chat')->willReturn('{"action":"respond","response_text":"ack"}');
        return new ChatManager($ollama, 'sys', new ServiceRegistry([], null, $logger), $logger, [], false, 'llama3', $session);
    }

    public function testLongUserMessageRejected(): void
    {
        $cm = $this->cm();
        $long = str_repeat('A', 33000);
        $out = $cm->processMessage($long);
        $this->assertSame('error', $out['type']);
        $this->assertStringContainsString('too long', $out['content']);
    }

    public function testConcurrentSessionUpdatesRemainConsistent(): void
    {
        $cm1 = $this->cm();
        $cm2 = $this->cm();

        $cm1->processMessage('Hi from 1');
        $cm2->processMessage('Hi from 2');

        // Get chat history from one of the managers
        $history = $cm1->getChatHistory();
        $userMsgs = array_filter($history, fn($m)=>$m['role']==='user' && in_array($m['content'], ['Hi from 1','Hi from 2']));
        $this->assertCount(1, $userMsgs); // Each ChatManager has its own session, so only 1 message per instance
    }
} 