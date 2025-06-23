<?php

declare(strict_types=1);

namespace Tests\Unit;

require_once __DIR__ . '/../../core/PhpSession.php';

use App\Core\PhpSession;
use PHPUnit\Framework\TestCase;

/**
 * Comprehensive tests for PhpSession
 * @covers \App\Core\PhpSession
 */
class PhpSessionTest extends TestCase
{
    private PhpSession $session;
    private array $originalSession;

    protected function setUp(): void
    {
        // Backup original $_SESSION
        $this->originalSession = $_SESSION ?? [];
        
        // Start with clean session for each test
        $_SESSION = [];
        
        $this->session = new PhpSession();
    }

    protected function tearDown(): void
    {
        // Restore original $_SESSION
        $_SESSION = $this->originalSession;
    }

    public function testGetWithExistingKey(): void
    {
        $_SESSION['test_key'] = 'test_value';
        $result = $this->session->get('test_key');
        $this->assertEquals('test_value', $result);
    }

    public function testGetWithNonExistentKeyReturnsDefault(): void
    {
        $result = $this->session->get('non_existent_key', 'default_value');
        $this->assertEquals('default_value', $result);
    }

    public function testGetWithNonExistentKeyReturnsNull(): void
    {
        $result = $this->session->get('non_existent_key');
        $this->assertNull($result);
    }

    public function testSetAndGet(): void
    {
        $this->session->set('string_key', 'string_value');
        $this->session->set('array_key', ['array', 'value']);
        $this->session->set('int_key', 42);
        $this->session->set('bool_key', true);

        $this->assertEquals('string_value', $this->session->get('string_key'));
        $this->assertEquals(['array', 'value'], $this->session->get('array_key'));
        $this->assertEquals(42, $this->session->get('int_key'));
        $this->assertTrue($this->session->get('bool_key'));

        // Verify data is actually stored in $_SESSION
        $this->assertEquals('string_value', $_SESSION['string_key']);
        $this->assertEquals(['array', 'value'], $_SESSION['array_key']);
        $this->assertEquals(42, $_SESSION['int_key']);
        $this->assertTrue($_SESSION['bool_key']);
    }

    public function testHasWithExistingKey(): void
    {
        $_SESSION['existing_key'] = 'value';
        $this->assertTrue($this->session->has('existing_key'));
    }

    public function testHasWithNonExistentKey(): void
    {
        $this->assertFalse($this->session->has('non_existent_key'));
    }

    public function testHasWithNullValue(): void
    {
        $_SESSION['null_key'] = null;
        $this->assertFalse($this->session->has('null_key')); // isset() returns false for null
    }

    public function testRemove(): void
    {
        $_SESSION['key_to_remove'] = 'value';
        $this->assertTrue($this->session->has('key_to_remove'));
        
        $this->session->remove('key_to_remove');
        $this->assertFalse($this->session->has('key_to_remove'));
        $this->assertArrayNotHasKey('key_to_remove', $_SESSION);
    }

    public function testRemoveNonExistentKey(): void
    {
        // Should not throw an error
        $this->session->remove('non_existent_key');
        $this->assertFalse($this->session->has('non_existent_key'));
    }

    public function testClear(): void
    {
        $_SESSION['key1'] = 'value1';
        $_SESSION['key2'] = 'value2';
        $_SESSION['key3'] = 'value3';

        $this->assertTrue($this->session->has('key1'));
        $this->assertTrue($this->session->has('key2'));
        $this->assertTrue($this->session->has('key3'));

        $this->session->clear();

        $this->assertFalse($this->session->has('key1'));
        $this->assertFalse($this->session->has('key2'));
        $this->assertFalse($this->session->has('key3'));
        $this->assertEquals([], $this->session->all());
        $this->assertEquals([], $_SESSION);
    }

    public function testAll(): void
    {
        $expectedData = [
            'key1' => 'value1',
            'key2' => ['array', 'data'],
            'key3' => 42
        ];

        foreach ($expectedData as $key => $value) {
            $this->session->set($key, $value);
        }

        $result = $this->session->all();
        $this->assertEquals($expectedData, $result);
        $this->assertEquals($expectedData, $_SESSION);
    }

    public function testAllWhenEmpty(): void
    {
        $result = $this->session->all();
        $this->assertEquals([], $result);
    }

    public function testOverwriteExistingKey(): void
    {
        $this->session->set('key', 'original_value');
        $this->assertEquals('original_value', $this->session->get('key'));
        $this->assertEquals('original_value', $_SESSION['key']);

        $this->session->set('key', 'new_value');
        $this->assertEquals('new_value', $this->session->get('key'));
        $this->assertEquals('new_value', $_SESSION['key']);
    }

    public function testComplexDataTypes(): void
    {
        $complexData = [
            'nested' => [
                'array' => [
                    'with' => 'values',
                    'and' => ['more', 'nesting']
                ]
            ],
            'object' => (object) ['property' => 'value']
        ];

        $this->session->set('complex', $complexData);
        $result = $this->session->get('complex');

        $this->assertEquals($complexData, $result);
        $this->assertEquals('values', $result['nested']['array']['with']);
        $this->assertEquals('value', $result['object']->property);
        
        // Verify it's stored in $_SESSION
        $this->assertEquals($complexData, $_SESSION['complex']);
    }

    public function testDirectSessionAccess(): void
    {
        // Test that changes to $_SESSION are reflected in PhpSession
        $_SESSION['direct_key'] = 'direct_value';
        $this->assertEquals('direct_value', $this->session->get('direct_key'));
        $this->assertTrue($this->session->has('direct_key'));

        // Test that PhpSession changes are reflected in $_SESSION
        $this->session->set('php_session_key', 'php_session_value');
        $this->assertEquals('php_session_value', $_SESSION['php_session_key']);
    }
} 