<?php

declare(strict_types=1);

namespace Tests\E2E\Interactions;

require_once __DIR__ . '/../E2ETestCase.php';

use Tests\E2E\E2ETestCase;

class UnreadEmailsVariationsTest extends E2ETestCase
{
    private static int $successCount = 0;
    private static int $totalCount = 0;

    public static function tearDownAfterClass(): void
    {
        $passRate = self::$totalCount > 0 ? (self::$successCount / self::$totalCount) : 0;
        
        // Output the final pass rate for visibility in the CI logs.
        echo sprintf("\n\n--- Unread Email Phrasing Test ---
Success Rate: %.2f%% (%d/%d)
Required: 90%%\n\n", $passRate * 100, self::$successCount, self::$totalCount);

        self::assertGreaterThanOrEqual(0.9, $passRate, "Model failed to meet 90% success rate for unread email queries.");
        
        // It's crucial to call the parent tearDown to allow its logging to complete.
        parent::tearDownAfterClass();
    }

    /**
     * @dataProvider unreadEmailPhrasings
     * @coversNothing
     */
    public function testItUnderstandsVariousWaysOfAskingForUnreadEmail(string $phrasing, string $description): void
    {
        self::$totalCount++;
        
        $turn = $this->performTurn(
            $phrasing,
            $description,
            'AI should use the search_emails tool with query "is:unread" or the unread_emails tool.'
        );

        // Check if the first event is a tool call (new granular approach)
        $firstEvent = $turn['events'][0] ?? null;
        $toolUsedCorrectly = false;

        if (isset($firstEvent['type']) && $firstEvent['type'] === 'tool_call') {
            $toolName = $firstEvent['tool_name'] ?? '';
            $args = $firstEvent['arguments'] ?? [];

            $isSearch = ($toolName === 'search_emails' && ($args['query'] ?? '') === 'is:unread');
            $isUnread = ($toolName === 'unread_emails');

            if ($isSearch || $isUnread) {
                // Check for the default count rule for unread_emails
                if ($toolName === 'unread_emails') {
                    if (!isset($args['max_results']) || $args['max_results'] == 5) {
                        $toolUsedCorrectly = true;
                    }
                } else {
                    $toolUsedCorrectly = true;
                }
            }
        }
        
        // Fallback: check debug info for tool calls (legacy/backup detection)
        if (!$toolUsedCorrectly && isset($firstEvent['_debug']['ollama_parsed_response'])) {
            $modelChoice = $firstEvent['_debug']['ollama_parsed_response'];
            
            if (($modelChoice['action'] ?? null) === 'tool_use') {
                $toolName = $modelChoice['tool_name'] ?? 'none';
                $args = $modelChoice['arguments'] ?? [];

                $isSearch = ($toolName === 'search_emails' && ($args['query'] ?? '') === 'is:unread');
                $isUnread = ($toolName === 'unread_emails');

                if ($isSearch || $isUnread) {
                    // Check for the default count rule
                    if (!isset($args['max_results']) || $args['max_results'] == 5) {
                        $toolUsedCorrectly = true;
                    }
                }
            }
        }

        if ($toolUsedCorrectly) {
            self::$successCount++;
            $turn['result'] = 'PASS';
        }
    }

    public function unreadEmailPhrasings(): array
    {
        return [
            'direct_question' => ["What are my unread emails?", "A direct question for unread emails."],
            'casual_inbox_check' => ["What's in my inbox today?", "A casual query about the inbox contents."],
            'new_mail_query' => ["Any new mail?", "A short, common query for new emails."],
            'latest_emails_request' => ["Show me my latest emails", "A request for the most recent emails."],
            'check_email_command' => ["Check my email", "A simple command to check email."],
            'short_casual' => ["Got anything new?", "A very short and casual way of asking."],
            'impersonal_request' => ["Are there any new emails?", "An impersonal request for new mail."],
            'polite_request' => ["Could you please check for new emails?", "A polite, formal request."],
            'background_command' => ["Just give me a summary of what's new.", "A command phrased as part of a larger thought."],
            'abbreviated' => ["new mail", "An abbreviated, command-like phrase."],
            'inquisitive' => ["I wonder if I have any new messages.", "An inquisitive, less direct query."],
            'action_oriented' => ["Let's see what's in the inbox.", "An action-oriented statement."]
        ];
    }
} 