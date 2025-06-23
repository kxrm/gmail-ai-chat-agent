<?php

namespace App\Services;

use Google\Client as Google_Client;
use Google\Service\Gmail;
use Google\Service\PeopleService;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Monolog\Logger;
use Readability\Readability;
use Soundasleep\Html2Text;

class GoogleService implements Service
{
    private Google_Client $client;
    private Gmail $gmailService;
    private PeopleService $peopleService;
    private array $tools;
    private ?Logger $logger;
    private ?array $stubs = null;

    public function __construct(array $config, ?array $access_token = null, ?Logger $logger = null)
    {
        // When the harness flag is enabled, force-load lightweight Gmail stubs **before** any Gmail classes are referenced
        if (getenv('PHP_AUTOMOCK_GOOGLE')) {
            $stubPath = __DIR__ . '/../tests/e2e/stubs/google_stubs.php';
            if (file_exists($stubPath)) {
                $this->stubs = require $stubPath; // safe to include multiple times
            }
        }

        $this->client = new Google_Client();
        $this->client->setAuthConfig($config['credentials_path']);
        $this->tools = $config['tools'] ?? [];
        $this->logger = $logger;

        // Set the access token if it was provided.
        if ($access_token) {
            $this->client->setAccessToken($access_token);
        }

        $this->gmailService = new Gmail($this->client);
        $this->peopleService = new PeopleService($this->client);
    }

    public function getName(): string
    {
        return 'google';
    }

    public function getAvailableTools(): array
    {
        return $this->tools;
    }
    
    public function getApiClient(): Google_Client
    {
        return $this->client;
    }

    public function setAccessToken(array $accessToken): void
    {
        $this->client->setAccessToken($accessToken);
    }

    /**
     * @param string $toolName The name of the tool to execute.
     * @param array $arguments The arguments for the tool.
     * @return array The result of the tool execution, as an associative array.
     */
    public function executeTool(string $toolName, array $arguments): array
    {
        if (!in_array($toolName, $this->tools)) {
            throw new \InvalidArgumentException("Tool '{$toolName}' is not supported by the Google service.");
        }

        switch ($toolName) {
            case 'unread_emails':
                return $this->handleUnreadEmails($arguments);
            case 'get_email':
                return $this->handleGetEmail($arguments);
            case 'search_emails':
                return $this->handleSearchEmails($arguments);
            case 'create_draft':
                return $this->handleCreateDraft($arguments);
            case 'send_email':
                return $this->handleSendEmail($arguments);
            case 'create_reply_draft':
                return $this->handleCreateReplyDraft($arguments);
            case 'send_reply':
                return $this->handleSendReply($arguments);
            case 'mark_email':
                return $this->handleMarkEmail($arguments);
            case 'send_draft':
                return $this->handleSendDraft($arguments);
            case 'search_contacts':
                return $this->handleSearchContacts($arguments);
            default:
                throw new \InvalidArgumentException("Tool '{$toolName}' is not recognized by the Google service.");
        }
    }

    // --- Gmail Command Handlers ---

    private function handleUnreadEmails(array $args): array
    {
        $maxResults = filter_var($args['max_results'] ?? 5, FILTER_VALIDATE_INT, ['options' => ['default' => 5, 'min_range' => 1]]);
        
        if ($this->stubs && isset($this->stubs['unread_emails'])) {
            $this->logger?->info("Using stub for unread_emails");
            $emails = call_user_func($this->stubs['unread_emails'], $maxResults);
            return ['status' => 'found_unread_emails', 'emails' => $emails];
        }
        
        try {
            $messagesResponse = $this->gmailService->users_messages->listUsersMessages('me', ['maxResults' => $maxResults, 'q' => 'is:unread']);
            $messages = $messagesResponse->getMessages();
            if (empty($messages)) {
                return ['status' => 'no_unread_emails', 'message' => "You have no unread emails."];
            }
            $emailSummary = ['status' => 'found_unread_emails', 'emails' => []];
            foreach ($messages as $message) {
                $msg = $this->gmailService->users_messages->get('me', $message->getId(), ['format' => 'full']);
                $details = $this->getEmailDetails($msg);
                $emailSummary['emails'][] = [
                    'from' => $details['from'],
                    'subject' => $details['subject'],
                    'message_id' => $message->getId()
                ];
            }
            return $emailSummary;
        } catch (\Throwable $e) {
            $this->logger?->error("Gmail API Error (unread_emails): " . $e->getMessage());
            return ['status' => 'error', 'message' => "I'm sorry, I encountered an error accessing your Gmail: " . $e->getMessage()];
        }
    }

