<?php
// Database credentials for this hosting package are loaded from includes/maten_config.local.php.
// For stronger production setups this file also supports DATABASE_URL and MATEN_DB_* env vars.

function matenConfigEnv(string $key, string $default = ''): string {
    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? $default;
    }
    return is_string($value) ? trim($value) : $default;
}

function matenDatabaseConfigFromUrl(string $databaseUrl): array {
    $parts = parse_url($databaseUrl);
    if (!is_array($parts)) {
        return [];
    }

    $path = isset($parts['path']) ? ltrim((string) $parts['path'], '/') : '';
    return [
        'host' => (string) ($parts['host'] ?? 'localhost'),
        'port' => isset($parts['port']) ? (string) $parts['port'] : '',
        'user' => rawurldecode((string) ($parts['user'] ?? '')),
        'pass' => rawurldecode((string) ($parts['pass'] ?? '')),
        'name' => $path,
    ];
}

function matenLoadDatabaseConfig(): array {
    $databaseUrl = matenConfigEnv('DATABASE_URL');
    if ($databaseUrl !== '') {
        $fromUrl = matenDatabaseConfigFromUrl($databaseUrl);
        if (!empty($fromUrl['user']) && !empty($fromUrl['name'])) {
            return $fromUrl;
        }
    }

    $fromEnv = [
        'host' => matenConfigEnv('MATEN_DB_HOST', 'localhost'),
        'port' => matenConfigEnv('MATEN_DB_PORT'),
        'user' => matenConfigEnv('MATEN_DB_USER'),
        'pass' => matenConfigEnv('MATEN_DB_PASS'),
        'name' => matenConfigEnv('MATEN_DB_NAME'),
    ];
    if ($fromEnv['user'] !== '' && $fromEnv['name'] !== '') {
        return $fromEnv;
    }

    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\') : '';
    $home = matenConfigEnv('HOME');
    $configPaths = array_values(array_unique(array_filter([
        matenConfigEnv('MATEN_CONFIG_FILE'),
        __DIR__ . '/maten_config.local.php',
        dirname(__DIR__, 2) . '/maten_config.local.php',
        $documentRoot !== '' ? dirname($documentRoot) . '/maten_config.local.php' : '',
        $home !== '' ? $home . '/maten_config.local.php' : '',
        $home !== '' ? $home . '/private/maten_config.local.php' : '',
    ])));

    foreach ($configPaths as $path) {
        if (!is_readable($path)) {
            continue;
        }
        $loaded = require $path;
        if (!is_array($loaded)) {
            http_response_code(500);
            die('Database config file must return an array.');
        }
        return [
            'host' => (string) ($loaded['host'] ?? 'localhost'),
            'port' => (string) ($loaded['port'] ?? ''),
            'user' => (string) ($loaded['user'] ?? ''),
            'pass' => (string) ($loaded['pass'] ?? ''),
            'name' => (string) ($loaded['name'] ?? ''),
        ];
    }

    http_response_code(500);
    die('Missing database config. Upload includes/maten_config.local.php or set MATEN_DB_* environment variables.');
}

$matenDbConfig = matenLoadDatabaseConfig();
$db_host = $matenDbConfig['host'];
$db_port = $matenDbConfig['port'];
$db_user = $matenDbConfig['user'];
$db_pass = $matenDbConfig['pass'];
$db_name = $matenDbConfig['name'];

if ($db_user === '' || $db_name === '') {
    http_response_code(500);
    die('Database config: user and name are required.');
}
