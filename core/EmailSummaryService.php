<?php

namespace App\Core;

use Psr\Log\LoggerInterface as Logger;

/**
 * Service for handling email summary and related operations
 */
class EmailSummaryService
{
    private SessionInterface $session;
    private Logger $logger;
    private bool $useToolRole;

    public function __construct(SessionInterface $session, Logger $logger, bool $useToolRole = false)
    {
        $this->session = $session;
        $this->logger = $logger;
        $this->useToolRole = $useToolRole;
    }

    /**
     * Build a human-readable summary from an array of emails
     */
    public function buildEmailSummary(array $emails): string
    {
        $count = count($emails);
        if ($count === 0) {
            return "You have no unread emails.";
        }

        // Helper to extract sender name (before the email address)
        $fmt = function (array $e): string {
            $sender = $e['from'] ?? '';
            if (preg_match('/^([^<]+)</', $sender, $m)) {
                $senderName = trim($m[1]);
            } else {
                $senderName = $sender;
            }
            return "{$senderName} about '{$e['subject']}'";
        };

        if ($count === 1) {
            $one = $fmt($emails[0]);
            return "You have one unread email from $one.";
        }

        $parts = array_map($fmt, $emails);
        return "You have $count unread emails.\n- " . implode("\n- ", $parts);
    }

    /**
     * Get the last unread emails from chat history
     */
    public function getLastUnreadEmails(): array
    {
        $chatHistory = $this->session->get('chat_history', []);
        for ($i = count($chatHistory) - 1; $i >= 0; $i--) {
            $entry = $chatHistory[$i];
            if ($entry['role'] === ($this->useToolRole ? 'tool' : 'user')) {
                $data = json_decode($entry['content'], true);
                if (isset($data['status']) && $data['status'] === 'found_unread_emails' && isset($data['emails'])) {
                    return $data['emails'];
                }
            }
        }
        return [];
    }

    /**
     * Check if unread_emails was already requested by the assistant since the last user message
     */
    public function isDuplicateUnreadCall(): bool
    {
        $chatHistory = $this->session->get('chat_history', []);
        if (empty($chatHistory)) {
            return false;
        }
        
        // Skip the most recent entry (index count-1) because it is the tool_use we are currently processing.
        for ($i = count($chatHistory) - 2; $i >= 0; $i--) {
            $entry = $chatHistory[$i];
            if ($entry['role'] === 'user') {
                // We reached the last user turn – no duplicate within this turn
                return false;
            }
            if ($entry['role'] === 'assistant') {
                $data = json_decode($entry['content'], true);
                if (json_last_error() === JSON_ERROR_NONE && isset($data['action'], $data['tool_name']) && $data['action'] === 'tool_use' && $data['tool_name'] === 'unread_emails') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Search for an email ID in the last summary by subject
     */
    public function searchIdInLastSummary(string $subject): ?string
    {
        $lastSummary = $this->session->get('last_summary', '');
        if (preg_match('/Email ID ([a-f0-9]+):.*' . preg_quote($subject, '/') . '/i', $lastSummary, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Get the last summary text from the current conversation turn
     */
    public function getLastSummaryThisTurn(): ?string
    {
        $chatHistory = $this->session->get('chat_history', []);
        if (empty($chatHistory)) {
            return null;
        }
        
        for ($i = count($chatHistory) - 1; $i >= 0; $i--) {
            $entry = $chatHistory[$i];
            if ($entry['role'] !== 'assistant') {
                continue;
            }

            $raw = $entry['content'];
            $data = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (isset($data['action'], $data['response_text'])) {
                    return $data['response_text'];
                }
                // Skip entries that are structured tool_use calls or other JSON payloads without summary.
                continue;
            }

            // Non-JSON assistant content – assume it's the literal summary.
            if (is_string($raw) && trim($raw) !== '') {
                return $raw;
            }
        }
        return null;
    }

    /**
     * Check if we have recent unread email results that can be reused
     */
    public function hasRecentUnreadResult(): bool
    {
        $chatHistory = $this->session->get('chat_history', []);
        if (empty($chatHistory)) {
            return false;
        }
        // Walk backwards until we hit a non-assistant role (user or tool depending on config)
        for ($i = count($chatHistory) - 1; $i >= 0; $i--) {
            $entry = $chatHistory[$i];
            if ($entry['role'] === 'assistant') {
                continue; // skip assistant messages – we want the prior user/tool result
            }
            // We expect tool results to be stored as 'user' when tool role unsupported
            $roleForTools = $this->useToolRole ? 'tool' : 'user';
            if ($entry['role'] !== $roleForTools) {
                return false; // a genuine human user message in between – no summary expected
            }
            $data = json_decode($entry['content'] ?? '', true);
            return isset($data['status']) && $data['status'] === 'found_unread_emails';
        }
        return false;
    }
} 