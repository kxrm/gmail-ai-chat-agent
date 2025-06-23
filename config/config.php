<?php

/**
 * Application Configuration
 *
 * Defines constants for key application settings.
 */

// --- Ollama Configuration ---
// These are now part of the returned array
// define('OLLAMA_HOST', 'http://host.docker.internal:11434');
// define('OLLAMA_MODEL', 'llama3:8b');

// You can add other configuration constants here in the future.

// Unified configuration for all services
return [
    'ollama_host' => 'http://host.docker.internal:11434',
    
    // Global email policy – keep false in dev, flip to true only in prod
    'allow_email_sending' => false,

    // Application-level debug mode
    'app_debug_mode' => true,

    // --- Llama3 Model without Tool Role Support (default) ---
    'ollama_model' => 'llama3:8b',
    'ollama_model_capabilities' => ['function-calling'],

    // If you want to test Gorilla again, comment the two lines above and uncomment below:
    // 'ollama_model' => 'gorilla-q4-custom:latest',
    // 'ollama_model_capabilities' => ['function-calling', 'parallel-function-calling', 'tool-role'],

    'services' => [
        'google' => [
            'class' => App\Services\GoogleService::class,
            'application_name' => 'PHP Gmail Chat Agent',
            'credentials_path' => __DIR__ . '/client_secret.json',
            'redirect_uri' => 'http://localhost:8000/gmail_oauth.php',
            'tools' => [
                'unread_emails',
                'search_emails',
                'get_email',
                'search_contacts',
                'create_draft',
                'send_email',
                'create_reply_draft',
                'send_reply',
                'send_draft',
                'mark_email',
            ],
        ],
        // To add a new service like Trello, you would just add it here:
        // 'trello' => [
        //     'class' => App\Services\TrelloService::class,
        //     'api_key' => 'YOUR_TRELLO_API_KEY',
        //     'token' => 'YOUR_TRELLO_TOKEN',
        // ]
    ]
]; 