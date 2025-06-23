<?php

namespace Tests\E2E\Interactions;

require_once __DIR__ . '/../E2ETestCase.php';

use Tests\E2E\E2ETestCase;

class SearchContactsTest extends E2ETestCase
{
    public function testDirectContactSearchRequest(): void
    {
        // === TURN 1: User directly asks to search for a contact ===
        $turn1 = $this->performTurn(
            'Search for Randy in my contacts',
            'User explicitly requests to search for Randy in contacts.',
            'AI should use search_contacts tool to find Randy.'
        );
        
        $this->assertArrayHasKey('events', $turn1, 'Turn 1: Should have events.');
        
        $searchContactsFound = false;
        
        foreach ($turn1['events'] as $event) {
            if (isset($event['_debug']['tool_execution']['tool_name'])) {
                $toolName = $event['_debug']['tool_execution']['tool_name'];
                
                if ($toolName === 'search_contacts') {
                    $searchContactsFound = true;
                    $args = $event['_debug']['tool_execution']['arguments'];
                    $this->assertStringContainsStringIgnoringCase('randy', $args['name'], 
                        'Turn 1: Should search for Randy.'
                    );
                    
                    // Verify the result
                    $result = $event['_debug']['tool_execution']['result'];
                    $this->assertEquals('found_contact', $result['status'], 
                        'Turn 1: Should find Randy successfully.'
                    );
                    $this->assertEquals('randy.johnson@techcorp.com', $result['contact']['email'], 
                        'Turn 1: Should return Randy\'s email address.'
                    );
                }
            }
        }
        
        $this->assertTrue($searchContactsFound, 'Turn 1: Should use search_contacts tool.');
        
        // Verify AI mentions the found contact
        $response = strtolower($turn1['final_response']['content']);
        $this->assertTrue(
            strpos($response, 'randy') !== false && 
            strpos($response, 'randy.johnson@techcorp.com') !== false,
            'Turn 1: Should mention Randy and his email address in response.'
        );
        
        $turn1['result'] = 'PASS';
    }
    
    public function testFindContactEmailRequest(): void
    {
        // === TURN 1: User asks for someone's email address ===
        $turn1 = $this->performTurn(
            'What is John\'s email address?',
            'User asks for John\'s email address.',
            'AI should search contacts to find John\'s email.'
        );
        
        $this->assertArrayHasKey('events', $turn1, 'Turn 1: Should have events.');
        
        $searchContactsFound = false;
        $multipleFound = false;
        
        foreach ($turn1['events'] as $event) {
            if (isset($event['_debug']['tool_execution']['tool_name'])) {
                $toolName = $event['_debug']['tool_execution']['tool_name'];
                
                if ($toolName === 'search_contacts') {
                    $searchContactsFound = true;
                    $args = $event['_debug']['tool_execution']['arguments'];
                    $this->assertStringContainsStringIgnoringCase('john', $args['name'], 
                        'Turn 1: Should search for John.'
                    );
                    
                    // Verify multiple contacts found
                    $result = $event['_debug']['tool_execution']['result'];
                    if ($result['status'] === 'found_multiple') {
                        $multipleFound = true;
                        $this->assertCount(2, $result['contacts'], 
                            'Turn 1: Should find 2 Johns.'
                        );
                    }
                }
            }
        }
        
        $this->assertTrue($searchContactsFound, 'Turn 1: Should use search_contacts tool.');
        $this->assertTrue($multipleFound, 'Turn 1: Should find multiple Johns.');
        
        // Verify AI asks for clarification
        $response = strtolower($turn1['final_response']['content']);
        $this->assertTrue(
            (strpos($response, 'john doe') !== false && strpos($response, 'john smith') !== false) ||
            (strpos($response, 'multiple') !== false && strpos($response, 'john') !== false),
            'Turn 1: Should mention both Johns or indicate multiple results.'
        );
        
        $turn1['result'] = 'PASS';
    }
    
