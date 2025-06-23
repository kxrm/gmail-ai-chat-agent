<?php

declare(strict_types=1);

namespace Tests\Unit;

require_once __DIR__ . '/../../core/ChatManager.php';
require_once __DIR__ . '/../../core/OllamaClient.php';
require_once __DIR__ . '/../../core/ServiceRegistry.php';
require_once __DIR__ . '/../../services/Service.php';
require_once __DIR__ . '/../../core/ArraySession.php';

use ChatManager;
use App\Services\ServiceRegistry;
use App\Core\ArraySession;
use Psr\Log\NullLogger;
use PHPUnit\Framework\TestCase;

class HistoryTransformTest extends TestCase
{
    private function createChatManager(bool $toolRoleSupport): ChatManager
    {
        $logger = new NullLogger();
        $session = new ArraySession();

        /** @var \OllamaClient&\PHPUnit\Framework\MockObject\MockObject $ollama */
        $ollama = $this->getMockBuilder(\OllamaClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $registry = new ServiceRegistry([], null, $logger);
        $cap = $toolRoleSupport ? ['tool-role'] : [];

        return new ChatManager($ollama, 'system', $registry, $logger, $cap, false, 'llama3', $session);
    }

    private function invokePrepareHistory(ChatManager $cm): array
    {
        $ref = new \ReflectionClass($cm);
        $method = $ref->getMethod('prepareHistoryForOllama');
        $method->setAccessible(true);
        return $method->invoke($cm);
    }

    public function testPrepareHistoryReturnsUnchangedHistory(): void
    {
        $cm = $this->createChatManager(false);
        
        // Get the session from the ChatManager and set the history
        $ref = new \ReflectionClass($cm);
        $sessionProperty = $ref->getProperty('session');
        $sessionProperty->setAccessible(true);
        $session = $sessionProperty->getValue($cm);
        
        $session->set('chat_history', [
            ['role' => 'user', 'content' => 'Hi'],
            ['role' => 'assistant', 'content' => json_encode(['action'=>'respond','response_text'=>'Hello'])],
        ]);
        
        $out = $this->invokePrepareHistory($cm);
        $expectedHistory = $session->get('chat_history');
        $this->assertSame($expectedHistory, $out);
    }

    public function testPrepareHistoryWithToolCallsReturnsAsIs(): void
    {
        $cm = $this->createChatManager(true);
        
        // Get the session from the ChatManager and set the history
        $ref = new \ReflectionClass($cm);
        $sessionProperty = $ref->getProperty('session');
        $sessionProperty->setAccessible(true);
        $session = $sessionProperty->getValue($cm);
        
        $session->set('chat_history', [
            ['role'=>'user','content'=>'Request'],
            ['role'=>'assistant','content'=>json_encode(['action'=>'tool_use','tool_name'=>'do_x','arguments'=>['a'=>1]])],
            ['role'=>'tool','content'=>json_encode(['status'=>'ok'])]
        ]);
        
        $out = $this->invokePrepareHistory($cm);

        // The current implementation just returns the history as-is
        $this->assertCount(3, $out);
        $this->assertEquals('user', $out[0]['role']);
        $this->assertEquals('assistant', $out[1]['role']);
        $this->assertEquals('tool', $out[2]['role']);
        
        // The assistant message should contain the original JSON structure
        $assistantContent = json_decode($out[1]['content'], true);
        $this->assertEquals('tool_use', $assistantContent['action']);
        $this->assertEquals('do_x', $assistantContent['tool_name']);
    }

    public function testPrepareHistoryWithGorillaModel(): void
    {
        $logger = new NullLogger();
        $session = new ArraySession();

        /** @var \OllamaClient&\PHPUnit\Framework\MockObject\MockObject $ollama */
        $ollama = $this->getMockBuilder(\OllamaClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $registry = new ServiceRegistry([], null, $logger);
        
        // Create ChatManager with gorilla model
        $cm = new ChatManager($ollama, 'system', $registry, $logger, [], false, 'gorilla', $session);
        
        // Get the session and set the history
        $ref = new \ReflectionClass($cm);
        $sessionProperty = $ref->getProperty('session');
        $sessionProperty->setAccessible(true);
        $session = $sessionProperty->getValue($cm);
        
        $session->set('chat_history', [
            ['role' => 'system', 'content' => 'System prompt'],
            ['role'=>'user','content'=>'Request'],
            ['role'=>'assistant','content'=>json_encode(['action'=>'respond','response_text'=>'Response'])]
        ]);
        
        $out = $this->invokePrepareHistory($cm);
        
        // For Gorilla model, user and assistant messages should be prefixed
        $this->assertCount(3, $out);
        $this->assertEquals('system', $out[0]['role']);
        $this->assertEquals('System prompt', $out[0]['content']); // System message unchanged
        $this->assertEquals('user', $out[1]['role']);
        $this->assertEquals('[user] Request', $out[1]['content']); // User message prefixed
        $this->assertEquals('assistant', $out[2]['role']);
        $this->assertStringStartsWith('[assistant]', $out[2]['content']); // Assistant message prefixed
    }
} 