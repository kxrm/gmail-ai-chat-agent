<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\ResponseBuilder;

class ResponseBuilderTest extends TestCase
{
    public function testSuccessResponse()
    {
        $response = ResponseBuilder::success('Operation completed successfully');
        
        $this->assertEquals('response', $response['type']);
        $this->assertEquals('Operation completed successfully', $response['content']);
        $this->assertArrayNotHasKey('_debug', $response);
    }

    public function testSuccessResponseWithDebug()
    {
        $debugInfo = ['execution_time' => 123.45, 'memory_usage' => '2MB'];
        $response = ResponseBuilder::success('Success with debug', $debugInfo);
        
        $this->assertEquals('response', $response['type']);
        $this->assertEquals('Success with debug', $response['content']);
        $this->assertArrayHasKey('_debug', $response);
        $this->assertEquals($debugInfo, $response['_debug']);
    }

    public function testErrorResponse()
    {
        $response = ResponseBuilder::error('Something went wrong');
        
        $this->assertEquals('error', $response['type']);
        $this->assertEquals('Something went wrong', $response['content']);
        $this->assertArrayNotHasKey('_debug', $response);
    }

    public function testErrorResponseWithDebug()
    {
        $debugInfo = ['error_code' => 500, 'stack_trace' => 'stack trace...'];
        $response = ResponseBuilder::error('Error with debug', $debugInfo);
        
        $this->assertEquals('error', $response['type']);
        $this->assertEquals('Error with debug', $response['content']);
        $this->assertArrayHasKey('_debug', $response);
        $this->assertEquals($debugInfo, $response['_debug']);
    }

    public function testToolCallResponse()
    {
        $response = ResponseBuilder::toolCall('search_emails');
        
        $this->assertEquals('tool_call', $response['type']);
        $this->assertEquals('search_emails', $response['tool_name']);
        $this->assertEquals([], $response['arguments']);
        $this->assertArrayNotHasKey('_debug', $response);
    }

    public function testToolCallResponseWithArguments()
    {
        $arguments = ['query' => 'is:unread', 'limit' => 10];
        $response = ResponseBuilder::toolCall('search_emails', $arguments);
        
        $this->assertEquals('tool_call', $response['type']);
        $this->assertEquals('search_emails', $response['tool_name']);
        $this->assertEquals($arguments, $response['arguments']);
        $this->assertArrayNotHasKey('_debug', $response);
    }

    public function testToolCallResponseWithDebug()
    {
        $arguments = ['query' => 'is:unread'];
        $debugInfo = ['ai_confidence' => 0.95];
        $response = ResponseBuilder::toolCall('search_emails', $arguments, $debugInfo);
        
        $this->assertEquals('tool_call', $response['type']);
        $this->assertEquals('search_emails', $response['tool_name']);
        $this->assertEquals($arguments, $response['arguments']);
        $this->assertArrayHasKey('_debug', $response);
        $this->assertEquals($debugInfo, $response['_debug']);
    }

    public function testCustomResponse()
    {
        $data = ['status' => 'pending', 'message' => 'Request queued'];
        $response = ResponseBuilder::custom('queued', $data);
        
        $this->assertEquals('queued', $response['type']);
        $this->assertEquals('pending', $response['status']);
        $this->assertEquals('Request queued', $response['message']);
        $this->assertArrayNotHasKey('_debug', $response);
    }

    public function testCustomResponseWithDebug()
    {
        $data = ['count' => 5, 'items' => ['a', 'b', 'c']];
        $debugInfo = ['query_time' => 0.05];
        $response = ResponseBuilder::custom('list', $data, $debugInfo);
        
        $this->assertEquals('list', $response['type']);
        $this->assertEquals(5, $response['count']);
        $this->assertEquals(['a', 'b', 'c'], $response['items']);
        $this->assertArrayHasKey('_debug', $response);
        $this->assertEquals($debugInfo, $response['_debug']);
    }

    public function testCustomResponseOverridesTypeInData()
    {
        // Test that the type parameter takes precedence over type in data
        $data = ['type' => 'should_be_overridden', 'value' => 42];
        $response = ResponseBuilder::custom('final_type', $data);
        
        $this->assertEquals('final_type', $response['type']);
        $this->assertEquals(42, $response['value']);
        // Verify the original type from data is not in the final response
        $this->assertNotEquals('should_be_overridden', $response['type']);
    }

    public function testEmptyDebugInfoIsIgnored()
    {
        $response = ResponseBuilder::success('No debug', []);
        
        $this->assertEquals('response', $response['type']);
        $this->assertEquals('No debug', $response['content']);
        $this->assertArrayNotHasKey('_debug', $response);
    }

    public function testStaticMethodsReturnCorrectStructure()
    {
        // Ensure all response types have consistent structure
        $success = ResponseBuilder::success('test');
        $error = ResponseBuilder::error('test');
        $toolCall = ResponseBuilder::toolCall('test_tool');
        $custom = ResponseBuilder::custom('custom', ['data' => 'test']);

        // All should have a type
        $this->assertArrayHasKey('type', $success);
        $this->assertArrayHasKey('type', $error);
        $this->assertArrayHasKey('type', $toolCall);
        $this->assertArrayHasKey('type', $custom);

        // Verify specific types
        $this->assertEquals('response', $success['type']);
        $this->assertEquals('error', $error['type']);
        $this->assertEquals('tool_call', $toolCall['type']);
        $this->assertEquals('custom', $custom['type']);
    }
} 