    public function testContactLookupBeforeAction(): void
    {
        // === TURN 1: User wants to perform an action that requires finding a contact ===
        $turn1 = $this->performTurn(
            'I need to get in touch with Charles',
            'User wants to contact Charles but doesn\'t specify how.',
            'AI should search for Charles to provide contact options.'
        );
        
        $this->assertArrayHasKey('events', $turn1, 'Turn 1: Should have events.');
        
        $searchContactsFound = false;
        
        foreach ($turn1['events'] as $event) {
            if (isset($event['_debug']['tool_execution']['tool_name'])) {
                $toolName = $event['_debug']['tool_execution']['tool_name'];
                
                if ($toolName === 'search_contacts') {
                    $searchContactsFound = true;
                    $args = $event['_debug']['tool_execution']['arguments'];
                    $this->assertStringContainsStringIgnoringCase('charles', $args['name'], 
                        'Turn 1: Should search for Charles.'
                    );
                    
                    // Verify Charles Schwab found
                    $result = $event['_debug']['tool_execution']['result'];
                    $this->assertEquals('found_contact', $result['status'], 
                        'Turn 1: Should find Charles Schwab.'
                    );
                    $this->assertEquals('customerservice@schwab.com', $result['contact']['email'], 
                        'Turn 1: Should return Charles Schwab customer service email.'
                    );
                }
            }
        }
        
        $this->assertTrue($searchContactsFound, 'Turn 1: Should use search_contacts tool.');
        
        // Verify AI provides contact information
        $response = strtolower($turn1['final_response']['content']);
        $this->assertTrue(
            strpos($response, 'charles') !== false && 
            strpos($response, 'customerservice@schwab.com') !== false,
            'Turn 1: Should provide Charles\'s contact information.'
        );
        
        $turn1['result'] = 'PASS';
    }
    
    public function testPartialNameSearch(): void
    {
        // === TURN 1: User provides partial name that should match ===
        $turn1 = $this->performTurn(
            'Do I have a contact named Rand?',
            'User asks about partial name that should match Randy.',
            'AI should search and find Randy as a match.'
        );
        
        $this->assertArrayHasKey('events', $turn1, 'Turn 1: Should have events.');
        
        $searchContactsFound = false;
        
        foreach ($turn1['events'] as $event) {
            if (isset($event['_debug']['tool_execution']['tool_name'])) {
                $toolName = $event['_debug']['tool_execution']['tool_name'];
                
                if ($toolName === 'search_contacts') {
                    $searchContactsFound = true;
                    $args = $event['_debug']['tool_execution']['arguments'];
                    $this->assertStringContainsStringIgnoringCase('rand', $args['name'], 
                        'Turn 1: Should search for Rand.'
                    );
                }
            }
        }
        
        $this->assertTrue($searchContactsFound, 'Turn 1: Should use search_contacts tool.');
        
        $turn1['result'] = 'PASS';
    }
    
    public function testContactNotFoundScenario(): void
    {
        // === TURN 1: User searches for non-existent contact ===
        $turn1 = $this->performTurn(
            'Find contact information for Zephyr',
            'User searches for Zephyr who is not in contacts.',
            'AI should search and report that Zephyr is not found.'
        );
        
        $this->assertArrayHasKey('events', $turn1, 'Turn 1: Should have events.');
        
        $searchContactsFound = false;
        $notFoundStatus = false;
        
        foreach ($turn1['events'] as $event) {
            if (isset($event['_debug']['tool_execution']['tool_name'])) {
                $toolName = $event['_debug']['tool_execution']['tool_name'];
                
                if ($toolName === 'search_contacts') {
                    $searchContactsFound = true;
                    $args = $event['_debug']['tool_execution']['arguments'];
                    $this->assertStringContainsStringIgnoringCase('zephyr', $args['name'], 
                        'Turn 1: Should search for Zephyr.'
                    );
                    
                    // Verify not found status
                    $result = $event['_debug']['tool_execution']['result'];
                    if ($result['status'] === 'no_contacts_found') {
                        $notFoundStatus = true;
                    }
                }
            }
        }
        
        $this->assertTrue($searchContactsFound, 'Turn 1: Should use search_contacts tool.');
        $this->assertTrue($notFoundStatus, 'Turn 1: Should receive no_contacts_found status.');
        
        // Verify AI reports not found
        $response = strtolower($turn1['final_response']['content']);
        $this->assertTrue(
            strpos($response, 'not found') !== false || 
            strpos($response, 'no contact') !== false ||
            strpos($response, 'couldn\'t find') !== false ||
            strpos($response, 'don\'t have') !== false,
            'Turn 1: Should indicate Zephyr was not found.'
        );
        
        $turn1['result'] = 'PASS';
    }
    
