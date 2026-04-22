<?php
declare(strict_types=1);

/* Prevent any PHP notice/warning from corrupting JSON output */
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

require_once __DIR__ . '/bootstrap.php';

if (!function_exists('api_cors_allowed_origin')) {
    function api_cors_allowed_origin(?string $origin): ?string
    {
        if ($origin === null || $origin === '') {
            return null;
        }

        $raw = trim((string) (api_env('API_CORS_ALLOW_ORIGIN', '') ?? ''));
        if ($raw === '') {
            return null; // CORS disabled by default.
        }

        if ($raw === '*') {
            // Only allow wildcard when credentials are explicitly disabled.
            $allowCredentials = api_env_bool('API_CORS_ALLOW_CREDENTIALS', false);
            return $allowCredentials ? null : '*';
        }

        $allowed = array_filter(array_map('trim', explode(',', $raw)));
        foreach ($allowed as $item) {
            if (strcasecmp($item, $origin) === 0) {
                return $origin;
            }
        }

        return null;
    }
}

if (!function_exists('api_set_cors_headers')) {
    function api_set_cors_headers(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        $resolvedOrigin = api_cors_allowed_origin(is_string($origin) ? $origin : null);

        if ($resolvedOrigin === null) {
            return;
        }

        $allowMethods = api_env('API_CORS_ALLOW_METHODS', 'GET, POST, OPTIONS') ?? 'GET, POST, OPTIONS';
        $allowHeaders = api_env('API_CORS_ALLOW_HEADERS', 'Content-Type, Authorization, X-Requested-With') ?? 'Content-Type, Authorization, X-Requested-With';
        $allowCredentials = api_env_bool('API_CORS_ALLOW_CREDENTIALS', false);
        $maxAge = (int) (api_env('API_CORS_MAX_AGE', '600') ?? '600');
        if ($maxAge < 0) {
            $maxAge = 600;
        }

        header('Access-Control-Allow-Origin: ' . $resolvedOrigin);
        header('Access-Control-Allow-Methods: ' . $allowMethods);
        header('Access-Control-Allow-Headers: ' . $allowHeaders);
        header('Access-Control-Max-Age: ' . $maxAge);
        if ($allowCredentials) {
            header('Access-Control-Allow-Credentials: true');
        }

        header('Vary: Origin');
    }
}

if (!function_exists('api_response_init')) {
    function api_response_init(): void
    {
        api_set_cors_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}

if (!function_exists('api_json')) {
    function api_json(int $status, array $payload): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('api_ok')) {
    function api_ok(array $payload = [], int $status = 200): void
    {
        api_json($status, ['ok' => true] + $payload);
    }
}

if (!function_exists('api_error')) {
    function api_error(string $error, int $status = 400, array $extra = []): void
    {
        api_json($status, ['ok' => false, 'error' => $error] + $extra);
    }
}

if (!function_exists('api_require_method')) {
    /**
     * @param string[] $allowed
     */
    function api_require_method(array $allowed): string
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $normalized = array_map(static fn(string $m): string => strtoupper(trim($m)), $allowed);

        if (!in_array($method, $normalized, true)) {
            api_error('Method not allowed', 405);
        }

        return $method;
    }
}
