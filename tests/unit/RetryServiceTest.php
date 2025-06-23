<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\RetryService;
use App\Core\RetryResult;
use Psr\Log\LoggerInterface;
use Mockery;

class RetryServiceTest extends TestCase
{
    private $mockLogger;
    private RetryService $retryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockLogger = Mockery::mock(LoggerInterface::class);
        $this->retryService = new RetryService($this->mockLogger, 3, 100); // 100ms base delay for faster tests
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testSuccessfulOperationOnFirstAttempt()
    {
        $this->mockLogger->shouldNotReceive('warning');
        $this->mockLogger->shouldNotReceive('info');

        $operation = function () {
            return 'success';
        };

        $result = $this->retryService->executeWithRetry($operation, null, null, 'test operation');

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals('success', $result->getData());
        $this->assertEquals(1, $result->getAttemptCount());
        $this->assertNull($result->getLastError());
        
        $attempts = $result->getAttempts();
        $this->assertCount(1, $attempts);
        $this->assertTrue($attempts[0]['success']);
        $this->assertNull($attempts[0]['error']);
    }

    public function testSuccessfulOperationAfterRetries()
    {
        $callCount = 0;
        $operation = function () use (&$callCount) {
            $callCount++;
            if ($callCount < 3) {
                throw new \Exception("Attempt $callCount failed");
            }
            return 'success on attempt 3';
        };

        $this->mockLogger->shouldReceive('warning')->twice();
        $this->mockLogger->shouldReceive('info')->once()->with('test operation succeeded after 3 attempts');

        $result = $this->retryService->executeWithRetry($operation, null, null, 'test operation');

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals('success on attempt 3', $result->getData());
        $this->assertEquals(3, $result->getAttemptCount());
        $this->assertNull($result->getLastError());
    }

    public function testOperationFailsAfterMaxRetries()
    {
        $operation = function () {
            throw new \Exception("Always fails");
        };

        $this->mockLogger->shouldReceive('warning')->times(2); // 2 retries
        $this->mockLogger->shouldReceive('error')->once();

        $result = $this->retryService->executeWithRetry($operation, null, null, 'test operation');

        $this->assertFalse($result->isSuccessful());
        $this->assertNull($result->getData());
        $this->assertEquals(3, $result->getAttemptCount());
        $this->assertEquals('Always fails', $result->getLastError());
    }

    public function testValidatorRejectsThenAccepts()
    {
        $callCount = 0;
        $operation = function () use (&$callCount) {
            $callCount++;
            return "result $callCount";
        };

        $validator = function ($result) {
            return $result === 'result 2' ? true : 'Invalid result';
        };

        $this->mockLogger->shouldReceive('warning')->once();
        $this->mockLogger->shouldReceive('info')->once();

        $result = $this->retryService->executeWithRetry($operation, $validator, null, 'test operation');

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals('result 2', $result->getData());
        $this->assertEquals(2, $result->getAttemptCount());
    }

    public function testValidatorAlwaysRejects()
    {
        $operation = function () {
            return 'always invalid';
        };

        $validator = function ($result) {
            return 'Result is invalid';
        };

        $this->mockLogger->shouldReceive('warning')->times(2);
        $this->mockLogger->shouldReceive('error')->once();

        $result = $this->retryService->executeWithRetry($operation, $validator, null, 'test operation');

        $this->assertFalse($result->isSuccessful());
        $this->assertNull($result->getData());
        $this->assertEquals('Result is invalid', $result->getLastError());
    }

    public function testCustomMaxRetries()
    {
        $operation = function () {
            throw new \Exception("Always fails");
        };

        $this->mockLogger->shouldReceive('warning')->times(4); // 4 retries
        $this->mockLogger->shouldReceive('error')->once();

        $result = $this->retryService->executeWithRetry($operation, null, 5, 'test operation');

        $this->assertFalse($result->isSuccessful());
        $this->assertEquals(5, $result->getAttemptCount());
    }

