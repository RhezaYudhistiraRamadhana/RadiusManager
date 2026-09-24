<?php
/**
 * RESTful API Authentication & Response Helpers
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

function apiError(string $message, int $code = 400, array $extra = []): void {
    http_response_code($code);
    echo json_encode(array_merge([
        'success' => false,
        'error'   => $message,
    ], $extra), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function apiSuccess(mixed $data = [], ?array $meta = null, int $code = 200): void {
    http_response_code($code);
    $response = [
        'success' => true,
        'data'    => $data,
    ];
    if ($meta !== null) {
        $response['meta'] = $meta;
    }
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function getJsonInput(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return $_POST;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function authenticateApi(): void {
    $expectedKey = defined('API_KEY') ? API_KEY : '';
    if (empty($expectedKey)) {
        apiError('API key is not configured on the server.', 500);
    }

    $providedKey = '';

    // 1. Authorization: Bearer <token>
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (empty($authHeader) && function_exists('apache_request_headers')) {
        $apacheHeaders = apache_request_headers();
        $authHeader = $apacheHeaders['Authorization'] ?? $apacheHeaders['authorization'] ?? '';
    }
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $providedKey = trim($matches[1]);
    }

    // 2. X-API-Key header
    if (empty($providedKey)) {
        if (!empty($_SERVER['HTTP_X_API_KEY'])) {
            $providedKey = trim($_SERVER['HTTP_X_API_KEY']);
        } elseif (function_exists('apache_request_headers')) {
            $apacheHeaders = apache_request_headers();
            $providedKey = $apacheHeaders['X-API-Key'] ?? $apacheHeaders['x-api-key'] ?? '';
        }
    }

    // 3. Fallback: ?api_key= query parameter
    if (empty($providedKey) && !empty($_GET['api_key'])) {
        $providedKey = trim($_GET['api_key']);
    }

    if (empty($providedKey) || !hash_equals($expectedKey, $providedKey)) {
        apiError('Unauthorized. Missing or invalid API key.', 401);
    }
}

// Automatically enforce authentication on include
authenticateApi();
