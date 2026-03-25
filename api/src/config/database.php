<?php

function load_env_file($filePath)
{
    static $loadedFiles = array();

    if (isset($loadedFiles[$filePath]) || !file_exists($filePath)) {
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || strpos($trimmed, '#') === 0) {
            continue;
        }

        $parts = explode('=', $trimmed, 2);

        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);
        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }

    $loadedFiles[$filePath] = true;
}

function env_value($key, $defaultValue)
{
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }

    $value = getenv($key);

    return $value !== false && $value !== '' ? $value : $defaultValue;
}

function get_pdo()
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    load_env_file(dirname(__DIR__, 2) . '/.env');

    $host = env_value('DB_HOST', '127.0.0.1');
    $port = env_value('DB_PORT', '3306');
    $dbName = env_value('DB_NAME', 'student_projects');
    $user = env_value('DB_USER', 'root');
    $password = env_value('DB_PASSWORD', '');

    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $dbName . ';charset=utf8mb4';

    try {
        $pdo = new PDO(
            $dsn,
            $user,
            $password,
            array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            )
        );
    } catch (PDOException $exception) {
        json_response(
            array(
                'error' => 'Database connection failed.',
                'details' => $exception->getMessage(),
            ),
            500
        );
    }

    return $pdo;
}
