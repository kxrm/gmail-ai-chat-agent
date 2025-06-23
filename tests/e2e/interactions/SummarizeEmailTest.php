<?php

declare(strict_types=1);

namespace Tests\E2E\Interactions;

require_once __DIR__ . '/../E2ETestCase.php';

use Tests\E2E\E2ETestCase;

class SummarizeEmailTest extends E2ETestCase
{
    /**
     * @coversNothing
     */
    public function testUserCanSummarizeEmail(): void
    {
        // === TURN 1: Ask for recent emails ===
        $turn1 = $this->performTurn(
            'Please tell me my most recent emails?',
            'User asks for a list of recent/unread emails.',
            'AI should use the search_emails tool with query "is:unread".'
        );

        $this->assertArrayHasKey('events', $turn1, 'Turn 1: Should have events.');
        $this->assertGreaterThan(0, count($turn1['events']), 'Turn 1: Should have at least one event.');
        
        // Find event with tool execution
        $toolExec1 = null;
        foreach ($turn1['events'] as $event) {
            if (isset($event['_debug']['tool_execution'])) {
                $toolExec1 = $event['_debug']['tool_execution'];
                break;
            }
        }
        
        $this->assertNotNull($toolExec1, 'Turn 1: Should have tool execution in one of the events.');

        $toolName1 = $toolExec1['tool_name'] ?? 'none';
        $args1 = $toolExec1['arguments'] ?? [];

        $toolUsedCorrectly1 = false;
        if ($toolName1 === 'search_emails') {
             if (isset($args1['max_results'])) {
                $this->assertEquals(5, $args1['max_results'], "If search_emails has max_results, it should be 5.");
             }
             $toolUsedCorrectly1 = true;
        } elseif ($toolName1 === 'unread_emails') {
             if (isset($args1['max_results'])) {
                $this->assertEquals(5, $args1['max_results'], "If unread_emails has max_results, it should be 5.");
             }
             $toolUsedCorrectly1 = true;
        }

        $this->assertTrue($toolUsedCorrectly1, 
            'Turn 1: AI should have used either search_emails or unread_emails. ' .
            "Actually used tool: '{$toolName1}' with arguments: " . json_encode($args1) . '. ' .
            'Expected: search_emails OR unread_emails with max_results=5'
        );

        $response1 = $turn1['final_response'];
        
        // More flexible content validation - check for any of the actual email subjects/senders
        $emailContentMentioned = false;
        $expectedContent = [
            'Bank of America', 'COMCAST', 'Automatic payment', 'Alvaro Guerra', 
            'Charles Schwab', 'eBay', 'macintosh se', 'lego nintendo', 'Noise transmissions'
        ];

        foreach ($expectedContent as $content) {
            if (stripos($response1['content'], $content) !== false) {
                $emailContentMentioned = true;
                break;
            }
        }

        $this->assertTrue($emailContentMentioned, 
            'Turn 1: Response should mention content from at least one of the available emails. ' .
            'Expected one of: ' . implode(', ', $expectedContent) . '. ' .
            'Actual response: ' . substr($response1['content'], 0, 200) . '...'
        );
        
        $turn1['result'] = 'PASS';

        // === TURN 2: Ask for a summary of one email ===
        $turn2 = $this->performTurn(
            'Summarize the email from Charles Schwab',
            'User asks for a summary of a specific email by sender.',
            'AI should use get_email with the correct message_id from context.'
        );

        $response2 = $turn2['final_response'];
        
        // Check if AI used a tool by looking through events
        $usedTool = false;
        $toolExec2 = null;
        
        if (isset($turn2['events']) && count($turn2['events']) > 0) {
            foreach ($turn2['events'] as $event) {
                if (isset($event['_debug']['tool_execution'])) {
                    $usedTool = true;
                    $toolExec2 = $event['_debug']['tool_execution'];
                    break;
                }
            }
        }
        
        if ($usedTool) {
            // If AI used a tool, validate it was the correct one
            $this->assertEquals('get_email', $toolExec2['tool_name'], 
                'Turn 2: If AI uses a tool, it should be get_email. ' .
                "Actually used: '{$toolExec2['tool_name']}' with args: " . json_encode($toolExec2['arguments'] ?? [])
            );
            
                         $this->assertEquals('1977dccf78acbbb1', $toolExec2['arguments']['message_id'], 
                 'Turn 2: AI should have found the correct message ID for the Charles Schwab email. ' .
                 "Actually used message_id: '{$toolExec2['arguments']['message_id']}', " .
                 'Expected: 1977dccf78acbbb1 (Charles Schwab email)'
             );
        } else {
            // If AI didn't use a tool, verify it still provided a reasonable response about Charles Schwab
            $schwabMentioned = (
                stripos($response2['content'], 'Charles Schwab') !== false ||
                stripos($response2['content'], 'Schwab') !== false ||
                stripos($response2['content'], 'Save Money Traveling') !== false ||
                stripos($response2['content'], 'traveling abroad') !== false
            );
            
            $this->assertTrue($schwabMentioned, 
                'Turn 2: Response should mention Charles Schwab or content from their email. ' .
                'Actual response: ' . substr($response2['content'], 0, 200) . '...'
            );
        }
        
        $turn2['result'] = 'PASS';
    }
} 