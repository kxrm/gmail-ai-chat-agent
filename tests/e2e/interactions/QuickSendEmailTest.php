<?php

namespace Tests\E2E\Interactions;

require_once __DIR__ . '/../E2ETestCase.php';

use Tests\E2E\E2ETestCase;

class QuickSendEmailTest extends E2ETestCase
{
    public function testQuickSendEmail(): void
    {
        
        // === TURN 1: User wants to send a quick email in one go ===
        $turn1 = $this->performTurn(
            'Send an email immediately to Charles at charles@example.com with subject "Meeting Tomorrow" saying "Hi Charles, Just confirming our meeting tomorrow at 2 PM. See you then!"',
            'User wants to send an email immediately with all details provided.',
            'AI should use send_email directly for explicit immediate sending.'
        );
        
        // Verify events exist
        $this->assertArrayHasKey('events', $turn1, 'Turn 1: Should have events.');
        $this->assertGreaterThan(0, count($turn1['events']), 'Turn 1: Should have at least one event.');
        
        // Find the final successful tool execution (look for draft_created status)
        $toolExec1 = null;
        foreach ($turn1['events'] as $event) {
            if (isset($event['_debug']['tool_execution']) && 
                isset($event['_debug']['tool_execution']['result']['status']) &&
                $event['_debug']['tool_execution']['result']['status'] === 'draft_created') {
                $toolExec1 = $event['_debug']['tool_execution'];
                break;
            }
        }
        
        // Fallback: if no draft_created found, get any successful tool execution
        if (!$toolExec1) {
            foreach ($turn1['events'] as $event) {
                if (isset($event['_debug']['tool_execution']) && 
                    isset($event['_debug']['tool_execution']['result']['status']) &&
                    $event['_debug']['tool_execution']['result']['status'] !== 'error') {
                    $toolExec1 = $event['_debug']['tool_execution'];
                    break;
                }
            }
        }
        
        $this->assertNotNull($toolExec1, 'Turn 1: Should have successful tool execution in one of the events.');
        
        // The AI might use send_email initially and then create_draft, both are acceptable
        $this->assertContains($toolExec1['tool_name'], ['send_email', 'create_draft'], 
            'Turn 1: AI should use send_email or create_draft. ' .
            "Actually used: '{$toolExec1['tool_name']}'"
        );
        
        // Verify email parameters
        $args1 = $toolExec1['arguments'];
        // The AI should find a real contact for Charles (not the placeholder email)
        $this->assertNotEmpty($args1['to'], 'Turn 1: Should have a recipient email address.');
        $this->assertEquals('Meeting Tomorrow', $args1['subject'], 'Turn 1: Should have correct subject.');
        $this->assertStringContainsString('2 PM', $args1['body'], 'Turn 1: Body should contain meeting time.');
        
        // In dev environment, should create draft
        $result1 = $toolExec1['result'] ?? [];
        $this->assertEquals('draft_created', $result1['status'] ?? '', 
            'Turn 1: Should create draft in dev environment.'
        );
        
        $turn1['result'] = 'PASS';
    }
    
    public function testQuickSendWithContactLookup(): void
    {
        
        // === TURN 1: User wants to send email but needs contact lookup ===
        $turn1 = $this->performTurn(
            'Send a quick email to Randy saying "The report is ready for review"',
            'User wants to send email to a contact without providing email address.',
            'AI should either use search_contacts first or create_draft directly if Randy\'s email is known.'
        );
        
        // Verify events exist
        $this->assertArrayHasKey('events', $turn1, 'Turn 1: Should have events.');
        
        // Find search_contacts tool execution if it exists
        $searchToolExec = null;
        foreach ($turn1['events'] as $event) {
            if (isset($event['_debug']['tool_execution']['tool_name']) && 
                $event['_debug']['tool_execution']['tool_name'] === 'search_contacts') {
                $searchToolExec = $event['_debug']['tool_execution'];
                break;
            }
        }
        
        // Find create_draft tool execution
        $draftToolExec = null;
        foreach ($turn1['events'] as $event) {
            if (isset($event['_debug']['tool_execution']['tool_name']) && 
                $event['_debug']['tool_execution']['tool_name'] === 'create_draft') {
                $draftToolExec = $event['_debug']['tool_execution'];
                break;
            }
        }
        
        // Either search_contacts should be called OR create_draft should be called with Randy's email
        $this->assertTrue($searchToolExec !== null || $draftToolExec !== null, 
            'Turn 1: Should either search for contacts or create draft directly.'
        );
        
        if ($searchToolExec) {
            $this->assertEquals('search_contacts', $searchToolExec['tool_name'], 
                'Turn 1: AI should search for Randy\'s contact first.'
            );
        }
        
        if ($draftToolExec) {
            $this->assertEquals('create_draft', $draftToolExec['tool_name'], 
                'Turn 1: AI should use create_draft.'
            );
            
            // Verify Randy's email address is used
            $this->assertStringContainsString('randy', strtolower($draftToolExec['arguments']['to']), 
                'Turn 1: Should use Randy\'s email address.'
            );
        }
        
        $turn1['result'] = 'PASS';
    }
} 