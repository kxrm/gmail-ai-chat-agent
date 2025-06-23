<?php

namespace Tests\E2E\Interactions;

require_once __DIR__ . '/../E2ETestCase.php';

use Tests\E2E\E2ETestCase;

class ContactDisambiguationTest extends E2ETestCase
{
    public function testSingleContactFound(): void
    {
        // === TURN 1: User asks to email someone with unique name ===
        $turn1 = $this->performTurn(
            'Create an email to Randy stating that the project deadline has been moved to next Friday',
            'User wants to create an email to Randy with specific content.',
            'AI should search for Randy and create draft email with the provided content.'
        );
        
        // Verify search_contacts was used
        $this->assertArrayHasKey('events', $turn1, 'Turn 1: Should have events.');
        
        $searchContactsFound = false;
        $createEmailFound = false;
        
        foreach ($turn1['events'] as $event) {
            if (isset($event['_debug']['tool_execution']['tool_name'])) {
                $toolName = $event['_debug']['tool_execution']['tool_name'];
                
                if ($toolName === 'search_contacts') {
                    $searchContactsFound = true;
                    $args = $event['_debug']['tool_execution']['arguments'];
                    $this->assertStringContainsStringIgnoringCase('randy', $args['name'], 
                        'Turn 1: Should search for Randy.'
                    );
                }
                
                if ($toolName === 'create_draft') {
                    $createEmailFound = true;
                    $args = $event['_debug']['tool_execution']['arguments'];
                    $this->assertStringContainsString('project deadline', $args['body'], 
                        'Turn 1: Email should contain the project deadline message.'
                    );
                    $this->assertStringContainsString('next Friday', $args['body'], 
                        'Turn 1: Email should mention next Friday.'
                    );
                }
            }
        }
        
        $this->assertTrue($searchContactsFound, 'Turn 1: Should use search_contacts.');
        $this->assertTrue($createEmailFound, 'Turn 1: Should create draft email.');
        
        // Verify AI asks about sending the draft
        $response = strtolower($turn1['final_response']['content']);
        $this->assertTrue(
            strpos($response, 'draft') !== false && 
            (strpos($response, 'send') !== false || strpos($response, 'changes') !== false),
            'Turn 1: Should mention draft and ask about sending or making changes. Got: ' . $response
        );
        
        $turn1['result'] = 'PASS';
    }
    
