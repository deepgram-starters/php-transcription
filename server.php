<?php
/**
 * PHP Transcription Starter - Backend Server
 *
 * This is a simple PHP built-in server router that provides a transcription API
 * endpoint powered by Deepgram's Speech-to-Text service. It's designed to be easily
 * modified and extended for your own projects.
 *
 * Key Features:
 * - Contract-compliant API endpoint: POST /api/transcription
 * - Accepts both file uploads and URLs
 * - CORS enabled for frontend communication
 * - JWT session auth with Bearer token validation
 * - Pure API server (frontend served separately)
 */

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Yosymfony\Toml\Toml;

// ============================================================================
// CONFIGURATION - Customize these values for your needs
// ============================================================================

/**
 * Default transcription model to use when none is specified
 * Options: "nova-3", "nova-2", "nova", "enhanced", "base"
 * See: https://developers.deepgram.com/docs/models-languages-overview
 */
define('DEFAULT_MODEL', 'nova-3');

/**
 * Maximum upload size (10MB)
 */
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);

/**
 * JWT token expiry time in seconds (1 hour)
 */
define('JWT_EXPIRY', 3600);

// ============================================================================
// ENVIRONMENT - Load .env and validate API key
// ============================================================================

/**
 * Load environment variables from .env file if it exists.
 * Falls back to system environment variables.
 */
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

/**
 * Loads the Deepgram API key from environment variables.
 * Exits with helpful error if not found.
 *
 * @return string The Deepgram API key
 */
function loadApiKey(): string
{
    $apiKey = $_ENV['DEEPGRAM_API_KEY'] ?? getenv('DEEPGRAM_API_KEY') ?: '';

    if (empty($apiKey)) {
        fwrite(STDERR, "\nERROR: Deepgram API key not found!\n\n");
        fwrite(STDERR, "Please set your API key using one of these methods:\n\n");
        fwrite(STDERR, "1. Create a .env file (recommended):\n");
        fwrite(STDERR, "   DEEPGRAM_API_KEY=your_api_key_here\n\n");
        fwrite(STDERR, "2. Environment variable:\n");
        fwrite(STDERR, "   export DEEPGRAM_API_KEY=your_api_key_here\n\n");
        fwrite(STDERR, "Get your API key at: https://console.deepgram.com\n\n");
        exit(1);
    }

    return $apiKey;
}

$apiKey = loadApiKey();

// ============================================================================
// SESSION AUTH - JWT tokens for production security
// ============================================================================

/**
 * Session secret for signing JWTs.
 * In production, set SESSION_SECRET env var for stable tokens across restarts.
 * In development, a random secret is generated each time.
 */
$sessionSecret = $_ENV['SESSION_SECRET'] ?? getenv('SESSION_SECRET') ?: bin2hex(random_bytes(32));

/**
 * Validates JWT from Authorization: Bearer header.
 * Returns decoded payload on success, sends 401 JSON error on failure.
 *
 * @param string $sessionSecret The secret key used to verify JWTs
 * @return object|null Decoded JWT payload, or null (response already sent)
 */
function requireSession(string $sessionSecret): ?object
{
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
        sendJson(401, [
            'error' => [
                'type' => 'AuthenticationError',
                'code' => 'MISSING_TOKEN',
                'message' => 'Authorization header with Bearer token is required',
            ],
        ]);
        return null;
    }

    $token = substr($authHeader, 7);

    try {
        $decoded = JWT::decode($token, new Key($sessionSecret, 'HS256'));
        return $decoded;
    } catch (\Firebase\JWT\ExpiredException $e) {
        sendJson(401, [
            'error' => [
                'type' => 'AuthenticationError',
                'code' => 'INVALID_TOKEN',
                'message' => 'Session expired, please refresh the page',
            ],
        ]);
        return null;
    } catch (\Exception $e) {
        sendJson(401, [
            'error' => [
                'type' => 'AuthenticationError',
                'code' => 'INVALID_TOKEN',
                'message' => 'Invalid session token',
            ],
        ]);
        return null;
    }
}

// ============================================================================
// HELPER FUNCTIONS - Modular logic for easier understanding and testing
// ============================================================================

/**
 * Sends a JSON response with the given status code and exits.
 *
 * @param int $statusCode HTTP status code
 * @param array $data Response data to encode as JSON
 */