    public function testContactSearchVariations(): void
    {
        // === TURN 1: Different ways to ask for contact search ===
        $variations = [
            'Look up Randy',
            'Find Randy in contacts',
            'Do you have Randy\'s information?',
            'Is Randy in my contact list?',
            'Show me Randy\'s details'
        ];
        
        $variation = $variations[array_rand($variations)];
        
        $turn1 = $this->performTurn(
            $variation,
            'User asks to find Randy using different phrasing.',
            'AI should recognize the request and use search_contacts.'
        );
        
        $this->assertArrayHasKey('events', $turn1, 'Turn 1: Should have events.');
        
        $searchContactsFound = false;
        
        foreach ($turn1['events'] as $event) {
            if (isset($event['_debug']['tool_execution']['tool_name'])) {
                $toolName = $event['_debug']['tool_execution']['tool_name'];
                
                if ($toolName === 'search_contacts') {
                    $searchContactsFound = true;
                    $args = $event['_debug']['tool_execution']['arguments'];
                    $this->assertStringContainsStringIgnoringCase('randy', $args['name'], 
                        'Turn 1: Should search for Randy regardless of phrasing.'
                    );
                }
            }
        }
        
        $this->assertTrue($searchContactsFound, 'Turn 1: Should use search_contacts tool.');
        
        $turn1['result'] = 'PASS';
    }
    
    public function testImplicitContactSearchForCommunication(): void
    {
        // === TURN 1: User wants to communicate but doesn't explicitly ask to search ===
        $turn1 = $this->performTurn(
            'I want to call Randy',
            'User wants to call Randy, implying need for contact information.',
            'AI should search for Randy to provide phone number or suggest email.'
        );
        
        $this->assertArrayHasKey('events', $turn1, 'Turn 1: Should have events.');
        
        $searchContactsFound = false;
        
        foreach ($turn1['events'] as $event) {
            if (isset($event['_debug']['tool_execution']['tool_name'])) {
                $toolName = $event['_debug']['tool_execution']['tool_name'];
                
                if ($toolName === 'search_contacts') {
                    $searchContactsFound = true;
                    $args = $event['_debug']['tool_execution']['arguments'];
                    $this->assertStringContainsStringIgnoringCase('randy', $args['name'], 
                        'Turn 1: Should search for Randy when user wants to call.'
                    );
                }
            }
        }
        
        $this->assertTrue($searchContactsFound, 'Turn 1: Should use search_contacts tool.');
        
        // AI should mention Randy's contact info or suggest alternatives
        $response = strtolower($turn1['final_response']['content']);
        $this->assertTrue(
            strpos($response, 'randy') !== false,
            'Turn 1: Should mention Randy in the response.'
        );
        
        $turn1['result'] = 'PASS';
    }
    
    public function testFullNameVsPartialNameSearch(): void
    {
        // === TURN 1: Search using full name format ===
        $turn1 = $this->performTurn(
            'Search for John Smith',
            'User searches for John Smith by full name.',
            'AI should search for John Smith and potentially find multiple Johns.'
        );
        
        $this->assertArrayHasKey('events', $turn1, 'Turn 1: Should have events.');
        
        $searchContactsFound = false;
        
        foreach ($turn1['events'] as $event) {
            if (isset($event['_debug']['tool_execution']['tool_name'])) {
                $toolName = $event['_debug']['tool_execution']['tool_name'];
                
                if ($toolName === 'search_contacts') {
                    $searchContactsFound = true;
                    $args = $event['_debug']['tool_execution']['arguments'];
                    
                    // Should search for either "John Smith" or "John" 
                    $searchTerm = strtolower($args['name']);
                    $this->assertTrue(
                        strpos($searchTerm, 'john') !== false,
                        'Turn 1: Should search for John (full name or partial).'
                    );
                }
            }
        }
        
        $this->assertTrue($searchContactsFound, 'Turn 1: Should use search_contacts tool.');
        
        $turn1['result'] = 'PASS';
    }
} 