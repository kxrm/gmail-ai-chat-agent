<?php

use Google\Client;
use Google\Service\Gmail;
use Google\Service\PeopleService;
use Monolog\Logger;

class GoogleServiceProvider {
    private Client $client;
    private Logger $logger;

    /**
     * @param Client $client The authenticated Google API client.
     * @param Logger $logger The logger instance.
     */
    public function __construct(Client $client, Logger $logger) {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * @return Client The raw Google API client.
     */
    public function getGoogleClient(): Client {
        return $this->client;
    }

    /**
     * Ensures the access token is set on the client before returning a service.
     * This is important because the token might have been refreshed.
     */
    private function ensureAccessToken() {
        if (isset($_SESSION['access_token']) && !empty($_SESSION['access_token'])) {
            $this->client->setAccessToken($_SESSION['access_token']);
        }
    }

    /**
     * @return Gmail The Google Gmail service instance.
     */
    public function getGmailService(): Gmail {
        $this->ensureAccessToken();
        return new Gmail($this->client);
    }

    /**
     * @return PeopleService The Google People service instance.
     */
    public function getPeopleService(): PeopleService {
        $this->ensureAccessToken();
        return new PeopleService($this->client);
    }

    /**
     * Retrieves the user's primary display name from Gmail settings.
     * Caches the result in the session to avoid repeated API calls.
     *
     * @return string The user's display name or 'User' as a fallback.
     */
    public function getUserDisplayName(): string {
        if (isset($_SESSION['user_display_name']) && !empty($_SESSION['user_display_name'])) {
            return $_SESSION['user_display_name'];
        }

        try {
            $gmailService = $this->getGmailService();
            $sendAsResponse = $gmailService->users_settings_sendAs->listUsersSettingsSendAs('me');
            foreach ($sendAsResponse->getSendAs() as $sendAs) {
                if ($sendAs->getIsDefault()) {
                    $displayName = $sendAs->getDisplayName();
                    $_SESSION['user_display_name'] = $displayName;
                    $this->logger->info("Cached user display name: {$displayName}");
                    return $displayName;
                }
            }
        } catch (Exception $e) {
            $this->logger->error("Could not fetch user display name: " . $e->getMessage());
        }

        return 'User'; // Fallback
    }

    // When you're ready to add Calendar, you would add a new method here:
    /*
    public function getCalendarService(): \Google\Service\Calendar {
        $this->ensureAccessToken();
        return new \Google\Service\Calendar($this->client);
    }
    */
} 