function sendJson(int $statusCode, array $data): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    echo json_encode($data);
    exit;
}

/**
 * Sends CORS headers for preflight OPTIONS requests and exits.
 */
function handleCorsPreFlight(): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(204);
    exit;
}

/**
 * Sets CORS headers on the current response.
 */
function setCorsHeaders(): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

/**
 * Validates that either a file or URL was provided in the request.
 *
 * @param array|null $file Uploaded file from $_FILES
 * @param string|null $url URL from request body
 * @return array|null Request info for Deepgram, or null if invalid
 */
function validateTranscriptionInput(?array $file, ?string $url): ?array
{
    if ($url && strlen(trim($url)) > 0) {
        return ['type' => 'url', 'url' => $url];
    }

    if ($file && $file['error'] === UPLOAD_ERR_OK && $file['size'] > 0) {
        return ['type' => 'file', 'tmp_name' => $file['tmp_name'], 'mimetype' => $file['type']];
    }

    return null;
}

/**
 * Calls the Deepgram transcription API using cURL.
 *
 * @param array $dgRequest Request info with type (url or file)
 * @param string $model The transcription model to use
 * @param string $apiKey The Deepgram API key
 * @return array ['success' => bool, 'data' => array|null, 'error' => string|null, 'httpCode' => int]
 */
