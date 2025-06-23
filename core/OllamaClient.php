<?php

class OllamaClient {
    private string $ollamaHost;
    private string $ollamaModel;
    private string $ollamaEndpoint;
    private \Psr\Log\LoggerInterface $logger;

    public function __construct(string $ollamaHost, string $ollamaModel, \Psr\Log\LoggerInterface $logger) {
        $this->ollamaHost = $ollamaHost;
        $this->ollamaModel = $ollamaModel;
        $this->ollamaEndpoint = $ollamaHost . '/api/chat';
        $this->logger = $logger;
    }

    /**
     * Sends a chat request to the Ollama API.
     *
     * @param array $messages An array of messages representing the chat history.
     * @param string $format The format of the request payload.
     * @return string The content of the AI's response.
     * @throws Exception If there is a connection error or an invalid response from Ollama.
     */
    public function chat(array $messages, string $format = ''): string {
        $data = [
            'model' => $this->ollamaModel,
            'messages' => $messages,
            'stream' => false,
        ];

        if ($format === 'json') {
            $data['format'] = 'json';
        }

        $this->logger->debug('Sending request to Ollama', ['endpoint' => $this->ollamaEndpoint, 'data' => $data]);

        $ch = curl_init($this->ollamaEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            $this->logger->error('cURL error connecting to Ollama', ['error' => $curl_error]);
            throw new Exception("Error connecting to Ollama: " . $curl_error);
        }
        
        if ($http_code !== 200) {
             $this->logger->error('Ollama server returned non-200 status', [
                'http_code' => $http_code,
                'response' => $result
            ]);
            throw new Exception("Ollama server returned HTTP status {$http_code}. Response: {$result}");
        }

        $this->logger->debug('Received response from Ollama', ['response' => $result]);
        $response = json_decode($result, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('Invalid JSON response from Ollama', ['error' => json_last_error_msg(), 'response' => $result]);
            throw new Exception("Invalid JSON response from Ollama: " . json_last_error_msg());
        }

        if (!isset($response['message']['content'])) {
            $this->logger->error('Ollama response missing message.content', ['response' => $response]);
            throw new Exception("Ollama response missing 'message.content'. Raw response: " . $result);
        }

        return $response['message']['content'];
    }
} 