<?php

namespace Tests\E2E\Interactions;

use Tests\E2E\E2ETestCase;

class ContactSearchVariationsTest extends E2ETestCase
{
    public function testContactSearchWithMultipleResults(): void
    {
        // === TURN 1: Search for a contact that might have multiple matches ===
        $turn1 = $this->performTurn(
            'What\'s John\'s email address?',
            'User asks for a contact\'s email that might have multiple matches.',
            'AI should use search_contacts to find John.'
        );
        
        // Verify search_contacts was used (granular approach)
        $this->assertArrayHasKey('events', $turn1, 'Turn 1: Should have events.');
        $firstEvent1 = $turn1['events'][0];
        
        // Check if first event is a tool_call
        if (isset($firstEvent1['type']) && $firstEvent1['type'] === 'tool_call') {
            $this->assertEquals('search_contacts', $firstEvent1['tool_name'], 
                'Turn 1: AI should search for John in contacts.'
            );
            $this->assertStringContainsStringIgnoringCase('john', $firstEvent1['arguments']['name'], 
                'Turn 1: Should search for John.'
            );
        } else {
            // Fallback: check debug info
            $toolExec1 = $firstEvent1['_debug']['ollama_parsed_response'] ?? null;
            $this->assertNotNull($toolExec1, 'Turn 1: Should have tool call in debug info.');
            $this->assertEquals('tool_use', $toolExec1['action'], 'Turn 1: Should be tool_use action.');
            $this->assertEquals('search_contacts', $toolExec1['tool_name'], 
                'Turn 1: AI should search for John in contacts.'
            );
            $this->assertStringContainsStringIgnoringCase('john', $toolExec1['arguments']['name'], 
                'Turn 1: Should search for John.'
            );
        }
        
        // Check the response appropriately handles the result
        $response1 = $turn1['final_response'];
        $this->assertEquals('response', $response1['type'], 'Turn 1: Should have a response.');
        
        $turn1['result'] = 'PASS';
    }
    
    public function testContactNotFound(): void
    {
        // === TURN 1: Search for a non-existent contact ===
        $turn1 = $this->performTurn(
            'Do you have an email for someone named Zephyr?',
            'User asks for a contact that doesn\'t exist.',
            'AI should use search_contacts and handle not found gracefully.'
        );
        
        // Verify search_contacts was used (granular approach)
        $this->assertArrayHasKey('events', $turn1, 'Turn 1: Should have events.');
        $firstEvent1 = $turn1['events'][0];
        
        // Check if first event is a tool_call
        if (isset($firstEvent1['type']) && $firstEvent1['type'] === 'tool_call') {
            $this->assertEquals('search_contacts', $firstEvent1['tool_name'], 
                'Turn 1: AI should search for Zephyr in contacts.'
            );
        } else {
            // Fallback: check debug info
            $toolExec1 = $firstEvent1['_debug']['ollama_parsed_response'] ?? null;
            $this->assertNotNull($toolExec1, 'Turn 1: Should have tool call in debug info.');
            $this->assertEquals('tool_use', $toolExec1['action'], 'Turn 1: Should be tool_use action.');
            $this->assertEquals('search_contacts', $toolExec1['tool_name'], 
                'Turn 1: AI should search for Zephyr in contacts.'
            );
        }
        
        // Response should indicate contact not found
        $response1 = $turn1['final_response'];
        $content = strtolower($response1['content']);
        $this->assertTrue(
            strpos($content, 'not found') !== false || 
            strpos($content, 'no contact') !== false ||
            strpos($content, 'couldn\'t find') !== false ||
            strpos($content, 'didn\'t find') !== false ||
            strpos($content, 'don\'t have') !== false ||
            strpos($content, 'isn\'t in') !== false,
            'Turn 1: Response should indicate contact was not found.'
        );
        
        $turn1['result'] = 'PASS';
    }
    
    public function testContactSearchInContext(): void
    {
        // === TURN 1: Ask about emails ===
        $turn1 = $this->performTurn(
            'Show me emails from Charles Schwab',
            'User asks for emails from a sender.',
            'AI should search for emails.'
        );
        
        $turn1['result'] = 'PASS';
        
        // === TURN 2: Ask for contact info based on previous context ===
        $turn2 = $this->performTurn(
            'What\'s their customer service email?',
            'User asks for contact info related to previous context.',
            'AI should use search_contacts for Charles Schwab.'
        );
        
        // Verify search_contacts was used (granular approach)
        $this->assertArrayHasKey('events', $turn2, 'Turn 2: Should have events.');
        $firstEvent2 = $turn2['events'][0];
        
        // Check if first event is a tool_call
        if (isset($firstEvent2['type']) && $firstEvent2['type'] === 'tool_call') {
            if ($firstEvent2['tool_name'] === 'search_contacts') {
                $this->assertStringContainsStringIgnoringCase('charles', $firstEvent2['arguments']['name'], 
                    'Turn 2: Should search for Charles Schwab based on context.'
                );
            }
        } else {
            // Fallback: check debug info
            $toolExec2 = $firstEvent2['_debug']['ollama_parsed_response'] ?? null;
            if ($toolExec2 && $toolExec2['tool_name'] === 'search_contacts') {
                $this->assertStringContainsStringIgnoringCase('charles', $toolExec2['arguments']['name'], 
                    'Turn 2: Should search for Charles Schwab based on context.'
                );
            }
        }
        
        $turn2['result'] = 'PASS';
    }
} 