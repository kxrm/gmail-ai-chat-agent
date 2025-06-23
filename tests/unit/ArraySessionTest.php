<?php

declare(strict_types=1);

namespace Tests\Unit;

require_once __DIR__ . '/../../core/ArraySession.php';

use App\Core\ArraySession;
use PHPUnit\Framework\TestCase;

/**
 * Comprehensive tests for ArraySession
 * @covers \App\Core\ArraySession
 */
class ArraySessionTest extends TestCase
{
    private ArraySession $session;

    protected function setUp(): void
    {
        $this->session = new ArraySession();
    }

    public function testGetWithExistingKey(): void
    {
        $this->session->set('test_key', 'test_value');
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
    }

    public function testHasWithExistingKey(): void
    {
        $this->session->set('existing_key', 'value');
        $this->assertTrue($this->session->has('existing_key'));
    }

    public function testHasWithNonExistentKey(): void
    {
        $this->assertFalse($this->session->has('non_existent_key'));
    }

    public function testHasWithNullValue(): void
    {
        $this->session->set('null_key', null);
        $this->assertFalse($this->session->has('null_key')); // isset() returns false for null
    }

    public function testRemove(): void
    {
        $this->session->set('key_to_remove', 'value');
        $this->assertTrue($this->session->has('key_to_remove'));
        
        $this->session->remove('key_to_remove');
        $this->assertFalse($this->session->has('key_to_remove'));
    }

    public function testRemoveNonExistentKey(): void
    {
        // Should not throw an error
        $this->session->remove('non_existent_key');
        $this->assertFalse($this->session->has('non_existent_key'));
    }

    public function testClear(): void
    {
        $this->session->set('key1', 'value1');
        $this->session->set('key2', 'value2');
        $this->session->set('key3', 'value3');

        $this->assertTrue($this->session->has('key1'));
        $this->assertTrue($this->session->has('key2'));
        $this->assertTrue($this->session->has('key3'));

        $this->session->clear();

        $this->assertFalse($this->session->has('key1'));
        $this->assertFalse($this->session->has('key2'));
        $this->assertFalse($this->session->has('key3'));
        $this->assertEquals([], $this->session->all());
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

        $this->session->set('key', 'new_value');
        $this->assertEquals('new_value', $this->session->get('key'));
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
    }
} 