    private function handleGetEmail(array $args): array
    {
        $messageId = $args['message_id'] ?? $args['id'] ?? '';
        if (empty($messageId)) {
            return ['status' => 'error', 'message' => 'Please provide a message ID.'];
        }

        if ($this->stubs && isset($this->stubs['getEmail'])) {
             $this->logger?->info("Using stub for getEmail with messageId: {$messageId}");
             $emailData = call_user_func($this->stubs['getEmail'], $messageId);
             if ($emailData) {
                 return array_merge(['status' => 'success'], $emailData);
             }
        }

        try {
            $msg = $this->gmailService->users_messages->get('me', $messageId, ['format' => 'full']);
            $details = $this->getEmailDetails($msg);
            return [
                'status' => 'success',
                'subject' => $details['subject'],
                'from' => $details['from'],
                'to' => $details['to'],
                'date' => $details['date'],
                'body' => $details['body']
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Error retrieving email: ' . $e->getMessage()];
        }
    }

    private function handleSearchEmails(array $args): array
    {
        $searchQuery = $args['query'] ?? '';
        if (empty($searchQuery)) {
            return ['status' => 'error', 'message' => 'Please provide a search query.'];
        }

        if ($this->stubs && isset($this->stubs['searchEmails'])) {
            $this->logger?->info("Using stub for searchEmails with query: {$searchQuery}");
            $emails = call_user_func($this->stubs['searchEmails'], $searchQuery);
            return ['status' => 'found_emails', 'emails' => $emails];
        }

        try {
            $messagesResponse = $this->gmailService->users_messages->listUsersMessages('me', ['maxResults' => 5, 'q' => $searchQuery]);
            $messages = $messagesResponse->getMessages();
            if (empty($messages)) {
                return ['status' => 'no_emails_found', 'message' => "I couldn't find any emails matching \"{$searchQuery}\"."];
            }
            
            $emailSummary = ['status' => 'found_emails', 'emails' => []];
            foreach ($messages as $message) {
                $details = $this->getEmailDetails($this->gmailService->users_messages->get('me', $message->getId(), ['format' => 'full']));
                $emailSummary['emails'][] = [
                    'from' => $details['from'],
                    'subject' => $details['subject'],
                    'message_id' => $message->getId()
                ];
            }
            return $emailSummary;
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Error during search: ' . $e->getMessage()];
        }
    }

    private function handleCreateDraft(array $args): array
    {
        if (empty($args['to']) || empty($args['subject']) || empty($args['body'])) {
            return ['status' => 'error', 'message' => "To create a draft, please provide 'to', 'subject', and 'body'."];
        }
        if (str_ends_with(strtolower($args['to']), '@example.com')) {
            return ['status' => 'error', 'message' => "Error: The email address '{$args['to']}' is a placeholder. You MUST use the 'search_contacts' tool to find a real email address first."];
        }
        
        if ($this->stubs && isset($this->stubs['createDraft'])) {
            $this->logger?->info("Using stub for createDraft");
            return call_user_func($this->stubs['createDraft'], $args['to'], $args['subject'], $args['body']);
        }
        
        try {
            $profile = $this->gmailService->users->getProfile('me');
            $rawMessage = $this->_prepareEmail(
                $profile->getEmailAddress(),
                $this->getSenderDisplayName(),
                $args['to'], $args['subject'], $args['body']
            );
            $message = new \Google\Service\Gmail\Message();
            $message->setRaw($this->base64url_encode($rawMessage));
            $draft = new \Google\Service\Gmail\Draft();
            $draft->setMessage($message);
            $createdDraft = $this->gmailService->users_drafts->create('me', $draft);
            return [
                'status' => 'draft_created', 'draft_id' => $createdDraft->getId(),
                'to' => $args['to'], 'subject' => $args['subject']
            ];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => "Error creating draft: " . $e->getMessage()];
        }
    }

