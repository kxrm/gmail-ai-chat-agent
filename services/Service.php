<?php

namespace App\Services;

use Monolog\Logger;
use Psr\Log\LoggerInterface;

interface Service
{
    /**
     * Returns the unique name of the service (e.g., "google", "trello").
     */
    public function getName(): string;

    /**
     * Returns an array of tool names provided by this service.
     * These names must match what the AI will use in its 'tool_name' response.
     * e.g., ['create_draft', 'search_contacts', 'unread_emails']
     */
    public function getAvailableTools(): array;

    /**
     * Executes a tool with the given arguments.
     *
     * @param string $toolName The name of the tool to execute.
     * @param array $arguments The arguments for the tool.
     * @return array The result of the tool execution, as an associative array.
     */
    public function executeTool(string $toolName, array $arguments): array;

    /**
     * Returns the underlying API client for this service (e.g., Google_Client).
     * This is necessary for OAuth flows that need access to the raw client.
     */
    public function getApiClient();

    public function getToolDefinitions(): array;
} 