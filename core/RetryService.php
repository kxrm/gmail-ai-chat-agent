<?php

namespace App\Core;

use Psr\Log\LoggerInterface as Logger;

/**
 * Result object for retry operations
 */
class RetryResult
{
    private $data;
    private bool $successful;
    private array $attempts;
    private ?string $lastError;

    public function __construct($data, bool $successful, array $attempts, ?string $lastError = null)
    {
        $this->data = $data;
        $this->successful = $successful;
        $this->attempts = $attempts;
        $this->lastError = $lastError;
    }

    public function getData()
    {
        return $this->data;
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function getAttempts(): array
    {
        return $this->attempts;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getAttemptCount(): int
    {
        return count($this->attempts);
    }
}

/**
 * Reusable retry service with exponential backoff
 */
class RetryService
{
    private Logger $logger;
    private int $defaultMaxRetries;
    private int $baseDelayMs;

    public function __construct(Logger $logger, int $defaultMaxRetries = 3, int $baseDelayMs = 1000)
    {
        $this->logger = $logger;
        $this->defaultMaxRetries = $defaultMaxRetries;
        $this->baseDelayMs = $baseDelayMs;
    }

    /**
     * Execute an operation with retry logic and exponential backoff
     * 
     * @param callable $operation The operation to execute
     * @param callable|null $validator Optional validator function to check if result is valid
     * @param int|null $maxRetries Override default max retries
     * @param string $operationName Name for logging purposes
     * @return RetryResult
     */
    public function executeWithRetry(
        callable $operation,
        ?callable $validator = null,
        ?int $maxRetries = null,
        string $operationName = 'operation'
    ): RetryResult {
        $maxRetries = $maxRetries ?? $this->defaultMaxRetries;
        $retryAttempts = [];
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $startTime = microtime(true);
                $result = $operation();
                $endTime = microtime(true);

                $attemptInfo = [
                    'attempt' => $attempt,
                    'duration_ms' => round(($endTime - $startTime) * 1000, 2),
                    'success' => false,
                    'error' => null
                ];

                // Validate the result if validator is provided
                if ($validator !== null) {
                    $validationResult = $validator($result);
                    if ($validationResult !== true) {
                        $error = is_string($validationResult) ? $validationResult : "Validation failed";
                        $attemptInfo['error'] = $error;
                        $attemptInfo['result'] = $result;
                        $retryAttempts[] = $attemptInfo;
                        $lastError = $error;

                        if ($attempt < $maxRetries) {
                            $this->performBackoff($attempt, $operationName, $error);
                            continue;
                        }
                    } else {
                        // Success!
                        $attemptInfo['success'] = true;
                        $attemptInfo['result'] = $result;
                        $retryAttempts[] = $attemptInfo;

                        if ($attempt > 1) {
                            $this->logger->info("$operationName succeeded after $attempt attempts");
                        }

                        return new RetryResult($result, true, $retryAttempts);
                    }
                } else {
                    // No validator - assume success
                    $attemptInfo['success'] = true;
                    $attemptInfo['result'] = $result;
                    $retryAttempts[] = $attemptInfo;

                    if ($attempt > 1) {
                        $this->logger->info("$operationName succeeded after $attempt attempts");
                    }

                    return new RetryResult($result, true, $retryAttempts);
                }

            } catch (\Exception $e) {
                $endTime = microtime(true);
                $attemptInfo = [
                    'attempt' => $attempt,
                    'duration_ms' => round(($endTime - $startTime) * 1000, 2),
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                $retryAttempts[] = $attemptInfo;
                $lastError = $e->getMessage();

                if ($attempt < $maxRetries) {
                    $this->performBackoff($attempt, $operationName, $e->getMessage());
                    continue;
                }
            }
        }

        // All retries failed
        $this->logger->error("$operationName failed after $maxRetries attempts. Last error: $lastError");
        return new RetryResult(null, false, $retryAttempts, $lastError);
    }

    /**
     * Perform exponential backoff delay
     */
    private function performBackoff(int $attempt, string $operationName, string $error): void
    {
        $backoffMs = pow(2, $attempt - 1) * $this->baseDelayMs; // 1s, 2s, 4s, 8s...
        $this->logger->warning("$operationName attempt $attempt failed, retrying in {$backoffMs}ms", ['error' => $error]);
        usleep($backoffMs * 1000); // Convert to microseconds
    }

    /**
     * Create a JSON validation function for AI responses
     */
    public static function createJsonValidator(): callable
    {
        return function ($jsonResponse) {
            if (!is_string($jsonResponse)) {
                return "Expected string response, got " . gettype($jsonResponse);
            }

            $decoded = json_decode(trim($jsonResponse), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return "JSON decode error: " . json_last_error_msg();
            }

            return true; // Valid JSON
        };
    }

    /**
     * Create a response structure validator for AI responses
     */
    public static function createResponseValidator(): callable
    {
        return function ($response) {
            if (!is_array($response)) {
                return "Expected array response, got " . gettype($response);
            }

            // Check if response has valid action
            if (!isset($response['action']) || !in_array($response['action'], ['respond', 'tool_use'])) {
                // Check if it's a direct tool-style payload that we can handle
                if (isset($response['status']) || isset($response['tool_name'])) {
                    return true; // We can handle this
                }
                return "Invalid response structure - missing or invalid action";
            }

            // Additional validation for tool_use action
            if ($response['action'] === 'tool_use') {
                // Must have either tool_name directly or in tool_call
                if (!isset($response['tool_name']) && !isset($response['tool_call']['tool_name'])) {
                    return "Tool use action missing tool_name";
                }
            }

            return true; // Valid response
        };
    }

    /**
     * Create a combined JSON + response validator
     */
    public static function createAiResponseValidator(): callable
    {
        $jsonValidator = self::createJsonValidator();
        $responseValidator = self::createResponseValidator();

        return function ($jsonResponse) use ($jsonValidator, $responseValidator) {
            // First validate JSON
            $jsonResult = $jsonValidator($jsonResponse);
            if ($jsonResult !== true) {
                return $jsonResult;
            }

            // Then validate response structure
            $decoded = json_decode(trim($jsonResponse), true);
            return $responseValidator($decoded);
        };
    }
} 