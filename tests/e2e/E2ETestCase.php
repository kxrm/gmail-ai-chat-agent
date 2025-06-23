<?php

declare(strict_types=1);

namespace Tests\E2E;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestStatus;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;
use Symfony\Component\Process\Process;

class E2ETestCase extends TestCase
{
    protected static ?Process $serverProcess = null;
    protected ?FileCookieJar $cookieJar = null;
    protected ?Client $client = null;
    protected array $log = [];
    private $logHandle;

    protected const SERVER_HOST = '127.0.0.1';
    protected const SERVER_PORT = 8081;
    protected const SERVER_URL = 'http://' . self::SERVER_HOST . ':' . self::SERVER_PORT;

    private const LOG_FILE_PATH = __DIR__ . '/../../../test-output.log';
    private const MAX_TURNS = 10; // Failsafe to prevent infinite loops

    public static function setUpBeforeClass(): void
    {
        $publicDir = realpath(__DIR__ . '/../../public');
        if (!is_dir($publicDir)) {
            self::fail('Public directory not found.');
        }

        $command = ['php', '-S', self::SERVER_HOST . ':' . self::SERVER_PORT, '-t', $publicDir];
        self::$serverProcess = new Process($command, $publicDir, ['PHP_AUTOMOCK_GOOGLE' => '1', 'APP_DEBUG_MODE' => 'true']);
        self::$serverProcess->disableOutput();
        self::$serverProcess->start();
        usleep(500000); // Wait for server to start

        // Clear the log file at the start of the test suite run
        file_put_contents(self::LOG_FILE_PATH, '');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$serverProcess !== null && self::$serverProcess->isRunning()) {
            self::$serverProcess->stop();
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize the cookie jar and a single Guzzle client for the entire test.
        $cookieFile = tempnam(sys_get_temp_dir(), 'e2e_cookie');
        $this->cookieJar = new FileCookieJar($cookieFile, true);
        $this->client = new Client(['base_uri' => self::SERVER_URL, 'cookies' => $this->cookieJar, 'http_errors' => false]);
        
        // 1. Perform mock OAuth login to get Google token into the session.
        $this->client->get('/gmail_oauth.php?test_mode=true');

        // 2. Reset the chat history to ensure a clean slate for the test.
        $this->resetChatHistory();

        $this->log = [
            'scenario' => $this->getName(),
            'test_file' => static::class,
            'status' => 'FAIL', // Default to FAIL, set to PASS on success
            'turns' => [],
        ];
        $this->logHandle = fopen('php://output', 'w');
    }

    protected function tearDown(): void
    {
        // On successful completion of a test, mark its status as PASS
        if ($this->getStatus() === \PHPUnit\Runner\BaseTestRunner::STATUS_PASSED) {
            $this->log['status'] = 'PASS';
        }
        echo json_encode($this->log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    protected function logMessage(string $message): void
    {
        fwrite($this->logHandle, $message);
        fflush($this->logHandle);
    }
    
    protected function performTurn(string $userInput, string $intention, string $expectation): array
    {
        $turnIndex = count($this->log['turns']) + 1;
        if ($turnIndex > self::MAX_TURNS) {
            $this->fail("Exceeded maximum number of turns (" . self::MAX_TURNS . ").");
        }

        $events = [];

        // Start the turn by sending the user's message
        $response = $this->sendRequest(['action' => 'send_message', 'message' => $userInput]);
        $events[] = $response;

        // Handle tool calls in the new granular workflow
        $currentStep = 0;
        while (isset($response['type']) && $response['type'] === 'tool_call' && $currentStep < self::MAX_TURNS) {
            // Execute the tool that the AI requested
            $toolResponse = $this->sendRequest([
                'action' => 'execute_tool',
                'tool_name' => $response['tool_name'],
                'arguments' => $response['arguments']
            ]);
            $events[] = $toolResponse;
            $response = $toolResponse;
            $currentStep++;
        }

        // Legacy support: handle old-style tool_success responses
        while (isset($response['type']) && $response['type'] === 'tool_success' && $currentStep < self::MAX_TURNS) {
            // Tell the ChatManager to continue processing with the tool result it just got.
            $response = $this->sendRequest(['action' => 'continue_processing']);
            $events[] = $response;
            $currentStep++;
        }

        $turn = [
            'turn' => $turnIndex,
            'intention' => $intention,
            'user_input' => $userInput,
            'expectation' => $expectation,
            'result' => 'FAIL', // Default to FAIL
            'events' => $events,
            'final_response' => $response,
        ];
        
        $this->log['turns'][] = &$turn;
        return $turn;
    }

    protected function sendRequest(array $payload): array
    {
        try {
            $response = $this->client->post('/api/ajax_handler.php', [
                'json' => $payload
            ]);

            return json_decode((string) $response->getBody(), true) ?? ['error' => 'Failed to decode JSON response', 'body' => (string) $response->getBody()];
        } catch (\Exception $e) {
            $this->fail("Request failed: " . $e->getMessage());
            return []; // Satisfy linter, though fail() will halt execution.
        }
    }

    private function resetChatHistory(): void
    {
        $this->client->post('/api/ajax_handler.php', [
            'json' => ['action' => 'reset_chat']
        ]);
    }
} 