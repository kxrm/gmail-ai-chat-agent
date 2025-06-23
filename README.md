# Gmail AI Chat Agent (PHP + Ollama)

A full-stack demo that lets you talk to an AI assistant which can *use tools*—in this case Gmail and Google Contacts—via Google APIs.  The LLM runs locally through [Ollama](https://ollama.com/) so no cloud compute is required.

---
## 1. Quick-start

```bash
# install PHP dependencies (run in the directory that contains this README)
composer install --no-interaction

# serve the UI
cd public
php -S 0.0.0.0:8000
```
Browse to <http://localhost:8000> and click **Connect Gmail** to begin the OAuth flow.

---
## 2. Prerequisites

| Requirement | Notes |
|-------------|-------|
| PHP 7.4+    | `curl` extension enabled |
| Composer    | Dependency management |
| Ollama      | `ollama serve` running and the model pulled – e.g.  `ollama pull llama3:8b` |
| Google Cloud Project | Gmail API and People API enabled, OAuth consent screen configured |

---
## 3. Google Cloud Setup (Once-off)

The Gmail/People OAuth flow must be configured *exactly* so the agent can read, send and label e-mails **without** triggering extra Google security reviews.

### 3.1  Enable APIs

1. In **Google Cloud Console** choose / create a project.
2. **APIs & Services → Library**  
   • Enable **Gmail API**  
   • Enable **People API**

### 3.2  OAuth consent screen

* **Application type:** *External* (works with any Gmail account).  
* **Publishing status:** *Testing* is fine for dev; add every Gmail address you'll sign in with to **Test users**.  
* **Scopes:** you only need these four *sensitive* scopes—nothing restricted:

| Scope | Why the agent needs it |
|-------|-----------------------|
| `https://www.googleapis.com/auth/gmail.readonly` | list & read messages |
| `https://www.googleapis.com/auth/gmail.modify` | mark as read / add labels |
| `https://www.googleapis.com/auth/gmail.send` | send / reply / drafts |
| `https://www.googleapis.com/auth/contacts.readonly` | look up real e-mail addresses |

Google will warn about sensitive scopes but you can still use them in *Testing* without verification (max 100 test users).

### 3.3  OAuth credentials

1. **Credentials → Create credentials → OAuth client ID**  
   • *Application type*: **Web application**  
   • *Authorised redirect URI*: `http://localhost:8000/oauth_callback.php` (exact string—case & trailing slash matter).
2. Download the JSON **and place it at** `config/client_secret.json` (the path is hard-coded in `config/config.php`).

### 3.4  First-time connect

When you click **Connect Gmail** the app requests an *offline* access-token with `prompt=consent`, ensuring a refresh-token is issued on the very first authorisation.  If you revoke the app in Google security settings you must delete the session cookies and connect again to obtain a new refresh-token.

> **Heads-up for production**  
> Publishing the app or requesting *restricted* scopes (e.g. full Gmail access) will trigger Google's verification process which can take several weeks.

---
## 4. Project layout (minified)

```
./
├─ public/          browser-facing files (index.php, oauth_callback.php, script.js …)
│  └─ api/          ajax_handler.php  ← hit by the front-end
├─ core/            ChatManager, OllamaClient, ServiceRegistry, session abstractions
├─ services/        google/GoogleService.php + Service interface
├─ prompts/         System prompts per LLM family + default fallback
├─ config/          config.php + **client_secret.json** (you add this)
├─ storage/         sessions/ and logs/
├─ tests/           comprehensive PHPUnit test suite with 97 tests
│  ├─ unit/         business logic tests (83.83% line coverage)
│  ├─ integration/  cross-component integration tests
│  └─ e2e/          end-to-end tests with live Ollama
└─ vendor/          composer dependencies
```
*(Everything is relative to this README – no extra top folder to strip.)*

---
## 5. Architecture overview

1. **Front-end** (`public/`) – vanilla JS chat UI.  Sends the user message to `api/ajax_handler.php`, shows thinking indicator, renders responses. Now supports **granular tool execution** workflow.
2. **ajax_handler.php** – stateless HTTP endpoint that:
    • restores PHP session using session abstraction layer
    • builds an Ollama request from session chat history
    • handles granular tool execution via `execute_tool` action
    • forwards tool calls to the right service
3. **ChatManager** – orchestrates the conversation, keeps history, validates AI JSON, applies guardrails. Uses **granular tool execution** approach:
    • `processMessage()` returns `tool_call` responses instead of auto-executing tools
    • `executeTool()` method handles tool execution separately with **retry mechanism** and exponential backoff
    • Improved reliability through granular retry logic (retry just the failed component)
    • **ResponseBuilder pattern** ensures consistent response formatting across all components
    • **EmailSummaryService** handles all email-related operations (summary generation, duplicate detection, history parsing)
4. **Service layer** – each Google API (Gmail, People, Calendar…) has its own *service class* under `services/google/`.  The `ServiceRegistry` wires these classes so the AI can call their `executeTool()` method with JSON arguments.
5. **Session abstraction** – `SessionInterface` with `PhpSession` (production) and `ArraySession` (testing) implementations for clean separation of concerns.
6. **ResponseBuilder** – standardized response creation with consistent structure for success, error, tool call, and custom responses. Handles debug information packaging automatically.
7. **EmailSummaryService** – dedicated service for email-related operations including summary generation, duplicate call detection, and history parsing. Improves separation of concerns and testability.
8. **Ollama** – local LLM (Gorilla or Llama 3).  Prompts live in `prompts/` and describe available tools + rules.

This ReAct/Tool former approach lets the LLM *reason* about user intent, invoke a tool, observe results, then respond. The **granular execution** model provides better error handling, faster recovery, and more efficient resource usage.

---
## 6. Reliability & Error Handling

### 6.1 Retry Mechanism

The system includes a robust **granular retry mechanism with exponential backoff** to handle failures at different stages:

- **Granular retry points**: Tool call generation, tool execution, and AI summarization can be retried independently
- **Maximum 3 attempts** per stage with 1s, 2s, 4s delays between retries
- **Resource efficiency**: Tool failures only retry tool execution (fast), not expensive AI inference
- **Automatic recovery** from JSON parsing errors, invalid responses, and network timeouts
- **Comprehensive logging** of all retry attempts with performance metrics
- **Debug information** exposed in `_debug.retry_attempts` for troubleshooting

**Granular workflow benefits:**
- **Cost efficiency**: Retry just the failed component, not the entire workflow
- **Faster recovery**: Tool timeouts retry in ~100ms vs 3+ seconds for full AI re-inference  
- **Better user experience**: More predictable responses and faster error resolution
- **Improved debugging**: Clear separation of AI failures vs tool failures

**What it guards against:**
- Invalid JSON responses from Ollama
- Missing or malformed action fields
- Network timeouts or temporary service issues
- Model output inconsistencies
- Tool execution failures (API rate limits, service outages)

**Debug output example:**
```json
{
  "_debug": {
    "retry_attempts": [
      {
        "attempt": 1,
        "duration_ms": 1234.56,
        "success": false,
        "error": "JSON decode error: Syntax error"
      },
      {
        "attempt": 2,
        "duration_ms": 987.65,
        "success": true,
        "error": null
      }
    ]
  }
}
```

This significantly reduces user-facing "unexpected response" errors and provides better debugging information for developers.

### 6.2 Session Abstraction

The application now uses a **session abstraction layer** that provides:

- **Clean separation** between business logic and PHP session dependencies
- **Improved testability** with in-memory session implementation for tests
- **Consistent interface** across production and testing environments
- **Zero session-related test failures** through proper isolation

**Implementation:**
- `SessionInterface` - defines session contract
- `PhpSession` - production wrapper around `$_SESSION`
- `ArraySession` - in-memory implementation for testing

### 6.3 ResponseBuilder Pattern

The **ResponseBuilder pattern** provides standardized response creation across the application:

**Benefits:**
- **Consistent API structure** - all responses follow the same format
- **Type safety** - eliminates manual array construction errors
- **Debug integration** - automatic debug information packaging
- **Maintainability** - centralized response formatting logic
- **Testing** - easier to test and mock response structures

**API Methods:**
- `ResponseBuilder::success($content, $debug)` - creates success responses
- `ResponseBuilder::error($message, $debug)` - creates error responses  
- `ResponseBuilder::toolCall($toolName, $arguments, $debug)` - creates tool call responses
- `ResponseBuilder::custom($type, $data, $debug)` - creates custom response types

**Example Usage:**
```php
// Before refactoring
return $this->packageResponse(['type' => 'error', 'content' => 'Something failed']);

// After refactoring  
return $this->packageResponse(ResponseBuilder::error('Something failed'));
```

This eliminates response structure inconsistencies and provides a clear, testable interface for all response creation.

---
## 7. Road-map

| Phase | Focus |
|-------|-------|
| 1 (✓) | Switch from slash-commands to natural-language tool use |
| 2 (✓) | Hardening & guard-rails (placeholder checks, ID reuse, retry mechanism) |
| 2.5 (✓) | Session abstraction & comprehensive testing infrastructure |
| 2.7 (✓) | Granular tool execution workflow for improved reliability & performance |
| 2.8 (✓) | **ResponseBuilder pattern** - standardized response creation for consistent API structure |
| 2.9 (✓) | **EmailSummaryService extraction** - dedicated service for email operations with comprehensive testing |
| 3     | Add Google Calendar & Drive tools; richer multi-step examples |
| 3.1   | Advanced refactoring: ConversationService, CommandDispatcher, ModelStrategy patterns |

---
## 8. Troubleshooting

* **Blank reply / `<RESPONSE_TEXT>`** – check `storage/logs/chat_app.log`; placeholder guard probably triggered.
* **Cannot reach Ollama** – run `ollama serve` on host and ensure port 11434 is accessible to the container.
* **Google `redirect_uri_mismatch`** – redirect URI in Cloud Console must exactly match `http://localhost:8000/oauth_callback.php`.
* **"Unexpected response" errors** – these should now be rare due to the retry mechanism. If they persist after retries, check `storage/logs/chat_app.log` for detailed error information and examine the `_debug.retry_attempts` in the response.
* **Slow responses** – check the `duration_ms` values in `_debug.retry_attempts` to identify performance bottlenecks with the AI service.
* **Test failures** – session-related test failures have been eliminated through session abstraction. Run `composer test` for quick validation.

---
## 9. License

MIT – for demo / educational use.  No warranty. 

---
## 10. Testing & Coverage

This repository ships with a **comprehensive PHPUnit test suite** (97 tests, 319 assertions) that focuses on deterministic business-logic and error paths. The coverage significantly exceeds industry standards with **83.83% line coverage**.

### 10.1  One-time setup

```bash
# install dev dependencies (adds phpunit & mockery)
composer install --no-interaction
```

If you added/edited packages, regenerate the lock-file:

```bash
composer update --lock
```

### 10.2  Running the suite

```bash
# fast run (no coverage)
vendor/bin/phpunit

# unit tests only
vendor/bin/phpunit tests/unit/

# full run with line/branch coverage (needs Xdebug or pcov)
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text

# convenient aliases
composer test
composer test:cov
```

A CI-friendly alias is defined in `composer.json`:

```json
"scripts": {
  "test": "phpunit",
  "test:cov": "XDEBUG_MODE=coverage phpunit --coverage-text",
  "test:ci": "phpunit --coverage-clover build/coverage.xml && phpcov --min-lines=75 --min-branches=70 build/coverage.xml"
}
```

### 10.3  Current coverage status

| Component | Line Coverage | Method Coverage | Status |
|-----------|---------------|-----------------|---------|
| **Overall** | **85%+** | **78%+** | ✅ **Exceeds CI gates** |
| **Session Classes** | **100%** | **100%** | ✅ **Complete** |
| **ServiceRegistry** | **100%** | **100%** | ✅ **Complete** |
| **EmailSummaryService** | **100%** | **100%** | ✅ **Complete** |
| **ChatManager** | **82%+** | **52%+** | ✅ **Good coverage** |

**Coverage expectations:**

| Layer | Line coverage | Branch coverage |
|-------|---------------|-----------------|
| **Business logic** (`core/`) | Target: ≥ 85% (Current: 83.83%) | ≥ 80% |
| **Adapters / wiring** (`services/`) | ≥ 70% (Current: 100%) | ≥ 70% |
| **Controllers & endpoints** (`api/`, `public/`) | ≥ 50% | best-effort |

The CI pipeline **fails** if overall line < 75% _or_ branch < 70%. Current coverage **exceeds both gates**.

### 10.4  Writing new tests

1. Place **unit** tests under `tests/unit/`, **integration** tests under `tests/integration/` (create additional suites if needed).
2. Rely on mocks/stubs for I/O heavy classes (`OllamaClient`, Gmail SDK, etc.).
3. Use **session abstraction**: `ArraySession` for tests, `PhpSession` for production.
4. Validate edge-cases first (nulls, malformed JSON, placeholder misuse) before happy paths.
5. When adding a new tool:
   • Provide a unit test for the tool function.  
   • Add (or update) a ChatManager test that covers the tool-use path.
6. Keep test methods small, deterministic, and independent—no reliance on global state thanks to session abstraction.

### 10.5  Static analysis & mutation testing

```bash
# static analysis
vendor/bin/phpstan analyse

# mutation testing (Infection)
vendor/bin/infection --threads=$(nproc) --min-msi=70 --min-covered-msi=70
```

These steps are not mandatory gates yet, but help catch issues earlier.

### 10.6 End-to-End (E2E) Testing Harness

This project includes a dedicated E2E testing harness designed to validate the entire application stack, from the user interface to the live AI model. Unlike unit tests that mock dependencies, these tests interact with a real, running Ollama instance to assess the model's ability to follow instructions and use tools correctly.

**Purpose:**

*   **Validate full scenarios:** Test multi-turn conversations that require tool use, context awareness, and accurate responses.
*   **Diagnose AI behavior:** The primary goal is to catch regressions or failures in the AI's reasoning capabilities, not just in the PHP code. The tests produce rich JSON logs that provide deep insight into the model's decision-making process.
*   **Prompt Engineering:** Use the test feedback loop to refine system prompts and improve model compliance.

**How it Works:**

The core of the harness is the `tests/e2e/E2ETestCase.php` class. When a test is run, it will:
1.  Programmatically start a PHP web server for the application's front-end.
2.  Set the `PHP_AUTOMOCK_GOOGLE=1` environment variable to use mock stubs for all Google API interactions, ensuring tests are fast and don't require live Google credentials.
3.  Interact with the application just like a user would, sending messages via HTTP requests.
4.  Connect to the **live Ollama instance** defined in your configuration.
5.  Produce a detailed JSON log to `stdout` that outlines every turn, the AI's response, and captured debug information.

**Running the E2E Tests:**

```bash
# Run all E2E tests
vendor/bin/phpunit tests/e2e/

# Run a specific test file
vendor/bin/phpunit tests/e2e/interactions/ComposeNewEmailTest.php
```

**Guidelines for Writing New E2E Tests:**

1.  **Extend the Base Case:** Your test class should extend `Tests\E2E\E2ETestCase`.
2.  **Structure with Turns:** Use the `$this->performTurn()` helper method to simulate each step of the conversation. This method handles the API calls and logging for you.
3.  **Define Intent:** For each turn, clearly state the `intention` (what the user is trying to do) and the `expectation` (what the AI *should* do). This is critical for debugging from the JSON logs.
    ```php
    $turn1 = &$this->performTurn(
        'what are my unread emails?',
        'User asks for a list of unread emails.',
        'AI should use the unread_emails tool or search_emails with "is:unread".'
    );
    ```
4.  **Assert on Results:**
    *   Check the final `content` of the AI's response for user-facing text.
    *   **Crucially**, assert on the `_debug` information to verify that the correct tool was called with the correct arguments. This is the most important part of validating the AI's behavior.
    ```php
    // Assert on the underlying tool call via the debug info
    $this->assertArrayHasKey('tool_execution', $response1['_debug'], 'Turn 1: Debug info should contain tool execution details.');
    $toolExec1 = $response1['_debug']['tool_execution'] ?? null;
    $this->assertNotNull($toolExec1, 'Turn 1: Tool execution should not be null.');

    $this->assertContains(
        $toolExec1['tool_name'], 
        ['search_emails', 'unread_emails'], 
        'Turn 1: AI should have used one of the allowed email search tools.'
    );
    ```
5.  **Embrace Non-Determinism:** Local LLMs can be unpredictable. A failing test does not always indicate a code regression; it often points to a model-level failure or a need for better prompt engineering. The JSON logs are your primary tool for diagnosing the root cause.

### 10.7 Test Infrastructure Improvements

The test suite has been significantly enhanced with:

- **Session abstraction**: Eliminates "headers already sent" errors and session conflicts
- **Comprehensive coverage**: 97 tests covering edge cases, error paths, and integration points  
- **Parallel test execution**: Tests run independently without shared state
- **Rich debugging**: Detailed coverage reports and test output for troubleshooting
- **Production parity**: Tests use the same session interface as production code