    private function handleSendEmail(array $args): array
    {
        if (empty($args['to']) || empty($args['subject']) || empty($args['body'])) {
            return ['status' => 'error', 'message' => "To send an email, please provide 'to', 'subject', and 'body'."];
        }

        if (str_ends_with(strtolower($args['to']), '@example.com')) {
             return ['status' => 'error', 'message' => "Error: The email address '{$args['to']}' is a placeholder. You MUST use the 'search_contacts' tool to find a real email address first."];
        }
        
        if ($this->stubs && isset($this->stubs['sendEmail'])) {
            $this->logger?->info("Using stub for sendEmail");
            return call_user_func($this->stubs['sendEmail'], $args['to'], $args['subject'], $args['body']);
        }
        
        if (!$this->allowEmailSending()) {
            $this->logger?->warning("Send request overridden by config. Creating draft instead.");
            $response = $this->handleCreateDraft($args);
            $response['message'] = "Note: Sending is disabled, so a draft was created instead.";
            return $response;
        }

        try {
            $profile = $this->gmailService->users->getProfile('me');
            $rawMessage = $this->_prepareEmail(
                $profile->getEmailAddress(),
                $this->getSenderDisplayName(),
                $args['to'], $args['subject'], $args['body']
            );
            $message = new \Google\Service\Gmail\Message();
            $message->setRaw($this->base64url_encode($rawMessage));
            $this->gmailService->users_messages->send('me', $message);
            return ['status' => 'success', 'message' => 'The email has been sent successfully.'];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => "Error sending email: " . $e->getMessage()];
        }
    }

    private function handleCreateReplyDraft(array $args): array
    {
        if (empty($args['message_id']) || empty($args['body'])) {
            return ['status' => 'error', 'message' => "To create a reply draft, please provide 'message_id' and 'body'."];
        }
        
        if ($this->stubs && isset($this->stubs['createReplyDraft'])) {
            $this->logger?->info("Using stub for createReplyDraft");
            return call_user_func($this->stubs['createReplyDraft'], $args['message_id'], $args['body']);
        }
        
        try {
            $originalMessage = $this->gmailService->users_messages->get('me', $args['message_id'], ['format' => 'full']);
            $details = $this->getEmailDetails($originalMessage);
            $profile = $this->gmailService->users->getProfile('me');

            $rawMessage = $this->_prepareEmail(
                $profile->getEmailAddress(),
                $this->getSenderDisplayName(),
                $details['from'], 
                'Re: ' . $details['subject'],
                $args['body'],
                ['In-Reply-To' => $details['message_id_header'], 'References' => $details['references_header']]
            );

            $message = new \Google\Service\Gmail\Message();
            $message->setRaw($this->base64url_encode($rawMessage));
            $message->setThreadId($originalMessage->getThreadId());
            
            $draft = new \Google\Service\Gmail\Draft();
            $draft->setMessage($message);
            $createdDraft = $this->gmailService->users_drafts->create('me', $draft);
            return [
                'status' => 'draft_created', 'draft_id' => $createdDraft->getId(),
                'to' => $details['from'], 'subject' => 'Re: ' . $details['subject']
            ];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => "Error creating reply draft: " . $e->getMessage()];
        }
    }

    private function handleSendReply(array $args): array
    {
        if (empty($args['message_id']) || empty($args['body'])) {
            return ['status' => 'error', 'message' => "To send a reply, please provide 'message_id' and 'body'."];
        }
        
        if ($this->stubs && isset($this->stubs['sendReply'])) {
            $this->logger?->info("Using stub for sendReply");
            return call_user_func($this->stubs['sendReply'], $args['message_id'], $args['body']);
        }
        
        if (!$this->allowEmailSending()) {
            $this->logger?->warning("Send request overridden by config. Creating reply draft instead.");
            $response = $this->handleCreateReplyDraft($args);
            $response['message'] = "Note: Sending is disabled, so a reply draft was created instead.";
            return $response;
        }

        try {
            $originalMessage = $this->gmailService->users_messages->get('me', $args['message_id'], ['format' => 'full']);
            $details = $this->getEmailDetails($originalMessage);
            $profile = $this->gmailService->users->getProfile('me');

            $rawMessage = $this->_prepareEmail(
                $profile->getEmailAddress(),
                $this->getSenderDisplayName(),
                $details['from'],
                'Re: ' . $details['subject'],
                $args['body'],
                ['In-Reply-To' => $details['message_id_header'], 'References' => $details['references_header']]
            );

            $message = new \Google\Service\Gmail\Message();
            $message->setRaw($this->base64url_encode($rawMessage));
            $message->setThreadId($originalMessage->getThreadId());
            $this->gmailService->users_messages->send('me', $message);
            return ['status' => 'success', 'message' => 'The reply has been sent successfully.'];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Error sending reply: ' . $e->getMessage()];
        }
    }

    private function handleMarkEmail(array $args): array
    {
        $messageId = $args['message_id'] ?? null;
        $status = $args['status'] ?? null;

        if (!$messageId || !$status || !in_array($status, ['read', 'unread'])) {
            return ['status' => 'error', 'message' => "Invalid arguments. 'message_id' and 'status' ('read' or 'unread') are required."];
        }

        if ($this->stubs && isset($this->stubs['markEmail'])) {
            $this->logger?->info("Using stub for markEmail with messageId: {$messageId}");
            $result = call_user_func($this->stubs['markEmail'], $messageId, $status);
            if ($result && $result['status'] === 'success') {
                 return ['status' => 'success', 'message' => "The email has been marked as {$status}."];
            }
        }

        $mods = new \Google\Service\Gmail\ModifyMessageRequest();
        if ($status === 'read') {
            $mods->setRemoveLabelIds(['UNREAD']);
        } else {
            $mods->setAddLabelIds(['UNREAD']);
        }

        try {
            $this->gmailService->users_messages->modify('me', $messageId, $mods);
            return ['status' => 'success', 'message' => "The email has been marked as {$status}."];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => "Error marking email as {$status}: " . $e->getMessage()];
        }
    }

    private function handleSendDraft(array $args): array
    {
        $draftId = $args['draft_id'] ?? null;
        if (!$draftId) {
            return ['status' => 'error', 'message' => "A 'draft_id' is required to send a draft."];
        }
        
        if ($this->stubs && isset($this->stubs['sendDraft'])) {
            $this->logger?->info("Using stub for sendDraft");
            return call_user_func($this->stubs['sendDraft'], $draftId);
        }
        
        if (!$this->allowEmailSending()) {
            $this->logger?->warning("Send draft request blocked by config. Draft remains unsent.");
            return ['status' => 'blocked', 'message' => 'Email sending is disabled. The draft remains in your drafts folder.'];
        }
        
        try {
            $draft = new \Google\Service\Gmail\Draft();
            $draft->setId($draftId);
            $this->gmailService->users_drafts->send('me', $draft);
            return ['status' => 'success', 'message' => 'Draft sent successfully.'];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Error sending draft: ' . $e->getMessage()];
        }
    }

    private function handleSearchContacts(array $args): array
    {
        $name = $args['name'] ?? '';
        if (empty($name)) {
            return ['status' => 'error', 'message' => "A 'name' is required to search contacts."];
        }

        if ($this->stubs && isset($this->stubs['searchContacts'])) {
            $this->logger?->info("Using stub for searchContacts with name: {$name}");
            return call_user_func($this->stubs['searchContacts'], $name);
        }

        try {
             $allConnections = [];
             $pageToken = null;
             do {
                 $response = $this->peopleService->people_connections->listPeopleConnections('people/me', [
                     'personFields' => 'names,emailAddresses', 'pageSize' => 1000, 'pageToken' => $pageToken
                 ]);
                 if ($response->getConnections()) {
                     $allConnections = array_merge($allConnections, $response->getConnections());
                 }
                 $pageToken = $response->getNextPageToken();
             } while ($pageToken);
 
             $otherContactsResponse = $this->peopleService->otherContacts->listOtherContacts(['readMask' => 'names,emailAddresses', 'pageSize' => 1000]);
             if ($otherContactsResponse->getOtherContacts()) {
                $allPeople = array_merge($allConnections, $otherContactsResponse->getOtherContacts());
             } else {
                $allPeople = $allConnections;
             }
             
             $foundContacts = [];
             $uniqueEmails = [];
             foreach ($allPeople as $person) {
                 if (!$person->getNames()) continue;
                 $displayName = $person->getNames()[0]->getDisplayName();
                 if (stripos($displayName, $name) !== false && $person->getEmailAddresses()) {
                     $email = $person->getEmailAddresses()[0]->getValue();
                     if (!isset($uniqueEmails[$email])) {
                         $foundContacts[] = ['name' => $displayName, 'email' => $email];
                         $uniqueEmails[$email] = true;
                     }
                 }
             }
              
             if (count($foundContacts) > 1) {
                return ['status' => 'found_multiple', 'contacts' => $foundContacts];
             } elseif (count($foundContacts) === 1) {
                 return ['status' => 'found_contact', 'contact' => $foundContacts[0]];
             } else {
                 return ['status' => 'no_contacts_found', 'message' => "I couldn't find anyone named '{$name}' in your contacts."];
             }
         } catch (Exception $e) {
             return ['status' => 'error', 'message' => 'Error reading contacts.'];
         }
    }

    // --- Helper Methods ---

    private function getSenderDisplayName(): string
    {
        try {
            $profile = $this->gmailService->users->getProfile('me');
            $email = $profile->getEmailAddress();

            // Attempt to get a list of "send-as" aliases
            $sendAsAliases = $this->gmailService->users_settings_sendAs->listUsersSettingsSendAs('me');
            foreach ($sendAsAliases->getSendAs() as $alias) {
                if ($alias->getSendAsEmail() === $email) {
                    return $alias->getDisplayName() ?: 'Me';
                }
            }
        } catch (\Exception $e) {
            $this->logger?->warning('Could not fetch display name, falling back to default.');
        }
        return 'Me';
    }

    private function getEmailDetails(\Google\Service\Gmail\Message $message): array
    {
        $payload = $message->getPayload();
        $headers = $payload->getHeaders();
        $details = ['from' => '', 'to' => '', 'subject' => '', 'date' => '', 'body' => '', 'snippet' => $message->getSnippet(), 'message_id_header' => '', 'references_header' => ''];

        foreach ($headers as $header) {
            $name = strtolower($header->getName());
            switch ($name) {
                case 'from':
                    $details['from'] = $header->getValue();
                    break;
                case 'to':
                    $details['to'] = $header->getValue();
                    break;
                case 'subject':
                    $details['subject'] = $header->getValue();
                    break;
                case 'date':
                    $details['date'] = $header->getValue();
                    break;
                case 'message-id':
                    $details['message_id_header'] = $header->getValue();
                    break;
                case 'references':
                case 'in-reply-to':
                    if (empty($details['references_header'])) { // Prefer 'References' but take 'In-Reply-To' as fallback
                        $details['references_header'] = $header->getValue();
                    }
                    break;
            }
        }

        if (!$payload) {
            $this->logger?->warning("Gmail message has no payload.", ['message_id' => $message->getId()]);
            $details['body'] = '[No content found]';
            return $details;
        }

        $body = $this->findBody($payload);
        $details['body'] = $this->sanitizeEmailBody($body);

        return $details;
    }

    private function findBody($payload) {
        $parts = $payload->getParts();
        if (!$parts) {
            $data = $payload->getBody()->getData();
            return base64_decode(strtr($data, '-_', '+/'));
        }

        $body = '';
        $html_body = '';
        foreach ($parts as $part) {
            if ($part->getMimeType() == 'text/html' && $part->getBody()->getData()) {
                $html_body = base64_decode(strtr($part->getBody()->getData(), '-_', '+/'));
            }
            if ($part->getMimeType() == 'text/plain' && $part->getBody()->getData()) {
                $body = base64_decode(strtr($part->getBody()->getData(), '-_', '+/'));
            }
            if(empty($body) && !empty($part->getParts())) {
                 $nested_body = $this->findBody($part);
                 if(!empty($nested_body)) $body = $nested_body;
            }
        }

        // Prefer plain text, but fall back to HTML if that's all there is
        return !empty($body) ? $body : $html_body;
    }

    private function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function _prepareEmail(string $senderEmail, string $senderDisplayName, string $to, string $subject, string $body, array $customHeaders = []): string
    {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($senderEmail, $senderDisplayName);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;

        foreach ($customHeaders as $headerName => $headerValue) {
            if (!empty($headerValue)) {
                $mail->addCustomHeader($headerName, $headerValue);
            }
        }

        $mail->preSend();
        return $mail->getSentMIMEMessage();
    }

    private function allowEmailSending(): bool
    {
        // Simple config check. Could be expanded to more complex rules.
        $config = require __DIR__ . '/../config/config.php';
        return isset($config['allow_email_sending']) && $config['allow_email_sending'] === true;
    }

    private function sanitizeEmailBody(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') return '';

        try {
            $readability = new Readability($raw);
            if (!$readability->init()) {
                // If it fails, just use the raw text, converted to text.
                return Html2Text::convert($raw, ['ignore_errors' => true]);
            }
            $content = $readability->getContent();
            $text = Html2Text::convert($content->textContent, ['ignore_errors' => true]);
            $text = trim(preg_replace('/\s+/', ' ', $text));
            return strlen($text) > 5000 ? substr($text, 0, 5000) . '... [truncated]' : $text;
        } catch (\Throwable $e) {
            $this->logger?->error('HTML sanitization failed, falling back to strip_tags.', ['error' => $e->getMessage()]);
            $text = strip_tags($raw);
            $text = trim(preg_replace('/\s+/', ' ', $text));
            return strlen($text) > 5000 ? substr($text, 0, 5000) . '... [truncated]' : $text;
        }
    }

    public function getToolDefinitions(): array
    {
        return [
            [
                'name' => 'unread_emails',
                'description' => 'Get a list of unread emails. Returns a maximum of 5 results by default.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'max_results' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of emails to return.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'search_emails',
                'description' => 'Search for emails matching a query. Returns a maximum of 5 results.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'The search query (e.g., "from:elonmusk@x.com is:unread").',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'get_email',
                'description' => 'Get the full content of a single email by its ID.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'message_id' => [
                            'type' => 'string',
                            'description' => 'The ID of the email to retrieve.',
                        ],
                    ],
                    'required' => ['message_id'],
                ],
            ],
            [
                'name' => 'mark_email',
                'description' => 'Mark a single email with a status (e.g., as read or unread).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'message_id' => [
                            'type' => 'string',
                            'description' => 'The ID of the email to mark.',
                        ],
                        'status' => [
                            'type' => 'string',
                            'description' => 'The status to apply. Must be either "read" or "unread".',
                        ],
                    ],
                    'required' => ['message_id', 'status'],
                ],
            ],
            [
                'name' => 'search_contacts',
                'description' => "Search for a contact by name to find their email address.",
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => "The name of the contact to search for."
                        ],
                    ],
                    'required' => ['name'],
                ],
            ],
            [
                'name' => 'create_draft',
                'description' => 'Create a new email draft.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'to' => [
                            'type' => 'string',
                            'description' => "The recipient's email address.",
                        ],
                        'subject' => [
                            'type' => 'string',
                            'description' => 'The subject of the email.',
                        ],
                        'body' => [
                            'type' => 'string',
                            'description' => 'The body of the email.',
                        ],
                    ],
                    'required' => ['to', 'subject', 'body'],
                ],
            ],
            [
                'name' => 'send_email',
                'description' => 'Create and send a new email immediately.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'to' => ['type' => 'string', 'description' => "The recipient's email address."],
                        'subject' => ['type' => 'string', 'description' => 'The subject of the email.'],
                        'body' => ['type' => 'string', 'description' => 'The body of the email.'],
                    ],
                    'required' => ['to', 'subject', 'body'],
                ],
            ],
            [
                'name' => 'create_reply_draft',
                'description' => 'Create a draft reply to a specific email.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'message_id' => ['type' => 'string', 'description' => 'The ID of the email to reply to.'],
                        'body' => ['type' => 'string', 'description' => 'The body of the reply.'],
                    ],
                    'required' => ['message_id', 'body'],
                ],
            ],
            [
                'name' => 'send_reply',
                'description' => 'Send a reply to a specific email.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'message_id' => ['type' => 'string', 'description' => 'The ID of the email to reply to.'],
                        'body' => ['type' => 'string', 'description' => 'The body of the reply.'],
                    ],
                    'required' => ['message_id', 'body'],
                ],
            ],
            [
                'name' => 'send_draft',
                'description' => 'Send an existing draft by its ID.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'draft_id' => ['type' => 'string', 'description' => 'The ID of the draft to send.'],
                    ],
                    'required' => ['draft_id'],
                ]
            ]
        ];
    }
} 