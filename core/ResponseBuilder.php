<?php

namespace App\Core;

class ResponseBuilder
{
    /**
     * Create a successful response
     */
    public static function success(string $content, array $debug = []): array
    {
        return self::package(['type' => 'response', 'content' => $content], $debug);
    }

    /**
     * Create an error response
     */
    public static function error(string $message, array $debug = []): array
    {
        return self::package(['type' => 'error', 'content' => $message], $debug);
    }

    /**
     * Create a tool call response
     */
    public static function toolCall(string $toolName, array $arguments = [], array $debug = []): array
    {
        return self::package([
            'type' => 'tool_call',
            'tool_name' => $toolName,
            'arguments' => $arguments
        ], $debug);
    }

    /**
     * Create a custom response with specific type and data
     */
    public static function custom(string $type, array $data, array $debug = []): array
    {
        $response = $data;
        $response['type'] = $type; // Ensure type is always set to the specified value
        return self::package($response, $debug);
    }

    /**
     * Package response with debug information if provided
     */
    private static function package(array $response, array $debug): array
    {
        if (!empty($debug)) {
            $response['_debug'] = $debug;
        }
        return $response;
    }
} 