function callDeepgramTranscription(array $dgRequest, string $model, string $apiKey): array
{
    $url = 'https://api.deepgram.com/v1/listen?model=' . urlencode($model);

    $ch = curl_init();

    if ($dgRequest['type'] === 'url') {
        // URL-based transcription
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['url' => $dgRequest['url']]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Token ' . $apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);
    } else {
        // File-based transcription
        $fileData = file_get_contents($dgRequest['tmp_name']);
        $mimetype = $dgRequest['mimetype'] ?: 'audio/wav';

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $fileData,
            CURLOPT_HTTPHEADER => [
                'Content-Type: ' . $mimetype,
                'Authorization: Token ' . $apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return [
            'success' => false,
            'data' => null,
            'error' => 'Failed to connect to Deepgram API: ' . $curlError,
            'httpCode' => 0,
        ];
    }

    $decoded = json_decode($response, true);

    if ($httpCode >= 400) {
        $errorMessage = $decoded['err_msg'] ?? $decoded['message'] ?? 'Deepgram API error';
        return [
            'success' => false,
            'data' => null,
            'error' => $errorMessage,
            'httpCode' => $httpCode,
        ];
    }

    return [
        'success' => true,
        'data' => $decoded,
        'error' => null,
        'httpCode' => $httpCode,
    ];
}

/**
 * Formats Deepgram's response into a simplified, consistent structure.
 *
 * @param array $transcription Raw Deepgram API response
 * @param string $modelName Name of model used
 * @return array Formatted response
 */
function formatTranscriptionResponse(array $transcription, string $modelName): array
{
    $result = $transcription['results']['channels'][0]['alternatives'][0] ?? null;

    if (!$result) {
        throw new \RuntimeException('No transcription results returned from Deepgram');
    }

    $response = [
        'transcript' => $result['transcript'] ?? '',
        'words' => $result['words'] ?? [],
        'metadata' => [
            'model_uuid' => $transcription['metadata']['model_uuid'] ?? null,
            'request_id' => $transcription['metadata']['request_id'] ?? null,
            'model_name' => $modelName,
        ],
    ];

    if (isset($transcription['metadata']['duration'])) {
        $response['duration'] = $transcription['metadata']['duration'];
    }

    return $response;
}

/**
 * Formats error responses in a consistent structure.
 *
 * @param string $message Error message
 * @param int $statusCode HTTP status code
 * @param string $errorCode Contract error code
 * @return array Formatted error response with statusCode and body
 */
function formatErrorResponse(string $message, int $statusCode = 500, string $errorCode = 'TRANSCRIPTION_FAILED'): array
{
    $type = $statusCode === 400 ? 'ValidationError' : 'TranscriptionError';

    return [
        'statusCode' => $statusCode,
        'body' => [
            'error' => [
                'type' => $type,
                'code' => $errorCode,
                'message' => $message,
                'details' => [
                    'originalError' => $message,
                ],
            ],
        ],
    ];
}

// ============================================================================
// ROUTING - Parse request URI and dispatch to handlers
// ============================================================================

// Parse the request
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Handle CORS preflight for all routes
if ($method === 'OPTIONS') {
    handleCorsPreFlight();
}

// ============================================================================
// SESSION ROUTES - Auth endpoints (unprotected)
// ============================================================================

/**
 * GET /api/session - Issues a signed JWT for session authentication.
 */
if ($uri === '/api/session' && $method === 'GET') {
    $now = time();
    $payload = [
        'iat' => $now,
        'exp' => $now + JWT_EXPIRY,
    ];

    $token = JWT::encode($payload, $sessionSecret, 'HS256');

    sendJson(200, ['token' => $token]);
}

// ============================================================================
// METADATA ROUTE - Returns deepgram.toml metadata
// ============================================================================

/**
 * GET /api/metadata - Returns metadata about this starter application.
 * Required for standardization compliance.
 */
if ($uri === '/api/metadata' && $method === 'GET') {
    try {
        $tomlPath = __DIR__ . '/deepgram.toml';

        if (!file_exists($tomlPath)) {
            sendJson(500, [
                'error' => 'INTERNAL_SERVER_ERROR',
                'message' => 'deepgram.toml not found',
            ]);
        }

        $config = Toml::parseFile($tomlPath);

        if (!isset($config['meta'])) {
            sendJson(500, [
                'error' => 'INTERNAL_SERVER_ERROR',
                'message' => 'Missing [meta] section in deepgram.toml',
            ]);
        }

        sendJson(200, $config['meta']);
    } catch (\Exception $e) {
        error_log('Error reading metadata: ' . $e->getMessage());
        sendJson(500, [
            'error' => 'INTERNAL_SERVER_ERROR',
            'message' => 'Failed to read metadata from deepgram.toml',
        ]);
    }
}

// ============================================================================
// API ROUTES - Transcription endpoint (protected)
// ============================================================================

/**
 * POST /api/transcription
 *
 * Contract-compliant transcription endpoint per starter-contracts specification.
 * Accepts either:
 * - A file upload (multipart/form-data with 'file' field)
 * - A URL to audio file (form data with 'url' field)
 *
 * Optional parameters:
 * - model: Deepgram model to use (default: "nova-3")
 *
 * Returns:
 * - Success (200): JSON with transcript, words, metadata, duration
 * - Error (4XX/5XX): JSON error response matching contract format
 *
 * Protected by JWT session auth (requireSession).
 */
if ($uri === '/api/transcription' && $method === 'POST') {
    // Validate session token
    $session = requireSession($sessionSecret);
    if ($session === null) {
        exit;
    }

    // Get file upload and form fields
    $file = $_FILES['file'] ?? null;
    $url = $_POST['url'] ?? null;
    $model = $_POST['model'] ?? DEFAULT_MODEL;

    // Validate input - must have either file or URL
    $dgRequest = validateTranscriptionInput($file, $url);
    if (!$dgRequest) {
        $err = formatErrorResponse('Either file or url must be provided', 400, 'MISSING_INPUT');
        sendJson($err['statusCode'], $err['body']);
    }

    // Call Deepgram transcription API
    $result = callDeepgramTranscription($dgRequest, $model, $apiKey);

    if (!$result['success']) {
        error_log('Transcription error: ' . $result['error']);
        $err = formatErrorResponse($result['error']);
        sendJson($err['statusCode'], $err['body']);
    }

    // Format and return response
    try {
        $response = formatTranscriptionResponse($result['data'], $model);
        sendJson(200, $response);
    } catch (\Exception $e) {
        error_log('Transcription format error: ' . $e->getMessage());
        $err = formatErrorResponse($e->getMessage());
        sendJson($err['statusCode'], $err['body']);
    }
}

// ============================================================================
// HEALTH CHECK
// ============================================================================

/**
 * GET /health - Returns a simple health check response.
 */
if ($uri === '/health' && $method === 'GET') {
    sendJson(200, ['status' => 'ok']);
}

// ============================================================================
// 404 - Not Found
// ============================================================================

setCorsHeaders();
sendJson(404, [
    'error' => 'NOT_FOUND',
    'message' => 'Endpoint not found: ' . $method . ' ' . $uri,
]);
