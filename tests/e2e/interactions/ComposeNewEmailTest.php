<?php

namespace Tests\E2E\Interactions;

require_once __DIR__ . '/../E2ETestCase.php';

use Tests\E2E\E2ETestCase;

class ComposeNewEmailTest extends E2ETestCase
{
    public function testComposeAndSendNewEmail(): void
    {
        
        // === TURN 1: User wants to compose an email to a contact ===
        $turn1 = $this->performTurn(
            'I need to send an email to Randy about the project update',
            'User wants to compose an email to a contact named Randy.',
            'AI should use search_contacts to find Randy\'s email address.'
        );
        
        // Verify search_contacts was used (check tool call in first event)
        $this->assertArrayHasKey('events', $turn1, 'Turn 1: Should have events.');
        $this->assertGreaterThan(0, count($turn1['events']), 'Turn 1: Should have at least one event.');
        
        $firstEvent1 = $turn1['events'][0];
        $this->assertEquals('tool_call', $firstEvent1['type'], 'Turn 1: First event should be tool_call.');
        $this->assertEquals('search_contacts', $firstEvent1['tool_name'], 
            'Turn 1: AI should search for Randy in contacts.'
        );
        
        $this->assertArrayHasKey('name', $firstEvent1['arguments'], 'Turn 1: Should search by name.');
        $this->assertStringContainsStringIgnoringCase('randy', $firstEvent1['arguments']['name'], 
            'Turn 1: Should search for Randy.'
        );
        
        // Find the event that contains the search_contacts execution result
        $searchExecutionEvent = null;
        foreach ($turn1['events'] as $event) {
            if (isset($event['_debug']['tool_execution']['tool_name']) && 
                $event['_debug']['tool_execution']['tool_name'] === 'search_contacts') {
                $searchExecutionEvent = $event;
                break;
            }
        }
        
        $this->assertNotNull($searchExecutionEvent, 'Turn 1: Should have search_contacts execution result.');
        $toolExec1 = $searchExecutionEvent['_debug']['tool_execution'];
        $this->assertEquals('search_contacts', $toolExec1['tool_name']);
        $this->assertArrayHasKey('result', $toolExec1, 'Turn 1: Should have execution result.');
        
        $turn1['result'] = 'PASS';
        
        // === TURN 2: User provides email details ===
        $turn2 = $this->performTurn(
            'Please create an email with subject "Q4 Project Update" and body "Hi Randy, I wanted to update you on our Q4 progress. We\'ve completed 3 out of 5 milestones. Let me know if you need more details. Best, Jay"',
            'User provides email details for creating the email.',
            'AI should use create_draft with the found email address and ask about sending.'
        );
        
        // Verify create_draft was used
        $this->assertArrayHasKey('events', $turn2, 'Turn 2: Should have events.');
        
        // Find the successful create_draft event (may not be first due to retries)
        $createDraftEvent = null;
        foreach ($turn2['events'] as $event) {
            if (isset($event['_debug']['tool_execution']['tool_name']) && 
                $event['_debug']['tool_execution']['tool_name'] === 'create_draft' &&
                isset($event['_debug']['tool_execution']['result']['status']) &&
                $event['_debug']['tool_execution']['result']['status'] === 'draft_created') {
                $createDraftEvent = $event;
                break;
            }
        }
        
        $this->assertNotNull($createDraftEvent, 'Turn 2: Should have successful create_draft execution.');
        $toolExec2 = $createDraftEvent['_debug']['tool_execution'];
        
        // Verify draft parameters
        $args2 = $toolExec2['arguments'];
        $this->assertArrayHasKey('to', $args2, 'Turn 2: Draft should have recipient.');
        $this->assertArrayHasKey('subject', $args2, 'Turn 2: Draft should have subject.');
        $this->assertArrayHasKey('body', $args2, 'Turn 2: Draft should have body.');
        
        $this->assertEquals('Q4 Project Update', $args2['subject'], 'Turn 2: Subject should match request.');
        $this->assertStringContainsString('Q4 progress', $args2['body'], 'Turn 2: Body should contain key content.');
        
        // Verify AI asks about sending the draft
        $response2 = strtolower($turn2['final_response']['content']);
        $this->assertTrue(
            strpos($response2, 'draft') !== false && 
            (strpos($response2, 'send') !== false || strpos($response2, 'changes') !== false),
            'Turn 2: Should mention draft and ask about sending or making changes.'
        );
        
        $turn2['result'] = 'PASS';
        
        // === TURN 3: Send the draft ===
        $turn3 = $this->performTurn(
            'Perfect, please send it',
            'User confirms and wants to send the draft.',
            'AI should use send_draft (which will be blocked in dev environment).'
        );
        
        // Verify send_draft was attempted
        $this->assertArrayHasKey('events', $turn3, 'Turn 3: Should have events.');
        
        // Find the send_draft event (may not be first due to retries)
        $sendEvent = null;
        foreach ($turn3['events'] as $event) {
            if (isset($event['_debug']['tool_execution']['tool_name']) && 
                $event['_debug']['tool_execution']['tool_name'] === 'send_draft') {
                $sendEvent = $event;
                break;
            }
        }
        
        $this->assertNotNull($sendEvent, 'Turn 3: Should have send_draft tool execution.');
        $toolExec3 = $sendEvent['_debug']['tool_execution'];
        
        // In dev environment, should be blocked
        $result3 = $toolExec3['result'] ?? [];
        $this->assertEquals('blocked', $result3['status'] ?? '', 
            'Turn 3: Send should be blocked in dev environment.'
        );
        
        $turn3['result'] = 'PASS';
    }
} 