    public function testMultipleContactsWithSameName(): void
    {
        // === TURN 1: User asks to email someone with a common name ===
        $turn1 = $this->performTurn(
            'Create an email to John about the quarterly review meeting',
            'User wants to email John but there are multiple Johns.',
            'AI should search contacts and ask for clarification about which John.'
        );
        
        // Verify search_contacts was used
        $this->assertArrayHasKey('events', $turn1, 'Turn 1: Should have events.');
        
        $searchContactsFound = false;
        $multipleContactsFound = false;
        
        foreach ($turn1['events'] as $event) {
            // Check for search_contacts tool call
            if (isset($event['tool_name']) && $event['tool_name'] === 'search_contacts') {
                $searchContactsFound = true;
                $this->assertStringContainsStringIgnoringCase('john', $event['arguments']['name'], 
                    'Turn 1: Should search for John.'
                );
            }
            
            // Check for search_contacts execution result
            if (isset($event['_debug']['tool_execution'])) {
                $toolExec = $event['_debug']['tool_execution'];
                
                if ($toolExec['tool_name'] === 'search_contacts') {
                    // Check if multiple contacts were returned
                    if (isset($toolExec['result']['status']) && 
                        $toolExec['result']['status'] === 'found_multiple') {
                        $multipleContactsFound = true;
                    }
                }
            }
        }
        
        $this->assertTrue($searchContactsFound, 'Turn 1: Should use search_contacts.');
        $this->assertTrue($multipleContactsFound, 'Turn 1: Should find multiple Johns.');
        
        // Check that AI asks for clarification
        $response = $turn1['final_response']['content'];
        $this->assertStringContainsStringIgnoringCase('john doe', $response, 
            'Turn 1: Should mention John Doe as an option.'
        );
        $this->assertStringContainsStringIgnoringCase('john smith', $response, 
            'Turn 1: Should mention John Smith as an option.'
        );
        
        $turn1['result'] = 'PASS';
        
        // === TURN 2: User clarifies which John ===
        $turn2 = $this->performTurn(
            'John Smith from Global Inc',
            'User clarifies they want John Smith.',
            'AI should create draft email to the correct John.'
        );
        
        // Verify email is created/sent to correct recipient
        $emailToolFound = false;
        
        foreach ($turn2['events'] as $event) {
            // Check for create_draft tool call
            if (isset($event['tool_name']) && $event['tool_name'] === 'create_draft') {
                $emailToolFound = true;
                $args = $event['arguments'];
                
                // Verify correct email address
                $this->assertEquals('john.smith@globalinc.com', $args['to'], 
                    'Turn 2: Should send to John Smith\'s email.'
                );
                
                // Verify content from original request
                $this->assertStringContainsString('quarterly review', $args['body'], 
                    'Turn 2: Should include original message about quarterly review.'
                );
            }
            
            // Also check execution results if available
            if (isset($event['_debug']['tool_execution'])) {
                $toolExec = $event['_debug']['tool_execution'];
                
                if ($toolExec['tool_name'] === 'create_draft' && !$emailToolFound) {
                    $emailToolFound = true;
                    $args = $toolExec['arguments'];
                    
                    // Verify correct email address
                    $this->assertEquals('john.smith@globalinc.com', $args['to'], 
                        'Turn 2: Should send to John Smith\'s email.'
                    );
                    
                    // Verify content from original request
                    $this->assertStringContainsString('quarterly review', $args['body'], 
                        'Turn 2: Should include original message about quarterly review.'
                    );
                }
            }
        }
        
        $this->assertTrue($emailToolFound, 'Turn 2: Should create draft email after clarification.');
        
        $turn2['result'] = 'PASS';
    }
    
    public function testContactNotFoundScenario(): void
    {
        // === TURN 1: User asks to email someone not in contacts ===
        $turn1 = $this->performTurn(
            'Create an email to Zephyr about the new project proposal',
            'User wants to email someone not in contacts.',
            'AI should search and report contact not found.'
        );
        
        // Verify search_contacts was used
        $searchContactsFound = false;
        $notFoundStatus = false;
        
        foreach ($turn1['events'] as $event) {
            // Check for search_contacts tool call
            if (isset($event['tool_name']) && $event['tool_name'] === 'search_contacts') {
                $searchContactsFound = true;
                $this->assertStringContainsStringIgnoringCase('zephyr', $event['arguments']['name'], 
                    'Turn 1: Should search for Zephyr.'
                );
            }
            
            // Check for search_contacts execution result
            if (isset($event['_debug']['tool_execution'])) {
                $toolExec = $event['_debug']['tool_execution'];
                
                if ($toolExec['tool_name'] === 'search_contacts') {
                    if (isset($toolExec['result']['status']) && 
                        $toolExec['result']['status'] === 'no_contacts_found') {
                        $notFoundStatus = true;
                    }
                }
            }
        }
        
        $this->assertTrue($searchContactsFound, 'Turn 1: Should use search_contacts.');
        $this->assertTrue($notFoundStatus, 'Turn 1: Should receive no_contacts_found status.');
        
        // Verify AI reports contact not found
        $response = strtolower($turn1['final_response']['content']);
        $this->assertTrue(
            strpos($response, 'not found') !== false || 
            strpos($response, 'no contact') !== false ||
            strpos($response, 'couldn\'t find') !== false ||
            strpos($response, 'didn\'t find') !== false ||
            strpos($response, 'don\'t have') !== false ||
            strpos($response, 'isn\'t in') !== false,
            'Turn 1: Should indicate Zephyr was not found in contacts.'
        );
        
        // Verify no email was created
        $emailCreated = false;
        foreach ($turn1['events'] as $event) {
            // Check for tool calls
            if (isset($event['tool_name']) && 
                in_array($event['tool_name'], ['create_draft', 'send_email'])) {
                $emailCreated = true;
            }
            
            // Also check execution results
            if (isset($event['_debug']['tool_execution']['tool_name']) && 
                in_array($event['_debug']['tool_execution']['tool_name'], ['create_draft', 'send_email'])) {
                $emailCreated = true;
            }
        }
        
        $this->assertFalse($emailCreated, 'Turn 1: Should NOT create email when contact not found.');
        
        $turn1['result'] = 'PASS';
    }
    
