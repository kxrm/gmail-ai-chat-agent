<?php

use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Monolog\Logger; // Add Monolog Logger import

/**
 * Encodes a string into a URL-safe base64 format.
 * @param string $data The string to encode.
 * @return string The URL-safe base64 encoded string.
 */
function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Retrieves the sender's display name from Gmail settings.
 * Moved from process_chat.php to centralize helper functions.
 *
 * @param Gmail $gmailService The Gmail service client.
 * @param Logger $logger The Monolog logger instance.
 * @return string|null The sender's display name, or null if not found or an error occurs.
 */
function getSenderDisplayName(Gmail $gmailService, Logger $logger): ?string {
    try {
        $sendAsResponse = $gmailService->users_settings_sendAs->listUsersSettingsSendAs('me');
        $sendAsAddresses = $sendAsResponse->getSendAs();

        foreach ($sendAsAddresses as $sendAs) {
            if ($sendAs->getIsDefault()) {
                $logger->debug("Sender display name found: " . ($sendAs->getDisplayName() ?? 'N/A'));
                return $sendAs->getDisplayName() ?? null;
            }
        }
    } catch (Exception $e) {
        $logger->error("Error fetching sender display name: " . $e->getMessage());
    }
    $logger->warning("No default sender display name found.");
    return null;
}

/**
 * Extracts relevant details from a Gmail message object.
 * Moved from process_chat.php to centralize helper functions.
 *
 * @param Message|null $message The Gmail message object.
 * @param Logger $logger The Monolog logger instance (for future use).
 * @return array An associative array containing 'subject', 'from', 'date', 'to', and 'body'.
 */
function getEmailDetails(?Message $message, Logger $logger): array {
    if (!$message) {
        $logger->warning("getEmailDetails called with null message.");
        return [
            'subject' => '(No Subject)',
            'from' => '(No Sender)',
            'to' => '(No Recipient)',
            'date' => '(No Date)',
            'body' => '(No Body)',
            'from_email_only' => '',
            'message_id_header' => '',
            'references_header' => ''
        ];
    }

    $headers = $message->getPayload()->getHeaders();
    $headers = $headers ?? [];

    $subject = '';
    $from = '';
    $date = '';
    $to = '';
    $messageIdHeader = '';
    $referencesHeader = '';
    $bodyContent = '';

    if (is_array($headers) || $headers instanceof Traversable) {
        foreach ($headers as $header) {
            if (!is_object($header) || !method_exists($header, 'getName') || !method_exists($header, 'getValue')) {
                $logger->warning("Invalid header object encountered in getEmailDetails.");
                continue;
            }
            $headerName = strtolower($header->getName());
            $headerValue = $header->getValue();

            switch ($headerName) {
                case 'subject':
                    $subject = $headerValue;
                    break;
                case 'from':
                    $from = $headerValue;
                    break;
                case 'date':
                    $date = $headerValue;
                    break;
                case 'to':
                    $to = $headerValue;
                    break;
                case 'message-id':
                    $messageIdHeader = $headerValue;
                    break;
                case 'references':
                    $referencesHeader = $headerValue;
                    break;
            }
        }
    }

    // Handle body content extraction more robustly
    if ($message->getPayload() && $message->getPayload()->getBody() && $message->getPayload()->getBody()->getSize()) {
        $bodyContent = base64_decode(strtr($message->getPayload()->getBody()->getData(), '-_', '+/'));

    } elseif ($message->getPayload() && $message->getPayload()->getParts()) {

        foreach ($message->getPayload()->getParts() as $part) {

            if ($part->getBody() && $part->getBody()->getSize()) {
                if ($part->getMimeType() == 'text/plain') {
                    $bodyContent = base64_decode(strtr($part->getBody()->getData(), '-_', '+/'));
                    break; // Prefer plain text
                } elseif ($part->getMimeType() == 'text/html' && empty($bodyContent)) {
                    $bodyContent = strip_tags(base64_decode(strtr($part->getBody()->getData(), '-_', '+/')));
                    
                    // Keep trying for plain text if HTML is found first
                }
            }
        }
    }

    // Limit the email body sent to Ollama to prevent exceeding token limits
    $maxBodyLength = 4000; // Adjust based on your model's context window
    if (strlen($bodyContent) > $maxBodyLength) {
        $bodyContent = substr($bodyContent, 0, $maxBodyLength) . "\n\n[...email truncated due to length...]\n";
        $logger->info("Email body truncated due to length.");
    }

    // Extract just the email from the "From" header
    preg_match('/<(.*?)>/', $from, $matches);
    $fromEmailOnly = $matches[1] ?? $from;

    $returnedDetails = [
        'subject' => $subject,
        'from' => $from,
        'date' => $date,
        'to' => $to,
        'body' => $bodyContent,
        'from_email_only' => $fromEmailOnly,
        'message_id_header' => $messageIdHeader,
        'references_header' => !empty($referencesHeader) ? $referencesHeader . ' ' . $messageIdHeader : $messageIdHeader
    ];

    $logger->debug("Extracted email details for subject: {$subject}");
    return $returnedDetails;
} 