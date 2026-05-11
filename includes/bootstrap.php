<?php

declare(strict_types=1);

session_start();

$projectRoot = dirname(__DIR__);
$logDir = $projectRoot . '/storage/logs';
$logFile = $logDir . '/php-error.log';
$isProduction = getenv('APP_ENV') === 'production' || getenv('APP_ENV') === 'prod';

if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

ini_set('log_errors', '1');
ini_set('error_log', $logFile);
ini_set('display_errors', $isProduction ? '0' : '1');
ini_set('display_startup_errors', '0');

error_reporting(E_ALL);

function app_fix_mojibake_string(string $value): string
{
    $previous = null;

    while ($value !== $previous) {
        $previous = $value;
        $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);

        if (!is_string($converted) || $converted === '') {
            break;
        }

        $looksBetter = substr_count($converted, 'Ã') < substr_count($value, 'Ã')
            || substr_count($converted, 'Â') < substr_count($value, 'Â')
            || substr_count($converted, 'Å') < substr_count($value, 'Å')
            || substr_count($converted, 'Ä') < substr_count($value, 'Ä');

        if (!$looksBetter) {
            break;
        }

        $value = $converted;
    }

    return $value;
}

function app_fix_mojibake(mixed $value): mixed
{
    if (is_string($value)) {
        return app_fix_mojibake_string($value);
    }

    if (!is_array($value)) {
        return $value;
    }

    foreach ($value as $key => $item) {
        $value[$key] = app_fix_mojibake($item);
    }

    return $value;
}

if (PHP_SAPI !== 'cli') {
    ob_start(static function (string $buffer): string {
        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Type:') !== 0) {
                continue;
            }

            if (stripos($header, 'text/html') === false) {
                return $buffer;
            }

            break;
        }

        return app_fix_mojibake_string($buffer);
    });
}

function app_debug_log(string $channel, array $context = []): void
{
    if (getenv('APP_ENV') === 'production' || getenv('APP_ENV') === 'prod') {
        return;
    }

    $projectRoot = dirname(__DIR__);
    $logDir = $projectRoot . '/storage/logs';
    $debugFile = $logDir . '/app-debug.log';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $payload = [
        'time' => date('c'),
        'channel' => $channel,
        'context' => $context,
    ];

    error_log(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, 3, $debugFile);
}

function app_csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function app_verify_csrf_token(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

set_exception_handler(static function (Throwable $exception): void {
    error_log(sprintf(
        "[%s] Uncaught %s: %s in %s:%d\nStack trace:\n%s",
        date('c'),
        $exception::class,
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    ));

    http_response_code(500);

    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=UTF-8');
    }

    echo 'Sunucu hatasi olustu. Ayrintilar storage/logs/php-error.log dosyasina yazildi.';
});

register_shutdown_function(static function () use ($logFile): void {
    $error = error_get_last();

    if ($error === null) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    error_log(sprintf(
        "[%s] Fatal error (%d): %s in %s:%d",
        date('c'),
        $error['type'],
        $error['message'],
        $error['file'],
        $error['line']
    ));

    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=UTF-8', true, 500);
    }

    echo "Fatal hata olustu. Ayrintilar $logFile dosyasina yazildi.";
});