    public function testPartialNameMatch(): void
    {
        // === TURN 1: User provides partial name that matches someone ===
        $turn1 = $this->performTurn(
            'Send an email to Charles about my account status inquiry',
            'User provides partial name that should match Charles Schwab.',
            'AI should find Charles Schwab Customer Service contact and create draft.'
        );
        
        // Verify search_contacts was used
        $searchContactsFound = false;
        $charlesFound = false;
        
        foreach ($turn1['events'] as $event) {
            // Check for search_contacts tool call
            if (isset($event['tool_name']) && $event['tool_name'] === 'search_contacts') {
                $searchContactsFound = true;
                $this->assertStringContainsStringIgnoringCase('charles', $event['arguments']['name'], 
                    'Turn 1: Should search for Charles.'
                );
            }
            
            // Check for search_contacts execution result
            if (isset($event['_debug']['tool_execution'])) {
                $toolExec = $event['_debug']['tool_execution'];
                
                if ($toolExec['tool_name'] === 'search_contacts') {
                    // Check if Charles Schwab was found
                    if (isset($toolExec['result']['status']) && 
                        $toolExec['result']['status'] === 'found_contact' &&
                        isset($toolExec['result']['contact']['email']) &&
                        $toolExec['result']['contact']['email'] === 'customerservice@schwab.com') {
                        $charlesFound = true;
                    }
                }
            }
        }
        
        $this->assertTrue($searchContactsFound, 'Turn 1: Should use search_contacts.');
        $this->assertTrue($charlesFound, 'Turn 1: Should find Charles Schwab Customer Service.');
        
        // Verify email was created with correct recipient
        $emailCreated = false;
        foreach ($turn1['events'] as $event) {
            // Check for create_draft tool call
            if (isset($event['tool_name']) && $event['tool_name'] === 'create_draft') {
                $emailCreated = true;
                $args = $event['arguments'];
                
                $this->assertEquals('customerservice@schwab.com', $args['to'], 
                    'Turn 1: Should create draft to Charles Schwab customer service.'
                );
                
                // Body should contain the inquiry since user provided context
                $this->assertStringContainsString('account status', $args['body'], 
                    'Turn 1: Should include account status inquiry in draft body.'
                );
            }
            
            // Also check execution results if available
            if (isset($event['_debug']['tool_execution'])) {
                $toolExec = $event['_debug']['tool_execution'];
                
                if ($toolExec['tool_name'] === 'create_draft' && !$emailCreated) {
                    $emailCreated = true;
                    $args = $toolExec['arguments'];
                    
                    $this->assertEquals('customerservice@schwab.com', $args['to'], 
                        'Turn 1: Should create draft to Charles Schwab customer service.'
                    );
                    
                    // Body should contain the inquiry since user provided context
                    $this->assertStringContainsString('account status', $args['body'], 
                        'Turn 1: Should include account status inquiry in draft body.'
                    );
                }
            }
        }
        
        $this->assertTrue($emailCreated, 'Turn 1: Should create draft email to Charles Schwab.');
        
        $turn1['result'] = 'PASS';
    }
} 