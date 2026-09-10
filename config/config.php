<?php

declare(strict_types=1);

function loadEnv(string $file): void
{
    if (!file_exists($file)) {
        return;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$key, $value] = array_pad(
            explode('=', $line, 2),
            2,
            ''
        );

        $key = trim($key);
        $value = trim($value);

        if (
            strlen($value) >= 2 &&
            (
                ($value[0] === '"' && $value[-1] === '"') ||
                ($value[0] === "'" && $value[-1] === "'")
            )
        ) {
            $value = substr($value, 1, -1);
        }

        if ($key !== '') {
            putenv($key . '=' . $value);
        }
    }
}

loadEnv(__DIR__ . '/../.env');

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);

    return ($value === false || $value === '')
        ? $default
        : $value;
}

return [
    'bot_token' => env('BOT_TOKEN'),
    'admin_id' => (int) env('ADMIN_ID', '0'),

    'db' => [
        'host' => env('DB_HOST', 'localhost'),
        'name' => env('DB_NAME', 'yoshlarhub'),
        'user' => env('DB_USER', 'root'),
        'pass' => env('DB_PASS', ''),
        'charset' => 'utf8mb4',
    ],
];
