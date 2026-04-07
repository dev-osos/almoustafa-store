<?php
declare(strict_types=1);

/**
 * Shared bootstrap for all APIs.
 * - Loads .env from project root once.
 * - Exposes env helpers for consistent config access.
 */

if (!defined('API_BOOTSTRAP_LOADED')) {
    define('API_BOOTSTRAP_LOADED', true);

    /** @var array<string, string> $API_ENV */
    $API_ENV = [];

    if (!function_exists('api_load_env')) {
        function api_load_env(string $envPath): void
        {
            global $API_ENV;

            if (!is_file($envPath) || !is_readable($envPath)) {
                return;
            }

            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                return;
            }

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                $parts = explode('=', $line, 2);
                if (count($parts) !== 2) {
                    continue;
                }

                $key = trim($parts[0]);
                $value = trim($parts[1]);

                if ($key === '') {
                    continue;
                }

                // Remove wrapping quotes if present.
                $len = strlen($value);
                if ($len >= 2) {
                    $first = $value[0];
                    $last = $value[$len - 1];
                    if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                        $value = substr($value, 1, -1);
                    }
                }

                $API_ENV[$key] = $value;

                // Keep getenv()/$_ENV/$_SERVER in sync for compatibility.
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                putenv($key . '=' . $value);
            }
        }
    }

    if (!function_exists('api_env')) {
        function api_env(string $key, ?string $default = null): ?string
        {
            global $API_ENV;

            if (array_key_exists($key, $API_ENV)) {
                return $API_ENV[$key];
            }

            $fromEnv = getenv($key);
            if ($fromEnv !== false) {
                return (string) $fromEnv;
            }

            if (isset($_ENV[$key])) {
                return (string) $_ENV[$key];
            }

            if (isset($_SERVER[$key])) {
                return (string) $_SERVER[$key];
            }

            return $default;
        }
    }

    if (!function_exists('api_env_bool')) {
        function api_env_bool(string $key, bool $default = false): bool
        {
            $value = api_env($key);
            if ($value === null) {
                return $default;
            }

            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }

            return $default;
        }
    }

    $rootEnvPath = dirname(__DIR__) . '/.env';
    api_load_env($rootEnvPath);
}
