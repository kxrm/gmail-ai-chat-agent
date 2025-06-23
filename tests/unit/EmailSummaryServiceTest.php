<?php

declare(strict_types=1);

namespace Tests\Unit;

require_once __DIR__ . '/../../core/EmailSummaryService.php';
require_once __DIR__ . '/../../core/ArraySession.php';

use App\Core\EmailSummaryService;
use App\Core\ArraySession;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class EmailSummaryServiceTest extends TestCase
{
    private EmailSummaryService $emailService;
    private ArraySession $session;
    private NullLogger $logger;

    protected function setUp(): void
    {
        $this->session = new ArraySession();
        $this->logger = new NullLogger();
        $this->emailService = new EmailSummaryService($this->session, $this->logger, false);
    }

    public function testBuildEmailSummaryWithNoEmails(): void
    {
        $result = $this->emailService->buildEmailSummary([]);
        $this->assertEquals("You have no unread emails.", $result);
    }

    public function testBuildEmailSummaryWithOneEmail(): void
    {
        $emails = [
            ['from' => 'John Doe <john@example.com>', 'subject' => 'Test Subject']
        ];
        
        $result = $this->emailService->buildEmailSummary($emails);
        $this->assertStringContainsString("You have one unread email from John Doe about 'Test Subject'", $result);
    }

    public function testBuildEmailSummaryWithMultipleEmails(): void
    {
        $emails = [
            ['from' => 'John Doe <john@example.com>', 'subject' => 'Test Subject 1'],
            ['from' => 'Jane Smith <jane@example.com>', 'subject' => 'Test Subject 2']
        ];
        
        $result = $this->emailService->buildEmailSummary($emails);
        $this->assertStringContainsString("You have 2 unread emails", $result);
        $this->assertStringContainsString("John Doe about 'Test Subject 1'", $result);
        $this->assertStringContainsString("Jane Smith about 'Test Subject 2'", $result);
    }

    public function testBuildEmailSummaryWithEmailWithoutBrackets(): void
    {
        $emails = [
            ['from' => 'john@example.com', 'subject' => 'Test Subject']
        ];
        
        $result = $this->emailService->buildEmailSummary($emails);
        $this->assertStringContainsString("john@example.com about 'Test Subject'", $result);
    }

    public function testGetLastUnreadEmailsWithNoHistory(): void
    {
        $result = $this->emailService->getLastUnreadEmails();
        $this->assertEquals([], $result);
    }

    public function testGetLastUnreadEmailsWithFoundEmails(): void
    {
        $expectedEmails = [
            ['subject' => 'Test 1', 'from' => 'sender1@example.com'],
            ['subject' => 'Test 2', 'from' => 'sender2@example.com']
        ];
        
        $this->session->set('chat_history', [
            ['role' => 'system', 'content' => 'You are helpful'],
            ['role' => 'user', 'content' => json_encode(['status' => 'found_unread_emails', 'emails' => $expectedEmails])]
        ]);
        
        $result = $this->emailService->getLastUnreadEmails();
        $this->assertEquals($expectedEmails, $result);
    }

    public function testGetLastUnreadEmailsWithToolRole(): void
    {
        $emailServiceWithToolRole = new EmailSummaryService($this->session, $this->logger, true);
        
        $expectedEmails = [
            ['subject' => 'Test 1', 'from' => 'sender1@example.com']
        ];
        
        $this->session->set('chat_history', [
            ['role' => 'system', 'content' => 'You are helpful'],
            ['role' => 'tool', 'content' => json_encode(['status' => 'found_unread_emails', 'emails' => $expectedEmails])]
        ]);
        
        $result = $emailServiceWithToolRole->getLastUnreadEmails();
        $this->assertEquals($expectedEmails, $result);
    }

    public function testIsDuplicateUnreadCallWithNoDuplicate(): void
    {
        $this->session->set('chat_history', [
            ['role' => 'system', 'content' => 'You are helpful'],
            ['role' => 'user', 'content' => 'Hello']
        ]);
        
        $result = $this->emailService->isDuplicateUnreadCall();
        $this->assertFalse($result);
    }

    public function testIsDuplicateUnreadCallWithDuplicate(): void
    {
        $this->session->set('chat_history', [
            ['role' => 'system', 'content' => 'You are helpful'],
            ['role' => 'user', 'content' => 'Show me unread emails'],
            ['role' => 'assistant', 'content' => json_encode(['action' => 'tool_use', 'tool_name' => 'unread_emails'])],
            ['role' => 'assistant', 'content' => json_encode(['action' => 'tool_use', 'tool_name' => 'unread_emails'])]
        ]);
        
        $result = $this->emailService->isDuplicateUnreadCall();
        $this->assertTrue($result);
    }

    public function testIsDuplicateUnreadCallStopsAtUserMessage(): void
    {
        $this->session->set('chat_history', [
            ['role' => 'system', 'content' => 'You are helpful'],
            ['role' => 'user', 'content' => 'First request'],
            ['role' => 'assistant', 'content' => json_encode(['action' => 'tool_use', 'tool_name' => 'unread_emails'])],
            ['role' => 'user', 'content' => 'Second request'],
            ['role' => 'assistant', 'content' => json_encode(['action' => 'tool_use', 'tool_name' => 'unread_emails'])]
        ]);
        
        $result = $this->emailService->isDuplicateUnreadCall();
        $this->assertFalse($result);
    }

    public function testSearchIdInLastSummaryFindsId(): void
    {
        $this->session->set('last_summary', 'Email ID abc123: Important Message from sender@example.com');
        
        $result = $this->emailService->searchIdInLastSummary('Important Message');
        $this->assertEquals('abc123', $result);
    }

    public function testSearchIdInLastSummaryNoMatch(): void
    {
        $this->session->set('last_summary', 'Email ID abc123: Important Message from sender@example.com');
        
        $result = $this->emailService->searchIdInLastSummary('Nonexistent Subject');
        $this->assertNull($result);
    }

    public function testSearchIdInLastSummaryEmptySummary(): void
    {
        $result = $this->emailService->searchIdInLastSummary('Any Subject');
        $this->assertNull($result);
    }

    public function testGetLastSummaryThisTurnWithValidSummary(): void
    {
        $this->session->set('chat_history', [
            ['role' => 'system', 'content' => 'You are helpful'],
            ['role' => 'assistant', 'content' => json_encode(['action' => 'respond', 'response_text' => 'Test summary'])]
        ]);
        
        $result = $this->emailService->getLastSummaryThisTurn();
        $this->assertEquals('Test summary', $result);
    }

    public function testGetLastSummaryThisTurnWithNoSummary(): void
    {
        $this->session->set('chat_history', [
            ['role' => 'system', 'content' => 'You are helpful'],
            ['role' => 'assistant', 'content' => json_encode(['action' => 'tool_use', 'tool_name' => 'test'])]
        ]);
        
        $result = $this->emailService->getLastSummaryThisTurn();
        $this->assertNull($result);
    }

    public function testGetLastSummaryThisTurnWithNonJsonContent(): void
    {
        $this->session->set('chat_history', [
            ['role' => 'system', 'content' => 'You are helpful'],
            ['role' => 'assistant', 'content' => 'Plain text summary']
        ]);
        
        $result = $this->emailService->getLastSummaryThisTurn();
        $this->assertEquals('Plain text summary', $result);
    }

    public function testHasRecentUnreadResultWithRecentResult(): void
    {
        $this->session->set('chat_history', [
            ['role' => 'system', 'content' => 'You are helpful'],
            ['role' => 'assistant', 'content' => 'Some response'],
            ['role' => 'user', 'content' => json_encode(['status' => 'found_unread_emails', 'emails' => []])]
        ]);
        
        $result = $this->emailService->hasRecentUnreadResult();
        $this->assertTrue($result);
    }

    public function testHasRecentUnreadResultWithNoRecentResult(): void
    {
        $this->session->set('chat_history', [
            ['role' => 'system', 'content' => 'You are helpful'],
            ['role' => 'assistant', 'content' => 'Some response'],
            ['role' => 'user', 'content' => 'Regular user message']
        ]);
        
        $result = $this->emailService->hasRecentUnreadResult();
        $this->assertFalse($result);
    }

    public function testHasRecentUnreadResultWithToolRole(): void
    {
        $emailServiceWithToolRole = new EmailSummaryService($this->session, $this->logger, true);
        
        $this->session->set('chat_history', [
            ['role' => 'system', 'content' => 'You are helpful'],
            ['role' => 'assistant', 'content' => 'Some response'],
            ['role' => 'tool', 'content' => json_encode(['status' => 'found_unread_emails', 'emails' => []])]
        ]);
        
        $result = $emailServiceWithToolRole->hasRecentUnreadResult();
        $this->assertTrue($result);
    }

    public function testHasRecentUnreadResultWithEmptyHistory(): void
    {
        $result = $this->emailService->hasRecentUnreadResult();
        $this->assertFalse($result);
    }
} 