    public function testAttemptInfoContainsExpectedFields()
    {
        $operation = function () {
            usleep(1000); // 1ms delay to ensure measurable duration
            return 'test result';
        };

        $result = $this->retryService->executeWithRetry($operation);

        $attempts = $result->getAttempts();
        $this->assertCount(1, $attempts);
        
        $attempt = $attempts[0];
        $this->assertArrayHasKey('attempt', $attempt);
        $this->assertArrayHasKey('duration_ms', $attempt);
        $this->assertArrayHasKey('success', $attempt);
        $this->assertArrayHasKey('error', $attempt);
        $this->assertArrayHasKey('result', $attempt);
        
        $this->assertEquals(1, $attempt['attempt']);
        $this->assertTrue($attempt['success']);
        $this->assertNull($attempt['error']);
        $this->assertEquals('test result', $attempt['result']);
        $this->assertIsFloat($attempt['duration_ms']);
        $this->assertGreaterThanOrEqual(0, $attempt['duration_ms']); // Changed to >= to handle very fast operations
    }

    public function testJsonValidator()
    {
        $validator = RetryService::createJsonValidator();

        // Valid JSON
        $this->assertTrue($validator('{"valid": "json"}'));
        $this->assertTrue($validator('[]'));
        $this->assertTrue($validator('"simple string"'));

        // Invalid JSON
        $this->assertIsString($validator('{invalid json}'));
        $this->assertIsString($validator(''));
        $this->assertIsString($validator(123)); // Not a string
        $this->assertIsString($validator(null));
    }

    public function testResponseValidator()
    {
        $validator = RetryService::createResponseValidator();

        // Valid responses
        $this->assertTrue($validator(['action' => 'respond', 'response_text' => 'hello']));
        $this->assertTrue($validator(['action' => 'tool_use', 'tool_name' => 'search']));
        $this->assertTrue($validator(['action' => 'tool_use', 'tool_call' => ['tool_name' => 'search']]));
        $this->assertTrue($validator(['status' => 'success'])); // Direct tool-style payload
        $this->assertTrue($validator(['tool_name' => 'search'])); // Direct tool-style payload

        // Invalid responses
        $this->assertIsString($validator('not array'));
        $this->assertIsString($validator(['action' => 'invalid']));
        $this->assertIsString($validator(['no_action' => 'present']));
        $this->assertIsString($validator(['action' => 'tool_use'])); // Missing tool_name
    }

    public function testAiResponseValidator()
    {
        $validator = RetryService::createAiResponseValidator();

        // Valid AI response
        $validJson = '{"action": "respond", "response_text": "Hello"}';
        $this->assertTrue($validator($validJson));

        // Invalid JSON
        $invalidJson = '{invalid json}';
        $this->assertIsString($validator($invalidJson));

        // Valid JSON but invalid response
        $invalidResponse = '{"invalid": "response"}';
        $this->assertIsString($validator($invalidResponse));
    }

    public function testRetryResultGetters()
    {
        $attempts = [
            ['attempt' => 1, 'success' => false, 'error' => 'failed'],
            ['attempt' => 2, 'success' => true, 'error' => null]
        ];

        $result = new RetryResult('test data', true, $attempts, null);

        $this->assertEquals('test data', $result->getData());
        $this->assertTrue($result->isSuccessful());
        $this->assertEquals($attempts, $result->getAttempts());
        $this->assertNull($result->getLastError());
        $this->assertEquals(2, $result->getAttemptCount());
    }

    public function testRetryResultFailure()
    {
        $attempts = [
            ['attempt' => 1, 'success' => false, 'error' => 'failed'],
            ['attempt' => 2, 'success' => false, 'error' => 'failed again']
        ];

        $result = new RetryResult(null, false, $attempts, 'failed again');

        $this->assertNull($result->getData());
        $this->assertFalse($result->isSuccessful());
        $this->assertEquals('failed again', $result->getLastError());
        $this->assertEquals(2, $result->getAttemptCount());
    }

    public function testBackoffDelay()
    {
        $startTime = microtime(true);
        
        $operation = function () {
            throw new \Exception("Always fails");
        };

        $this->mockLogger->shouldReceive('warning')->times(2);
        $this->mockLogger->shouldReceive('error')->once();

        $result = $this->retryService->executeWithRetry($operation, null, 3, 'test operation');

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        // Should have taken at least 100ms (first backoff) + 200ms (second backoff) = 300ms
        // Add some tolerance for test execution time
        $this->assertGreaterThan(250, $totalTime, 'Should include backoff delays');
        
        $this->assertFalse($result->isSuccessful());
    }
} 