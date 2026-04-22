<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (!function_exists('api_db_config')) {
    /**
     * @return array{host:string,port:string,name:string,user:string,pass:string,timeout:int,charset:string}
     */
    function api_db_config(): array
    {
        $timeout = (int) (api_env('DB_TIMEOUT', '2') ?? '2');
        if ($timeout < 1) {
            $timeout = 2;
        }

        $charset = api_env('DB_CHARSET', 'utf8mb4') ?? 'utf8mb4';

        return [
            'host' => api_env('DB_HOST', '127.0.0.1') ?? '127.0.0.1',
            'port' => api_env('DB_PORT', '3306') ?? '3306',
            'name' => api_env('DB_NAME', 'store') ?? 'store',
            'user' => api_env('DB_USER', 'root') ?? 'root',
            'pass' => api_env('DB_PASS', '') ?? '',
            'timeout' => $timeout,
            'charset' => $charset,
        ];
    }
}

if (!function_exists('api_pdo')) {
    function api_pdo(): PDO
    {
        static $pdo = null;

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $db = api_db_config();
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $db['host'],
            $db['port'],
            $db['name'],
            $db['charset']
        );

        $attrs = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => $db['timeout'],
        ];

        if (defined('PDO::MYSQL_ATTR_INIT_COMMAND') && !defined('Pdo\Mysql::ATTR_INIT_COMMAND')) {
            $attrs[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES ' . $db['charset'];
        }

        $pdo = new PDO($dsn, $db['user'], $db['pass'], $attrs);
        return $pdo;
    }
}
